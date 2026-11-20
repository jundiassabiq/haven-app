<?php
declare(strict_types=1);

/**
 * Mesin status antrean email (Req 6.11, 6.12) — murni, tanpa DB, tanpa
 * superglobal, dan tanpa time(). Satu baris `mail_queue` masuk sebagai jumlah
 * percobaan ulang yang gagal dan waktu percobaan terakhir; `bin/mail-queue.php`
 * yang membaca, mengirim, dan menghapus barisnya.
 *
 * Req 6.11: email yang gagal dikirim disimpan di antrean dan dicoba ulang
 *   sampai lima kali dengan jeda sepuluh menit, dan tetap di antrean selama
 *   belum terkirim dan percobaan kelima belum gagal.
 * Req 6.12: setelah percobaan ulang kelima gagal, email keluar dari antrean —
 *   pemanggil mencatat waktu, alamat penerima, dan tipe tanpa isi email,
 *   memberi notifikasi ke semua admin aktif, lalu menghapus barisnya.
 *
 * Hitungan `$failedRetries` adalah percobaan ULANG yang gagal, bukan percobaan
 * pertama dari `Mailer::send`. Baris baru masuk antrean dengan nilai 0 dan
 * `last_attempt_at` = waktu percobaan pertama yang gagal, jadi `decide`
 * mengizinkan lima percobaan ulang (nilai 0 sampai 4) sebelum menjatuhkan
 * email pada nilai 5.
 */
final class MailQueue
{
    /** Jumlah percobaan ulang maksimal sebelum email dijatuhkan (Req 6.11). */
    public const MAX_RETRY = 5;

    /** Jeda antar percobaan dalam menit (Req 6.11). */
    public const INTERVAL_MIN = 10;

    /** Kirim ulang sekarang. */
    public const SEND = 'send';

    /** Belum waktunya: biarkan di antrean tanpa diubah. */
    public const WAIT = 'wait';

    /** Sudah habis percobaan: catat tanpa isi, beri tahu admin, hapus dari antrean (Req 6.12). */
    public const DROP = 'drop';

    /**
     * Keputusan untuk satu email di antrean pada waktu $now.
     *
     * - `drop` bila percobaan ulang yang gagal sudah mencapai `MAX_RETRY`.
     *   Diperiksa lebih dulu daripada jeda waktu: email yang sudah gagal lima
     *   kali tidak perlu menunggu sepuluh menit untuk dijatuhkan, dan
     *   menahannya hanya menunda pemberitahuan ke admin (Req 6.12).
     * - `wait` bila jeda sepuluh menit sejak percobaan terakhir belum lewat.
     *   Waktu percobaan terakhir yang berada di masa depan (jam server
     *   berubah) juga berarti `wait`, bukan kirim beruntun.
     * - `send` bila jeda sudah lewat dan percobaan gagal masih di bawah
     *   `MAX_RETRY`. Tepat sepuluh menit sudah terhitung lewat.
     *
     * Nilai `$failedRetries` negatif, yang mustahil datang dari kolom
     * `TINYINT UNSIGNED` tetapi mungkin dari pemanggil lain, dibaca sebagai 0.
     *
     * @return 'send'|'wait'|'drop'
     */
    public static function decide(int $failedRetries, DateTimeImmutable $lastAttempt, DateTimeImmutable $now): string
    {
        if (max(0, $failedRetries) >= self::MAX_RETRY) {
            return self::DROP;
        }

        $elapsed = $now->getTimestamp() - $lastAttempt->getTimestamp();

        return $elapsed >= self::INTERVAL_MIN * 60 ? self::SEND : self::WAIT;
    }
}
