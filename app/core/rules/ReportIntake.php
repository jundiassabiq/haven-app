<?php
declare(strict_types=1);

/**
 * Aturan penerimaan laporan (Req 2, 4, 5) — murni, tanpa DB, tanpa superglobal,
 * dan tanpa time(). Dipakai `Report::create` dan `ReportController` supaya
 * status anak serta urgensi dihitung di satu tempat saja.
 *
 * Req 2.6: intakeErrors menolak kategori kosong atau yang bukan kunci
 *   `Report::CATEGORIES`.
 * Req 2.9: kategori `perkawinan-anak` menandai laporan sebagai kasus anak.
 * Req 2.10: kategori `perdagangan-orang` memberi urgensi "tinggi".
 * Req 4.1, 4.2: jawaban usia wajib, tepat tiga pilihan (`ya`, `tidak`,
 *   `tidak-yakin`), dan jawaban kosong atau tak dikenal ditolak.
 * Req 4.3: jawaban `ya` atau `tidak-yakin` menandai kasus anak, jawaban
 *   `tidak` tidak.
 * Req 4.7: kasus anak selalu berurgensi "tinggi", apa pun jawaban lain.
 * Req 5.1: peran pelapor wajib, tepat dua pilihan (`korban`, `saksi`).
 *
 * Catatan desain (design.md, "Konflik aturan anak"): kategori
 * `perkawinan-anak` SELALU menandai kasus anak, termasuk saat jawaban usia
 * adalah `tidak`. Req 2.9 dan Req 4.3 berbenturan di titik itu dan desain
 * mengutamakan perlindungan anak. Mengubah keputusan ini berarti mengubah
 * `isChild()` di bawah dan Property 7.
 */
final class ReportIntake
{
    /** Jawaban usia korban yang sah (Req 4.1). */
    public const AGE = ['ya', 'tidak', 'tidak-yakin'];

    /** Peran pelapor yang sah (Req 5.1). */
    public const REPORTER = ['korban', 'saksi'];

    /** Teks pilihan usia di form lapor, kuncinya sama dengan self::AGE. */
    public const AGE_LABELS = [
        'ya' => 'Ya, korban di bawah 18 tahun',
        'tidak' => 'Tidak, korban 18 tahun atau lebih',
        'tidak-yakin' => 'Tidak yakin',
    ];

    /** Teks pilihan peran di form lapor, kuncinya sama dengan self::REPORTER. */
    public const REPORTER_LABELS = [
        'korban' => 'Saya korbannya',
        'saksi' => 'Saya saksi atau melaporkan untuk orang lain',
    ];

    /** Jawaban usia yang menandai kasus anak (Req 4.3). */
    private const CHILD_AGE = ['ya', 'tidak-yakin'];

    /** Kategori yang ikut menandai kasus anak (Req 2.9). */
    public const CHILD_CATEGORY = 'perkawinan-anak';

    /** Kategori yang selalu berurgensi tinggi (Req 2.10). */
    public const URGENT_CATEGORY = 'perdagangan-orang';

    /**
     * Nilai turunan yang disimpan bersama laporan: status kasus anak dan
     * urgensi awal (Req 2.9, 2.10, 4.3, 4.7).
     *
     * Urgensi "tinggi" berlaku bila kasus anak, kategori `perdagangan-orang`,
     * atau pelapor menjawab bahaya `ya`. Di luar itu aturan lama tetap berlaku:
     * "sedang" bila kejadian masih berlangsung, "rendah" bila tidak.
     *
     * Masukan yang tidak lolos `intakeErrors` tetap menghasilkan nilai yang
     * bisa dibaca — nilai tak dikenal diperlakukan seperti kosong, jadi
     * pemanggil tidak pernah mendapat urgensi di luar `Report::URGENCY`.
     *
     * @param array{jenis?:mixed, usia?:mixed, bahaya?:mixed, berlangsung?:mixed} $in masukan form lapor
     * @return array{is_child:bool, urgency:string}
     */
    public static function derive(array $in): array
    {
        $category = self::text($in['jenis'] ?? null);
        $isChild = self::isChild(self::text($in['usia'] ?? null), $category);

        if ($isChild || $category === self::URGENT_CATEGORY) {
            return ['is_child' => $isChild, 'urgency' => 'tinggi'];
        }

        $inDanger = self::text($in['bahaya'] ?? null) === 'ya';

        return [
            'is_child' => false,
            'urgency' => Report::initialUrgency($inDanger, self::text($in['berlangsung'] ?? null)),
        ];
    }

    /**
     * Benar bila laporan ini kasus anak: jawaban usia `ya` atau `tidak-yakin`,
     * atau kategori `perkawinan-anak` (Req 2.9, 4.3).
     */
    public static function isChild(string $ageAnswer, string $category): bool
    {
        return in_array($ageAnswer, self::CHILD_AGE, true) || $category === self::CHILD_CATEGORY;
    }

    /**
     * Galat pilihan wajib di awal form lapor: kategori, usia korban, dan peran
     * pelapor (Req 2.6, 4.2, 5.1). Kosong bila ketiganya sah.
     *
     * Pemeriksaan kronologi, kontak, dan persetujuan tetap milik
     * `Report::validationErrors`; kunci galat di sini memakai nama kolom form
     * yang sama supaya kedua daftar bisa digabung tanpa saling menimpa.
     *
     * @param array{jenis?:mixed, usia?:mixed, peran?:mixed} $in masukan form lapor
     * @return array<string,string> nama kolom => pesan Bahasa Indonesia
     */
    public static function intakeErrors(array $in): array
    {
        $errors = [];

        $category = self::text($in['jenis'] ?? null);
        if ($category === '') {
            $errors['jenis'] = 'Pilih jenis kejadian, atau pilih "Belum yakin".';
        } elseif (!array_key_exists($category, Report::CATEGORIES)) {
            $errors['jenis'] = 'Jenis kejadian itu tidak ada dalam daftar. Pilih salah satu pilihan di atas.';
        }

        $age = self::text($in['usia'] ?? null);
        if ($age === '') {
            $errors['usia'] = 'Pilih salah satu jawaban tentang usia korban. Jawaban ini wajib.';
        } elseif (!in_array($age, self::AGE, true)) {
            $errors['usia'] = 'Jawaban usia tidak dikenali. Pilih "Ya", "Tidak", atau "Tidak yakin".';
        }

        $reporter = self::text($in['peran'] ?? null);
        if ($reporter === '') {
            $errors['peran'] = 'Pilih peranmu: korban atau saksi. Pilihan ini wajib.';
        } elseif (!in_array($reporter, self::REPORTER, true)) {
            $errors['peran'] = 'Peran pelapor tidak dikenali. Pilih "Korban" atau "Saksi".';
        }

        return $errors;
    }

    /**
     * Teks setelah trim. Nilai non-skalar (null, array, objek) menjadi string
     * kosong, jadi masukan yang dipalsukan ditolak sebagai pilihan kosong dan
     * tidak pernah memicu galat tipe.
     */
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
}
