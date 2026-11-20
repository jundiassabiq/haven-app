<?php
declare(strict_types=1);

/** Runs our own .sql files statement by statement (used by bin/db-reset.php and the MySQL tests). */
final class SqlFile
{
    /** Splits on ";" outside quotes and "--" comments. */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\') {
                    $buf .= $sql[++$i] ?? '';
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            }
            if ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    /**
     * Executes a file, replacing the database name "haven_v2" with $dbName. Returns statements run.
     *
     * START TRANSACTION, COMMIT, and ROLLBACK are handed to PDO instead of exec(), so a file that wraps
     * itself in a transaction (database/seed-content.sql) can be rolled back here when a statement fails.
     * On failure the whole transaction is rolled back, the error is reported to whoever ran the load
     * (stderr on the command line), and a RuntimeException naming the file and statement is thrown.
     */
    public static function run(PDO $pdo, string $file, string $dbName): int
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/\b(DATABASE IF NOT EXISTS|USE) haven_v2\b/', '$1 ' . $dbName, $sql);
        $n = 0;
        foreach (self::split($sql) as $i => $stmt) {
            try {
                self::exec($pdo, $stmt);
            } catch (Throwable $e) {
                self::abort($pdo, $file, $i + 1, $stmt, $e);
            }
            $n++;
        }
        return $n;
    }

    /** Runs one statement, routing transaction control through PDO so rollBack() works. */
    private static function exec(PDO $pdo, string $stmt): void
    {
        switch (strtoupper(preg_replace('/\s+/', ' ', $stmt) ?? '')) {
            case 'START TRANSACTION':
            case 'BEGIN':
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }
                return;
            case 'COMMIT':
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                return;
            case 'ROLLBACK':
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return;
            default:
                $pdo->exec($stmt);
        }
    }

    /** Rolls back, reports the failure, and rethrows. Never returns. */
    private static function abort(PDO $pdo, string $file, int $position, string $stmt, Throwable $e): void
    {
        $rolledBack = false;
        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
                $rolledBack = true;
            } catch (Throwable) {
                // Connection already gone; the server drops the open transaction itself.
            }
        }
        $message = sprintf(
            "Gagal memuat %s pada pernyataan ke-%d: %s\nPernyataan: %s\n%s",
            basename($file),
            $position,
            $e->getMessage(),
            self::preview($stmt),
            $rolledBack
                ? 'Transaksi dibatalkan, data sebelumnya tidak berubah.'
                : 'Berkas ini tidak memakai transaksi, jadi pernyataan yang sudah jalan tetap tersimpan.'
        );
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . "\n");
        }
        throw new RuntimeException($message, 0, $e);
    }

    /** First line of a statement, shortened, so error output stays readable. */
    private static function preview(string $stmt): string
    {
        $first = trim(strtok($stmt, "\n") ?: $stmt);
        return mb_strlen($first) > 120 ? mb_substr($first, 0, 117) . '...' : $first;
    }
}
