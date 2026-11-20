<?php
declare(strict_types=1);

/**
 * Agregasi statistik dan ekspor anonim (Req 7) — murni, tanpa DB dan tanpa time().
 * Rentang tanggal selalu masuk sebagai parameter.
 *
 * Req 7.1: breakdown dan cross menghitung laporan per kategori, provinsi, bulan,
 *   dan status, hanya untuk laporan di dalam rentang (inRange).
 * Req 7.2, 7.14: medianHours memberi median jam bulat dan null bila belum ada
 *   balasan pendamping, slaPercent membulatkan ke satu desimal.
 * Req 7.3, 7.4: merge memindahkan sel di bawah 5 ke grup "Lainnya", untuk satu
 *   maupun dua dimensi.
 * Req 7.5, 7.6: csv hanya memuat kolom dimensi, label, jumlah dengan
 *   penggabungan sel yang sama seperti tampilan layar.
 * Req 7.10: tidak ada keluaran dengan hitungan 1 sampai 4 — ini yang melindungi
 *   identitas penyintas di daerah atau kategori yang sepi.
 * Req 7.11: jumlah semua grup sama dengan total laporan dalam rentang, maka sisa
 *   penggabungan 1 sampai 4 ditambahkan ke sel terkecil, bukan dibuang.
 * Req 7.12: parseRange menolak rentang terbalik dan tanggal tak terbaca.
 * Req 7.13: total rentang 1 sampai 4 menghasilkan nol baris rincian.
 */
final class Aggregator
{
    /** Hitungan terkecil yang boleh berdiri sebagai satu baris (Req 7.3, 7.10). */
    public const MIN_CELL = 5;

    /** Nama grup gabungan sel kecil (Req 7.3, 7.4). */
    public const OTHER_LABEL = 'Lainnya';

    /** Label untuk baris laporan yang nilai dimensinya kosong atau tak terbaca. */
    public const UNKNOWN_LABEL = 'Tidak diketahui';

    /** Pemisah label pada agregasi dua dimensi (Req 7.4). */
    public const CROSS_SEPARATOR = ' / ';

    /** Dimensi yang dipakai halaman statistik (Req 7.1). `month` diturunkan dari created_at. */
    public const DIMENSIONS = ['category', 'province', 'month', 'status'];

    /** Dimensi turunan: bulan kalender YYYY-MM dari kolom created_at. */
    public const MONTH_DIM = 'month';

    /** Satu-satunya kolom yang boleh ada di berkas ekspor (Req 7.5). */
    public const CSV_HEADER = ['dimensi', 'label', 'jumlah'];

    /** Batas panjang label ekspor, menjaga kolom label tetap berisi nama grup (Req 7.5). */
    public const CSV_LABEL_MAX = 120;

    /** Pesan untuk rentang yang totalnya 1 sampai 4 (Req 7.13). */
    public const INSUFFICIENT_MESSAGE = 'Data belum cukup untuk ditampilkan.';

    /** Pesan untuk rentang tanpa laporan sama sekali. */
    public const EMPTY_MESSAGE = 'Belum ada data pada rentang ini.';

    /** Pengganti angka median saat belum ada balasan pendamping (Req 7.14). */
    public const NO_MEDIAN_MESSAGE = 'belum ada data';

    /** Pesan rentang tanggal tidak valid (Req 7.12). */
    public const RANGE_ERROR_EMPTY = 'Isi tanggal awal dan tanggal akhir dengan format YYYY-MM-DD.';
    public const RANGE_ERROR_FORMAT = 'Rentang tanggal tidak valid. Pakai format YYYY-MM-DD, misalnya 2026-01-31.';
    public const RANGE_ERROR_ORDER = 'Tanggal awal tidak boleh setelah tanggal akhir.';

    /** Kolom waktu laporan yang dipakai penyaringan rentang dan dimensi bulan. */
    private const DATE_FIELD = 'created_at';

    /** Karakter awal label yang dijadikan rumus oleh pengolah lembar kerja. */
    private const CSV_RISKY_PREFIX = "=+-@\t\r";

