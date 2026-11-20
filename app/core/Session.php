<?php
declare(strict_types=1);

/** Hardened PHP session with idle timeout and one-request flash data. */
final class Session
{
    private static bool $expired = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (Config::int('SESSION_IDLE_MINUTES', 30) * 60));
        session_name('haven_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => App::basePath() === '' ? '/' : App::basePath() . '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $idle = Config::int('SESSION_IDLE_MINUTES', 30) * 60;
        $last = $_SESSION['_last'] ?? null;
        if ($last !== null && time() - (int) $last > $idle) {
            $_SESSION = [];
            session_regenerate_id(true);
            self::$expired = true;
            $_SESSION['_flash_next']['info'] = 'Sesi berakhir karena tidak ada aktivitas selama ' . ($idle / 60) . ' menit. Silakan masuk lagi.';
        }
        $_SESSION['_last'] = time();

        // Rotate flash: what was set last request is readable now.
        $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);
    }

    public static function expired(): bool
    {
        return self::$expired;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Available on the next request. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Available on this request (e.g. when rendering directly instead of redirecting). */
    public static function flashNow(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key): mixed
    {
        return $_SESSION['_flash'][$key] ?? null;
    }

    /** New session id, old one destroyed. Call on login and privilege change. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Empty the session but keep it alive for the flash message that follows. */
    public static function reset(): void
    {
        $_SESSION = [];
        self::regenerate();
    }
}
