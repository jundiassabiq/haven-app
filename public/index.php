<?php
declare(strict_types=1);

/* Front controller. Every request that is not a real file under public/ lands here. */

// Built-in server (php -S): let it serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

ini_set('display_errors', Config::bool('APP_DEBUG') ? '1' : '0');
error_reporting(E_ALL);

$router = new Router();
(require APP_PATH . '/routes.php')($router);
App::run($router);
