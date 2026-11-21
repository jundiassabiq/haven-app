<?php
declare(strict_types=1);

/**
 * Module 5: bookings. Booking runs in a transaction that locks the slot row (SELECT … FOR UPDATE on MySQL),
 * so two survivors clicking the same slot at the same time cannot both get it.
 */
final class Booking
{
    public const MAX_UPCOMING_PER_REPORT = 2;
    public const SURVIVOR_CANCEL_HOURS = 2;

    private static function lock(): string
    {
        return Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    /**
     * @throws DomainException with a user-facing message
     */
    public static function book(int $slotId, array $report, ?int $nowTs = null): int
    {
        $nowTs ??= time();
        if (!in_array($report['status'], CaseWorkflow::ACTIVE, true) || $report['assigned_to'] === null) {
            throw new DomainException('Jadwal bisa dipesan setelah pendamping ditugaskan.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $slot = Database::one('SELECT * FROM companion_slots WHERE id = ?' . self::lock(), [$slotId]);
            if (!$slot) {
                throw new DomainException('Slot ini sudah tidak tersedia.');
            }
            if ((int) $slot['companion_id'] !== (int) $report['assigned_to']) {
                throw new DomainException('Slot ini bukan milik pendampingmu.');
            }
            if (strtotime($slot['starts_at']) < $nowTs + 3600) {
                throw new DomainException('Slot ini sudah lewat atau dimulai kurang dari 1 jam lagi. Pilih waktu lain.');
            }
            $taken = (int) Database::value("SELECT COUNT(*) FROM bookings WHERE slot_id = ? AND status = 'aktif'", [$slotId]);
            if ($taken > 0) {
                throw new DomainException('Slot ini baru saja dipesan orang lain. Pilih waktu lain.');
            }
            $upcoming = (int) Database::value(
                "SELECT COUNT(*) FROM bookings b JOIN companion_slots s ON s.id = b.slot_id WHERE b.report_id = ? AND b.status = 'aktif' AND s.starts_at > ?",
                [(int) $report['id'], date('Y-m-d H:i:s', $nowTs)]
            );
            if ($upcoming >= self::MAX_UPCOMING_PER_REPORT) {
                throw new DomainException('Kamu sudah punya ' . self::MAX_UPCOMING_PER_REPORT . ' sesi mendatang. Batalkan salah satu dulu untuk memesan yang lain.');
            }
            $id = Database::insert(
                "INSERT INTO bookings (slot_id, report_id, status, created_at) VALUES (?, ?, 'aktif', ?)",
                [$slotId, (int) $report['id'], date('Y-m-d H:i:s', $nowTs)]
            );
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param 'survivor'|'companion' $by
     * @throws DomainException
     */
    public static function cancel(int $bookingId, string $by, ?int $nowTs = null): array
    {
        $nowTs ??= time();
        $b = self::find($bookingId);
        if (!$b || $b['status'] !== 'aktif') {
            throw new DomainException('Sesi ini sudah dibatalkan atau selesai.');
        }
        $start = strtotime($b['starts_at']);
        if ($start <= $nowTs) {
            throw new DomainException('Sesi yang sudah dimulai tidak bisa dibatalkan.');
        }
        if ($by === 'survivor' && $start - $nowTs < self::SURVIVOR_CANCEL_HOURS * 3600) {
            throw new DomainException('Sesi hanya bisa dibatalkan paling lambat ' . self::SURVIVOR_CANCEL_HOURS . ' jam sebelum mulai. Kirim pesan ke pendampingmu.');
        }
        Database::run("UPDATE bookings SET status = 'dibatalkan', cancelled_by = ?, cancelled_at = ? WHERE id = ? AND status = 'aktif'", [$by, date('Y-m-d H:i:s', $nowTs), $bookingId]);
        return $b;
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT b.*, s.starts_at, s.duration_min, s.mode, s.location, s.companion_id FROM bookings b JOIN companion_slots s ON s.id = b.slot_id WHERE b.id = ?',
            [$id]
        );
    }

    /** All bookings of a report, newest session first. */
    public static function forReport(int $reportId): array
    {
        return Database::all(
            'SELECT b.*, s.starts_at, s.duration_min, s.mode, s.location, u.name AS companion_name
             FROM bookings b JOIN companion_slots s ON s.id = b.slot_id LEFT JOIN users u ON u.id = s.companion_id
             WHERE b.report_id = ? ORDER BY s.starts_at DESC',
            [$reportId]
        );
    }

    /** Bookings across all reports of a survivor account. */
    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT b.*, s.starts_at, s.duration_min, s.mode, s.location, u.name AS companion_name, r.code AS report_code
             FROM bookings b JOIN companion_slots s ON s.id = b.slot_id JOIN reports r ON r.id = b.report_id LEFT JOIN users u ON u.id = s.companion_id
             WHERE r.user_id = ? ORDER BY s.starts_at DESC',
            [$userId]
        );
    }

    /** Split into upcoming (active, in the future) and past/cancelled. */
    public static function split(array $rows, ?int $nowTs = null): array
    {
        $nowTs ??= time();
        $up = [];
        $past = [];
        foreach ($rows as $r) {
            if ($r['status'] === 'aktif' && strtotime($r['starts_at']) + (int) $r['duration_min'] * 60 > $nowTs) {
                $up[] = $r;
            } else {
                $past[] = $r;
            }
        }
        return [array_reverse($up), $past];
    }

    public static function stateLabel(array $b, ?int $nowTs = null): string
    {
        $nowTs ??= time();
        if ($b['status'] === 'dibatalkan') {
            return $b['cancelled_by'] === 'companion' ? 'Dibatalkan pendamping' : 'Dibatalkan olehmu';
        }
        return strtotime($b['starts_at']) + (int) $b['duration_min'] * 60 <= $nowTs ? 'Selesai' : 'Dikonfirmasi';
    }
}
