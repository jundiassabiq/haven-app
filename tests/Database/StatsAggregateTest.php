<?php
declare(strict_types=1);

namespace Haven\Tests\Database;

use Config;
use Database;
use Haven\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;
use Sla;
use Stats;

/**
 * Tes integrasi untuk statistik agregat di jalur nyata: query, penggabungan sel
 * kecil, median, persentase SLA, dan berkas ekspor (Req 7.1 sampai 7.6, 7.10
 * sampai 7.14, 10.1).
 *
 * Fungsi murni Aggregator diuji terpisah. Yang dijaga di sini adalah bahwa
 * Stats benar-benar memakainya, sehingga tidak ada baris 1 sampai 4 dan tidak
 * ada total yang hilang pada keluaran yang dilihat admin.
 */
final class StatsAggregateTest extends TestCase
{
    /** Rentang tetap supaya hasilnya tidak bergantung pada tanggal menjalankan tes. */
    private const RANGE = ['from' => '2026-01-01', 'to' => '2026-03-31'];

    private array $range;
    private int $seq = 0;
    private ?string $slaDefault = null;
    private ?string $slaDanger = null;

    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }

        TestDatabase::bind();
        TestDatabase::truncate('messages', 'report_status_logs', 'reports');

        $range = Stats::range(self::RANGE['from'], self::RANGE['to']);
        $this->assertIsArray($range);
        $this->range = $range;

        $this->slaDefault = Config::get(Sla::ENV_DEFAULT);
        $this->slaDanger = Config::get(Sla::ENV_DANGER);
    }

    protected function tearDown(): void
    {
        // Nilai kosong dibaca Sla sebagai "tidak diatur", sama seperti tidak ada di .env.
        Config::set(Sla::ENV_DEFAULT, $this->slaDefault ?? '');
        Config::set(Sla::ENV_DANGER, $this->slaDanger ?? '');
        TestDatabase::release();
    }

    /** Req 7.13: total 1 sampai 4 tidak menghasilkan satu baris rincian pun. */
    public function testRangeWithFewerThanFiveReportsShowsNoBreakdownRows(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->report(['category' => 'fisik', 'province' => 'Bali', 'created_at' => '2026-02-0' . ($i + 1) . ' 09:00:00']);
        }

        $agg = Stats::aggregate($this->range);

        $this->assertSame(4, $agg['total']);
        $this->assertTrue($agg['insufficient']);
        $this->assertFalse($agg['empty']);
        foreach (array_keys(Stats::DIMENSIONS) as $dim) {
            $this->assertSame([], $agg['sections'][$dim]['rows'], "Dimensi $dim masih memunculkan baris pada rentang sepi.");
        }
        $this->assertSame([], $agg['cross']['rows']);
        $this->assertNull($agg['median']);
    }

    /** Rentang tanpa laporan: "belum ada data", bukan galat. */
    public function testEmptyRangeIsReportedAsEmpty(): void
    {
        $agg = Stats::aggregate($this->range);

        $this->assertSame(0, $agg['total']);
        $this->assertTrue($agg['empty']);
        $this->assertFalse($agg['insufficient']);
        $this->assertNull($agg['median']);
        $this->assertNull($agg['sla']);
    }

    /**
     * Req 7.10 dan 7.11 pada keluaran yang benar-benar dilihat admin: tidak ada
     * baris bernilai 1 sampai 4, dan jumlah tiap dimensi sama dengan total.
     */
    public function testBreakdownsKeepSmallCellsHiddenAndTotalsIntact(): void
    {
        $this->seed12();

        $agg = Stats::aggregate($this->range);

        $this->assertSame(12, $agg['total']);
        $sections = $agg['sections'] + ['cross' => $agg['cross']];
        foreach ($sections as $name => $section) {
            $sum = 0;
            foreach ($section['rows'] as $label => $n) {
                $this->assertGreaterThanOrEqual(
                    Stats::MIN_GROUP,
                    $n,
                    "Dimensi $name memunculkan sel kecil: $label = $n."
                );
                $sum += $n;
            }
            $this->assertSame(12, $sum, "Jumlah grup dimensi $name tidak sama dengan total laporan.");
        }
    }

    /** Req 7.1, 7.3: kategori di bawah 5 kasus muncul sebagai "Lainnya", bukan hilang. */
    public function testSmallCategoriesAreMergedIntoLainnya(): void
    {
        $this->seed12();

        $rows = Stats::aggregate($this->range)['sections']['category']['rows'];

        $this->assertSame(7, $rows['Pelecehan fisik'] ?? null);
        $this->assertSame(5, $rows['Lainnya'] ?? null, 'KDRT (3) dan kekerasan psikis (2) harus bergabung menjadi satu grup 5.');
        $this->assertArrayNotHasKey('KDRT', $rows);
    }

    /** Req 7.2: median jam dari status "diterima" ke pesan pendamping pertama. */
    public function testMedianUsesTheFirstCompanionReply(): void
    {
        $this->seed5WithReplies();

        $agg = Stats::aggregate($this->range);

        $this->assertSame(3, $agg['replied']);
        $this->assertSame(2, $agg['median'], 'Median dari 60, 120, dan 180 menit adalah 120 menit, yaitu 2 jam.');
    }

    /** Req 7.14: tanpa balasan pendamping, median tidak punya angka. */
    public function testMedianIsNullWhenNoCaseHasACompanionReply(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $id = $this->report(['category' => 'fisik', 'province' => 'Bali', 'created_at' => '2026-02-10 09:00:00']);
            $this->received($id, '2026-02-10 09:00:00');
        }

        $agg = Stats::aggregate($this->range);

        $this->assertSame(5, $agg['total']);
        $this->assertSame(0, $agg['replied']);
        $this->assertNull($agg['median']);
        $this->assertNull($agg['sla']);
    }

    /** Req 7.2 dan 6.1: batas SLA diambil dari konfigurasi lewat Sla::hours, dua cabang sekaligus. */
    public function testSlaPercentFollowsConfiguredHours(): void
    {
        Config::set(Sla::ENV_DEFAULT, '1');
        Config::set(Sla::ENV_DANGER, '2');
        $this->seed5WithReplies();

        $agg = Stats::aggregate($this->range);

        $this->assertSame(['danger' => 2, 'default' => 1], $agg['sla_hours']);
        // 60 menit (biasa, batas 1 jam) dan 120 menit (bahaya, batas 2 jam) masuk; 180 menit tidak.
        $this->assertSame(2, $agg['sla_met']);
        $this->assertSame(66.7, $agg['sla']);
    }

    /** Req 7.5, 7.6: ekspor hanya memuat hitungan agregat, dengan penggabungan yang sama. */
    public function testCsvContainsOnlyAggregateCounts(): void
    {
        $this->seed12();

        $csv = Stats::csv($this->range);
        $lines = array_values(array_filter(explode("\r\n", $csv), static fn (string $l) => $l !== ''));

        $this->assertSame('dimensi,label,jumlah', $lines[0]);
        $this->assertStringNotContainsString('HV-', $csv, 'Kode laporan tidak boleh ada di ekspor.');
        $this->assertStringNotContainsString('kronologi', mb_strtolower($csv));
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $csv, 'Ekspor tidak boleh memuat tanggal, paling halus bulan.');
        $this->assertMatchesRegularExpression('/^2026-0\d$/', $this->firstLabel($lines, 'Bulan'));

        $perDimension = [];
        foreach (array_slice($lines, 1) as $line) {
            [$dimension, $label, $n] = str_getcsv($line);
            $this->assertNotSame('', $label);
            $this->assertGreaterThanOrEqual(Stats::MIN_GROUP, (int) $n, "Baris ekspor '$dimension / $label' bernilai $n.");
            $perDimension[$dimension] = ($perDimension[$dimension] ?? 0) + (int) $n;
        }

        $this->assertNotSame([], $perDimension);
        foreach ($perDimension as $dimension => $sum) {
            $this->assertSame(12, $sum, "Jumlah baris ekspor dimensi $dimension tidak sama dengan total laporan.");
        }
    }

    /** Req 7.13: rentang sepi juga tidak menghasilkan baris di berkas ekspor. */
    public function testCsvHasNoDataRowsForASmallRange(): void
    {
        $this->report(['category' => 'fisik', 'province' => 'Bali', 'created_at' => '2026-02-01 09:00:00']);
        $this->report(['category' => 'kdrt', 'province' => 'Aceh', 'created_at' => '2026-02-02 09:00:00']);

        $csv = Stats::csv($this->range);

        $this->assertSame("dimensi,label,jumlah\r\n", $csv);
    }

    /** Req 10.1: statistik tidak pernah membaca kronologi atau kontak. */
    public function testStatisticsReadNoChronologyColumn(): void
    {
        $this->report(['category' => 'fisik', 'province' => 'Bali', 'created_at' => '2026-02-01 09:00:00']);

        $row = Stats::rows($this->range)[0];

        $this->assertSame(Stats::COLUMNS, array_keys($row));
        foreach (['chronology_enc', 'contact_enc', 'victim_contact_enc', 'code', 'pin_hash', 'user_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $row);
        }
    }

    /** 7 kasus besar, 3 dan 2 kasus kecil: cukup untuk memaksa satu grup "Lainnya" di tiap dimensi. */
    private function seed12(): void
    {
        $mix = array_merge(
            array_fill(0, 7, ['category' => 'fisik', 'province' => 'Jawa Barat']),
            array_fill(0, 3, ['category' => 'kdrt', 'province' => 'Bali']),
            array_fill(0, 2, ['category' => 'psikis', 'province' => 'Aceh'])
        );
        foreach ($mix as $i => $row) {
            $this->report($row + ['created_at' => '2026-0' . (1 + intdiv($i, 7)) . '-1' . ($i % 7) . ' 09:00:00']);
        }
    }

    /** 5 kasus, tiga di antaranya dibalas pendamping setelah 60, 120, dan 180 menit. */
    private function seed5WithReplies(): void
    {
        $plan = [
            ['minutes' => 60, 'danger' => 0],
            ['minutes' => 120, 'danger' => 1],
            ['minutes' => 180, 'danger' => 0],
            ['minutes' => null, 'danger' => 0],
            ['minutes' => null, 'danger' => 0],
        ];
        foreach ($plan as $case) {
            $received = '2026-02-10 09:00:00';
            $id = $this->report([
                'category' => 'fisik',
                'province' => 'Jawa Barat',
                'in_danger' => $case['danger'],
                'created_at' => $received,
            ]);
            $this->received($id, $received);
            if ($case['minutes'] !== null) {
                $this->reply($id, date('Y-m-d H:i:s', (int) strtotime($received) + $case['minutes'] * 60));
            }
        }
    }

    private function report(array $row): int
    {
        $code = 'HV-' . str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return Database::insert(
            'INSERT INTO reports (code, pin_hash, category, province, status, in_danger, chronology_enc, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                'hash-uji',
                $row['category'],
                $row['province'],
                $row['status'] ?? 'diterima',
                (int) ($row['in_danger'] ?? 0),
                'ciphertext-uji',
                $row['created_at'],
                $row['created_at'],
            ]
        );
    }

    private function received(int $reportId, string $at): void
    {
        Database::run(
            "INSERT INTO report_status_logs (report_id, from_status, to_status, created_at) VALUES (?, NULL, 'diterima', ?)",
            [$reportId, $at]
        );
    }

    private function reply(int $reportId, string $at): void
    {
        Database::run(
            "INSERT INTO messages (report_id, sender_role, body_enc, created_at) VALUES (?, 'companion', 'ciphertext-uji', ?)",
            [$reportId, $at]
        );
    }

    /** Label pertama milik satu dimensi di berkas ekspor. */
    private function firstLabel(array $lines, string $dimension): string
    {
        foreach (array_slice($lines, 1) as $line) {
            $fields = str_getcsv($line);
            if ($fields[0] === $dimension) {
                return (string) $fields[1];
            }
        }

        return '';
    }
}
