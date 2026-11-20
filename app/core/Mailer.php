<?php
declare(strict_types=1);

/**
 * Pengiriman email (Req 6.9 sampai 6.14).
 *
 * Urutan `send()`, mengikuti desain:
 *
 * 1. Pagar `MailPolicy::containsForbidden`: kronologi, PIN, dan kode laporan
 *    tidak boleh masuk subjek atau isi. Email yang kena pagar tidak dikirim,
 *    tidak diantre, dan tidak ditulis ke `storage/mail.log` (Req 6.13, 10.2).
 * 2. `MailPolicy::mayDeliver`: pengguna dengan `allow_email = 0` hanya menerima
 *    atur ulang kata sandi dan undangan. Tipe lain tidak dikirim DAN tidak
 *    masuk antrean (Req 6.14).
 * 3. Mode `log` (host atau port SMTP kosong): isi email ditulis ke
 *    `storage/mail.log` seperti sebelum fitur ini ada, tanpa membuka koneksi
 *    SMTP sama sekali (Req 6.10).
 * 4. Selain itu: satu percobaan lewat `SmtpClient` dengan batas 30 detik.
 *    Gagal berarti barisnya masuk `mail_queue` dengan alamat dan isi
 *    terenkripsi, lalu `bin/mail-queue.php` yang mencoba ulang
 *    (Req 6.9, 6.11, 10.3).
 *
 * Yang tidak ada di sini: keputusan percobaan ulang (`MailQueue`) dan
 * pemrosesan antrean (`bin/mail-queue.php`). `Mailer` hanya melakukan satu
 * percobaan per panggilan, supaya permintaan web tidak pernah tertahan 5 x 30
 * detik.
 */
final class Mailer
{
    /** Kunci `.env` untuk alamat pengirim dan nama yang tampil di kotak masuk. */
    public const ENV_FROM = 'MAIL_FROM';
    public const ENV_FROM_NAME = 'MAIL_FROM_NAME';

    /** Alamat pengirim cadangan bila `.env` tidak memuat satu pun alamat. */
    private const FROM_FALLBACK = 'no-reply@localhost';

    /** Panjang kolom `mail_queue.subject`. */
    private const SUBJECT_MAX = 190;

    /**
     * Kirim satu email. Mengembalikan benar bila email sudah terkirim atau
     * sudah tercatat di `storage/mail.log`, dan salah bila email ditolak
     * kebijakan atau gagal dikirim (dan karena itu masuk antrean).
     *
     * `$type` memakai nama tipe `MailPolicy` (`password_reset`, `invitation`,
     * atau `general`). Tipe menentukan apakah email tetap dikirim ke pengguna
     * yang mematikan email (Req 6.14) dan ikut dicatat saat pengiriman gagal
     * (Req 6.12).
     */
    public static function send(string $to, string $subject, string $body, string $type = MailPolicy::TYPE_GENERAL): bool
    {
        $type = MailPolicy::normalizeType($type);
        $to = trim($to);

        // Pagar terakhir sebelum email keluar, diantre, atau dicatat (Req 6.13).
        if (MailPolicy::containsForbidden($subject, $body)) {
            Logger::error('mail: email ditahan sebelum dikirim karena subjek atau isinya memuat kode laporan, PIN, atau kronologi. ' . self::describe($to, $type));
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::error('mail: alamat penerima tidak sah, email tidak dikirim dan tidak diantre. ' . self::describe($to, $type));
            return false;
        }

        // Tipe ALWAYS tidak perlu membaca preferensi: mayDeliver selalu benar
        // untuknya, jadi atur ulang kata sandi tetap jalan walau DB bermasalah.
        $allowEmail = in_array($type, MailPolicy::ALWAYS, true) || self::allowsEmail($to);
        if (!MailPolicy::mayDeliver($type, $allowEmail)) {
            return false;
        }

        if (self::mode() === MailPolicy::MODE_LOG) {
            self::writeLog($to, $subject, $body, $type);
            return true;
        }

        try {
            self::attempt($to, $subject, $body);
            return true;
        } catch (Throwable $e) {
            Logger::error('mail: kirim gagal, email masuk antrean untuk dicoba ulang. ' . self::describe($to, $type) . ' Alasan: ' . $e->getMessage());
            self::enqueue($to, $subject, $body, $type);
            return false;
        }
    }

    /** Mode pengiriman menurut `.env`: `smtp` atau `log` (Req 6.10). */
    public static function mode(): string
    {
        return MailPolicy::mode(self::smtpEnv());
    }

    /**
     * Konfigurasi SMTP dari `.env`, dengan kunci `MailPolicy::ENV_*`.
     *
     * @return array<string,?string>
     */
    public static function smtpEnv(): array
    {
        return [
            MailPolicy::ENV_HOST => Config::get(MailPolicy::ENV_HOST),
            MailPolicy::ENV_PORT => Config::get(MailPolicy::ENV_PORT),
            MailPolicy::ENV_USER => Config::get(MailPolicy::ENV_USER),
            MailPolicy::ENV_PASS => Config::get(MailPolicy::ENV_PASS),
            MailPolicy::ENV_ENCRYPTION => Config::get(MailPolicy::ENV_ENCRYPTION),
        ];
    }

