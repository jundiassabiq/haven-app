<?php
declare(strict_types=1);

/**
 * Module 10 (admin): ekspor agregat anonim (Req 7.5 sampai 7.9).
 *
 * Tiga pagar sebelum satu byte CSV keluar, dan semuanya di luar method ini:
 *   - CSRF diperiksa App::run untuk setiap POST, sebelum middleware jalan, jadi
 *     token yang hilang atau salah berhenti dengan 419 dan tanpa berkas (Req 7.9, 10.9).
 *   - Middleware `role:admin` menolak peran lain dengan 403; yang tercatat di
 *     audit adalah `access.denied`, bukan ekspor, karena tidak ada ekspor yang
 *     terjadi (Req 7.8).
 *   - Isi berkas dibangun Stats::csv dari baris yang sudah digabung, jadi tidak
 *     ada kode laporan, user id, teks bebas, kronologi, atau stempel waktu yang
 *     lebih halus dari bulan (Req 7.5, 10.1).
 */
final class StatsController
{
    public function export(): void
    {
        $range = Stats::range(input('dari'), input('sampai'));
        if (is_string($range)) {
            // Req 7.12: rentang tidak sah berarti tidak ada berkas. Pesannya
            // muncul di halaman statistik, yang juga tidak menampilkan angka.
            redirect('/admin?' . http_build_query(['dari' => input('dari'), 'sampai' => input('sampai')]));
        }

        $csv = "\xEF\xBB\xBF" . Stats::csv($range);

        // Req 7.7 dan 10.5: pelaku, perannya, waktu, dan rentang tercatat dalam
        // request yang sama. actor_id dan created_at ditulis Audit::log sendiri.
        Audit::log(
            'stats.export',
            'stats',
            null,
            'Peran ' . Auth::role() . ' mengekspor agregat rentang ' . $range['from'] . ' sampai ' . $range['to']
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . Stats::exportFilename($range) . '"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }
}
