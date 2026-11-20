<?php
declare(strict_types=1);

/**
 * Perencana pengingat kasus (Req 6.3 sampai 6.6, 6.15) — murni, tanpa DB,
 * tanpa superglobal, dan tanpa time(). Baris kasus, kunci pengingat yang sudah
 * ada, waktu sekarang, konfigurasi SLA, dan daftar admin aktif semuanya masuk
 * sebagai parameter. `bin/reminders.php` yang menulis ke database.
 *
 * Dua tipe pengingat:
 *
 * - `danger_unanswered` (Req 6.3): kasus yang ditandai dalam bahaya dan masih
 *   berstatus `diterima` lebih lama dari SLA-nya sejak laporan dibuat.
 *   Penerima: semua admin aktif.
 * - `no_reply` (Req 6.4): pesan penyintas terakhir belum dibalas pendamping
 *   lebih lama dari SLA kasus itu. Penerima: pendamping tertuju dan semua admin
 *   aktif, atau semua admin aktif saja bila kasus belum punya pendamping.
 *
 * Satu pengingat per kasus per tipe per periode SLA (Req 6.5): periode dihitung
 * `Sla::periodIndex` dari saat kasus pertama kali melewati batas. Kunci
 * `"{case_id}:{type}:{period}"` dibandingkan dengan daftar `$sent`, dan di
 * database kunci yang sama dijaga `UNIQUE (report_id, type, period)` pada tabel
 * `report_reminders` plus `INSERT IGNORE`, sehingga dua run berdekatan tidak
 * menambah baris maupun notifikasi (Req 6.6).
 *
 * Bacaan Req 6.3 dan 6.4 yang dipakai di sini, supaya satu kasus tidak memicu
 * dua pengingat sekaligus untuk hal yang sama:
 *
 * - `no_reply` hanya berjalan bila ada pesan penyintas. Laporan baru yang belum
 *   pernah dibalas bukan "pesan penyintas terakhir yang menunggu balasan"; itu
 *   wilayah Req 6.3 untuk kasus bahaya. Kasus biasa yang belum dibalas tetap
 *   terlihat lewat lencana "Lewat SLA" (`Sla::isOverdue`, Req 6.8).
 * - Pesan `system` (mis. "pendamping bergabung") bukan balasan pendamping, jadi
 *   pemanggil hanya boleh mengisi `last_reply_at` dari pesan `companion`.
 * - Kasus `selesai` dan `ditutup` tidak lagi menunggu balasan, jadi tidak
 *   mendapat pengingat `no_reply`.
 *
 * Req 6.15: pengingat dikirim sebagai notifikasi dalam aplikasi. Penerima yang
 * dikembalikan selalu id akun staf (admin atau pendamping), dan perencana tidak
 * pernah mengembalikan alamat email, sehingga penyintas anonim tanpa email
 * tidak mungkin dibuatkan email oleh skrip pengingat.
 *
 * Req 10.2: rencana ini tidak memuat kronologi, PIN, atau isi pesan. Teks
 * notifikasi disusun pemanggil dari `LABELS` dan kode laporan saja.
 */
final class ReminderPlanner
{
    public const TYPE_DANGER = 'danger_unanswered';
    public const TYPE_NO_REPLY = 'no_reply';

    /** Urutan tipe di dalam satu kasus, sekaligus nilai enum `report_reminders.type`. */
    public const TYPES = [self::TYPE_DANGER, self::TYPE_NO_REPLY];

    /** Judul notifikasi tanpa kronologi dan tanpa isi pesan (Req 10.2). */
    public const LABELS = [
        self::TYPE_DANGER => 'Kasus dalam bahaya belum ditinjau',
        self::TYPE_NO_REPLY => 'Pesan penyintas belum dibalas',
    ];

    /** Status `diterima` yang menjadi syarat tipe `danger_unanswered` (Req 6.3). */
    public const STATUS_NEW = 'diterima';

    /** Status yang masih menunggu balasan pendamping. */
    public const OPEN_STATUSES = ['diterima', 'ditinjau', 'didampingi', 'dirujuk'];

    /**
     * Pengingat yang perlu dibuat pada waktu $now.
     *
     * Urutan hasil mengikuti urutan kasus masukan, dan di dalam satu kasus
     * mengikuti `TYPES`, supaya keluaran bisa diuji apa adanya.
     *
     * Kasus yang tidak punya penerima sama sekali (tidak ada admin aktif dan
     * tidak ada pendamping tertuju) dilewati: tidak ada yang bisa diberi tahu,
     * jadi periode itu tidak boleh ditandai sudah dikirim.
     *
     * @param list<array<string,mixed>> $cases baris kasus dengan kunci `id`,
     *        `in_danger`, `status`, `assigned_to`, `created_at`, dan kolom
     *        turunan `last_survivor_at` serta `last_reply_at`
     * @param list<string> $sent kunci `key()` pengingat yang sudah pernah dibuat
     * @param array<string,mixed> $env konfigurasi SLA untuk `Sla::hours`
     * @param list<int> $adminIds id admin aktif
     * @return list<array{case_id:int, type:string, period:int, sla_hours:int, recipients:list<int>}>
     */
    public static function plan(array $cases, array $sent, DateTimeImmutable $now, array $env = [], array $adminIds = []): array
    {
        $admins = self::ids($adminIds);
        $already = array_fill_keys(array_values(array_filter($sent, 'is_string')), true);

        $plan = [];
        foreach ($cases as $case) {
            if (!is_array($case)) {
                continue;
            }
            $caseId = self::caseId($case);
            if ($caseId === null) {
                continue;
            }

            foreach (self::TYPES as $type) {
                $item = $type === self::TYPE_DANGER
                    ? self::danger($case, $caseId, $now, $env, $admins)
                    : self::noReply($case, $caseId, $now, $env, $admins);

                if ($item === null || isset($already[self::key($caseId, $type, $item['period'])])) {
                    continue;
                }
                $plan[] = $item;
            }
        }

        return $plan;
    }

