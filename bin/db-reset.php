<?php
declare(strict_types=1);

/*
 * Drops and recreates all tables, then loads demo data.
 *   php bin/db-reset.php            schema + seed
 *   php bin/db-reset.php --no-seed  schema only
 * Uses DB_* from .env. The database name in .env replaces "haven_v2" in the SQL files.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$dbName = (string) Config::get('DB_NAME');
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "DB_NAME hanya boleh huruf, angka, dan garis bawah.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::get('DB_HOST'), Config::int('DB_PORT', 3306)),
        (string) Config::get('DB_USER'),
        (string) Config::get('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Tidak bisa terhubung ke MySQL: " . $e->getMessage() . "\nPastikan MySQL di XAMPP sudah Start.\n");
    exit(1);
}

$files = [ROOT_PATH . '/database/schema.sql'];
if (!in_array('--no-seed', $argv, true)) {
    $files[] = ROOT_PATH . '/database/seed.sql';
    $files[] = ROOT_PATH . '/database/seed-content.sql';
}

foreach ($files as $file) {
    echo basename($file) . ': ' . SqlFile::run($pdo, $file, $dbName) . " pernyataan dijalankan\n";
}
echo "Selesai. Database '$dbName' siap.\n";
