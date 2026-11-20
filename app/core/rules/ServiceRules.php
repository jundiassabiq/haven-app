<?php
declare(strict_types=1);

/**
 * Aturan direktori layanan (Req 1) — murni, tanpa DB dan tanpa time().
 * Hari ini selalu masuk sebagai parameter DateTimeImmutable.
 *
 * Req 1.1, 1.11: publicView menyembunyikan telepon dan WhatsApp selama layanan
 *   belum terverifikasi, dan menampilkannya hanya bila terverifikasi.
 * Req 1.2: label "Perlu verifikasi" menggantikan nomor di tampilan publik.
 * Req 1.3, 1.4, 1.5: verificationErrors menuntut minimal satu kontak dan
 *   tanggal verifikasi YYYY-MM-DD yang tidak melewati hari ini.
 * Req 1.6: nextVerified mereset status verifikasi saat kontak berubah.
 * Req 1.7: isStale menandai verifikasi yang lebih tua dari 180 hari
 *   ("Perlu dicek ulang", hanya label di daftar Admin — nomor tetap tampil).
 * Req 1.8: formatDate menulis tanggal verifikasi sebagai DD-MM-YYYY.
 * Req 1.9: unverifiedCounts menghitung layanan belum terverifikasi per tipe.
 */
final class ServiceRules
{
    /** Batas umur verifikasi sebelum perlu dicek ulang (Req 1.7). */
    public const STALE_DAYS = 180;

    /** Teks pengganti nomor di tampilan publik (Req 1.2). */
    public const UNVERIFIED_LABEL = 'Perlu verifikasi';

    /** Teks penanda di daftar Admin untuk verifikasi kedaluwarsa (Req 1.7). */
    public const STALE_LABEL = 'Perlu dicek ulang';

    /** Tipe layanan yang dihitung di ringkasan Admin (Req 1.9). */
    public const COUNTED_TYPES = ['uptd', 'polisi', 'rs', 'lbh'];

    /** Kolom kontak yang perubahannya mereset verifikasi (Req 1.6). */
    private const CONTACT_FIELDS = ['phone', 'whatsapp'];

    /**
     * Bentuk publik satu layanan: satu-satunya jalur yang boleh dipakai halaman
     * publik dan JSON publik, supaya tidak ada tampilan yang membocorkan nomor
     * layanan yang belum dicek (Req 1.1, 1.2, 1.8, 1.11).
     *
     * `verified` pada hasil berarti "kontaknya layak tampil": benar hanya bila
     * layanan terverifikasi dan punya setidaknya satu kontak. Dengan begitu
     * invarian Req 1.11 berlaku untuk semua baris, termasuk baris lama yang
     * terverifikasi tetapi kontaknya kosong: `phone` dan `whatsapp` bernilai
     * null tepat ketika `verified` salah.
     *
     * @param array<string,mixed> $s satu baris tabel `services`
     * @return array{id:int, name:string, type:string, area:string, hours:string,
     *     tags:list<string>, verified:bool, phone:?string, whatsapp:?string,
     *     verified_at:?string, contact_label:?string}
     */
    public static function publicView(array $s): array
    {
        $phone = self::contact($s['phone'] ?? null);
        $whatsapp = self::contact($s['whatsapp'] ?? null);
        $verified = self::flag($s['is_verified'] ?? null) && ($phone !== null || $whatsapp !== null);
        $verifiedAt = self::storedYmd($s['verified_at'] ?? null);

        return [
            'id' => (int) ($s['id'] ?? 0),
            'name' => self::text($s['name'] ?? null),
            'type' => self::text($s['type'] ?? null),
            'area' => self::area($s),
            'hours' => self::text($s['hours'] ?? null),
            'tags' => self::tags($s['tags'] ?? null),
            'verified' => $verified,
            'phone' => $verified ? $phone : null,
            'whatsapp' => $verified ? $whatsapp : null,
            // Tanggal verifikasi tampil di samping nomor (Req 1.8).
            'verified_at' => $verified && $verifiedAt !== null ? self::formatDate($verifiedAt) : null,
            // Pengganti nomor di daftar publik (Req 1.2).
            'contact_label' => $verified ? null : self::UNVERIFIED_LABEL,
        ];
    }