    /** Kunci idempotensi satu pengingat, sama dengan `UNIQUE (report_id, type, period)`. */
    public static function key(int $caseId, string $type, int $period): string
    {
        return $caseId . ':' . $type . ':' . $period;
    }

    /**
     * Req 6.3: kasus bahaya yang masih `diterima` lewat SLA sejak dibuat.
     * Penerima: semua admin aktif.
     *
     * @param array<string,mixed> $case
     * @param array<string,mixed> $env
     * @param list<int> $admins
     * @return array{case_id:int, type:string, period:int, sla_hours:int, recipients:list<int>}|null
     */
    private static function danger(array $case, int $caseId, DateTimeImmutable $now, array $env, array $admins): ?array
    {
        if (!self::flag($case['in_danger'] ?? null) || self::status($case) !== self::STATUS_NEW) {
            return null;
        }

        return self::item($caseId, self::TYPE_DANGER, self::time($case['created_at'] ?? null, $now), $now, Sla::hours(true, $env), $admins);
    }

    /**
     * Req 6.4: pesan penyintas terakhir belum dibalas lewat SLA.
     * Penerima: pendamping tertuju lebih dulu, lalu semua admin aktif.
     *
     * @param array<string,mixed> $case
     * @param array<string,mixed> $env
     * @param list<int> $admins
     * @return array{case_id:int, type:string, period:int, sla_hours:int, recipients:list<int>}|null
     */
    private static function noReply(array $case, int $caseId, DateTimeImmutable $now, array $env, array $admins): ?array
    {
        $status = self::status($case);
        if ($status !== '' && !in_array($status, self::OPEN_STATUSES, true)) {
            return null;
        }

        $survivorAt = self::time($case['last_survivor_at'] ?? null, $now);
        if ($survivorAt === null) {
            return null;
        }

        $replyAt = self::time($case['last_reply_at'] ?? null, $now);
        if ($replyAt !== null && $replyAt >= $survivorAt) {
            // Pesan penyintas terakhir sudah dibalas: tidak ada yang ditunggu.
            return null;
        }

        $companion = self::id($case['assigned_to'] ?? null);
        $recipients = $companion === null ? $admins : self::ids(array_merge([$companion], $admins));
        $sla = Sla::hours(self::flag($case['in_danger'] ?? null), $env);

        return self::item($caseId, self::TYPE_NO_REPLY, $survivorAt, $now, $sla, $recipients);
    }

    /**
     * Satu baris rencana, atau null bila batas belum terlewat (Req 6.5) atau
     * tidak ada penerima.
     *
     * Batas terlewat bila selisihnya lebih dari SLA, bukan sama dengan, sesuai
     * "more than its Reply_SLA" pada Req 6.3 dan 6.4 dan sama seperti
     * `Sla::isOverdue`.
     *
     * @param list<int> $recipients
     * @return array{case_id:int, type:string, period:int, sla_hours:int, recipients:list<int>}|null
     */
    private static function item(int $caseId, string $type, ?DateTimeImmutable $since, DateTimeImmutable $now, int $sla, array $recipients): ?array
    {
        if ($since === null || $recipients === []) {
            return null;
        }

        $exceededAt = $since->add(new DateInterval('PT' . max(1, $sla) . 'H'));
        if ($now->getTimestamp() <= $exceededAt->getTimestamp()) {
            return null;
        }

        return [
            'case_id' => $caseId,
            'type' => $type,
            'period' => Sla::periodIndex($exceededAt, $now, $sla),
            'sla_hours' => $sla,
            'recipients' => $recipients,
        ];
    }

    /** Id laporan, atau null bila tidak terbaca sebagai id positif. */
    private static function caseId(array $case): ?int
    {
        return self::id($case['id'] ?? $case['report_id'] ?? null);
    }

    /** Id positif dari nilai database, atau null. */
    private static function id(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (is_string($raw) && preg_match('/^[0-9]+$/', trim($raw)) === 1) {
            $id = (int) trim($raw);
            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Daftar id positif tanpa duplikat, urutan masukan dipertahankan.
     *
     * @return list<int>
     */
    private static function ids(array $raw): array
    {
        $ids = [];
        foreach ($raw as $value) {
            $id = self::id($value);
            if ($id !== null && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** Status kasus dalam huruf kecil, atau string kosong bila tidak terbaca. */
    private static function status(array $case): string
    {
        $raw = $case['status'] ?? null;

        return is_string($raw) ? strtolower(trim($raw)) : '';
    }

    /**
     * Menerima DateTimeInterface atau teks waktu MySQL ('Y-m-d H:i:s'). Teks
     * tanpa zona waktu dibaca pada zona $now, sama seperti `Sla`. Nilai tak
     * terbaca jadi null.
     */
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
            return new DateTimeImmutable(trim($raw), $now->getTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    /** Nilai 1/'1'/true dianggap benar; kosong, '0', dan null salah. */
    private static function flag(mixed $raw): bool
    {
        return $raw === true || $raw === 1 || $raw === '1';
    }
}
