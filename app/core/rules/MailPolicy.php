<?php
declare(strict_types=1);

/**
 * Kebijakan pengiriman email (Req 6.10, 6.13, 6.14) — murni, tanpa DB, tanpa
 * superglobal, dan tanpa time(). Isi `.env` masuk sebagai array, sehingga
 * `Mailer` dan `bin/mail-queue.php` memanggil aturan yang sama dan tes bisa
 * jalan tanpa konfigurasi nyata.
 *
 * Req 6.10: `mode` bernilai `log` bila host atau port SMTP kosong. Dalam mode
 *   itu `Mailer` menulis email ke `storage/mail.log` seperti sekarang dan tidak
 *   boleh membuka koneksi SMTP sama sekali.
 * Req 6.13: `containsForbidden` menjadi pagar terakhir sebelum email dikirim,
 *   diantrekan, atau dicatat — kronologi, PIN, dan kode laporan tidak boleh ada
 *   di subjek maupun isi.
 * Req 6.14: `mayDeliver` menolak semua email selain atur ulang kata sandi dan
 *   undangan untuk pengguna dengan `allow_email = 0`. Penolakan di sini berarti
 *   email tidak dikirim DAN tidak masuk `mail_queue`.
 *
 * Kelas ini tidak pernah memutuskan email berhasil terkirim. Keputusan
 * percobaan ulang ada di `MailQueue`, dan I/O SMTP ada di `SmtpClient`
 * (tugas 9.4).
 */
final class MailPolicy
{
    /** Mode pengiriman: lewat SMTP sungguhan. */
    public const MODE_SMTP = 'smtp';

    /** Mode pengiriman: tulis ke `storage/mail.log`, tanpa koneksi (Req 6.10). */
    public const MODE_LOG = 'log';

    /**
     * Tipe email yang tetap dikirim meski `allow_email = 0` (Req 6.14).
     * Keduanya satu-satunya jalan pengguna masuk ke akunnya, jadi mematikannya
     * akan mengunci pengguna di luar akun sendiri.
     */
    public const ALWAYS = ['password_reset', 'invitation'];

    /** Tipe email bawaan `Mailer::send` untuk surat yang bukan dua di atas. */
    public const TYPE_GENERAL = 'general';

    /** Kunci `.env` untuk SMTP (Req 6.9). Host dan port menentukan `mode`. */
    public const ENV_HOST = 'MAIL_HOST';
    public const ENV_PORT = 'MAIL_PORT';
    public const ENV_USER = 'MAIL_USER';
    public const ENV_PASS = 'MAIL_PASS';
    public const ENV_ENCRYPTION = 'MAIL_ENCRYPTION';

    /**
     * Panjang minimal satu `$secrets` agar diperiksa. Potongan sangat pendek
     * ("ya", "aku") muncul di hampir semua kalimat dan hanya akan memblokir
     * email yang aman tanpa melindungi apa pun. PIN (6 angka) dan kode laporan
     * (9 karakter) tetap di atas batas ini, dan keduanya juga dikenali lewat
     * pola di bawah walau tidak diberikan pemanggil.
     */
    public const MIN_SECRET_LENGTH = 4;

    /** Kode laporan `HV-XXXXXX`, juga bentuk tanpa tanda hubung seperti `ReportCode::normalize`. */
    private const CODE_PATTERN = '/(?<![A-Za-z0-9])HV-?[A-Za-z0-9]{6}(?![A-Za-z0-9])/';

    /** PIN enam angka sebagai token utuh, bukan potongan dari deret yang lebih panjang. */
    private const PIN_PATTERN = '/(?<![A-Za-z0-9])[0-9]{6}(?![A-Za-z0-9])/';

    /**
     * Mode pengiriman menurut konfigurasi: `log` bila host atau port SMTP
     * kosong, `smtp` bila keduanya terisi (Req 6.10).
     *
     * Hanya "kosong" yang diperiksa, sesuai bunyi Req 6.10 ("absent or empty").
     * Port yang terisi tetapi tidak masuk akal (`0`, `abc`) tetap berarti mode
     * `smtp`: kegagalannya muncul sebagai percobaan kirim yang gagal dan
     * ditangani `MailQueue`, bukan disembunyikan sebagai mode `log` yang
     * membuat pengelola menyangka email sudah berangkat.
     *
     * @param array<string,mixed> $env isi .env yang relevan, mis. ['MAIL_HOST' => 'smtp.example.com']
     */
    public static function mode(array $env): string
    {
        $host = self::text($env[self::ENV_HOST] ?? null);
        $port = self::text($env[self::ENV_PORT] ?? null);

        return $host === '' || $port === '' ? self::MODE_LOG : self::MODE_SMTP;
    }

