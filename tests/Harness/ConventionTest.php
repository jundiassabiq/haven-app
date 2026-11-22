<?php
declare(strict_types=1);

namespace Haven\Tests\Harness;

use Haven\Tests\Support\PropertyTestCase;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Menjaga konvensi tes properti fitur sdg5-improvements: tiap tes properti
 * membawa tag "Feature: sdg5-improvements, Property N: ..." dan rujukan
 * "**Validates: Requirements x.y**", serta satu properti bernomor hanya
 * diklaim oleh satu metode tes.
 */
final class ConventionTest extends TestCase
{
    public function testEveryPropertyTestCarriesItsFeatureTag(): void
    {
        $methods = self::propertyTestMethods();
        if ($methods === []) {
            $this->markTestSkipped('Belum ada tes properti. Tes ini aktif begitu berkas properti pertama ditulis.');
        }

        foreach ($methods as $method) {
            $label = $method->class . '::' . $method->getName();
            $doc = (string) $method->getDocComment();

            $this->assertMatchesRegularExpression(
                PropertyTestCase::TAG_PATTERN,
                $doc,
                "$label belum punya komentar tag 'Feature: sdg5-improvements, Property N: ...'."
            );
            $this->assertMatchesRegularExpression(
                PropertyTestCase::VALIDATES_PATTERN,
                $doc,
                "$label belum menyebut '**Validates: Requirements x.y**'."
            );
        }
    }

    public function testEachNumberedPropertyIsClaimedOnce(): void
    {
        $methods = self::propertyTestMethods();
        if ($methods === []) {
            $this->markTestSkipped('Belum ada tes properti.');
        }

        $owners = [];
        foreach ($methods as $method) {
            if (preg_match('/Property\s+(\d+)\s*:/', (string) $method->getDocComment(), $m) !== 1) {
                continue;
            }
            $owners[(int) $m[1]][] = $method->class . '::' . $method->getName();
        }

        foreach ($owners as $number => $claimants) {
            $this->assertCount(
                1,
                $claimants,
                "Property $number diklaim lebih dari satu tes: " . implode(', ', $claimants)
            );
        }
    }

    /** @return list<ReflectionMethod> */
    private static function propertyTestMethods(): array
    {
        $methods = [];
        foreach (self::testClasses() as $class) {
            $reflection = new ReflectionClass($class);
            if (!$reflection->isSubclassOf(PropertyTestCase::class)) {
                continue;
            }
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $class && str_starts_with($method->getName(), 'test')) {
                    $methods[] = $method;
                }
            }
        }
        return $methods;
    }

    /** Nama kelas tes diturunkan dari path berkas lewat PSR-4 (Haven\Tests\ => tests/). */
    private static function testClasses(): array
    {
        $root = ROOT_PATH . '/tests';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        $classes = [];
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
            $class = 'Haven\\Tests\\' . str_replace('/', '\\', substr($relative, 0, -4));
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }
        sort($classes);
        return $classes;
    }
}
