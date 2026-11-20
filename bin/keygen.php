<?php
declare(strict_types=1);

/*
 * Creates .env from .env.example if needed and fills APP_KEY.
 *   php bin/keygen.php          set APP_KEY only if empty
 *   php bin/keygen.php --force  replace an existing key (existing encrypted data becomes unreadable)
 *   php bin/keygen.php --show   print a key without writing anything
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$key = Crypto::generateKey();

if (in_array('--show', $args, true)) {
    echo $key, PHP_EOL;
    exit(0);
}

$env = ROOT_PATH . '/.env';
if (!is_file($env)) {
    copy(ROOT_PATH . '/.env.example', $env);
    echo "Membuat .env dari .env.example\n";
}

$content = (string) file_get_contents($env);
$hasKey = preg_match('/^APP_KEY=(base64:\S+)\s*$/m', $content) === 1;

if ($hasKey && !in_array('--force', $args, true)) {
    echo "APP_KEY sudah ada. Gunakan --force untuk menggantinya (data terenkripsi lama tidak akan bisa dibaca).\n";
    exit(0);
}

$content = preg_match('/^APP_KEY=.*$/m', $content)
    ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content)
    : rtrim($content) . "\nAPP_KEY=$key\n";
file_put_contents($env, $content);
echo "APP_KEY ditulis ke .env\n";
