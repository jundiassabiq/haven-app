<?php
declare(strict_types=1);

/** Report codes (HV-XXXXXX) and 6-digit PINs. */
final class ReportCode
{
    /** No 0/O and 1/I/L, so codes read back correctly over the phone. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const PATTERN = '/^HV-[A-Z0-9]{6}$/';

    /**
     * @param callable(string):bool $exists returns true when a code is already taken
     */
    public static function generate(callable $exists, int $maxTries = 20): string
    {
        $n = strlen(self::ALPHABET);
        for ($try = 0; $try < $maxTries; $try++) {
            $code = 'HV-';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, $n - 1)];
            }
            if (!$exists($code)) {
                return $code;
            }
        }
        throw new RuntimeException('Tidak bisa membuat kode laporan unik.');
    }

    public static function pin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function normalize(string $input): string
    {
        $c = strtoupper(preg_replace('/\s+/', '', $input));
        if (preg_match('/^HV[A-Z0-9]{6}$/', $c)) {
            $c = 'HV-' . substr($c, 2);
        }
        return $c;
    }

    public static function valid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }
}
