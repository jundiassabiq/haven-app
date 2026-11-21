<?php
declare(strict_types=1);

namespace Haven\Tests\Database;

use Assignment;
use Database;
use Haven\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;
use Report;

/**
 * Tes integrasi antrean admin di jalur nyata (Req 2.7, 2.8, 4.8, 4.9, 4.10):
 * filter kategori, filter kasus anak, penanda `is_child` untuk lencana "Anak",
 * dan daftar kandidat penugasan.
 *
 * Aturan murni `Assignment::candidates` diuji terpisah. Yang dijaga di sini
 * adalah kolom yang benar-benar dibaca dari database: tanpa `users.child_trained`
 * di query kandidat, setiap kasus anak akan salah jatuh ke jalur peringatan
 * Req 4.10.
 */
final class AdminQueueTest extends TestCase
{
    private int $seq = 0;

    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }

        TestDatabase::bind();
        TestDatabase::truncate('report_status_logs', 'messages', 'reports', 'users');
    }

    protected function tearDown(): void
    {
        TestDatabase::release();
    }

    /** Req 4.8: filter kasus anak hanya mengembalikan laporan dengan `is_child = 1`. */
    public function testChildFilterReturnsOnlyChildCases(): void
    {
        $child = $this->report(['category' => 'perkawinan-anak', 'is_child' => 1]);
        $adult = $this->report(['category' => 'kdrt', 'is_child' => 0]);

        $codes = $this->codes(['anak' => '1']);

        $this->assertSame([$child], $codes);
        $this->assertSame(1, Report::queueCount(['anak' => '1']));
        $this->assertSame([$adult], $this->codes(['anak' => '0']));
        $this->assertSame(2, Report::queueCount([]), 'Tanpa filter, kedua laporan tetap ikut.');
    }

    /** Req 4.8: nilai filter yang tidak dikenal tidak boleh menyaring antrean diam-diam. */
    public function testUnknownChildFilterValueIsIgnored(): void
    {
        $this->report(['category' => 'perkawinan-anak', 'is_child' => 1]);
        $this->report(['category' => 'kdrt', 'is_child' => 0]);

        $this->assertCount(2, $this->codes(['anak' => 'mungkin']));
        $this->assertCount(2, $this->codes(['anak' => '']));
    }

    /** Req 4.8: lencana "Anak" bergantung pada kolom `is_child`, jadi baris antrean harus membawanya. */
    public function testQueueRowsCarryTheChildFlagForTheBadge(): void
    {
        $this->report(['category' => 'perkawinan-anak', 'is_child' => 1]);
        $this->report(['category' => 'kdrt', 'is_child' => 0]);

        $flags = [];
        foreach (Report::queue([], 20, 0) as $row) {
            $this->assertArrayHasKey('is_child', $row);
            $flags[$row['category']] = (int) $row['is_child'];
        }

        $this->assertSame(['perkawinan-anak' => 1, 'kdrt' => 0], $flags);
    }

    /** Req 2.7, 2.8: filter kategori memakai kunci `Report::CATEGORIES`, termasuk kategori baru. */
    public function testCategoryFilterReturnsOnlyTheSelectedCategory(): void
    {
        $this->report(['category' => 'kdrt']);
        $this->report(['category' => 'kdrt']);
        $perdagangan = $this->report(['category' => 'perdagangan-orang']);

        $this->assertSame([$perdagangan], $this->codes(['jenis' => 'perdagangan-orang']));
        $this->assertCount(2, $this->codes(['jenis' => 'kdrt']));
        $this->assertCount(3, $this->codes(['jenis' => 'bukan-kategori']), 'Kategori tak dikenal tidak menyaring antrean.');
    }

    /** Filter kategori dan filter kasus anak dipakai bersama tanpa saling menimpa. */
    public function testCategoryAndChildFiltersCombine(): void
    {
        $childKdrt = $this->report(['category' => 'kdrt', 'is_child' => 1]);
        $this->report(['category' => 'kdrt', 'is_child' => 0]);
        $this->report(['category' => 'psikis', 'is_child' => 1]);

        $this->assertSame([$childKdrt], $this->codes(['jenis' => 'kdrt', 'anak' => '1']));
    }

    /**
     * Req 4.9: query kandidat harus memilih `child_trained`, karena itu satu-satunya
     * kolom yang membedakan pendamping terlatih di wilayah laporan.
     */
    public function testCandidateQuerySelectsChildTrainedSoTrainedCompanionsAreOffered(): void
    {
        $trained = $this->companion('Nindya', 'Banyumas', true);
        $this->companion('Bayu', 'Banyumas', false);
        $this->companion('Laras', 'Semarang', true);

        $rows = Report::companionsByLoad('Banyumas');
        $this->assertArrayHasKey('child_trained', $rows[0]);

        $candidates = Assignment::candidates($rows, ['is_child' => 1, 'city' => 'Banyumas', 'province' => 'Jawa Tengah']);

        $this->assertFalse($candidates['warning'], 'Banyumas punya pendamping terlatih, jadi tidak ada peringatan Req 4.10.');
        $this->assertFalse($candidates['needsConfirm']);
        $this->assertSame([$trained], array_column($candidates['list'], 'id'));
    }

    /** Req 4.10: wilayah tanpa pendamping terlatih menawarkan semua pendamping dan butuh konfirmasi. */
    public function testRegionWithoutATrainedCompanionFallsBackToTheWarningPath(): void
    {
        $local = $this->companion('Sari', 'Purbalingga', false);
        $elsewhere = $this->companion('Nindya', 'Banyumas', true);

        $candidates = Assignment::candidates(
            Report::companionsByLoad('Purbalingga'),
            ['is_child' => 1, 'city' => 'Purbalingga', 'province' => 'Jawa Tengah']
        );

        $this->assertTrue($candidates['warning']);
        $this->assertTrue($candidates['needsConfirm']);
        $this->assertSame([$local, $elsewhere], array_column($candidates['list'], 'id'), 'Pendamping berwilayah sama harus di depan.');
    }

    /** Pendamping nonaktif tidak pernah ditawarkan, termasuk yang terlatih kasus anak. */
    public function testInactiveCompanionsAreNotOffered(): void
    {
        $active = $this->companion('Nindya', 'Banyumas', true);
        $this->companion('Dewi', 'Banyumas', true, false);

        $this->assertSame([$active], array_column(Report::companionsByLoad('Banyumas'), 'id'));
    }

    /** @param array<string,string> $filters @return list<string> kode laporan hasil antrean */
    private function codes(array $filters): array
    {
        return array_column(Report::queue($filters, 20, 0), 'code');
    }

    private function report(array $row): string
    {
        $code = 'HV-' . str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);
        Database::insert(
            'INSERT INTO reports (code, pin_hash, category, province, city, status, is_child, chronology_enc, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                'hash-uji',
                $row['category'],
                $row['province'] ?? 'Jawa Tengah',
                $row['city'] ?? 'Banyumas',
                $row['status'] ?? 'diterima',
                (int) ($row['is_child'] ?? 0),
                'ciphertext-uji',
                $row['created_at'] ?? '2026-02-10 09:00:00',
                $row['created_at'] ?? '2026-02-10 09:00:00',
            ]
        );

        return $code;
    }

    private function companion(string $name, string $region, bool $trained, bool $active = true): int
    {
        return Database::insert(
            "INSERT INTO users (name, email, password_hash, role, region, child_trained, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 'companion', ?, ?, ?, ?, ?)",
            [
                $name,
                mb_strtolower($name) . '@uji.test',
                'hash-uji',
                $region,
                (int) $trained,
                (int) $active,
                '2026-01-01 08:00:00',
                '2026-01-01 08:00:00',
            ]
        );
    }
}
