<?php
declare(strict_types=1);

/**
 * Aturan batas waktu balasan (Reply_SLA) — murni, tanpa DB dan tanpa time().
 *
 * Req 6.1: 2 jam untuk kasus berbahaya, 48 jam untuk kasus lain, dapat diatur
 * lewat SLA_DANGER_HOURS dan SLA_DEFAULT_HOURS di .env. Nilai yang bukan
 * bilangan bulat positif memakai default.
 * Req 6.5: periodIndex memberi nomor periode SLA untuk kunci pengingat.
 * Req 6.8: isOverdue menentukan lencana "Lewat SLA" di antrean.
 */
final class Sla
{
    public const DANGER_HOURS = 2;
    public const DEFAULT_HOURS = 48;

    public const ENV_DANGER = 'SLA_DANGER_HOURS';
    public const ENV_DEFAULT = 'SLA_DEFAULT_HOURS';

    /**
     * Jam SLA untuk satu kasus.
     *
     * @param array<string,mixed> $env isi .env yang relevan, mis. ['SLA_DANGER_HOURS' => '2']
     */
    public static function hours(bool $inDanger, array $env = []): int
    {
        $key = $inDanger ? self::ENV_DANGER : self::ENV_DEFAULT;
        $fallback = $inDanger ? self::DANGER_HOURS : self::DEFAULT_HOURS;

        return self::positiveInt($env[$key] ?? null) ?? $fallback;
    }

    /**
     * Benar bila kasus sudah melewati SLA-nya pada waktu $now.
     *
     * Acuan waktunya: pesan penyintas terakhir yang belum dibalas, atau waktu
     * laporan dibuat bila pihak Haven belum pernah membalas. Bila pesan
     * penyintas terakhir sudah dibalas, tidak ada yang ditunggu dan hasilnya
     * salah.
     *
     * @param array{in_danger?:mixed, created_at?:mixed, last_survivor_at?:mixed, last_reply_at?:mixed} $case
     * @param array<string,mixed> $env
     */
    public static function isOverdue(array $case, DateTimeImmutable $now, array $env = []): bool
    {
        $since = self::reference($case, $now);
        if ($since === null) {
            return false;
        }

        $slaSeconds = self::hours(self::flag($case['in_danger'] ?? null), $env) * 3600;

        return $now->getTimestamp() - $since->getTimestamp() > $slaSeconds;
    }

    /**
     * Nomor periode SLA sejak kasus pertama kali melewati batas: 0 pada periode
     * pertama, 1 setelah satu rentang SLA berikutnya, dan seterusnya. Dipakai
     * sebagai bagian kunci unik (report_id, type, period) agar satu kasus hanya
     * mendapat satu pengingat per periode.
     */
    public static function periodIndex(DateTimeImmutable $exceededAt, DateTimeImmutable $now, int $slaHours): int
    {
        $elapsed = $now->getTimestamp() - $exceededAt->getTimestamp();
        if ($elapsed <= 0) {
            return 0;
        }

        return intdiv($elapsed, max(1, $slaHours) * 3600);
    }

    /** Waktu acuan perhitungan SLA, atau null bila kasus tidak punya acuan yang bisa dibaca. */
    private static function reference(array $case, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $survivorAt = self::time($case['last_survivor_at'] ?? null, $now);
        $replyAt = self::time($case['last_reply_at'] ?? null, $now);

        if ($survivorAt !== null) {
            // Pesan penyintas terakhir masih menunggu balasan.
            return $replyAt === null || $replyAt < $survivorAt ? $survivorAt : null;
        }

        // Belum ada pesan penyintas: yang ditunggu adalah balasan pertama atas laporan.
        return $replyAt === null ? self::time($case['created_at'] ?? null, $now) : null;
    }

    /** Menerima DateTimeInterface atau teks waktu MySQL ('Y-m-d H:i:s'). Nilai tak terbaca jadi null. */
    private static function time(mixed $raw, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($raw instanceof DateTimeImmutable) {
            return $raw;
        }
        if ($raw instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable(trim($raw), $now->getTimezone());
        } catch (Throwable) {
            return null;
        }

        return $parsed;
    }

    /** Nilai 1/'1'/true dianggap benar, sisanya salah. */
    private static function flag(mixed $raw): bool
    {
        return $raw === true || $raw === 1 || $raw === '1';
    }

    /**
     * Bilangan bulat positif dari konfigurasi, atau null bila bukan.
     * Kosong, nol, negatif, desimal, bukan angka, dan angka di luar jangkauan
     * integer PHP semuanya ditolak.
     */
    private static function positiveInt(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw >= 1 ? $raw : null;
        }
        if (!is_string($raw)) {
            return null;
        }

        $value = trim($raw);
        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        $hours = (int) $value;
        // Menjaga dari luapan: "999999999999999999999" tidak sama dengan PHP_INT_MAX.
        if ((string) $hours !== ltrim($value, '0')) {
            return null;
        }

        return $hours >= 1 ? $hours : null;
    }
}