    /**
     * Laporan yang tanggal pembuatannya berada di dalam rentang, termasuk kedua
     * ujungnya. Dipakai bersama oleh breakdown, cross, dan penghitungan total,
     * supaya total pada layar dan jumlah seluruh grup dihitung dari himpunan
     * baris yang sama (Req 7.11).
     *
     * Rentang kosong berarti tanpa penyaringan. Batas yang tidak terbaca
     * diabaikan sebagai batas; pakai parseRange lebih dulu agar rentang salah
     * ditolak di depan (Req 7.12). Baris dengan created_at tak terbaca tidak
     * bisa dibuktikan berada di dalam rentang, jadi tidak dihitung.
     *
     * @param list<array<string,mixed>> $rows baris tabel reports
     * @param array{from?:mixed, to?:mixed, 0?:mixed, 1?:mixed} $range hasil parseRange
     * @return list<array<string,mixed>>
     */
    public static function inRange(array $rows, array $range = []): array
    {
        $from = self::ymd($range['from'] ?? $range[0] ?? null);
        $to = self::ymd($range['to'] ?? $range[1] ?? null);

        $kept = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($from === null && $to === null) {
                $kept[] = $row;
                continue;
            }

            $date = self::rowDate($row);
            if ($date === null) {
                continue;
            }
            if (($from !== null && $date < $from) || ($to !== null && $date > $to)) {
                continue;
            }
            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Hitungan laporan per nilai satu dimensi (Req 7.1). Kuncinya nilai mentah
     * dari baris, jadi pemanggil bisa menerjemahkan slug kategori menjadi label
     * tampilan sebelum merge. Dimensi `month` diturunkan menjadi YYYY-MM supaya
     * tidak ada stempel waktu lebih halus dari bulan yang pernah keluar (Req 7.5).
     *
     * Nilai kosong atau tak terbaca masuk ke "Tidak diketahui" dan tetap
     * dihitung, sehingga jumlah seluruh grup sama dengan jumlah baris dalam
     * rentang (Req 7.11).
     *
     * @param list<array<string,mixed>> $rows
     * @param array{from?:mixed, to?:mixed} $range
     * @return array<string,int> label => jumlah
     */
    public static function breakdown(array $rows, string $dim, array $range = []): array
    {
        $counts = [];
        foreach (self::inRange($rows, $range) as $row) {
            $label = self::dimensionValue($row, $dim);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Hitungan laporan per kombinasi dua dimensi, misalnya kategori dan provinsi
     * (Req 7.4). Label gabungan dipisah CROSS_SEPARATOR supaya hasilnya bisa
     * langsung masuk merge dan csv seperti agregasi satu dimensi.
     *
     * @param list<array<string,mixed>> $rows
     * @param array{from?:mixed, to?:mixed} $range
     * @return array<string,int> "nilai1 / nilai2" => jumlah
     */
    public static function cross(array $rows, string $d1, string $d2, array $range = []): array
    {
        $counts = [];
        foreach (self::inRange($rows, $range) as $row) {
            $label = self::dimensionValue($row, $d1) . self::CROSS_SEPARATOR . self::dimensionValue($row, $d2);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Penggabungan sel kecil, pengganti Stats::mergeSmall yang menyembunyikan
     * sisa kecil dan karena itu melanggar Req 7.11.
     *
     * Urutan aturan:
     *   1. Total 0: tidak ada baris, tampilkan EMPTY_MESSAGE.
     *   2. Total 1 sampai 4: tidak ada baris sama sekali dan `insufficient`
     *      benar, tampilkan INSUFFICIENT_MESSAGE (Req 7.13).
     *   3. Sel di bawah $min dijumlahkan ke grup "Lainnya" (Req 7.3, 7.4).
     *   4. Bila "Lainnya" sendiri tinggal 1 sampai 4, isinya ditambahkan ke sel
     *      terkecil yang tersisa, bukan dibuang, supaya tidak ada baris bernilai
     *      1 sampai 4 (Req 7.10) sementara jumlah grup tetap sama dengan total
     *      (Req 7.11). Konsekuensinya label sel terkecil itu sedikit melebar;
     *      itu harga yang dipilih agar total tetap jujur.
     *
     * Sel bernilai 0 tidak pernah menjadi baris: ia tidak menambah total dan
     * labelnya tidak memberi informasi apa pun.
     *
     * @param array<string,int|float|string> $counts label => jumlah
     * @return array{labels:list<string>, values:list<int>, rows:array<string,int>,
     *     total:int, hidden:int, insufficient:bool, merged:bool}
     */
    public static function merge(array $counts, int $min = self::MIN_CELL): array
    {
        $min = max(1, $min);
        $cells = self::normalizeCounts($counts);
        $total = array_sum($cells);

        if ($total <= 0) {
            return self::result([], 0, 0, false, false);
        }
        if ($total < $min) {
            // Req 7.13: rentang sekecil ini tidak boleh menghasilkan rincian apa pun.
            return self::result([], $total, $total, true, false);
        }

        $kept = [];
        $small = 0;
        $merged = false;
        foreach ($cells as $label => $n) {
            if ($n >= $min) {
                $kept[$label] = $n;
                continue;
            }
            $small += $n;
            if ($n > 0) {
                $merged = true;
            }
        }

        if ($small >= $min) {
            $kept[self::OTHER_LABEL] = ($kept[self::OTHER_LABEL] ?? 0) + $small;
        } elseif ($small > 0) {
            if ($kept === []) {
                // Tidak terjangkau: tanpa sel besar, $small sama dengan $total
                // yang sudah dipastikan minimal $min. Dijaga agar jalur sesat
                // pun tidak pernah memunculkan baris 1 sampai 4.
                return self::result([], $total, $total, true, true);
            }
            $kept[self::smallestLabel($kept)] += $small;
            $merged = true;
        }

        // Pagar terakhir untuk Req 7.10: lebih baik tidak menampilkan rincian
        // daripada membocorkan satu sel kecil.
        foreach ($kept as $n) {
            if ($n > 0 && $n < $min) {
                return self::result([], $total, $total, true, true);
            }
        }

        return self::result(self::sortCells($kept), $total, 0, false, $merged);
    }

    /**
     * Median durasi balasan dalam jam bulat (Req 7.2), atau null bila tidak ada
     * durasi yang bisa dipakai — tampilannya "belum ada data" (Req 7.14).
     *
     * Masukannya menit (mis. selisih TIMESTAMPDIFF dari status "diterima" ke
     * pesan pendamping pertama). Jumlah data genap memakai rata-rata dua nilai
     * tengah, lalu hasilnya dibulatkan ke jam terdekat. Nilai negatif dan bukan
     * angka dilewati, bukan dianggap nol, supaya jam kacau tidak menarik median.
     *
     * @param list<int|float|string|null> $durationsMinutes
     */
    public static function medianHours(array $durationsMinutes): ?int
    {
        $minutes = [];
        foreach ($durationsMinutes as $raw) {
            $value = self::minutes($raw);
            if ($value !== null) {
                $minutes[] = $value;
            }
        }

        if ($minutes === []) {
            return null;
        }

        sort($minutes);
        $count = count($minutes);
        $mid = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $minutes[$mid]
            : ($minutes[$mid - 1] + $minutes[$mid]) / 2;

        return (int) round($median / 60);
    }

    /**
     * Persentase kasus yang memenuhi SLA balasan, satu desimal (Req 7.2), atau
     * null bila tidak ada kasus yang bisa dinilai. Pemanggil menentukan $met
     * dengan batas jam dari Sla::hours.
     *
     * $met dijepit ke rentang 0 sampai $total agar hitungan yang tidak konsisten
     * tidak pernah menghasilkan persentase di luar 0 sampai 100.
     */
    public static function slaPercent(int $met, int $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        $met = max(0, min($met, $total));

        return round($met / $total * 100, 1);
    }

    /**
     * Rentang tanggal yang sudah diperiksa, atau pesan galat Bahasa Indonesia
     * (Req 7.12). Pemanggil wajib memeriksa `is_string` lebih dulu: selama
     * hasilnya teks, halaman tidak boleh menampilkan statistik dan tidak boleh
     * membuat berkas ekspor.
     *
     * Hanya YYYY-MM-DD yang diterima, dan tanggalnya harus benar-benar ada:
     * '2026-02-30', '2026-2-3', dan '31-01-2026' ditolak. `from_at` dan `to_at`
     * adalah batas DATETIME inklusif untuk query.
     *
     * @return array{from:string, to:string, from_at:string, to_at:string}|string
     */
    public static function parseRange(string $from, string $to): array|string
    {
        if (trim($from) === '' || trim($to) === '') {
            return self::RANGE_ERROR_EMPTY;
        }

        $start = self::ymd($from);
        $end = self::ymd($to);
        if ($start === null || $end === null) {
            return self::RANGE_ERROR_FORMAT;
        }
        if ($start > $end) {
            return self::RANGE_ERROR_ORDER;
        }

        return [
            'from' => $start,
            'to' => $end,
            'from_at' => $start . ' 00:00:00',
            'to_at' => $end . ' 23:59:59',
        ];
    }

    /**
     * Berkas ekspor agregat (Req 7.5, 7.6). Hanya tiga kolom: dimensi, label,
     * jumlah. Tidak ada kode laporan, user id, teks bebas, maupun stempel waktu
     * yang lebih halus dari bulan — label yang berbentuk tanggal atau waktu
     * dipotong menjadi YYYY-MM, dan label yang terlalu panjang dipangkas.
     *
     * Hitungan mentah masuk dan merge dijalankan di dalam, jadi berkas ekspor
     * memakai penggabungan sel yang sama dengan tampilan layar walaupun
     * pemanggil lupa memanggil merge (Req 7.6). Dimensi yang totalnya 1 sampai 4
     * tidak menyumbang satu baris pun (Req 7.10, 7.13).
     *
     * @param array<string, array<string,int|float|string>> $sections nama dimensi => hitungan
     */
    public static function csv(array $sections, int $min = self::MIN_CELL): string
    {
        $lines = [self::csvRow(self::CSV_HEADER)];

        foreach ($sections as $dimension => $counts) {
            $name = self::csvLabel((string) $dimension);
            if ($name === '' || !is_array($counts)) {
                continue;
            }

            $merged = self::merge(self::csvLabels($counts), $min);
            foreach ($merged['rows'] as $label => $n) {
                $lines[] = self::csvRow([$name, (string) $label, (string) $n]);
            }
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Bentuk hasil merge. `labels` dan `values` untuk grafik, `rows` untuk tabel
     * dan csv, `hidden` jumlah laporan yang tidak muncul sebagai baris (hanya
     * pada kasus total 1 sampai 4).
     *
     * @param array<string,int> $rows
     * @return array{labels:list<string>, values:list<int>, rows:array<string,int>,
     *     total:int, hidden:int, insufficient:bool, merged:bool}
     */
    private static function result(array $rows, int $total, int $hidden, bool $insufficient, bool $merged): array
    {
        return [
            'labels' => array_map('strval', array_keys($rows)),
            'values' => array_values($rows),
            'rows' => $rows,
            'total' => $total,
            'hidden' => $hidden,
            'insufficient' => $insufficient,
            'merged' => $merged,
        ];
    }

    /**
     * Label dan hitungan yang sudah bersih: kunci menjadi teks (kosong menjadi
     * "Tidak diketahui"), nilai menjadi bilangan bulat tak negatif, dan label
     * kembar dijumlahkan supaya tidak ada baris ganda.
     *
     * @param array<string,int|float|string> $counts
     * @return array<string,int>
     */
    private static function normalizeCounts(array $counts): array
    {
        $cells = [];
        foreach ($counts as $label => $n) {
            $key = self::text((string) $label);
            if ($key === '') {
                $key = self::UNKNOWN_LABEL;
            }
            $cells[$key] = ($cells[$key] ?? 0) + self::count($n);
        }

        return $cells;
    }

    /**
     * Baris diurutkan dari terbesar, label sama besar diurutkan menurut abjad
     * agar keluaran selalu sama untuk masukan yang sama. "Lainnya" selalu di
     * baris terakhir.
     *
     * @param array<string,int> $cells
     * @return array<string,int>
     */
    private static function sortCells(array $cells): array
    {
        $other = null;
        if (array_key_exists(self::OTHER_LABEL, $cells)) {
            $other = $cells[self::OTHER_LABEL];
            unset($cells[self::OTHER_LABEL]);
        }

        $labels = array_keys($cells);
        usort($labels, static function (string $a, string $b) use ($cells): int {
            return $cells[$b] <=> $cells[$a] ?: strcmp($a, $b);
        });

        $sorted = [];
        foreach ($labels as $label) {
            if ($cells[$label] > 0) {
                $sorted[$label] = $cells[$label];
            }
        }
        if ($other !== null && $other > 0) {
            $sorted[self::OTHER_LABEL] = $other;
        }

        return $sorted;
    }

    /**
     * Label sel dengan hitungan terkecil, tujuan penggabungan sisa 1 sampai 4
     * (Req 7.11). Hitungan sama besar dipilih menurut abjad agar hasilnya tetap.
     *
     * @param array<string,int> $cells tidak boleh kosong
     */
    private static function smallestLabel(array $cells): string
    {
        $labels = array_keys($cells);
        usort($labels, static function (string $a, string $b) use ($cells): int {
            return $cells[$a] <=> $cells[$b] ?: strcmp($a, $b);
        });

        return $labels[0];
    }

    /** Nilai satu dimensi pada satu baris laporan; `month` menjadi YYYY-MM. */
    private static function dimensionValue(array $row, string $dim): string
    {
        if ($dim === self::MONTH_DIM) {
            $date = self::rowDate($row);

            return $date === null ? self::UNKNOWN_LABEL : substr($date, 0, 7);
        }

        $value = self::text($row[$dim] ?? null);

        return $value === '' ? self::UNKNOWN_LABEL : $value;
    }

    /** Tanggal laporan sebagai 'Y-m-d', atau null bila created_at tak terbaca. */
    private static function rowDate(array $row): ?string
    {
        return self::ymd($row[self::DATE_FIELD] ?? null);
    }

    /**
     * Normalisasi tanggal menjadi 'Y-m-d'. Menerima DateTimeInterface, 'Y-m-d',
     * dan nilai DATETIME MySQL ('Y-m-d H:i:s' atau dengan 'T'). Tanggal yang
     * tidak ada di kalender ditolak (Req 7.12).
     */
    private static function ymd(mixed $raw): ?string
    {
        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        $value = self::text($raw);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/', $value, $m) !== 1) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    /** Hitungan tak negatif. Nilai bukan angka atau negatif menjadi 0. */
    private static function count(mixed $raw): int
    {
        if (is_int($raw)) {
            return max(0, $raw);
        }
        if (is_float($raw)) {
            return is_finite($raw) ? max(0, (int) round($raw)) : 0;
        }
        if (is_string($raw) && is_numeric(trim($raw))) {
            return max(0, (int) round((float) trim($raw)));
        }

        return 0;
    }

    /** Durasi menit yang bisa dipakai median, atau null bila negatif atau bukan angka. */
    private static function minutes(mixed $raw): ?float
    {
        if (is_int($raw)) {
            return $raw < 0 ? null : (float) $raw;
        }
        if (is_float($raw)) {
            return is_finite($raw) && $raw >= 0 ? $raw : null;
        }
        if (is_string($raw) && is_numeric(trim($raw))) {
            $value = (float) trim($raw);

            return is_finite($value) && $value >= 0 ? $value : null;
        }

        return null;
    }

    /**
     * Label siap ekspor: tanpa pemutus baris, tanggal dan waktu dipotong ke
     * bulan, dan panjangnya dibatasi (Req 7.5). Label dua dimensi disaring
     * bagian per bagian supaya "2026-03-17 / Jawa Barat" ikut terpotong.
     */
    private static function csvLabel(string $raw): string
    {
        $parts = explode(self::CROSS_SEPARATOR, $raw);
        foreach ($parts as $i => $part) {
            $part = self::text(str_replace(["\r", "\n", "\t", "\0"], ' ', $part));
            $parts[$i] = self::monthOnly($part);
        }

        $label = self::text(implode(self::CROSS_SEPARATOR, $parts));

        return mb_substr($label, 0, self::CSV_LABEL_MAX);
    }

    /**
     * Hitungan dengan label yang sudah disaring untuk ekspor. Label yang menjadi
     * sama setelah disaring dijumlahkan, jadi total tetap utuh (Req 7.11).
     *
     * @param array<string,int|float|string> $counts
     * @return array<string,int>
     */
    private static function csvLabels(array $counts): array
    {
        $out = [];
        foreach ($counts as $label => $n) {
            $key = self::csvLabel((string) $label);
            if ($key === '') {
                $key = self::UNKNOWN_LABEL;
            }
            $out[$key] = ($out[$key] ?? 0) + self::count($n);
        }

        return $out;
    }

    /** Tanggal atau stempel waktu menjadi YYYY-MM; teks lain tidak diubah (Req 7.5). */
    private static function monthOnly(string $value): string
    {
        if (preg_match('/^(\d{4}-\d{2})-\d{2}([ T].*)?$/', $value, $m) === 1) {
            return $m[1];
        }

        return $value;
    }

    /**
     * Satu baris CSV. Kolom dikutip hanya bila perlu, dan label yang diawali
     * karakter rumus diberi kutip satu agar pengolah lembar kerja tidak
     * menjalankannya.
     *
     * @param list<string> $fields
     */
    private static function csvRow(array $fields): string
    {
        $out = [];
        foreach ($fields as $field) {
            $value = str_replace(["\r", "\n", "\0"], ' ', $field);
            if ($value !== '' && str_contains(self::CSV_RISKY_PREFIX, $value[0])) {
                $value = "'" . $value;
            }
            if (preg_match('/[",]/', $value) === 1) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $out[] = $value;
        }

        return implode(',', $out);
    }

    /** Teks setelah trim. Nilai non-skalar (null, array, objek) menjadi string kosong. */
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
}
