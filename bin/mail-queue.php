<?php
declare(strict_types=1);

/*
 * Pemroses antrean email (Req 6.11, 6.12).
 *   php bin/mail-queue.php
 *
 * Dijadwalkan tiap 5 sampai 10 menit lewat Windows Task Scheduler atau cron,
 * tanpa daemon. Keputusan kirim, tunggu, atau jatuhkan ada di MailQueue
 * (murni); berkas ini hanya membaca baris, mencoba kirim lewat Mailer::attempt,
 * lalu memperbarui atau menghapus barisnya.
 *
 * Tiap email berdiri sendiri dan tidak dibungkus satu transaksi, berbeda dari
 * bin/reminders.php. Percakapan SMTP bisa memakan 30 detik per email, dan
 * menahan transaksi selama itu akan mengunci baris antrean tanpa guna. Satu
 * email yang gagal juga tidak boleh membatalkan hasil email lain yang sudah
 * terkirim di run yang sama.
 *
 * Pada percobaan ulang kelima yang gagal: kegagalan dicatat dengan waktu,
 * alamat penerima, dan tipe TANPA isi email, semua admin aktif diberi
 * notifikasi, lalu barisnya dihapus dari antrean (Req 6.12, 10.2).
 *
 * Keluar 0 bila run selesai. Bila database tidak bisa dibaca sama sekali:
 * tulis ke log aplikasi dan keluar 1.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

/**
 * Isi antrean, baris terlama lebih dulu.
 *
 * Seluruh antrean dibaca sekaligus: tabel ini hanya berisi email yang gagal,
 * jadi ukurannya kecil, dan MailQueue::decide yang menentukan mana yang belum
 * waktunya dicoba lagi.
 *
 * @return list<array<string,mixed>>
 */
function mail_queue_rows(): array
{
    return Database::all(
        'SELECT id, to_email_enc, type, subject, body_enc, failed_retries, last_attempt_at
           FROM mail_queue
          ORDER BY id'
    );
}

/**
 * Waktu percobaan terakhir dari kolom DATETIME. Nilai yang tidak bisa dibaca
 * dianggap sudah lama lewat, supaya baris rusak tetap diproses dan tidak
 * menunggu selamanya di antrean.
 */
function mail_queue_last_attempt(mixed $raw, DateTimeImmutable $now): DateTimeImmutable
{
    try {
        return new DateTimeImmutable(is_string($raw) && $raw !== '' ? $raw : '@0');
    } catch (Throwable) {
        return $now->modify('-' . MailQueue::INTERVAL_MIN . ' minutes');
    }
}

/**
 * Catat kegagalan terakhir, beri tahu semua admin aktif, lalu hapus barisnya
 * (Req 6.12). Log dan judul notifikasi tidak memuat isi email (Req 10.2);
 * notifikasi memakai alamat tersamar karena judulnya terbaca di antarmuka,
 * sedangkan log memuat alamat utuh seperti yang diminta Req 6.12.
 */
function mail_queue_drop(int $id, ?string $to, string $type, string $reason): void
{
    $known = $to !== null && $to !== '';

    Logger::error(
        'mail-queue: email dijatuhkan setelah ' . MailQueue::MAX_RETRY . ' percobaan ulang gagal. '
        . Mailer::describe($known ? $to : '', $type) . ' Alasan: ' . $reason
    );

    Notifier::admins(
        null,
        'pengingat',
        'Email gagal dikirim ke ' . ($known ? mask_email($to) : 'alamat yang tidak bisa dibaca')
            . ' (tipe ' . MailPolicy::normalizeType($type) . ')',
        '/admin'
    );

    Database::run('DELETE FROM mail_queue WHERE id = ?', [$id]);
}

$now = new DateTimeImmutable('now');

try {
    $rows = mail_queue_rows();
} catch (Throwable $e) {
    Logger::error('mail-queue: antrean tidak bisa dibaca, tidak ada email yang diproses. ' . $e->getMessage());
    fwrite(STDERR, 'Antrean email gagal diproses: ' . $e->getMessage() . "\n");
    exit(1);
}

// Konfigurasi SMTP bisa dikosongkan setelah ada email di antrean. Mode `log`
// berarti tidak boleh ada koneksi SMTP (Req 6.10), jadi sisa antrean
// diselesaikan dengan menulisnya ke storage/mail.log.
$logMode = Mailer::mode() === MailPolicy::MODE_LOG;

$sent = 0;
$failed = 0;
$dropped = 0;
$waiting = 0;

try {
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $type = (string) $row['type'];
        $retries = (int) $row['failed_retries'];
        $to = Crypto::dec((string) $row['to_email_enc']);

        $decision = MailQueue::decide($retries, mail_queue_last_attempt($row['last_attempt_at'], $now), $now);

        if ($decision === MailQueue::WAIT) {
            $waiting++;
            continue;
        }

        if ($decision === MailQueue::DROP) {
            mail_queue_drop($id, $to, $type, 'batas percobaan ulang tercapai');
            $dropped++;
            continue;
        }

        $body = Crypto::dec((string) $row['body_enc']);
        if ($to === null || $body === null) {
            // Ciphertext berubah atau APP_KEY sudah lain: email ini tidak akan
            // pernah bisa dikirim, jadi diperlakukan seperti kegagalan terakhir.
            mail_queue_drop($id, $to, $type, 'alamat atau isi email tidak bisa didekripsi');
            $dropped++;
            continue;
        }

        if ($logMode) {
            Mailer::writeLog($to, (string) $row['subject'], $body, $type);
            Database::run('DELETE FROM mail_queue WHERE id = ?', [$id]);
            $sent++;
            continue;
        }

        try {
            Mailer::attempt($to, (string) $row['subject'], $body);
            Database::run('DELETE FROM mail_queue WHERE id = ?', [$id]);
            $sent++;
            continue;
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }

        $retries++;
        Database::run(
            'UPDATE mail_queue SET failed_retries = ?, last_attempt_at = ? WHERE id = ?',
            [$retries, now(), $id]
        );

        // Percobaan ulang kelima yang gagal diputuskan sekarang, bukan di run
        // berikutnya, supaya admin tahu di saat kegagalannya terjadi (Req 6.12).
        if (MailQueue::decide($retries, $now, $now) === MailQueue::DROP) {
            mail_queue_drop($id, $to, $type, $reason);
            $dropped++;
            continue;
        }

        Logger::error(
            'mail-queue: percobaan ulang ke-' . $retries . ' gagal, email tetap di antrean. '
            . Mailer::describe($to, $type) . ' Alasan: ' . $reason
        );
        $failed++;
    }
} catch (Throwable $e) {
    // Database hilang di tengah run. Email yang sudah diproses tetap seperti
    // apa adanya; sisanya menunggu run berikutnya.
    Logger::error('mail-queue: run berhenti di tengah jalan. ' . $e->getMessage());
    fwrite(STDERR, 'Antrean email gagal diproses: ' . $e->getMessage() . "\n");
    exit(1);
}

echo count($rows) . " email di antrean: $sent diproses, $failed gagal dan dicoba lagi nanti, "
    . "$dropped dijatuhkan, $waiting belum waktunya.\n";
exit(0);