    /**
     * Galat khusus penandaan verifikasi, kosong bila layanan tidak ditandai
     * terverifikasi. Pemeriksaan format nama, tipe, wilayah, dan catatan
     * verifikasi tetap milik `Service::validationErrors`.
     *
     * Req 1.4: tanpa telepon dan tanpa WhatsApp (setelah trim) simpanan ditolak.
     * Req 1.5: tanggal verifikasi yang bukan YYYY-MM-DD valid, atau melewati
     * tanggal server hari ini, ditolak.
     *
     * @param array<string,mixed> $in masukan form Admin
     * @return array<string,string> nama kolom => pesan Bahasa Indonesia
     */
    public static function verificationErrors(array $in, DateTimeImmutable $today): array
    {
        if (!self::flag($in['is_verified'] ?? null)) {
            return [];
        }

        $errors = [];

        if (self::contact($in['phone'] ?? null) === null && self::contact($in['whatsapp'] ?? null) === null) {
            $errors['phone'] = 'Layanan terverifikasi perlu minimal satu nomor telepon atau WhatsApp.';
        }

        $raw = self::text($in['verified_at'] ?? null);
        $date = self::ymd($raw);
        if ($raw === '') {
            $errors['verified_at'] = 'Isi tanggal verifikasi dengan format YYYY-MM-DD.';
        } elseif ($date === null) {
            $errors['verified_at'] = 'Tanggal verifikasi tidak valid. Pakai format YYYY-MM-DD, misalnya 2026-05-17.';
        } elseif ($date > $today->format('Y-m-d')) {
            $errors['verified_at'] = 'Tanggal verifikasi tidak boleh melewati tanggal hari ini.';
        }

        return $errors;
    }

    /**
     * Status verifikasi setelah penyimpanan (Req 1.6).
     *
     * Salah bila Admin tidak menandai terverifikasi, dan juga salah bila
     * layanan tadinya terverifikasi lalu telepon atau WhatsApp-nya berubah —
     * nomor baru harus dicek dulu, jadi layanan kembali belum terverifikasi
     * dalam operasi simpan yang sama dan tetap begitu sampai Admin menandainya
     * lagi. Perbandingan kontak memakai nilai setelah trim; kolom kontak yang
     * tidak dikirim pada masukan dianggap tidak berubah.
     *
     * @param array<string,mixed> $old baris tersimpan, array kosong untuk layanan baru
     * @param array<string,mixed> $new masukan form Admin
     */
    public static function nextVerified(array $old, array $new): bool
    {
        if (!self::flag($new['is_verified'] ?? null)) {
            return false;
        }
        if (!self::flag($old['is_verified'] ?? null)) {
            // Layanan baru atau yang baru ditandai Admin: verifikasi berlaku.
            return true;
        }

        return !self::contactChanged($old, $new);
    }

    /**
     * Benar bila verifikasi lebih tua dari 180 hari pada tanggal $today
     * (Req 1.7). Tanggal kosong, tidak terbaca, hari ini, atau di masa depan
     * tidak dianggap kedaluwarsa.
     */
    public static function isStale(?string $verifiedAt, DateTimeImmutable $today): bool
    {
        $date = self::midnight(self::storedYmd($verifiedAt), $today);
        if ($date === null) {
            return false;
        }

        $start = $today->setTime(0, 0);
        if ($date >= $start) {
            return false;
        }

        return (int) $date->diff($start)->days > self::STALE_DAYS;
    }

