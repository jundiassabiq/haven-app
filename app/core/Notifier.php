<?php
declare(strict_types=1);

/**
 * In-app notifications (Module 11). Titles never contain message or report content.
 * Recipients: a user account (user_id), or an anonymous survivor (user_id NULL + report_id, read on the case page).
 */
final class Notifier
{
    public const TYPES = ['status' => 'Status', 'pesan' => 'Pesan', 'jadwal' => 'Jadwal', 'tugas' => 'Penugasan', 'rujukan' => 'Rujukan', 'laporan' => 'Laporan baru', 'pengingat' => 'Pengingat'];

    public static function send(?int $userId, ?int $reportId, string $type, string $title, string $link): void
    {
        Database::run(
            'INSERT INTO notifications (user_id, report_id, type, title, link, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $reportId, $type, mb_substr($title, 0, 160), $link, now()]
        );
    }

    public static function user(int $userId, ?int $reportId, string $type, string $title, string $link): void
    {
        self::send($userId, $reportId, $type, $title, $link);
    }

    /** To the survivor of a report: their account if it has one, otherwise the anonymous case inbox. */
    public static function survivor(array $report, string $type, string $title, string $anchor = ''): void
    {
        $uid = $report['user_id'] !== null ? (int) $report['user_id'] : null;
        self::send($uid, (int) $report['id'], $type, $title, '/kasus/' . $report['code'] . $anchor);
    }

    public static function admins(?int $reportId, string $type, string $title, string $link): void
    {
        foreach (Database::all("SELECT id FROM users WHERE role = 'admin' AND is_active = 1") as $a) {
            self::send((int) $a['id'], $reportId, $type, $title, $link);
        }
    }

    /** Skip if the recipient already has an unread notification of this type for this report (avoids one per message). */
    public static function hasUnread(?int $userId, int $reportId, string $type): bool
    {
        return (bool) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE user_id <=> ? AND report_id = ? AND type = ? AND read_at IS NULL',
            [$userId, $reportId, $type]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public static function forUser(int $userId, int $limit = 100): array
    {
        return Database::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId]);
    }

    /** Survivor-side notifications for one report (anonymous inbox or the owner's account). */
    public static function forSurvivor(array $report, int $limit = 10): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE report_id = ? AND user_id <=> ? ORDER BY id DESC LIMIT ' . max(1, $limit),
            [(int) $report['id'], $report['user_id']]
        );
    }

    public static function markSurvivorRead(array $report): void
    {
        Database::run('UPDATE notifications SET read_at = ? WHERE report_id = ? AND user_id <=> ? AND read_at IS NULL', [now(), (int) $report['id'], $report['user_id']]);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM notifications WHERE id = ?', [$id]);
    }

    public static function markRead(int $id): void
    {
        Database::run('UPDATE notifications SET read_at = ? WHERE id = ? AND read_at IS NULL', [now(), $id]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $userId]);
    }

    /** "10 menit lalu", "kemarin", or a date. */
    public static function ago(string $datetime): string
    {
        $d = time() - strtotime($datetime);
        return match (true) {
            $d < 60 => 'baru saja',
            $d < 3600 => floor($d / 60) . ' menit lalu',
            $d < 86400 => floor($d / 3600) . ' jam lalu',
            $d < 172800 => 'kemarin',
            default => id_date($datetime),
        };
    }
}
