<?php
declare(strict_types=1);

/** Reads .env (KEY=VALUE lines) once and exposes typed values. Real environment variables win over the file. */
final class Config
{
    private static array $values = [];

    private const DEFAULTS = [
        'APP_NAME'        => 'Haven',
        'APP_ENV'         => 'local',
        'APP_DEBUG'       => 'false',
        'APP_KEY'         => '',
        'APP_DEMO'        => 'true',
        'DB_HOST'         => '127.0.0.1',
        'DB_PORT'         => '3306',
        'DB_NAME'         => 'haven_v2',
        'DB_USER'         => 'root',
        'DB_PASS'         => '',
        'SESSION_IDLE_MINUTES' => '30',
        /*
         * SMTP (Req 6.9, 6.10). Nilainya kosong di sini supaya pemasangan baru
         * berjalan dalam mode `log`: tanpa MAIL_HOST dan MAIL_PORT, Mailer
         * menulis email ke storage/mail.log dan tidak membuka koneksi.
         *
         * Kuncinya perlu terdaftar di sini, bukan hanya di .env, karena hanya
         * kunci yang dikenal Config yang bisa ditimpa variabel lingkungan asli.
         * Itu satu-satunya cara memberi MAIL_PASS tanpa menulisnya ke berkas.
         */
        'MAIL_HOST'       => '',
        'MAIL_PORT'       => '',
        'MAIL_USER'       => '',
        'MAIL_PASS'       => '',
        'MAIL_ENCRYPTION' => '',
        'MAIL_FROM'       => '',
        'MAIL_FROM_NAME'  => '',
    ];

    public static function load(string $envFile): void
    {
        $values = self::DEFAULTS;
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                    $v = substr($v, 1, -1);
                }
                $values[$k] = $v;
            }
        }
        foreach ($values as $k => $v) {
            $env = getenv($k);
            if ($env !== false) {
                $values[$k] = $env;
            }
        }
        self::$values = $values;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower((string) self::get($key, 'false')), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    /** Test helper. */
    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