    /**
     * Tanggal YYYY-MM-DD menjadi DD-MM-YYYY (Req 1.8). Nilai yang bukan tanggal
     * valid menghasilkan string kosong supaya tampilan tidak memuat teks aneh.
     */
    public static function formatDate(string $ymd): string
    {
        $date = self::ymd($ymd);
        if ($date === null) {
            return '';
        }

        [$year, $month, $day] = explode('-', $date);

        return $day . '-' . $month . '-' . $year;
    }

    /**
     * Jumlah layanan belum terverifikasi per tipe untuk daftar Admin (Req 1.9).
     * Selalu tepat empat kunci, bernilai 0 untuk tipe tanpa entri. Tipe lain
     * (hotline, psikolog, kampus) tidak ikut dihitung.
     *
     * @param list<array<string,mixed>> $services
     * @return array{uptd:int, polisi:int, rs:int, lbh:int}
     */
    public static function unverifiedCounts(array $services): array
    {
        /** @var array{uptd:int, polisi:int, rs:int, lbh:int} $counts */
        $counts = array_fill_keys(self::COUNTED_TYPES, 0);

        foreach ($services as $service) {
            if (!is_array($service) || self::flag($service['is_verified'] ?? null)) {
                continue;
            }
            $type = self::text($service['type'] ?? null);
            if (array_key_exists($type, $counts)) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    /** Benar bila salah satu kontak berbeda antara data lama dan masukan baru. */
    private static function contactChanged(array $old, array $new): bool
    {
        foreach (self::CONTACT_FIELDS as $field) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            if (self::contact($old[$field] ?? null) !== self::contact($new[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** Wilayah layanan untuk tampilan publik. Layanan nasional berlaku di mana saja. */
    private static function area(array $s): string
    {
        $province = self::text($s['province'] ?? null);
        if ($province === 'Nasional') {
            return 'Seluruh Indonesia';
        }

        $parts = array_filter([self::text($s['city'] ?? null), $province], static fn(string $p): bool => $p !== '');

        return implode(', ', $parts);
    }

    /** Daftar tag dari kolom `tags` yang dipisah koma, tanpa entri kosong. */
    private static function tags(mixed $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', self::text($raw)))));
    }

    /** Kontak setelah trim, atau null bila kosong atau tidak terbaca. */
    private static function contact(mixed $raw): ?string
    {
        $value = self::text($raw);

        return $value === '' ? null : $value;
    }

    /** Teks setelah trim. Nilai non-skalar (null, array, objek) menjadi string kosong. */
    private static function text(mixed $raw): string
    {
        if (is_string($raw)) {
            return trim($raw);
        }
        if (is_int($raw) || is_float($raw)) {
            return trim((string) $raw);
        }

        return '';
    }

    /**
     * Normalisasi tanggal menjadi 'Y-m-d', atau null bila bukan tanggal
     * YYYY-MM-DD yang valid. Ketat: '17-05-2026', '2026-5-17', dan
     * '2026-02-30' semuanya ditolak (Req 1.5).
     */
    private static function ymd(mixed $raw): ?string
    {
        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        $value = self::text($raw);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    /**
     * Tanggal yang dibaca dari basis data. Kolom `verified_at` bertipe DATE,
     * tetapi beberapa pemanggil meneruskan nilai DATETIME, jadi bagian jam
     * dibuang sebelum divalidasi.
     */
    private static function storedYmd(mixed $raw): ?string
    {
        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        $value = self::text($raw);
        if (preg_match('/^(\S+)[ T]/', $value, $m) === 1) {
            $value = $m[1];
        }

        return self::ymd($value);
    }

    /** Tengah malam pada tanggal $ymd di zona waktu $reference. */
    private static function midnight(?string $ymd, DateTimeImmutable $reference): ?DateTimeImmutable
    {
        if ($ymd === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $reference->getTimezone());

        return $date === false ? null : $date;
    }

    /** Nilai 1/'1'/true/'on' dianggap benar; kosong, '0', dan null salah. */
    private static function flag(mixed $raw): bool
    {
        return !empty($raw);
    }
}
