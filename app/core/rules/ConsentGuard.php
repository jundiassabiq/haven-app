<?php
declare(strict_types=1);

/**
 * Pagar persetujuan korban untuk laporan saksi (Req 5.7, 5.8) — murni, tanpa
 * DB, tanpa superglobal, dan tanpa time(). `CaseWorkflow::transition`
 * memanggilnya sebelum menulis status, jadi satu-satunya tempat aturan ini
 * tinggal ada di sini.
 *
 * Req 5.8: perubahan status menjadi `dirujuk` ditolak bila laporan dibuat
 *   saksi dan persetujuan korban belum tercatat. Syarat ini berlaku seragam
 *   untuk setiap transisi ke `dirujuk`, apa pun status saat ini.
 *
 * Perbandingan teks dilakukan apa adanya, tanpa trim dan tanpa penyesuaian
 * huruf. Kedua nilai datang dari kolom enum (`reports.reporter_type` dan
 * `reports.status`), jadi nilainya selalu persis salah satu kunci yang dikenal;
 * nilai lain diperlakukan sebagai bukan saksi atau bukan rujukan dan tidak
 * pernah menolak transisi atas dasar persetujuan.
 */
final class ConsentGuard
{
    /** Nilai `reports.reporter_type` yang memicu syarat persetujuan (Req 5.2). */
    public const WITNESS = 'saksi';

    /** Status tujuan yang dijaga (Req 5.8). */
    public const GUARDED = 'dirujuk';

    /** Pesan penolakan yang dilihat pendamping (Req 5.8). */
    public const ERROR = 'Laporan ini dibuat saksi, jadi rujukan butuh persetujuan korban. Catat persetujuan korban dulu di panel "Persetujuan korban", lalu ubah statusnya.';

    /**
     * Pesan galat bila perubahan status harus ditolak, atau null bila boleh
     * dilanjutkan.
     *
     * @param string $reporterType nilai `reports.reporter_type`
     * @param bool   $hasConsent   persetujuan korban sudah tercatat
     * @param string $to           status tujuan
     */
    public static function check(string $reporterType, bool $hasConsent, string $to): ?string
    {
        return self::blocks($reporterType, $hasConsent, $to) ? self::ERROR : null;
    }

    /** Benar bila transisi ini ditolak karena persetujuan korban belum tercatat. */
    public static function blocks(string $reporterType, bool $hasConsent, string $to): bool
    {
        return self::requiresConsent($reporterType, $to) && !$hasConsent;
    }

    /**
     * Benar bila status tujuan ini memerlukan persetujuan korban: laporan
     * saksi yang akan dirujuk. Dipakai tampilan untuk menjelaskan syaratnya
     * sebelum pendamping menekan simpan.
     */
    public static function requiresConsent(string $reporterType, string $to): bool
    {
        return $reporterType === self::WITNESS && $to === self::GUARDED;
    }
}
