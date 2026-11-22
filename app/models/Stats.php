<?php
declare(strict_types=1);

/**
 * Module 10: statistik agregat. Isi laporan tidak pernah dibaca di sini.
 *
 * Setiap hitungan yang keluar dari kelas ini melewati Aggregator::merge dan
 * penjaga self::safe(), jadi dua invarian privasi berlaku di jalur nyata
 * (halaman admin, JSON grafik, berkas ekspor), bukan hanya di fungsi murni:
 *
 *   - tidak ada baris dengan hitungan 1 sampai 4 (Req 7.10)
 *   - jumlah seluruh grup sama dengan total laporan dalam rentang (Req 7.11)
 *
 * Bila salah satu invarian gagal, rincian dibuang dan halaman menampilkan
 * "data belum cukup". Lebih baik kehilangan satu tabel daripada menunjukkan
 * satu sel yang bisa menunjuk ke satu penyintas di kota kecil.
 *
 * Req 10.1: semua query di sini hanya menyebut kolom pada self::COLUMNS.
 * `chronology_enc`, `contact_enc`, dan `victim_contact_enc` tidak pernah
 * dibaca, jadi halaman statistik dan ekspor agregat tidak membuka jalur baru
 * ke kronologi untuk admin.
 */
final class Stats
{
    /** Hitungan terkecil yang boleh berdiri sebagai satu baris (Req 7.3). */
    public const MIN_GROUP = Aggregator::MIN_CELL;

    /** Dimensi Req 7.1 dan namanya di layar serta di kolom `dimensi` berkas ekspor. */
    public const DIMENSIONS = [
        'category' => 'Jenis kejadian',
        'province' => 'Provinsi',
        'month' => 'Bulan',
        'status' => 'Status',
    ];

    /** Dimensi silang Req 7.4. */
    public const CROSS = ['category', 'province'];
    public const CROSS_LABEL = 'Jenis kejadian dan provinsi';

    /** Panjang rentang bawaan halaman statistik, dalam bulan. */
    public const DEFAULT_MONTHS = 12;

    /** Satu-satunya kolom `reports` yang boleh dibaca statistik (Req 10.1). */
    public const COLUMNS = ['category', 'province', 'status', 'in_danger', 'created_at'];

    /** Pesan pada kartu angka saat rentang terlalu sepi untuk ditampilkan (Req 7.13). */
    public const INSUFFICIENT_SHORT = 'Data belum cukup';

    /**
     * Rentang tanggal pilihan admin, atau pesan galat Bahasa Indonesia bila
     * rentangnya tidak sah (Req 7.12). Dua kolom kosong berarti admin belum
     * memilih apa pun, jadi halaman memakai rentang bawaan, bukan galat.
     *
     * Pemanggil wajib memeriksa `is_string`: selama hasilnya teks, tidak ada
     * statistik yang boleh tampil dan tidak ada berkas ekspor yang boleh dibuat.
     *
     * @return array{from:string, to:string, from_at:string, to_at:string}|string
     */
    public static function range(string $from = '', string $to = ''): array|string
    {
        if ($from === '' && $to === '') {
            return self::defaultRange();
        }

        return Aggregator::parseRange($from, $to);
    }

    /** Rentang bawaan: awal bulan DEFAULT_MONTHS bulan lalu sampai hari ini. */
    public static function defaultRange(): array
    {
        $from = date('Y-m-01', (int) strtotime('first day of -' . (self::DEFAULT_MONTHS - 1) . ' months'));
        $range = Aggregator::parseRange($from, date('Y-m-d'));
        if (is_string($range)) {
            // Tidak terjangkau: kedua tanggal dibuat oleh date() sendiri.
            throw new LogicException('Rentang bawaan statistik tidak sah: ' . $range);
        }

        return $range;
    }

    /**
     * Baris laporan dalam rentang, hanya kolom yang dibutuhkan agregasi
     * (Req 10.1). Slug kategori dan status diterjemahkan ke label tampilan di
     * sini, sebelum penggabungan sel kecil, supaya layar dan berkas ekspor
     * memakai nama yang sama.
     *
     * @param array{from_at:string, to_at:string} $range
     * @return list<array<string,mixed>>
     */
    public static function rows(array $range): array
    {
        $rows = Database::all(
            'SELECT ' . implode(', ', self::COLUMNS) . ' FROM reports WHERE created_at BETWEEN ? AND ?',
            [$range['from_at'], $range['to_at']]
        );

        foreach ($rows as $i => $row) {
            $rows[$i]['category'] = Report::CATEGORIES[$row['category']] ?? (string) $row['category'];
            $rows[$i]['status'] = Report::STATUS[$row['status']] ?? (string) $row['status'];
        }

        return $rows;
    }

