<?php
declare(strict_types=1);

/** Appends to storage/logs/app.log. Never log decrypted report content. */
final class Logger
{
    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    private static function write(string $level, string $message): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/app.log', '[' . date('Y-m-d H:i:s') . "] $level $message\n", FILE_APPEND | LOCK_EX);
    }
}