    /**
     * Satu percobaan kirim lewat SMTP. Melempar `RuntimeException` bila gagal,
     * termasuk bila percakapan melewati 30 detik (Req 6.9).
     *
     * Dipakai `send()` untuk percobaan pertama dan `bin/mail-queue.php` untuk
     * setiap percobaan ulang, supaya keduanya memakai jalur yang sama.
     */
    public static function attempt(string $to, string $subject, string $body): void
    {
        $env = self::smtpEnv();
        if (MailPolicy::mode($env) !== MailPolicy::MODE_SMTP) {
            throw new RuntimeException('Konfigurasi SMTP kosong: ' . MailPolicy::ENV_HOST . ' dan ' . MailPolicy::ENV_PORT . ' harus terisi.');
        }

        SmtpClient::fromEnv($env)->send(self::fromAddress(), self::fromName(), $to, $subject, $body);
    }

    /**
     * Simpan email yang gagal dikirim ke `mail_queue` (Req 6.11).
     *
     * Alamat dan isi dienkripsi AES-256-GCM karena keduanya bisa memuat data
     * pribadi (Req 10.3). Subjek disimpan apa adanya: kolom itu dibaca
     * pengelola saat memeriksa antrean, dan pagar `containsForbidden` sudah
     * memastikan subjek tidak memuat kode laporan, PIN, atau kronologi.
     *
     * `failed_retries` mulai dari 0 dan `last_attempt_at` adalah waktu
     * percobaan pertama yang gagal, sesuai hitungan `MailQueue::decide`:
     * nilai 0 sampai 4 berarti masih ada percobaan ulang, nilai 5 berarti
     * email dijatuhkan.
     */
    public static function enqueue(string $to, string $subject, string $body, string $type, ?string $failedAt = null): bool
    {
        $at = $failedAt ?? now();

        try {
            Database::run(
                'INSERT INTO mail_queue (to_email_enc, type, subject, body_enc, failed_retries, last_attempt_at, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?)',
                [
                    self::encrypt($to),
                    MailPolicy::normalizeType($type),
                    mb_substr($subject, 0, self::SUBJECT_MAX),
                    self::encrypt($body),
                    $at,
                    $at,
                ]
            );
            return true;
        } catch (Throwable $e) {
            // Tanpa isi email: log tidak boleh memuat badan surat (Req 10.2).
            Logger::error('mail: email gagal dikirim DAN gagal masuk antrean. ' . self::describe($to, $type) . ' Alasan: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tulis email ke `storage/mail.log` (Req 6.10). Formatnya sama seperti
     * sebelumnya, dengan tambahan baris tipe supaya jelas email apa yang
     * seharusnya terkirim.
     */
    public static function writeLog(string $to, string $subject, string $body, string $type = MailPolicy::TYPE_GENERAL): void
    {
        $entry = str_repeat('=', 60) . "\n"
            . 'Tanggal : ' . now() . "\n"
            . "Kepada  : $to\n"
            . 'Tipe    : ' . MailPolicy::normalizeType($type) . "\n"
            . "Subjek  : $subject\n\n"
            . $body . "\n\n";

        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        @file_put_contents(STORAGE_PATH . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    /** Alamat pengirim: `MAIL_FROM`, lalu `MAIL_USER`, lalu alamat cadangan. */
    public static function fromAddress(): string
    {
        foreach ([Config::get(self::ENV_FROM), Config::get(MailPolicy::ENV_USER)] as $candidate) {
            $value = trim((string) $candidate);
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return self::FROM_FALLBACK;
    }

    /** Nama pengirim yang tampil di kotak masuk. */
    public static function fromName(): string
    {
        $name = trim((string) Config::get(self::ENV_FROM_NAME));

        return $name === '' ? (string) Config::get('APP_NAME', 'Haven') : $name;
    }

    /**
     * Penerima dan tipe untuk satu baris log, tanpa isi email (Req 6.12, 10.2).
     * Req 6.12 meminta alamat penerima tercatat, jadi alamat ditulis utuh;
     * `storage/logs/` tidak bisa dibuka dari web.
     */
    public static function describe(string $to, string $type): string
    {
        return 'penerima=' . ($to === '' ? '(kosong)' : $to) . ' tipe=' . MailPolicy::normalizeType($type);
    }

    /** Absolute URL for links inside emails. */
    public static function absoluteUrl(string $path, array $query = []): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        return $scheme . '://' . $host . url($path, $query);
    }

    /**
     * Preferensi `allow_email` pemilik alamat ini (Req 6.14).
     *
     * Alamat yang bukan milik pengguna Haven (mis. tujuan undangan yang belum
     * punya akun) tidak punya preferensi, jadi dianggap boleh menerima. Bila
     * tabel tidak bisa dibaca, jawabannya salah: lebih baik email non-esensial
     * tidak terkirim daripada terkirim ke orang yang sudah mematikannya. Atur
     * ulang kata sandi dan undangan tidak lewat jalur ini.
     */
    private static function allowsEmail(string $to): bool
    {
        try {
            $value = Database::value('SELECT allow_email FROM users WHERE email = ? LIMIT 1', [$to]);
        } catch (Throwable $e) {
            Logger::error('mail: preferensi allow_email tidak bisa dibaca, email non-esensial tidak dikirim. Alasan: ' . $e->getMessage());
            return false;
        }

        return $value === null || (bool) (int) $value;
    }

    /**
     * Ciphertext AES-256-GCM untuk kolom antrean (Req 10.3).
     *
     * `Crypto::enc` mengembalikan null untuk string kosong, sedangkan
     * `to_email_enc` dan `body_enc` NOT NULL, jadi dipakai instance kunci
     * bawaan yang sama supaya isi kosong pun tetap tersimpan terenkripsi.
     */
    private static function encrypt(string $plain): string
    {
        return $plain === '' ? Crypto::default()->encrypt('') : (string) Crypto::enc($plain);
    }
}
