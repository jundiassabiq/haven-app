<?php
declare(strict_types=1);

/**
 * Terjemahan antarmuka statis (Req 8) — murni, tanpa Session, tanpa superglobal.
 * Bahasa aktif dan kamus selalu masuk sebagai parameter.
 *
 * Req 8.5: setiap string antarmuka statis halaman publik, form lapor, dan
 *   halaman lacak tersedia dalam Bahasa Indonesia dan Inggris. Isi yang ditulis
 *   penyintas, pendamping, dan admin tidak diterjemahkan, jadi tidak masuk kamus.
 * Req 8.6: pilihan bahasa disimpan di sesi; `lang()` membaca pilihan itu dan
 *   memberi nilai untuk atribut `lang` pada `<html>`.
 * Req 8.7: `t()` memakai string Bahasa Indonesia bila kunci tidak punya
 *   terjemahan Inggris.
 *
 * Kamus ada di `app/lang/id.php` dan `app/lang/en.php`, masing-masing
 * mengembalikan array datar `kunci => teks`. Pemanggil menyusunnya menjadi
 * `['id' => [...], 'en' => [...]]` lalu meneruskannya ke sini.
 */
final class I18n
{
    /** Bahasa cadangan untuk semua kunci (Req 8.7). */
    public const DEFAULT_LANG = 'id';

    /** Bahasa yang didukung, kode => nama dalam bahasa itu sendiri. */
    public const LANGS = ['id' => 'Bahasa Indonesia', 'en' => 'English'];

    /** Kunci penyimpanan pilihan bahasa di `Session` (Req 8.6). */
    public const SESSION_KEY = 'lang';

    /**
     * Terjemahan satu kunci.
     *
     * Urutan pencarian: bahasa yang diminta, lalu Bahasa Indonesia (Req 8.7),
     * lalu kunci itu sendiri supaya halaman tidak pernah menampilkan ruang
     * kosong saat ada kunci baru yang belum masuk kamus.
     *
     * `$vars` mengisi penanda `:nama` di dalam teks, misalnya
     * `t('track.locked', $lang, $dict, ['menit' => 15])`. Penanda yang tidak ada
     * di `$vars` dibiarkan utuh.
     *
     * @param array<string,array<string,mixed>> $dict kamus per bahasa
     * @param array<string,string|int|float> $vars nilai pengganti penanda
     */
    public static function t(string $key, string $lang, array $dict, array $vars = []): string
    {
        $text = self::lookup($dict, self::normalizeLang($lang) ?? self::DEFAULT_LANG, $key)
            ?? self::lookup($dict, self::DEFAULT_LANG, $key)
            ?? $key;

        return $vars === [] ? $text : self::fill($text, $vars);
    }

    /**
     * Benar bila kunci punya teks di bahasa itu sendiri (bukan hasil cadangan).
     * Dipakai `bin/` dan tes untuk melihat kunci yang belum diterjemahkan.
     *
     * @param array<string,array<string,mixed>> $dict
     */
    public static function has(string $key, string $lang, array $dict): bool
    {
        $code = self::normalizeLang($lang);

        return $code !== null && self::lookup($dict, $code, $key) !== null;
    }

    /**
     * Bahasa aktif dari pilihan yang tersimpan di sesi, atau Bahasa Indonesia
     * bila pengguna belum memilih atau nilainya tidak dikenal (Req 8.6).
     */
    public static function lang(?string $sessionChoice): string
    {
        return self::normalizeLang($sessionChoice) ?? self::DEFAULT_LANG;
    }

    /**
     * Kode bahasa yang didukung dari masukan apa pun, atau null bila tidak
     * dikenal. Dipakai `POST /bahasa` untuk menolak nilai di luar daftar.
     * Menerima juga bentuk panjang seperti `en-US` dan `id-ID`.
     */
    public static function normalizeLang(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $code = strtolower(trim($raw));
        if ($code === '') {
            return null;
        }
        // 'en-US' dan 'en_US' dianggap 'en'.
        $code = (string) preg_split('/[-_]/', $code)[0];

        return isset(self::LANGS[$code]) ? $code : null;
    }

    /** Teks satu kunci di satu bahasa, atau null bila tidak ada isinya. */
    private static function lookup(array $dict, string $lang, string $key): ?string
    {
        $table = $dict[$lang] ?? null;
        if (!is_array($table)) {
            return null;
        }

        $text = $table[$key] ?? null;
        if (is_int($text) || is_float($text)) {
            $text = (string) $text;
        }
        if (!is_string($text) || trim($text) === '') {
            // Kunci kosong dianggap belum diterjemahkan, bukan terjemahan kosong.
            return null;
        }

        return $text;
    }

    /** Mengganti penanda `:nama` dengan nilai dari `$vars`. */
    private static function fill(string $text, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $name => $value) {
            if (!is_string($name) || $name === '' || !is_scalar($value)) {
                continue;
            }
            $pairs[':' . $name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
        if ($pairs === []) {
            return $text;
        }

        // Penanda terpanjang lebih dulu supaya ':jam' tidak memotong ':jamkerja'.
        uksort($pairs, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($text, $pairs);
    }
}
