<?php
declare(strict_types=1);

/** Module 6: one encrypted thread per report between the survivor and the assigned companion. */
final class Message
{
    public const MAX_LEN = 2000;

    public static function create(int $reportId, string $role, ?int $senderId, string $body): int
    {
        return Database::insert(
            'INSERT INTO messages (report_id, sender_role, sender_id, body_enc, created_at) VALUES (?, ?, ?, ?, ?)',
            [$reportId, $role, $senderId, Crypto::enc($body), now()]
        );
    }

    public static function system(int $reportId, string $body): void
    {
        self::create($reportId, 'system', null, $body);
    }

    /** Messages after an id, oldest first, decrypted. */
    public static function since(int $reportId, int $afterId): array
    {
        $rows = Database::all(
            'SELECT m.id, m.sender_role, m.sender_id, m.body_enc, m.read_at, m.created_at, u.name AS sender_name
             FROM messages m LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.report_id = ? AND m.id > ? ORDER BY m.id LIMIT 500',
            [$reportId, $afterId]
        );
        foreach ($rows as &$r) {
            $r['body'] = Crypto::dec($r['body_enc']) ?? '[Pesan tidak dapat didekripsi]';
            unset($r['body_enc']);
        }
        return $rows;
    }

    /** The viewer has seen everything the other side (and the system) sent. */
    public static function markReadFor(int $reportId, string $viewerRole): void
    {
        $other = $viewerRole === 'survivor' ? ['companion', 'system'] : ['survivor', 'system'];
        Database::run(
            'UPDATE messages SET read_at = ? WHERE report_id = ? AND sender_role IN (?, ?) AND read_at IS NULL',
            [now(), $reportId, $other[0], $other[1]]
        );
    }

    /** Highest id of the viewer's own messages already read by the other side. */
    public static function readUpto(int $reportId, string $viewerRole): int
    {
        return (int) Database::value(
            'SELECT COALESCE(MAX(id), 0) FROM messages WHERE report_id = ? AND sender_role = ? AND read_at IS NOT NULL',
            [$reportId, $viewerRole]
        );
    }

    public static function unreadFor(int $reportId, string $viewerRole): int
    {
        $from = $viewerRole === 'survivor' ? 'companion' : 'survivor';
        return (int) Database::value('SELECT COUNT(*) FROM messages WHERE report_id = ? AND sender_role = ? AND read_at IS NULL', [$reportId, $from]);
    }
}
