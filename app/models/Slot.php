<?php
declare(strict_types=1);

/** Module 5: companion availability. Plain SQL so tests can run it on SQLite. */
final class Slot
{
    public const MODES = ['suara' => 'Panggilan suara', 'video' => 'Video', 'tatap' => 'Tatap muka'];
    public const DURATIONS = [30, 45, 60];

    /** @return string|null error */
    public static function validate(string $date, string $time, int $duration, string $mode, string $location, int $nowTs): ?string
    {
        $d = DateTime::createFromFormat('!Y-m-d H:i', "$date $time");
        if (!$d || $d->format('Y-m-d H:i') !== "$date $time") {
            return 'Tanggal atau jam tidak valid.';
        }
        if ($d->getTimestamp() < $nowTs + 3600) {
            return 'Slot harus dimulai setidaknya 1 jam dari sekarang.';
        }
        if ($d->getTimestamp() > $nowTs + 60 * 86400) {
            return 'Slot hanya bisa dibuka untuk 60 hari ke depan.';
        }
        if (!in_array($duration, self::DURATIONS, true)) {
            return 'Durasi tidak tersedia.';
        }
        if (!isset(self::MODES[$mode])) {
            return 'Cara bertemu tidak tersedia.';
        }
        if ($mode === 'tatap' && trim($location) === '') {
            return 'Tulis lokasi pertemuan tatap muka.';
        }
        return null;
    }

    /** True when [start, start+duration) overlaps another slot of the same companion. */
    public static function overlaps(int $companionId, string $startsAt, int $duration): bool
    {
        $start = strtotime($startsAt);
        $end = $start + $duration * 60;
        $rows = Database::all(
            'SELECT starts_at, duration_min FROM companion_slots WHERE companion_id = ? AND starts_at > ? AND starts_at < ?',
            [$companionId, date('Y-m-d H:i:s', $start - 3 * 3600), date('Y-m-d H:i:s', $end)]
        );
        foreach ($rows as $r) {
            $s = strtotime($r['starts_at']);
            if ($s < $end && $start < $s + (int) $r['duration_min'] * 60) {
                return true;
            }
        }
        return false;
    }

    public static function create(int $companionId, string $startsAt, int $duration, string $mode, ?string $location): int
    {
        return Database::insert(
            'INSERT INTO companion_slots (companion_id, starts_at, duration_min, mode, location, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$companionId, $startsAt, $duration, $mode, $mode === 'tatap' ? $location : null, now()]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM companion_slots WHERE id = ?', [$id]);
    }

    /** Companion's upcoming slots with the active booking (if any). */
    public static function upcomingFor(int $companionId): array
    {
        return Database::all(
            "SELECT s.*, b.id AS booking_id, r.code AS report_code
             FROM companion_slots s
             LEFT JOIN bookings b ON b.slot_id = s.id AND b.status = 'aktif'
             LEFT JOIN reports r ON r.id = b.report_id
             WHERE s.companion_id = ? AND s.starts_at >= ?
             ORDER BY s.starts_at",
            [$companionId, date('Y-m-d H:i:s', time() - 3600)]
        );
    }

    /** Free slots of a companion that start at least an hour from now. */
    public static function availableFor(int $companionId): array
    {
        return Database::all(
            "SELECT s.* FROM companion_slots s
             WHERE s.companion_id = ? AND s.starts_at >= ?
               AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = s.id AND b.status = 'aktif')
             ORDER BY s.starts_at LIMIT 30",
            [$companionId, date('Y-m-d H:i:s', time() + 3600)]
        );
    }

    /** Deletes a free slot of this companion. @return bool false when booked or not theirs */
    public static function deleteFree(int $id, int $companionId): bool
    {
        $st = Database::run(
            "DELETE FROM companion_slots WHERE id = ? AND companion_id = ? AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = companion_slots.id AND b.status = 'aktif')",
            [$id, $companionId]
        );
        return $st->rowCount() === 1;
    }

    public static function label(array $slot): string
    {
        $t = strtotime($slot['starts_at']);
        return ID_DAYS[(int) date('w', $t)] . ', ' . date('j', $t) . ' ' . ID_MONTHS[(int) date('n', $t)] . ' · ' . date('H.i', $t);
    }
}
