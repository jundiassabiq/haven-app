<?php
declare(strict_types=1);

/**
 * Aturan penugasan pendamping (Req 4.9, 4.10) — murni, tanpa DB, tanpa
 * superglobal, dan tanpa time(). Daftar pendamping dan baris laporan selalu
 * masuk sebagai parameter.
 *
 * Req 4.9: pada kasus anak, hanya pendamping terlatih kasus anak
 *   (`users.child_trained = 1`) di wilayah laporan yang ditawarkan.
 * Req 4.10: bila tidak ada pendamping terlatih di wilayah laporan, Admin
 *   mendapat peringatan, seluruh pendamping ditawarkan dengan pendamping
 *   berwilayah sama di depan, dan penugasan baru selesai setelah Admin
 *   mengonfirmasi (`needsConfirm = true`).
 *
 * Keputusan desain (hasil analisis requirements yang disetujui pemilik):
 * jalur Req 4.10 menawarkan SEMUA pendamping, termasuk yang terlatih tetapi
 * berwilayah lain — pendamping terlatih tidak lagi dipakai sebagai penyaring
 * di jalur ini, hanya urutan wilayah yang menentukan. Mengubah keputusan ini
 * berarti mengubah `candidates()` dan Property 11.
 *
 * Kasus bukan anak tidak diatur Req 4.9 dan 4.10: seluruh pendamping
 * ditawarkan dengan wilayah sama di depan, tanpa peringatan dan tanpa
 * konfirmasi, sama seperti perilaku antrean sebelum fitur ini.
 */
final class Assignment
{
    /** Peringatan Admin saat tidak ada pendamping terlatih di wilayah laporan (Req 4.10). */
    public const WARNING = 'Tidak ada pendamping terlatih kasus anak di wilayah laporan ini. Pilih pendamping lain lalu konfirmasi penugasannya.';

    /**
     * Kandidat pendamping untuk satu laporan.
     *
     * `list` mempertahankan urutan masukan di dalam tiap kelompok wilayah,
     * jadi urutan beban kerja dari pemanggil (`Report::companionsByLoad`)
     * tetap terjaga. Baris pendamping dikembalikan utuh tanpa kolom tambahan.
     *
     * `warning` dan `needsConfirm` selalu bernilai sama: keduanya benar hanya
     * pada jalur Req 4.10. Dipisah supaya pemanggil bisa membaca keduanya
     * sesuai maksudnya — `warning` untuk teks di layar, `needsConfirm` untuk
     * pagar di `CaseWorkflow` yang menolak penugasan tanpa `confirm=1`.
     *
     * Baris yang bukan array atau yang perannya terbaca selain `companion`
     * dibuang: hanya pendamping yang boleh ditugaskan.
     *
     * @param list<array<string,mixed>> $companions baris `users` pendamping, urutan pilihan pemanggil
     * @param array<string,mixed> $report baris `reports` (`is_child`, `city`, `province`)
     * @return array{list:list<array<string,mixed>>, warning:bool, needsConfirm:bool, message:?string}
     */
    public static function candidates(array $companions, array $report): array
    {
        $pool = self::pool($companions);
        $regions = self::reportRegions($report);

        [$sameRegion, $elsewhere] = self::partition(
            $pool,
            static fn(array $c): bool => self::inRegions($c, $regions)
        );

        if (!self::flag($report['is_child'] ?? null)) {
            return self::result(array_merge($sameRegion, $elsewhere), false);
        }

        // Req 4.9: pendamping terlatih di wilayah laporan.
        $trained = array_values(array_filter($sameRegion, static fn(array $c): bool => self::flag($c['child_trained'] ?? null)));
        if ($trained !== []) {
            return self::result($trained, false);
        }

        // Req 4.10: tidak ada yang terlatih di wilayah laporan.
        return self::result(array_merge($sameRegion, $elsewhere), true);
    }

