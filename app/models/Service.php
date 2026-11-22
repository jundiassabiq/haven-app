<?php
declare(strict_types=1);

/** Module 9: help-service directory. Contacts are public only after the team verifies them. */
final class Service
{
    public const TYPES = [
        'hotline' => 'Hotline', 'uptd' => 'UPTD PPA', 'polisi' => 'Kepolisian (Unit PPA)', 'rs' => 'Rumah sakit',
        'lbh' => 'Lembaga bantuan hukum', 'psikolog' => 'Psikolog', 'kampus' => 'Satgas kampus',
    ];

    /**
     * Tag per slot panel rujukan anak (Req 4.5). Pencocokan lewat tag, bukan nama,
     * supaya mengganti nama layanan tidak memutus panel.
     */
    public const CHILD_PANEL_TAGS = [
        'uptd_ppa_anak' => 'panel-anak:uptd',
        'kpai' => 'panel-anak:kpai',
        'sapa_129' => 'panel-anak:sapa129',
    ];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM services WHERE id = ?', [$id]);
    }

    public static function all(): array
    {
        return Database::all(
            "SELECT s.*, (SELECT COUNT(*) FROM reports r WHERE r.service_id = s.id) AS referrals
             FROM services s ORDER BY s.is_verified DESC, (s.province = 'Nasional') DESC, s.province, s.city, s.name"
        );
    }

    /** National services always match a regional filter. */
    public static function search(array $f, int $limit = 200): array
    {
        $w = ['1=1'];
        $p = [];
        if (!empty($f['provinsi'])) {
            $w[] = "(s.province = ? OR s.province = 'Nasional')";
            $p[] = $f['provinsi'];
        }
        if (!empty($f['kota'])) {
            $w[] = "(s.city = ? OR s.province = 'Nasional')";
            $p[] = $f['kota'];
        }
        if (!empty($f['jenis']) && isset(self::TYPES[$f['jenis']])) {
            $w[] = 's.type = ?';
            $p[] = $f['jenis'];
        }
        if (!empty($f['h24'])) {
            $w[] = 's.is_24h = 1';
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes(mb_substr($f['q'], 0, 80), '%_\\') . '%';
            $w[] = '(s.name LIKE ? OR s.tags LIKE ? OR s.city LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return Database::all(
            'SELECT s.* FROM services s WHERE ' . implode(' AND ', $w) .
            " ORDER BY (s.province = 'Nasional') DESC, s.is_verified DESC, s.province, s.city, s.name LIMIT " . max(1, $limit),
            $p
        );
    }

    public static function provinces(): array
    {
        return array_column(Database::all("SELECT DISTINCT province FROM services WHERE province <> 'Nasional' ORDER BY province"), 'province');
    }

    public static function cities(?string $province): array
    {
        return array_column(Database::all(
            "SELECT DISTINCT city FROM services WHERE province <> 'Nasional' AND (? = '' OR province = ?) ORDER BY city",
            [(string) $province, (string) $province]
        ), 'city');
    }

    /**
     * Public JSON and HTML shape, lewat `ServiceRules::publicView` (Req 1.1, 1.11).
     * Kunci lama tetap ada; `verified_at` (DD-MM-YYYY) dan `contact_label`
     * ditambahkan untuk tampilan.
     */
    public static function publicArray(array $s): array
    {
        return ServiceRules::publicView($s);
    }

    /**
     * Tiga layanan panel rujukan anak (Req 4.5). Selalu tepat tiga kunci:
     * `uptd_ppa_anak`, `kpai`, `sapa_129`. Nilainya baris `services`, atau null
     * bila seed belum memuat layanan dengan tag itu, supaya pemanggil bisa
     * menampilkan entri pengganti tanpa kontak.
     *
     * @return array{uptd_ppa_anak:?array, kpai:?array, sapa_129:?array}
     */
    public static function findChildServices(): array
    {
        $found = array_fill_keys(array_keys(self::CHILD_PANEL_TAGS), null);
        // Layanan terverifikasi menang bila dua baris memakai tag yang sama.
        $rows = Database::all("SELECT * FROM services WHERE tags LIKE '%panel-anak:%' ORDER BY is_verified DESC, id");

        foreach ($rows as $row) {
            $tags = array_map('trim', explode(',', (string) ($row['tags'] ?? '')));
            foreach (self::CHILD_PANEL_TAGS as $key => $tag) {
                if ($found[$key] === null && in_array($tag, $tags, true)) {
                    $found[$key] = $row;
                }
            }
        }

        return $found;
    }

    /**
     * Baris layanan yang mungkin memuat nomor darurat 112 atau 129, bahan
     * `Emergency::options` (Req 8.12). Pencocokan tepat dilakukan di sana karena
     * nomor tersimpan bisa berisi spasi atau tanda kurung; di sini cukup
     * menyaring kandidatnya agar kueri tetap murah. Hanya layanan terverifikasi,
     * supaya nama lembaga yang tampil sudah dicek tim (Req 1.1).
     *
     * @return list<array<string,mixed>>
     */
    public static function emergencyLines(): array
    {
        $like = [];
        $params = [];
        foreach (Emergency::NUMBERS as $number) {
            foreach (['phone', 'whatsapp'] as $field) {
                $like[] = "$field LIKE ?";
                $params[] = '%' . $number . '%';
            }
        }

        return Database::all(
            'SELECT * FROM services WHERE is_verified = 1 AND (' . implode(' OR ', $like) . ') ORDER BY id',
            $params
        );
    }

    /** Status verifikasi untuk pesan dan audit (Req 1.10). */
    public static function verifyLabel(bool $verified): string
    {
        return $verified ? 'terverifikasi' : 'belum terverifikasi';
    }

    public static function validationErrors(array $in, ?DateTimeImmutable $today = null): array
    {
        $e = Validator::make($in, [
            'name' => 'required|max:160',
            'type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'province' => 'required|max:60',
            'city' => 'required|max:80',
            'hours' => 'max:80',
            'phone' => ['regex:/^[0-9+\-() ]{3,30}$/'],
            'whatsapp' => ['regex:/^[0-9+\- ]{9,30}$/'],
            'tags' => 'max:255',
            'address' => 'max:500',
            'verified_note' => 'max:255',
        ], [
            'name.required' => 'Tulis nama layanan.',
            'phone' => 'Nomor telepon hanya boleh angka, spasi, +, -, dan tanda kurung.',
            'whatsapp' => 'Tulis nomor WhatsApp 9 sampai 30 digit.',
        ]);
        if (!empty($in['is_verified'])) {
            // Kontak dan tanggal verifikasi: aturan murni, hari ini dari pemanggil (Req 1.3, 1.4, 1.5).
            $e = array_merge($e, ServiceRules::verificationErrors($in, $today ?? new DateTimeImmutable('now')));
            if (trim((string) ($in['verified_note'] ?? '')) === '') {
                $e['verified_note'] = 'Catat cara verifikasinya.';
            }
        }
        return $e;
    }

    /**
     * Menyimpan layanan. `$old` adalah baris tersimpan; mengubah telepon atau
     * WhatsApp layanan terverifikasi mengembalikannya ke belum terverifikasi dan
     * mengosongkan `verified_at` dalam `UPDATE` yang sama (Req 1.6).
     */
    public static function save(array $in, ?int $id, array $old = []): int
    {
        if ($id !== null && $old === []) {
            $old = self::find($id) ?? [];
        }
        $verified = ServiceRules::nextVerified($old, $in);
        $vals = [
            trim($in['name']), $in['type'], trim($in['province']), trim($in['city']), trim($in['address'] ?? '') ?: null,
            trim($in['phone'] ?? '') ?: null, trim($in['whatsapp'] ?? '') ?: null, trim($in['hours'] ?? ''),
            (int) !empty($in['is_24h']), implode(', ', array_filter(array_map('trim', explode(',', (string) ($in['tags'] ?? ''))))),
            (int) $verified, $verified ? $in['verified_at'] : null, $verified ? trim((string) ($in['verified_note'] ?? '')) : null, now(),
        ];
        if ($id === null) {
            return Database::insert(
                'INSERT INTO services (name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, updated_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$vals, now()]
            );
        }
        Database::run(
            'UPDATE services SET name = ?, type = ?, province = ?, city = ?, address = ?, phone = ?, whatsapp = ?, hours = ?, is_24h = ?, tags = ?,
               is_verified = ?, verified_at = ?, verified_note = ?, updated_at = ? WHERE id = ?',
            [...$vals, $id]
        );
        return $id;
    }

    /** @return bool false when reports still refer to it */
    public static function delete(int $id): bool
    {
        if ((int) Database::value('SELECT COUNT(*) FROM reports WHERE service_id = ?', [$id]) > 0) {
            return false;
        }
        Database::run('DELETE FROM services WHERE id = ?', [$id]);
        return true;
    }
}