    /**
     * Seluruh angka halaman statistik untuk satu rentang: rincian per dimensi
     * (Req 7.1), satu rincian silang (Req 7.4), median waktu balasan dan
     * persentase SLA (Req 7.2).
     *
     * `empty` benar bila rentang tidak punya laporan sama sekali, `insufficient`
     * benar bila totalnya 1 sampai 4. Pada keadaan kedua tidak ada satu pun
     * baris rincian, dan median serta persentase SLA ikut ditahan: dengan
     * sesedikit itu, angkanya adalah angka satu kasus (Req 7.13).
     *
     * @param array{from:string, to:string, from_at:string, to_at:string} $range
     */
    public static function aggregate(array $range): array
    {
        $rows = self::rows($range);
        $total = count($rows);
        $insufficient = $total > 0 && $total < self::MIN_GROUP;

        $sections = [];
        foreach (array_keys(self::DIMENSIONS) as $dim) {
            $sections[$dim] = self::safe(Aggregator::breakdown($rows, $dim), $total);
        }

        $replies = $insufficient ? [] : self::replies($range);
        $met = self::slaMet($replies);

        return [
            'range' => $range,
            'total' => $total,
            'empty' => $total === 0,
            'insufficient' => $insufficient,
            'sections' => $sections,
            'cross' => self::safe(Aggregator::cross($rows, self::CROSS[0], self::CROSS[1]), $total),
            'median' => Aggregator::medianHours(array_column($replies, 'minutes')),
            'replied' => count($replies),
            'sla' => Aggregator::slaPercent($met, count($replies)),
            'sla_met' => $met,
            'sla_hours' => ['danger' => sla_hours(true), 'default' => sla_hours(false)],
        ];
    }

    /**
     * Berkas ekspor agregat (Req 7.5, 7.6). Isinya baris yang sudah digabung
     * dan sudah lolos penjaga invarian, jadi ekspor tidak pernah memuat sel
     * yang tidak boleh tampil di layar.
     *
     * @param array{from:string, to:string, from_at:string, to_at:string} $range
     */
    public static function csv(array $range): string
    {
        $agg = self::aggregate($range);

        $sections = [];
        foreach (self::DIMENSIONS as $dim => $label) {
            $sections[$label] = $agg['sections'][$dim]['rows'];
        }
        $sections[self::CROSS_LABEL] = $agg['cross']['rows'];

        return Aggregator::csv($sections, self::MIN_GROUP);
    }

    /** Nama berkas ekspor, memuat rentang supaya berkas lama tidak tertukar. */
    public static function exportFilename(array $range): string
    {
        return 'haven-statistik-' . $range['from'] . '-sampai-' . $range['to'] . '.csv';
    }

    /**
     * Data grafik untuk halaman admin. Hanya dua grafik, keduanya memakai
     * rincian yang sudah digabung: laporan per bulan dan jenis kejadian.
     * Label teks di `label` adalah padanan grafik untuk pembaca layar.
     *
     * @param array{from:string, to:string, from_at:string, to_at:string} $range
     */
    public static function charts(array $range): array
    {
        $agg = self::aggregate($range);
        $months = self::chronological($agg['sections']['month']['rows']);
        $categories = $agg['sections']['category'];

        return [
            'trend' => [
                'type' => 'bar',
                'labels' => array_keys($months),
                'series' => [['name' => 'Laporan', 'values' => array_values($months)]],
                'label' => 'Grafik batang laporan per bulan, ' . self::rangeText($range) . ': ' . self::seriesText($months, $agg) . '.',
            ],
            'categories' => [
                'type' => 'hbar',
                'labels' => $categories['labels'],
                'series' => [['name' => 'Kasus', 'values' => $categories['values']]],
                'hidden' => $categories['hidden'],
                'label' => 'Grafik batang jenis kejadian, ' . self::rangeText($range) . ': ' . self::seriesText($categories['rows'], $agg) . '.',
            ],
        ];
    }

