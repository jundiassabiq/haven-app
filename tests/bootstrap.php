<?php
declare(strict_types=1);

/*
 * Bootstrap untuk PHPUnit (feature: sdg5-improvements).
 *
 * Urutan:
 *   1. autoload Composer (PHPUnit, Eris, namespace Haven\Tests\)
 *   2. app/bootstrap.php  -> ROOT_PATH, autoloader aplikasi (core, core/rules, controllers, models), .env
 *   3. pengaman: arahkan konfigurasi DB ke database uji, bukan database pengembangan
 *
 * Kelas aturan murni di app/core/rules/*.php ikut ter-autoload lewat autoloader
 * aplikasi di app/bootstrap.php, jadi tes cukup menulis `Sla::hours(...)` tanpa require.
 */

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($vendor)) {
    fwrite(STDERR, "Dependensi tes belum terpasang. Jalankan: php composer.phar install\n");
    exit(1);
}
require $vendor;

require dirname(__DIR__) . '/app/bootstrap.php';

/*
 * Semua tes memakai database uji. Namanya wajib berakhiran "_test" supaya
 * bin/db-reset.php tidak pernah menghapus tabel database pengembangan.
 */
$testDb = getenv('HAVEN_TEST_DB') ?: 'haven_test';
if (!preg_match('/^[A-Za-z0-9_]+_test$/', $testDb)) {
    fwrite(STDERR, "HAVEN_TEST_DB harus hanya huruf/angka/garis bawah dan berakhiran _test (sekarang: $testDb).\n");
    exit(1);
}
Config::set('DB_NAME', $testDb);

/*
 * Tes enkripsi (Property 14 dan 15) butuh APP_KEY yang sah. Kalau .env belum
 * punya kunci, pakai kunci tetap khusus tes supaya hasil tes bisa diulang.
 */
try {
    Crypto::parseKey((string) Config::get('APP_KEY'));
} catch (Throwable) {
    Config::set('APP_KEY', 'base64:' . base64_encode('haven-test-key-0123456789abcdef!'));
}

if (!is_dir(STORAGE_PATH . '/cache/phpunit')) {
    @mkdir(STORAGE_PATH . '/cache/phpunit', 0777, true);
}
