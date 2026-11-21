<?php
declare(strict_types=1);

namespace Haven\Tests\Database;

use Config;
use Crypto;
use Database;
use Haven\Tests\Support\TestDatabase;
use MailPolicy;
use MailQueue;
use Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Tes integrasi antrean email (Req 6.11, 6.12, 6.14, 10.2, 10.3).
 *
 * Keputusan kirim, tunggu, atau jatuhkan diuji terpisah sebagai aturan murni
 * (`MailQueue::decide`). Yang dijaga di sini adalah yang hanya terlihat dengan
 * database dan proses sungguhan: apa yang benar-benar tersimpan di
 * `mail_queue`, apa yang terjadi pada percobaan ulang kelima, dan apa yang
 * masuk ke log.
 *
 * SMTP tidak dipalsukan. Pengiriman diarahkan ke port tertutup di 127.0.0.1,
 * jadi kegagalannya kegagalan koneksi nyata.
 */
final class MailQueueRunnerTest extends TestCase
{
    /** Port tertutup: koneksi langsung ditolak, jadi tes tidak menunggu lama. */
    private const DEAD_PORT = '9';

    /** Penanda isi email yang tidak boleh muncul di log maupun notifikasi. */
    private const BODY_MARK = 'kalimat-isi-email-yang-tidak-boleh-dicatat';

    private const TO = 'rani@contoh.test';

