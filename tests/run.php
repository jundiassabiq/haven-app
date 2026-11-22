<?php
declare(strict_types=1);

/*
 * Tiny test runner, no dependencies.
 *   php tests/run.php            all *Test.php files
 *   php tests/run.php Crypto     only files whose name contains "Crypto"
 * Exit code 1 when any test fails.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

final class AssertionFailed extends Exception
{
}

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$GLOBALS['__file'], $name, $fn];
}

function assert_true(mixed $cond, string $msg = 'Expected true'): void
{
    if ($cond !== true) {
        throw new AssertionFailed($msg . ' (got ' . var_export($cond, true) . ')');
    }
}

function assert_false(mixed $cond, string $msg = 'Expected false'): void
{
    if ($cond !== false) {
        throw new AssertionFailed($msg . ' (got ' . var_export($cond, true) . ')');
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_throws(string $class, callable $fn, ?callable $check = null): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!$e instanceof $class) {
            throw new AssertionFailed("Expected $class, got " . $e::class . ': ' . $e->getMessage());
        }
        if ($check) {
            $check($e);
        }
        return;
    }
    throw new AssertionFailed("Expected $class to be thrown");
}

/** Fresh in-memory SQLite with just the tables a test needs. */
function sqlite(string ...$ddl): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    foreach ($ddl as $sql) {
        $pdo->exec($sql);
    }
    return $pdo;
}

$filter = $argv[1] ?? '';
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    if ($filter === '' || stripos(basename($file), $filter) !== false) {
        $GLOBALS['__file'] = basename($file, '.php');
        require $file;
    }
}

$pass = 0;
$fail = 0;
$current = '';
foreach ($GLOBALS['__tests'] as [$file, $name, $fn]) {
    if ($file !== $current) {
        echo "\n$file\n";
        $current = $file;
    }
    $_SESSION = [];
    try {
        $fn();
        $pass++;
        echo "  PASS  $name\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
    }
}
echo "\n$pass lulus, $fail gagal\n";
exit($fail > 0 ? 1 : 0);
