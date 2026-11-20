<?php
declare(strict_types=1);

/** Per-session synchronizer token. Forms send `_csrf`; fetch() sends header X-CSRF-Token. */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function valid(?string $submitted): bool
    {
        $expected = $_SESSION[self::KEY] ?? null;
        return is_string($expected) && $expected !== '' && is_string($submitted) && hash_equals($expected, $submitted);
    }

    /** @throws HttpException 419 */
    public static function verifyRequest(): void
    {
        $submitted = $_POST[self::KEY] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::valid(is_string($submitted) ? $submitted : null)) {
            throw new HttpException(419, 'Formulir ini sudah terlalu lama dibuka atau sesi berganti. Muat ulang halaman, lalu kirim lagi.');
        }
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
