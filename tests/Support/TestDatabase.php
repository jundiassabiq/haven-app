<?php
declare(strict_types=1);

namespace Haven\Tests\Support;

use Config;
use Database;
use PDO;
use PDOException;
use RuntimeException;
use SqlFile;

/**
 * Helper database uji untuk tes integrasi (feature: sdg5-improvements).
 *
 * Database uji dibuat oleh `bin/db-reset.php` dengan DB_NAME ditimpa menjadi
 * `haven_test` (lihat tests/bootstrap.php). Nama database wajib berakhiran
 * `_test`; tanpa itu helper ini menolak jalan, supaya skema database
 * pengembangan tidak pernah terhapus oleh tes.
 */
final class TestDatabase
{
    private static ?PDO $pdo = null;
    private static bool $prepared = false;

    /** Nama database uji, berasal dari tests/bootstrap.php. */
    public static function name(): string
    {
        $name = (string) Config::get('DB_NAME');
        if (!preg_match('/^[A-Za-z0-9_]+_test$/', $name)) {
            throw new RuntimeException("Tes menolak memakai database '$name': nama harus berakhiran _test.");
        }
        return $name;
    }

    /** True bila server MySQL bisa dihubungi. Dipakai untuk markTestSkipped. */
    public static function serverAvailable(): bool
    {
        try {
            self::serverConnection();
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** Alasan tes DB tidak bisa jalan, atau null bila bisa. */
    public static function unavailableReason(): ?string
    {
        try {
            self::serverConnection();
            return null;
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Koneksi ke database uji. Skema dibuat sekali per proses lewat bin/db-reset.php.
     */
    public static function pdo(bool $withSeed = false): PDO
    {
        if (!self::$prepared) {
            self::reset($withSeed);
        }
        if (self::$pdo === null) {
            self::$pdo = self::connect(self::name());
        }
        return self::$pdo;
    }

    /**
     * Jalankan ulang bin/db-reset.php pada database uji (drop + create semua tabel).
     * Ini juga memverifikasi Requirement 10.12: skema jalan tanpa galat di DB kosong.
     */
    public static function reset(bool $withSeed = false): void
    {
        $name = self::name();
        $script = ROOT_PATH . '/bin/db-reset.php';
        if (!is_file($script)) {
            throw new RuntimeException("bin/db-reset.php tidak ditemukan di $script.");
        }

        // Pastikan database uji ada sebelum skrip memakai USE <nama>.
        self::serverConnection()->exec(
            "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        $args = $withSeed ? [] : ['--no-seed'];
        [$code, $out, $err] = self::runScript($script, $args, ['DB_NAME' => $name]);
        if ($code !== 0) {
            throw new RuntimeException("bin/db-reset.php gagal (kode $code).\n$out\n$err");
        }

        self::$pdo = null;
        self::$prepared = true;
    }

    /** Muat satu berkas .sql tambahan (mis. database/seed-content.sql) ke database uji. */
    public static function loadSqlFile(string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException("Berkas SQL tidak ditemukan: $path");
        }
        return SqlFile::run(self::pdo(), $path, self::name());
    }

    /** Arahkan model aplikasi (Database::pdo()) ke database uji. */
    public static function bind(bool $withSeed = false): PDO
    {
        $pdo = self::pdo($withSeed);
        Database::setConnection($pdo);
        return $pdo;
    }

    /** Lepaskan koneksi uji dari aplikasi. Panggil di tearDown. */
    public static function release(): void
    {
        Database::setConnection(null);
    }

    /** Kosongkan tabel tertentu tanpa membangun ulang skema. */
    public static function truncate(string ...$tables): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                    throw new RuntimeException("Nama tabel tidak sah: $table");
                }
                $pdo->exec("TRUNCATE TABLE `$table`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** Daftar tabel di database uji. */
    public static function tables(): array
    {
        return self::pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** Buang status cache antar proses (dipakai tes yang sengaja mengulang reset). */
    public static function forget(): void
    {
        self::$pdo = null;
        self::$prepared = false;
    }

    /** Koneksi ke server MySQL tanpa memilih database. */
    private static function serverConnection(): PDO
    {
        try {
            return new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;charset=utf8mb4',
                    (string) Config::get('DB_HOST'),
                    Config::int('DB_PORT', 3306)
                ),
                (string) Config::get('DB_USER'),
                (string) Config::get('DB_PASS'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'MySQL tidak bisa dihubungi (' . $e->getCode() . '). Start MySQL di XAMPP lalu ulangi.'
            );
        }
    }

    private static function connect(string $name): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) Config::get('DB_HOST'),
                Config::int('DB_PORT', 3306),
                $name
            ),
            (string) Config::get('DB_USER'),
            (string) Config::get('DB_PASS'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+07:00'",
            ]
        );
    }

    /**
     * Jalankan skrip CLI proyek dengan variabel lingkungan tambahan.
     *
     * @param  list<string>          $args
     * @param  array<string, string> $env
     * @return array{0: int, 1: string, 2: string} kode keluar, stdout, stderr
     */
    public static function runScript(string $script, array $args = [], array $env = []): array
    {
        $cmd = array_merge([PHP_BINARY, $script], $args);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $merged = array_merge(getenv(), $env);

        $process = proc_open($cmd, $descriptors, $pipes, ROOT_PATH, $merged);
        if (!is_resource($process)) {
            throw new RuntimeException('Tidak bisa menjalankan ' . basename($script));
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