    /** "1 Januari 2026 sampai 31 Maret 2026" untuk judul dan label grafik. */
    public static function rangeText(array $range): string
    {
        return id_date($range['from'], false, true) . ' sampai ' . id_date($range['to'], false, true);
    }

    /** Angka operasional antrean admin. Bukan rincian per dimensi, jadi di luar penggabungan sel kecil. */
    public static function kpis(): array
    {
        return [
            'waiting' => (int) Database::value("SELECT COUNT(*) FROM reports WHERE status = 'diterima'"),
            'oldestWaiting' => Database::value("SELECT MIN(created_at) FROM reports WHERE status = 'diterima'"),
            'active' => (int) Database::value("SELECT COUNT(*) FROM reports WHERE status IN ('didampingi','dirujuk')"),
            'companions' => (int) Database::value("SELECT COUNT(DISTINCT assigned_to) FROM reports WHERE status IN ('didampingi','dirujuk')"),
        ];
    }

    public static function workload(): array
    {
        return Database::all(
            "SELECT u.id, u.name, u.region,
               (SELECT COUNT(*) FROM reports r WHERE r.assigned_to = u.id AND r.status IN ('didampingi','dirujuk')) AS active,
               (SELECT COUNT(*) FROM report_status_logs l JOIN reports r ON r.id = l.report_id
                  WHERE r.assigned_to = u.id AND l.to_status = 'didampingi' AND l.created_at >= ?) AS this_month
             FROM users u WHERE u.role = 'companion' AND u.is_active = 1 ORDER BY active DESC, u.name",
            [date('Y-m-01 00:00:00')]
        );
    }

    /** Numbers on the public home page. */
    public static function publicNumbers(): array
    {
        return [
            'reports' => (int) Database::value('SELECT COUNT(*) FROM reports'),
            'companions' => (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'companion' AND is_active = 1"),
            'sessions' => (int) Database::value(
                "SELECT COUNT(*) FROM bookings b JOIN companion_slots s ON s.id = b.slot_id WHERE b.status = 'aktif' AND DATE_ADD(s.starts_at, INTERVAL s.duration_min MINUTE) <= ?",
                [now()]
            ),
        ];
    }

    public static function companion(int $id): array
    {
        return [
            'active' => Report::activeCountFor($id),
            'unanswered' => (int) Database::value(
                "SELECT COUNT(*) FROM reports r WHERE r.assigned_to = ? AND r.status IN ('didampingi','dirujuk')
                   AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'companion')",
                [$id]
            ),
            'today' => (int) Database::value(
                "SELECT COUNT(*) FROM bookings b JOIN companion_slots s ON s.id = b.slot_id WHERE s.companion_id = ? AND b.status = 'aktif' AND DATE(s.starts_at) = ?",
                [$id, date('Y-m-d')]
            ),
            'unread' => (int) Database::value(
                "SELECT COUNT(*) FROM messages m JOIN reports r ON r.id = m.report_id WHERE r.assigned_to = ? AND m.sender_role = 'survivor' AND m.read_at IS NULL",
                [$id]
            ),
        ];
    }

    public static function survivor(int $id): array
    {
        return [
            'active' => (int) Database::value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status NOT IN ('selesai','ditutup')", [$id]),
            'upcoming' => (int) Database::value(
                "SELECT COUNT(*) FROM bookings b JOIN companion_slots s ON s.id = b.slot_id JOIN reports r ON r.id = b.report_id WHERE r.user_id = ? AND b.status = 'aktif' AND s.starts_at > ?",
                [$id, now()]
            ),
            'unread' => (int) Database::value(
                "SELECT COUNT(*) FROM messages m JOIN reports r ON r.id = m.report_id WHERE r.user_id = ? AND m.sender_role IN ('companion','system') AND m.read_at IS NULL",
                [$id]
            ),
        ];
    }