    /** @var array<string,?string> */
    private array $savedConfig = [];

    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }

        TestDatabase::bind();
        TestDatabase::truncate('mail_queue', 'notifications', 'users');

        foreach ([MailPolicy::ENV_HOST, MailPolicy::ENV_PORT, MailPolicy::ENV_USER, MailPolicy::ENV_ENCRYPTION] as $key) {
            $this->savedConfig[$key] = Config::get($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedConfig as $key => $value) {
            Config::set($key, (string) $value);
        }
        TestDatabase::release();
    }

    /** Kirim yang gagal masuk antrean dengan alamat dan isi terenkripsi (Req 6.11, 10.3). */
    public function testFailedSendIsQueuedWithTheAddressAndBodyEncrypted(): void
    {
        $this->useDeadSmtp();

        $queued = Mailer::send(self::TO, 'Undangan bergabung di Haven', self::BODY_MARK, 'invitation');

        $this->assertFalse($queued, 'Kirim gagal tidak boleh dilaporkan sebagai berhasil.');

        $row = Database::one('SELECT * FROM mail_queue');
        $this->assertNotNull($row, 'Email yang gagal dikirim harus ada di antrean.');
        $this->assertSame(0, (int) $row['failed_retries'], 'Baris baru mulai dari nol percobaan ulang.');
        $this->assertSame('invitation', $row['type']);
        $this->assertStringNotContainsString(self::TO, (string) $row['to_email_enc']);
        $this->assertStringNotContainsString(self::BODY_MARK, (string) $row['body_enc']);
        $this->assertSame(self::TO, Crypto::dec((string) $row['to_email_enc']));
        $this->assertSame(self::BODY_MARK, Crypto::dec((string) $row['body_enc']));
    }

    /** Pengguna yang mematikan email tidak dikirimi dan tidak diantrekan (Req 6.14). */
    public function testEmailOffMeansNoSendAndNoQueueRow(): void
    {
        $this->useDeadSmtp();
        $this->user('penyintas@contoh.test', 'survivor', allowEmail: false);

        $sent = Mailer::send('penyintas@contoh.test', 'Kabar dari Haven', self::BODY_MARK);

        $this->assertFalse($sent);
        $this->assertSame(0, $this->queueCount(), 'Email yang ditolak kebijakan tidak boleh masuk antrean.');
    }

    /** Percobaan ulang yang gagal menaikkan hitungan dan email tetap di antrean (Req 6.11). */
    public function testFailedRetryIncrementsTheCounterAndKeepsTheRow(): void
    {
        $this->queueRow(0, (new \DateTimeImmutable('now'))->modify('-' . (MailQueue::INTERVAL_MIN + 1) . ' minutes'));

        [$code, $out, $err] = $this->runQueueScript();

        $this->assertSame(0, $code, "bin/mail-queue.php gagal.\n$out\n$err");
        $row = Database::one('SELECT * FROM mail_queue');
        $this->assertNotNull($row, "Email yang belum terkirim tetap di antrean sampai percobaan kelima gagal.\n$out\n$err");
        $this->assertSame(1, (int) $row['failed_retries']);
    }

    /**
     * Percobaan ulang kelima yang gagal: log tanpa isi email, notifikasi ke
     * semua admin aktif, baris dihapus (Req 6.12, 10.2).
     */
    public function testFifthFailedRetryNotifiesActiveAdminsAndLeavesTheQueueEmpty(): void
    {
        $activeAdmin = $this->user('admin@contoh.test', 'admin');
        $this->user('mantan-admin@contoh.test', 'admin', active: false);
        $this->user('pendamping@contoh.test', 'companion');
        $this->queueRow(MailQueue::MAX_RETRY, new \DateTimeImmutable('now'));

        $logSize = $this->logSize();
        [$code, $out, $err] = $this->runQueueScript();
        $this->assertSame(0, $code, "bin/mail-queue.php gagal.\n$out\n$err");

        $this->assertSame(0, $this->queueCount(), 'Email yang dijatuhkan harus hilang dari antrean.');

        $notifications = Database::all('SELECT user_id, title FROM notifications');
        $this->assertCount(1, $notifications, 'Hanya admin aktif yang diberi tahu.');
        $this->assertSame($activeAdmin, (int) $notifications[0]['user_id']);
        $this->assertStringContainsString('ra***@contoh.test', $notifications[0]['title']);
        $this->assertStringNotContainsString(self::BODY_MARK, $notifications[0]['title']);

        $log = $this->logSince($logSize);
        $this->assertStringContainsString(self::TO, $log, 'Log kegagalan terakhir memuat alamat penerima (Req 6.12).');
        $this->assertStringContainsString('tipe=password_reset', $log);
        $this->assertStringNotContainsString(self::BODY_MARK, $log, 'Log tidak boleh memuat isi email (Req 6.12, 10.2).');
    }

    /** Arahkan SMTP ke port tertutup: mode `smtp` dengan koneksi yang pasti gagal. */
    private function useDeadSmtp(): void
    {
        Config::set(MailPolicy::ENV_HOST, '127.0.0.1');
        Config::set(MailPolicy::ENV_PORT, self::DEAD_PORT);
        Config::set(MailPolicy::ENV_USER, '');
        Config::set(MailPolicy::ENV_ENCRYPTION, '');
    }

    /**
     * Jalankan bin/mail-queue.php sebagai proses terpisah, dengan database uji,
     * kunci enkripsi yang sama, dan SMTP yang pasti gagal.
     *
     * @return array{0:int,1:string,2:string}
     */
    private function runQueueScript(): array
    {
        return TestDatabase::runScript(ROOT_PATH . '/bin/mail-queue.php', [], [
            'DB_NAME' => TestDatabase::name(),
            'APP_KEY' => (string) Config::get('APP_KEY'),
            MailPolicy::ENV_HOST => '127.0.0.1',
            MailPolicy::ENV_PORT => self::DEAD_PORT,
            MailPolicy::ENV_USER => '',
            MailPolicy::ENV_ENCRYPTION => '',
        ]);
    }

    private function queueRow(int $failedRetries, \DateTimeImmutable $lastAttempt): void
    {
        Database::insert(
            'INSERT INTO mail_queue (to_email_enc, type, subject, body_enc, failed_retries, last_attempt_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (string) Crypto::enc(self::TO),
                'password_reset',
                'Atur ulang kata sandi Haven',
                (string) Crypto::enc(self::BODY_MARK),
                $failedRetries,
                $lastAttempt->format('Y-m-d H:i:s'),
                $lastAttempt->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function user(string $email, string $role, bool $active = true, bool $allowEmail = true): int
    {
        return Database::insert(
            'INSERT INTO users (name, email, password_hash, role, allow_email, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            ['Uji', $email, 'hash-uji', $role, (int) $allowEmail, (int) $active, now(), now()]
        );
    }

    private function queueCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM mail_queue');
    }

    private function logSize(): int
    {
        $file = STORAGE_PATH . '/logs/app.log';

        return is_file($file) ? (int) filesize($file) : 0;
    }

    /** Bagian log aplikasi yang ditulis setelah ukuran $from. */
    private function logSince(int $from): string
    {
        $file = STORAGE_PATH . '/logs/app.log';
        if (!is_file($file)) {
            return '';
        }

        return (string) file_get_contents($file, offset: $from);
    }
}
