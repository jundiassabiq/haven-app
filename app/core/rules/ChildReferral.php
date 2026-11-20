<?php
declare(strict_types=1);

/**
 * Panel rujukan anak (Req 4.4, 4.5, 4.6) — murni, tanpa DB dan tanpa time().
 * Barisnya datang dari `Service::findChildServices()`.
 *
 * Req 4.5: panel selalu tepat tiga entri bernama — UPTD PPA anak, KPAI, dan
 *   SAPA 129 — dan kontak hanya diambil dari layanan terverifikasi.
 * Req 4.6: entri yang layanannya belum terverifikasi (atau belum ada di basis
 *   data) tetap tampil dengan namanya, berlabel "Perlu verifikasi" dan tanpa
 *   kontak, sementara dua entri lain tetap tampil seperti biasa.
 *
 * Nomor layanan yang belum dicek tim tidak boleh bocor ke panel ini: anak yang
 * menelepon nomor mati atau salah sambung bisa kehilangan satu-satunya
 * kesempatan meminta tolong. Karena itu kontak hanya lewat
 * `ServiceRules::publicView` dan masih disaring sekali lagi di sini.
 */
final class ChildReferral
{
    /**
     * Slot panel beserta nama cadangannya, dalam urutan tampil. Kuncinya sama
     * dengan kunci `Service::findChildServices()`. Nama cadangan dipakai bila
     * seed belum memuat layanan untuk slot itu, supaya panel tetap tiga entri
     * bernama (Req 4.5).
     */
    public const SLOTS = [
        'uptd_ppa_anak' => 'UPTD PPA (layanan anak)',
        'kpai' => 'Komisi Perlindungan Anak Indonesia (KPAI)',
        'sapa_129' => 'SAPA 129',
    ];

    /** Pengganti kontak untuk layanan yang nomornya belum dicek (Req 4.6). */
    public const UNVERIFIED_LABEL = ServiceRules::UNVERIFIED_LABEL;

    /**
     * Tiga entri panel rujukan anak, selalu dalam urutan `self::SLOTS`.
     *
     * Kunci yang tidak ada, nilai null, dan nilai yang bukan array sama-sama
     * menghasilkan entri tanpa kontak — jadi seed yang belum lengkap atau baris
     * layanan yang hilang tidak pernah menghilangkan entri dari panel.
     * `contact` berisi telepon bila ada, kalau tidak WhatsApp, dan selalu null
     * selama layanan belum terverifikasi.
     *
     * @param array<string,mixed> $threeServices hasil `Service::findChildServices()`
     * @return list<array{key:string, name:string, verified:bool, contact:?string,
     *     contact_kind:?string, verified_at:?string, label:?string}>
     */
    public static function panel(array $threeServices): array
    {
        $panel = [];
        foreach (self::SLOTS as $key => $fallbackName) {
            $panel[] = self::entry($key, $fallbackName, $threeServices[$key] ?? null);
        }

        return $panel;
    }

    /**
     * Satu entri panel. `$row` adalah baris tabel `services`, atau null bila
     * slot ini belum punya layanan.
     *
     * @return array{key:string, name:string, verified:bool, contact:?string,
     *     contact_kind:?string, verified_at:?string, label:?string}
     */
    private static function entry(string $key, string $fallbackName, mixed $row): array
    {
        // publicView sudah mengosongkan nomor layanan yang belum terverifikasi;
        // `verified` di sana juga berarti kontaknya ada (Req 1.1, 1.11).
        $pub = is_array($row) ? ServiceRules::publicView($row) : null;
        $verified = $pub !== null && $pub['verified'];

        $name = $pub !== null && $pub['name'] !== '' ? $pub['name'] : $fallbackName;
        $phone = $verified ? $pub['phone'] : null;
        $whatsapp = $verified ? $pub['whatsapp'] : null;

        return [
            'key' => $key,
            'name' => $name,
            'verified' => $verified,
            'contact' => $phone ?? $whatsapp,
            'contact_kind' => $phone !== null ? 'phone' : ($whatsapp !== null ? 'whatsapp' : null),
            // Tanggal pengecekan (DD-MM-YYYY) tampil di samping nomor (Req 1.8).
            'verified_at' => $verified ? $pub['verified_at'] : null,
            'label' => $verified ? null : self::UNVERIFIED_LABEL,
        ];
    }
}
