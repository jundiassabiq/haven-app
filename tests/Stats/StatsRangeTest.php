<?php
declare(strict_types=1);

namespace Haven\Tests\Stats;

use Aggregator;
use PHPUnit\Framework\TestCase;
use Router;
use Stats;
use StatsController;

/**
 * Tes unit untuk bagian Stats yang tidak menyentuh database: pembacaan rentang
 * tanggal pilihan admin (Req 7.12) dan pendaftaran endpoint ekspor (Req 7.8).
 */
final class StatsRangeTest extends TestCase
{
    public function testEmptyFormUsesTheDefaultRangeInsteadOfAnError(): void
    {
        $range = Stats::range('', '');

        $this->assertIsArray($range, 'Halaman statistik yang baru dibuka tidak boleh menampilkan galat rentang.');
        $this->assertSame($range['from'] . ' 00:00:00', $range['from_at']);
        $this->assertSame($range['to'] . ' 23:59:59', $range['to_at']);
        $this->assertSame(date('Y-m-d'), $range['to']);
        $this->assertSame(
            date('Y-m-01', (int) strtotime('first day of -' . (Stats::DEFAULT_MONTHS - 1) . ' months')),
            $range['from']
        );
    }

    public function testValidRangeIsAcceptedWithInclusiveBounds(): void
    {
        $range = Stats::range('2026-01-01', '2026-03-31');

        $this->assertSame(
            ['from' => '2026-01-01', 'to' => '2026-03-31', 'from_at' => '2026-01-01 00:00:00', 'to_at' => '2026-03-31 23:59:59'],
            $range
        );
    }

    /** Req 7.12: rentang terbalik, tanggal tak terbaca, dan satu kolom kosong semuanya ditolak. */
    public function testInvalidRangeReturnsAnIndonesianMessage(): void
    {
        $this->assertSame(Aggregator::RANGE_ERROR_ORDER, Stats::range('2026-03-31', '2026-01-01'));
        $this->assertSame(Aggregator::RANGE_ERROR_FORMAT, Stats::range('31-01-2026', '2026-03-31'));
        $this->assertSame(Aggregator::RANGE_ERROR_FORMAT, Stats::range('2026-02-30', '2026-03-31'));
        $this->assertSame(Aggregator::RANGE_ERROR_EMPTY, Stats::range('2026-01-01', ''));
    }

    public function testExportFilenameNamesTheRange(): void
    {
        $range = Stats::range('2026-01-01', '2026-03-31');

        $this->assertSame('haven-statistik-2026-01-01-sampai-2026-03-31.csv', Stats::exportFilename($range));
    }

    /**
     * Req 7.8: ekspor hanya untuk admin. Penjaganya middleware `role:admin`,
     * yang berhenti dengan 403 sebelum satu baris CSV dibuat.
     * Req 7.9: jalurnya POST, jadi Csrf::verifyRequest di App::run memeriksanya.
     */
    public function testExportRouteIsAdminOnlyAndPostOnly(): void
    {
        $router = new Router();
        (require ROOT_PATH . '/app/routes.php')($router);

        $route = $router->match('POST', '/admin/statistik/ekspor');

        $this->assertSame([StatsController::class, 'export'], $route['handler']);
        $this->assertSame(['role:admin'], $route['middleware']);

        $this->expectExceptionCode(405);
        $router->match('GET', '/admin/statistik/ekspor');
    }
}
