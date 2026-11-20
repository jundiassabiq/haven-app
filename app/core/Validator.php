<?php
declare(strict_types=1);

/**
 * Server-side validation with Indonesian messages.
 * Rules: required, accepted, email, min:n, max:n, same:field, in:a,b, regex:/.../, date, integer, between:a,b
 * Rules may be a "a|b" string or an array (use the array form for regex patterns containing "|").
 */
final class Validator
{
    private const MESSAGES = [
        'required' => 'Bagian ini wajib diisi.',
        'accepted' => 'Centang untuk melanjutkan.',
        'email' => 'Format email belum benar. Contoh: nama@email.com',
        'min' => 'Tulis minimal :p karakter.',
        'max' => 'Maksimal :p karakter.',
        'same' => 'Isian ini tidak sama dengan sebelumnya.',
        'in' => 'Pilihan ini tidak tersedia.',
        'regex' => 'Formatnya belum sesuai.',
        'date' => 'Tanggal tidak valid.',
        'integer' => 'Masukkan angka bulat.',
        'between' => 'Masukkan angka antara :p.',
    ];

    /**
     * @param array<string, string|array> $rules
     * @param array<string, string> $messages keyed "field.rule" or "field"
     * @return array<string, string> first error per field
     */
    public static function make(array $data, array $rules, array $messages = []): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $list = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $raw = $data[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $empty = $value === null || $value === '' || $value === [];

            foreach ($list as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if ($empty && !in_array($name, ['required', 'accepted'], true)) {
                    continue; // optional and empty: skip format checks
                }
                if (!self::passes($name, $param, $value, $data)) {
                    $errors[$field] = $messages["$field.$name"] ?? $messages[$field]
                        ?? str_replace(':p', (string) $param, self::MESSAGES[$name] ?? 'Isian tidak valid.');
                    break;
                }
            }
        }
        return $errors;
    }

    private static function passes(string $rule, ?string $param, mixed $value, array $data): bool
    {
        $str = is_scalar($value) ? (string) $value : '';
        return match ($rule) {
            'required' => !($value === null || $value === '' || $value === []),
            'accepted' => in_array($value, ['1', 'on', 'yes', 'true', true, 1], true),
            'email' => filter_var($str, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($str) <= 190,
            'min' => mb_strlen($str) >= (int) $param,
            'max' => mb_strlen($str) <= (int) $param,
            'same' => is_scalar($data[$param] ?? null) && $str === (string) $data[$param],
            'in' => in_array($str, explode(',', (string) $param), true),
            'regex' => preg_match((string) $param, $str) === 1,
            'date' => self::isDate($str),
            'integer' => filter_var($str, FILTER_VALIDATE_INT) !== false,
            'between' => self::between($str, (string) $param),
            default => throw new InvalidArgumentException("Aturan validasi tidak dikenal: $rule"),
        };
    }

    private static function isDate(string $s): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }

    private static function between(string $s, string $param): bool
    {
        [$min, $max] = array_map('intval', explode(',', $param));
        return filter_var($s, FILTER_VALIDATE_INT) !== false && (int) $s >= $min && (int) $s <= $max;
    }
}
