<?php
declare(strict_types=1);

/**
 * Mode tampilan: penuh atau ringan (Req 8) — murni, tanpa Session dan tanpa
 * superglobal. Pilihan sesi dan header permintaan masuk sebagai parameter.
 *
 * Req 8.10: permintaan dengan header `Save-Data: on` dimulai dalam mode ringan
 *   selama pengguna belum memilih mode di sesi ini.
 * Req 8.13: pilihan pengguna disimpan di sesi dan menang atas header
 *   `Save-Data`, termasuk pilihan "penuh" pada perangkat yang mengirim header itu.
 *
 * Mode ringan sendiri (tanpa gambar sampul, tanpa canvas, tanpa polling)
 * diterapkan di layout dan view pada tugas 12.5.
 */
final class Mode
{
    /** Kunci penyimpanan pilihan mode di `Session` (Req 8.13). */
    public const SESSION_KEY = 'mode';

    /** Nilai tersimpan: mode ringan. */
    public const LITE = 'ringan';

    /** Nilai tersimpan: mode penuh. */
    public const FULL = 'penuh';

    /** Nilai yang sah untuk disimpan di sesi. */
    public const CHOICES = [self::LITE, self::FULL];

    /** Nama header permintaan yang meminta hemat data (Req 8.10). */
    public const SAVE_DATA_HEADER = 'Save-Data';

    /**
     * Sinonim yang diterima dari form dan dari nilai sesi lama. Memudahkan
     * `POST /mode` menerima tombol bernilai '1'/'0' atau 'on'/'off' tanpa
     * menambah cabang di controller.
     */
    private const ALIASES = [
        'ringan' => self::LITE, 'lite' => self::LITE, 'on' => self::LITE, '1' => self::LITE, 'true' => self::LITE,
        'penuh' => self::FULL, 'full' => self::FULL, 'off' => self::FULL, '0' => self::FULL, 'false' => self::FULL,
    ];

    /**
     * Benar bila permintaan ini harus dilayani dalam mode ringan.
     *
     * Pilihan yang tersimpan di sesi selalu menang (Req 8.13). Hanya bila belum
     * ada pilihan yang sah, header `Save-Data: on` menyalakan mode ringan
     * (Req 8.10). Tanpa keduanya, mode penuh.
     *
     * @param ?string $sessionChoice nilai `Session::get(Mode::SESSION_KEY)`
     * @param ?string $saveDataHeader isi header `Save-Data` apa adanya
     */
    public static function lite(?string $sessionChoice, ?string $saveDataHeader): bool
    {
        return self::resolve($sessionChoice, $saveDataHeader) === self::LITE;
    }

    /**
     * Mode aktif sebagai nilai tetap (`Mode::LITE` atau `Mode::FULL`), untuk
     * penanda tombol dan kelas pada `<body>`.
     */
    public static function resolve(?string $sessionChoice, ?string $saveDataHeader): string
    {
        $choice = self::normalizeChoice($sessionChoice);
        if ($choice !== null) {
            return $choice;
        }

        return self::savesData($saveDataHeader) ? self::LITE : self::FULL;
    }

    /**
     * Benar bila pengguna sudah memilih mode di sesi ini. Dipakai layout untuk
     * menjelaskan bahwa mode ringan menyala karena header, bukan karena pilihan.
     */
    public static function chosen(?string $sessionChoice): bool
    {
        return self::normalizeChoice($sessionChoice) !== null;
    }

    /**
     * Nilai mode yang sah dari masukan apa pun, atau null bila tidak dikenal.
     * Dipakai `POST /mode` untuk menolak nilai asing tanpa mengubah sesi.
     */
    public static function normalizeChoice(mixed $raw): ?string
    {
        if (is_bool($raw)) {
            return $raw ? self::LITE : self::FULL;
        }
        if (is_int($raw)) {
            $raw = (string) $raw;
        }
        if (!is_string($raw)) {
            return null;
        }

        return self::ALIASES[strtolower(trim($raw))] ?? null;
    }

    /**
     * Benar bila header `Save-Data` meminta hemat data. Nilai resminya hanya
     * token `on`; perbandingan mengabaikan besar kecil huruf dan spasi, dan
     * daftar berkoma dianggap meminta hemat data bila salah satu tokennya `on`.
     * Header lain (`off`, kosong, tidak dikirim) tidak menyalakan mode ringan.
     */
    public static function savesData(?string $saveDataHeader): bool
    {
        if ($saveDataHeader === null) {
            return false;
        }

        foreach (explode(',', $saveDataHeader) as $token) {
            if (strtolower(trim($token)) === 'on') {
                return true;
            }
        }

        return false;
    }
}
