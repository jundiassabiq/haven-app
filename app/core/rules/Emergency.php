<?php
declare(strict_types=1);

/**
 * Opsi panggilan darurat (Req 8) — murni, tanpa DB dan tanpa time().
 * Baris layanan masuk sebagai parameter.
 *
 * Req 8.12: 112 dan SAPA 129 tampil di form lapor dan halaman lacak dalam
 *   setiap bahasa dan kedua mode, tanpa JavaScript dan tanpa interaksi apa pun.
 *   Datanya diambil dari layanan terverifikasi bila ada.
 * Req 8.14: bila tidak ada entri layanan terverifikasi untuk 112 atau 129,
 *   nomornya tetap ditampilkan.
 *
 * Karena itu hasil `options()` selalu tepat dua entri dengan nomor 112 dan 129,
 * dalam urutan itu, berapa pun isi daftar layanan yang diberikan. Yang berubah
 * hanya labelnya: nama layanan terverifikasi bila ada, atau label bawaan.
 * Nomor darurat tidak pernah disembunyikan seperti kontak layanan biasa
 * (Req 1.1) karena keduanya nomor publik nasional, bukan hasil pengecekan tim.
 */
final class Emergency
{
    /** Nomor yang wajib selalu tampil, dalam urutan tampil (Req 8.12, 8.14). */
    public const NUMBERS = ['112', '129'];

    /** Label bawaan bila tidak ada layanan terverifikasi untuk nomor itu. */
    public const DEFAULT_LABELS = [
        '112' => 'Layanan darurat nasional',
        '129' => 'SAPA 129 (Kementerian PPPA)',
    ];

    /** Kunci kamus untuk label bawaan, supaya label itu ikut berbahasa Inggris. */
    public const LABEL_KEYS = [
        '112' => 'emergency.112.label',
        '129' => 'emergency.129.label',
    ];

    /**
     * Daftar opsi panggilan darurat: selalu dua entri, nomor 112 lalu 129.
     *
     * Nama diambil dari layanan yang terverifikasi dan nomornya cocok, supaya
     * halaman menyebut lembaga seperti yang dicatat Admin. Baris yang belum
     * terverifikasi diabaikan dan nomornya tetap tampil dengan label bawaan
     * (Req 8.14). Nama layanan ditulis Admin, jadi tidak diterjemahkan
     * (Req 8.5); hanya label bawaan yang mengikuti bahasa aktif.
     *
     * @param list<array<string,mixed>> $verifiedServices baris tabel `services`
     * @param string $lang bahasa aktif untuk label bawaan
     * @param array<string,array<string,mixed>> $dict kamus per bahasa
     * @return list<array{label:string, number:string}>
     */
    public static function options(array $verifiedServices, string $lang = I18n::DEFAULT_LANG, array $dict = []): array
    {
        $options = [];
        foreach (self::NUMBERS as $number) {
            $options[] = [
                'label' => self::serviceName($verifiedServices, $number) ?? self::defaultLabel($number, $lang, $dict),
                'number' => $number,
            ];
        }

        return $options;
    }

    /**
     * Nama layanan terverifikasi yang nomornya sama dengan $number, atau null
     * bila tidak ada. Entri pertama yang cocok menang.
     */
    private static function serviceName(array $services, string $number): ?string
    {
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            // Satu-satunya jalur baca kontak layanan, jadi baris yang belum
            // terverifikasi tidak bisa menyumbang nama di sini (Req 1.1).
            $view = ServiceRules::publicView($service);
            if (!$view['verified'] || !self::matches($view, $number)) {
                continue;
            }
            $name = trim($view['name']);
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /** Benar bila telepon atau WhatsApp layanan adalah nomor darurat itu. */
    private static function matches(array $view, string $number): bool
    {
        foreach (['phone', 'whatsapp'] as $field) {
            if (self::digits($view[$field] ?? null) === $number) {
                return true;
            }
        }

        return false;
    }

    /** Hanya angka dari satu kontak, supaya '(112)' dan '112 ' tetap cocok. */
    private static function digits(?string $contact): string
    {
        return (string) preg_replace('/\D+/', '', (string) $contact);
    }

    /** Label bawaan dalam bahasa aktif, dengan cadangan konstanta (Req 8.7). */
    private static function defaultLabel(string $number, string $lang, array $dict): string
    {
        $key = self::LABEL_KEYS[$number] ?? null;
        if ($key !== null && (I18n::has($key, $lang, $dict) || I18n::has($key, I18n::DEFAULT_LANG, $dict))) {
            return I18n::t($key, $lang, $dict);
        }

        return self::DEFAULT_LABELS[$number] ?? $number;
    }
}
