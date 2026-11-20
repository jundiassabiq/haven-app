<?php
declare(strict_types=1);

/**
 * Authenticated encryption for sensitive fields (chronology, contacts, notes, messages).
 * AES-256-GCM; stored as base64(iv[12] | tag[16] | ciphertext). Tampered or wrong-key data fails to decrypt.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private static ?Crypto $default = null;

    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('Kunci enkripsi harus 32 byte.');
        }
    }

    /** Instance from APP_KEY ("base64:..."). */
    public static function default(): self
    {
        if (self::$default === null) {
            self::$default = new self(self::parseKey((string) Config::get('APP_KEY')));
        }
        return self::$default;
    }

    public static function parseKey(string $appKey): string
    {
        if (!str_starts_with($appKey, 'base64:')) {
            throw new RuntimeException('APP_KEY belum diatur. Jalankan: php bin/keygen.php');
        }
        $raw = base64_decode(substr($appKey, 7), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('APP_KEY tidak valid. Jalankan: php bin/keygen.php');
        }
        return $raw;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($cipher === false) {
            throw new RuntimeException('Enkripsi gagal.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    /** @return string|null null when data is malformed, tampered, or encrypted with another key */
    public function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            return null;
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($raw, self::IV_LEN + self::TAG_LEN);
        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /** Null-safe shortcuts using the default key. */
    public static function enc(?string $plain): ?string
    {
        return $plain === null || $plain === '' ? null : self::default()->encrypt($plain);
    }

    public static function dec(?string $encoded): ?string
    {
        return $encoded === null || $encoded === '' ? null : self::default()->decrypt($encoded);
    }
}
