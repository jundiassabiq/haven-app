<?php
declare(strict_types=1);

/*
 * Shared bootstrap for web (public/index.php), CLI scripts (bin/), and tests.
 * It loads config, the autoloader, and helpers. It does NOT start the session or send headers.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    foreach (['core', 'core/rules', 'controllers', 'models'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_PATH . '/core/helpers.php';

Config::load(ROOT_PATH . '/.env');