    /**
     * Benar bila email tipe ini boleh dikirim ke pengguna tersebut (Req 6.14).
     *
     * Pengguna dengan `allow_email = 1` menerima semua tipe. Pengguna dengan
     * `allow_email = 0` hanya menerima `password_reset` dan `invitation`;
     * untuk tipe lain pemanggil tidak boleh mengirim maupun mengantrekan.
     *
     * Nama tipe dibandingkan setelah dinormalkan, jadi `Password-Reset` dan
     * ` password_reset ` dianggap sama.
     */
    public static function mayDeliver(string $type, bool $allowEmail): bool
    {
        return $allowEmail || in_array(self::normalizeType($type), self::ALWAYS, true);
    }

    /**
     * Nama tipe email yang dipakai sebagai kunci: huruf kecil, tanpa spasi di
     * ujung, tanda hubung menjadi garis bawah. Nilai kosong menjadi
     * `self::TYPE_GENERAL` agar tipe yang hilang tidak pernah lolos sebagai
     * salah satu `ALWAYS`.
     */
    public static function normalizeType(string $type): string
    {
        $key = strtolower(trim($type));
        $key = str_replace([' ', '-'], '_', $key);

        return $key === '' ? self::TYPE_GENERAL : $key;
    }

    /**
     * Benar bila subjek atau isi email memuat teks yang dilarang: kronologi,
     * PIN, atau kode laporan (Req 6.13).
     *
     * Dua lapis pemeriksaan:
     *
     * 1. Setiap nilai `$secrets` (mis. kronologi dan PIN laporan yang sedang
     *    diproses) dicari sebagai substring. Perbandingan mengabaikan besar
     *    kecil huruf dan menyamakan spasi berurutan, sehingga kronologi yang
     *    dilipat ulang oleh template tetap tertangkap.
     * 2. Pola kode laporan `HV-XXXXXX` dan PIN enam angka dicari walau
     *    pemanggil tidak memberikan nilainya, karena pagar ini juga harus
     *    menangkap kode laporan milik orang lain yang bocor ke template.
     *
     * Pola PIN sengaja hanya mengenali enam angka sebagai token utuh. Tanpa
     * batas itu, tautan atur ulang kata sandi yang memuat token acak panjang
     * akan ikut terblokir dan Req 6.14 (email atur ulang selalu boleh)
     * mustahil dipenuhi.
     *
     * @param list<mixed>|array<string,mixed> $secrets kronologi, PIN, kode laporan, atau potongan lain
     *        yang tidak boleh muncul. Nilai kosong dan yang lebih pendek dari
     *        `MIN_SECRET_LENGTH` dilewati.
     */
    public static function containsForbidden(string $subject, string $body, array $secrets = []): bool
    {
        $haystack = self::normalizeSpace($subject . "\n" . $body);

        if (preg_match(self::CODE_PATTERN, $haystack) === 1 || preg_match(self::PIN_PATTERN, $haystack) === 1) {
            return true;
        }

        $lower = mb_strtolower($haystack);
        foreach ($secrets as $secret) {
            $needle = mb_strtolower(self::normalizeSpace(self::text($secret)));
            if (mb_strlen($needle) < self::MIN_SECRET_LENGTH) {
                continue;
            }
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Teks setelah trim. Nilai non-skalar (null, array, objek) menjadi string
     * kosong, jadi konfigurasi atau daftar rahasia yang bentuknya tidak terduga
     * tidak pernah memicu galat tipe.
     */
    private static function text(mixed $raw): string
    {
        if (is_string($raw)) {
            return trim($raw);
        }
        if (is_int($raw) || is_float($raw)) {
            return trim((string) $raw);
        }

        return '';
    }

    /**
     * Semua rangkaian spasi, tab, dan baris baru menjadi satu spasi.
     *
     * Bila teks bukan UTF-8 yang sah, pola `/u` gagal dan mengembalikan null.
     * Dalam keadaan itu dipakai pola tanpa `/u`, lalu teks aslinya, supaya
     * pagar Req 6.13 tidak pernah kehilangan isi yang harus diperiksa.
     */
    private static function normalizeSpace(string $text): string
    {
        $clean = preg_replace('/\s+/u', ' ', $text);
        if (!is_string($clean)) {
            $clean = preg_replace('/\s+/', ' ', $text);
        }

        return trim(is_string($clean) ? $clean : $text);
    }
}
