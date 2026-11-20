<?php
declare(strict_types=1);

namespace Haven\Tests\Support;

use Eris\TestTrait;
use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\TestCase;

/**
 * Induk untuk tes properti bernomor (feature: sdg5-improvements).
 *
 * Dua aturan dipaksakan di sini:
 *
 * 1. Minimal 100 iterasi per properti. Eris memakai 100 sebagai bawaan;
 *    naikkan dengan atribut ErisRepeat atau limitTo(). Bila jumlahnya
 *    diturunkan di bawah 100, tes gagal.
 * 2. Setiap metode tes wajib punya komentar tag berisi
 *    "Feature: sdg5-improvements, Property N: <teks properti>" dan
 *    "**Validates: Requirements x.y**". Dicek tests/Harness/ConventionTest.php.
 *
 * Fungsi murni di app/core/rules/* tidak butuh database. Properti yang butuh
 * MySQL memakai Haven\Tests\Support\TestDatabase dan berkasnya diletakkan di
 * tests/Database/ supaya ikut test suite "db", bukan "unit".
 */
abstract class PropertyTestCase extends TestCase
{
    use TestTrait;

    /** Jumlah iterasi minimum untuk setiap tes properti. */
    public const MIN_ITERATIONS = 100;

    /** Pola tag komentar yang wajib ada di tiap metode tes properti. */
    public const TAG_PATTERN = '/Feature:\s*sdg5-improvements,\s*Property\s+\d+\s*:/';

    /** Pola rujukan requirement yang wajib menyertai tag. */
    public const VALIDATES_PATTERN = '/\*\*Validates:\s*Requirements?\s*[\d.,\s]+\*\*/';

    /**
     * Berjalan setelah setUp() dan hook Eris, sebelum metode tes dijalankan.
     * Properti $iterations milik TestTrait dan hanya terlihat dari kelas ini.
     */
    #[PreCondition]
    protected function assertMinimumIterations(): void
    {
        $this->assertGreaterThanOrEqual(
            self::MIN_ITERATIONS,
            $this->iterations,
            sprintf(
                'Tes properti harus menjalankan minimal %d iterasi, sekarang %d.',
                self::MIN_ITERATIONS,
                $this->iterations
            )
        );
    }
}