    /**
     * Rincian yang sudah digabung dan sudah diperiksa. Penjaga terakhir untuk
     * Req 7.10 dan 7.11 di jalur nyata: satu saja sel 1 sampai 4, atau jumlah
     * grup yang tidak sama dengan total, membuat seluruh rincian ditahan.
     *
     * @param array<string,int> $counts
     */
    private static function safe(array $counts, int $total): array
    {
        $merged = Aggregator::merge($counts, self::MIN_GROUP);

        $sum = 0;
        foreach ($merged['rows'] as $n) {
            if ($n < self::MIN_GROUP) {
                return self::suppressed($total, 'ada sel di bawah ' . self::MIN_GROUP);
            }
            $sum += $n;
        }
        if ($merged['rows'] !== [] && $sum !== $total) {
            return self::suppressed($total, 'jumlah grup ' . $sum . ' tidak sama dengan total ' . $total);
        }

        return $merged;
    }

    /** Rincian ditahan. Catatan log tidak memuat label maupun isi laporan. */
    private static function suppressed(int $total, string $why): array
    {
        Logger::error('Stats: rincian agregat ditahan karena ' . $why . '.');

        return [
            'labels' => [],
            'values' => [],
            'rows' => [],
            'total' => $total,
            'hidden' => $total,
            'insufficient' => true,
            'merged' => true,
        ];
    }

    /**
     * Waktu balasan pertama per laporan, dalam menit, untuk laporan yang dibuat
     * di dalam rentang (Req 7.2): dari baris `report_status_logs` berstatus
     * "diterima" ke pesan pendamping pertama di `messages`. Laporan tanpa
     * balasan pendamping tidak punya baris di sini, jadi median menjadi null
     * dan tampilannya "belum ada data" (Req 7.14).
     *
     * @return list<array{in_danger:mixed, minutes:mixed}>
     */
    private static function replies(array $range): array
    {
        return Database::all(
            "SELECT r.in_danger, TIMESTAMPDIFF(MINUTE, l.received_at, m.first_reply_at) AS minutes
               FROM reports r
               JOIN (SELECT report_id, MIN(created_at) AS received_at FROM report_status_logs
                      WHERE to_status = 'diterima' GROUP BY report_id) l ON l.report_id = r.id
               JOIN (SELECT report_id, MIN(created_at) AS first_reply_at FROM messages
                      WHERE sender_role = 'companion' GROUP BY report_id) m ON m.report_id = r.id
              WHERE r.created_at BETWEEN ? AND ? AND m.first_reply_at >= l.received_at",
            [$range['from_at'], $range['to_at']]
        );
    }

    /**
     * Jumlah kasus yang dibalas di dalam batas SLA-nya. Batas jamnya dari
     * Sla::hours lewat sla_hours(), jadi angka 2 dan 48 jam hanya ada di
     * konfigurasi (Req 6.1, 7.2).
     *
     * @param list<array{in_danger:mixed, minutes:mixed}> $replies
     */
    private static function slaMet(array $replies): int
    {
        $met = 0;
        foreach ($replies as $reply) {
            $minutes = $reply['minutes'];
            if (!is_numeric($minutes) || (float) $minutes < 0) {
                continue;
            }
            if ((float) $minutes <= sla_hours((bool) (int) $reply['in_danger']) * 60) {
                $met++;
            }
        }

        return $met;
    }

    /**
     * Baris bulan diurutkan dari bulan terlama, "Lainnya" di belakang. Hanya
     * urutan yang berubah, hitungannya tidak, jadi totalnya tetap (Req 7.11).
     *
     * @param array<string,int> $rows
     * @return array<string,int>
     */
    private static function chronological(array $rows): array
    {
        $other = null;
        if (array_key_exists(Aggregator::OTHER_LABEL, $rows)) {
            $other = $rows[Aggregator::OTHER_LABEL];
            unset($rows[Aggregator::OTHER_LABEL]);
        }

        ksort($rows);
        if ($other !== null) {
            $rows[Aggregator::OTHER_LABEL] = $other;
        }

        return $rows;
    }

    /**
     * Isi grafik sebagai teks untuk pembaca layar, atau pesan rentang kosong
     * dan rentang terlalu sepi (Req 7.13, 7.14).
     *
     * @param array<string,int> $rows
     */
    private static function seriesText(array $rows, array $agg): string
    {
        if ($agg['empty']) {
            return Aggregator::EMPTY_MESSAGE;
        }
        if ($rows === []) {
            return Aggregator::INSUFFICIENT_MESSAGE;
        }

        $parts = [];
        foreach ($rows as $label => $n) {
            $parts[] = $label . ' ' . $n;
        }

        return implode(', ', $parts);
    }
}
