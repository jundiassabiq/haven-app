<?php
declare(strict_types=1);

/**
 * Persetujuan korban atas tindak lanjut laporan saksi (Req 5.7).
 *
 * Satu laporan hanya punya satu baris persetujuan (UNIQUE report_id), berisi
 * pendamping yang mencatatnya dan waktu pencatatan. Tabel ini tidak menyimpan
 * identitas korban, IP, maupun teks apa pun dari laporan (Req 10.4).
 */
final class ReportConsent
{
    /**
     * Mencatat persetujuan korban. Mengembalikan false bila persetujuan untuk
     * laporan ini sudah ada, sehingga pemanggil tahu tidak ada data baru yang
     * ditulis (tombol ditekan dua kali, atau dua pendamping berurutan).
     */
    public static function record(int $reportId, int $companionId): bool
    {
        $st = Database::run(
            'INSERT IGNORE INTO report_consents (report_id, companion_id, created_at) VALUES (?, ?, ?)',
            [$reportId, $companionId, now()]
        );
        return $st->rowCount() === 1;
    }

    public static function exists(int $reportId): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM report_consents WHERE report_id = ?', [$reportId]);
    }

    /** Baris persetujuan beserta nama pendamping yang mencatatnya, atau null. */
    public static function forReport(int $reportId): ?array
    {
        return Database::one(
            'SELECT c.*, u.name AS companion_name FROM report_consents c LEFT JOIN users u ON u.id = c.companion_id WHERE c.report_id = ?',
            [$reportId]
        );
    }
}
