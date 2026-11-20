<?php
declare(strict_types=1);

namespace Haven\Tests\Database;

use Database;
use Haven\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tes integrasi untuk helper database uji. Juga menutup Requirement 10.12:
 * bin/db-reset.php pada database kosong selesai tanpa galat dan membuat
 * kembali seluruh tabel.
 */
final class TestDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }
    }

    protected function tearDown(): void
    {
        TestDatabase::release();
    }

    public function testResetCreatesEveryTableOnAnEmptyDatabase(): void
    {
        TestDatabase::reset();

        $tables = TestDatabase::tables();
        foreach (['users', 'reports', 'services', 'messages', 'audit_logs'] as $table) {
            $this->assertContains($table, $tables, "Tabel $table tidak dibuat oleh bin/db-reset.php.");
        }
    }

    public function testHelperUsesTheTestDatabaseOnly(): void
    {
        $this->assertSame('haven_test', TestDatabase::name());

        $current = TestDatabase::pdo()->query('SELECT DATABASE()')->fetchColumn();
        $this->assertSame('haven_test', $current);
    }

    public function testBindRoutesApplicationQueriesToTheTestDatabase(): void
    {
        TestDatabase::bind();

        $this->assertSame('haven_test', Database::value('SELECT DATABASE()'));
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM reports'));
    }

    public function testTruncateEmptiesATableWithoutRebuildingTheSchema(): void
    {
        $pdo = TestDatabase::bind();
        $pdo->exec(
            "INSERT INTO users (name, email, password_hash, role) "
            . "VALUES ('Uji', 'uji@example.test', 'x', 'survivor')"
        );
        $this->assertGreaterThan(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());

        TestDatabase::truncate('users');

        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testRunScriptReportsExitCodes(): void
    {
        // --show hanya mencetak kunci, tidak menulis .env.
        [$code, $out] = TestDatabase::runScript(ROOT_PATH . '/bin/keygen.php', ['--show']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('base64:', $out);
    }

    public function testPdoFetchesAssociativeRows(): void
    {
        $row = TestDatabase::pdo()->query('SELECT 1 AS satu')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['satu' => 1], $row);
    }
}
