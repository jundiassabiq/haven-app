<?php
declare(strict_types=1);

namespace Haven\Tests\Harness;

use Config;
use Crypto;
use Eris\Generators;
use Eris\TestTrait;
use Haven\Tests\Support\PropertyTestCase;
use PHPUnit\Framework\TestCase;

/**
 * Memastikan kerangka tes bersama benar-benar hidup: bootstrap aplikasi termuat,
 * kelas aturan murni ter-autoload, Eris berjalan 100 iterasi, dan tes tidak
 * pernah menunjuk database pengembangan.
 */
final class HarnessTest extends TestCase
{
    use TestTrait;

    public function testAppBootstrapIsLoaded(): void
    {
        $this->assertTrue(defined('ROOT_PATH'), 'app/bootstrap.php belum termuat.');
        $this->assertFileExists(ROOT_PATH . '/app/bootstrap.php');
        $this->assertSame(ROOT_PATH . '/app', APP_PATH);
        $this->assertSame(ROOT_PATH . '/storage', STORAGE_PATH);
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertNotSame('', (string) Config::get('APP_NAME'));
    }

    public function testApplicationClassesAreAutoloadable(): void
    {
        foreach (['Config', 'Database', 'Crypto', 'SqlFile', 'Report'] as $class) {
            $this->assertTrue(class_exists($class), "Kelas $class tidak ter-autoload.");
        }
    }

    /**
     * Aturan murni fitur ini hidup di app/core/rules/*.php dan harus bisa dipakai
     * tanpa require manual. Direktori masih kosong sampai tugas 2.3; begitu
     * berkas pertama masuk, tes ini menjaga nama kelas tetap cocok dengan nama berkas.
     */
    public function testPureRuleClassesAreAutoloadable(): void
    {
        $dir = APP_PATH . '/core/rules';
        $this->assertDirectoryExists($dir, 'Direktori aturan murni app/core/rules belum ada.');

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $class = basename($file, '.php');
            $this->assertTrue(
                class_exists($class),
                "app/core/rules/$class.php ada tetapi kelas $class tidak ter-autoload."
            );
        }
    }

    public function testTestsNeverTargetTheDevelopmentDatabase(): void
    {
        $name = (string) Config::get('DB_NAME');
        $this->assertMatchesRegularExpression('/_test$/', $name);
        $this->assertNotSame('haven_v2', $name);
    }

    public function testEncryptionKeyIsUsableInTests(): void
    {
        $this->assertSame('rahasia', Crypto::dec(Crypto::enc('rahasia')));
    }

    /** Membuktikan Eris terpasang dan menjalankan tepat 100 iterasi secara bawaan. */
    public function testErisRunsOneHundredIterationsByDefault(): void
    {
        $runs = 0;
        $this->forAll(Generators::choose(0, 1000))
            ->then(function (int $n) use (&$runs): void {
                $runs++;
                $this->assertGreaterThanOrEqual(0, $n);
            });

        $this->assertSame(PropertyTestCase::MIN_ITERATIONS, $runs);
    }
}