    /**
     * Benar bila wilayah kerja pendamping sama dengan wilayah laporan.
     * Kota maupun provinsi laporan dihitung sebagai wilayah laporan, supaya
     * pendamping yang wilayah kerjanya ditulis setingkat provinsi tetap
     * terhitung berwilayah sama.
     *
     * @param array<string,mixed> $companion
     * @param array<string,mixed> $report
     */
    public static function sameRegion(array $companion, array $report): bool
    {
        return self::inRegions($companion, self::reportRegions($report));
    }

    /**
     * @param list<array<string,mixed>> $list
     * @return array{list:list<array<string,mixed>>, warning:bool, needsConfirm:bool, message:?string}
     */
    private static function result(array $list, bool $warning): array
    {
        return [
            'list' => array_values($list),
            'warning' => $warning,
            'needsConfirm' => $warning,
            'message' => $warning ? self::WARNING : null,
        ];
    }

    /**
     * Pendamping yang layak dipertimbangkan, urutan masukan dipertahankan.
     *
     * @param list<array<string,mixed>> $companions
     * @return list<array<string,mixed>>
     */
    private static function pool(array $companions): array
    {
        $pool = [];
        foreach ($companions as $companion) {
            if (!is_array($companion)) {
                continue;
            }
            $role = self::normalize($companion['role'] ?? null);
            if ($role !== '' && $role !== 'companion') {
                continue;
            }
            $pool[] = $companion;
        }

        return $pool;
    }

    /**
     * Memecah daftar menjadi yang cocok dan yang tidak, masing-masing dengan
     * urutan masukan utuh. Pembagian manual dipakai, bukan pengurutan, supaya
     * tidak ada baris yang berpindah posisi di dalam kelompoknya.
     *
     * @param list<array<string,mixed>> $list
     * @param callable(array<string,mixed>):bool $matches
     * @return array{0:list<array<string,mixed>>, 1:list<array<string,mixed>>}
     */
    private static function partition(array $list, callable $matches): array
    {
        $yes = [];
        $no = [];
        foreach ($list as $item) {
            if ($matches($item)) {
                $yes[] = $item;
            } else {
                $no[] = $item;
            }
        }

        return [$yes, $no];
    }

    /**
     * Wilayah laporan yang sudah dinormalkan: kota dan provinsi, tanpa nilai
     * kosong. Laporan tanpa keduanya menghasilkan daftar kosong, jadi tidak
     * ada pendamping yang terhitung berwilayah sama dan urutan masukan tetap.
     *
     * @param array<string,mixed> $report
     * @return list<string>
     */
    private static function reportRegions(array $report): array
    {
        $regions = [];
        foreach (['city', 'province'] as $field) {
            $value = self::normalize($report[$field] ?? null);
            if ($value !== '' && !in_array($value, $regions, true)) {
                $regions[] = $value;
            }
        }

        return $regions;
    }

    /**
     * @param array<string,mixed> $companion
     * @param list<string> $regions
     */
    private static function inRegions(array $companion, array $regions): bool
    {
        $region = self::normalize($companion['region'] ?? null);

        return $region !== '' && in_array($region, $regions, true);
    }

    /**
     * Teks pembanding wilayah: huruf kecil, spasi di tepi dibuang, dan deret
     * spasi di dalam dipadatkan jadi satu. Perbandingan tetap ketat di luar
     * itu — "Banyumas" sama dengan "banyumas", tetapi tidak sama dengan
     * "Kabupaten Banyumas". Nilai non-skalar menjadi string kosong.
     */
    private static function normalize(mixed $raw): string
    {
        if (is_string($raw)) {
            $value = $raw;
        } elseif (is_int($raw) || is_float($raw)) {
            $value = (string) $raw;
        } else {
            return '';
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /** Nilai 1/'1'/true dianggap benar; kosong, '0', dan null salah. */
    private static function flag(mixed $raw): bool
    {
        return !empty($raw);
    }
}
