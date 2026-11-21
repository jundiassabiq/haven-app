<?php
declare(strict_types=1);

/** Internal notes by the companion. Never shown to the survivor or admin. Encrypted. */
final class CaseNote
{
    public static function add(int $reportId, int $authorId, ?string $title, string $body): void
    {
        Database::run(
            'INSERT INTO case_notes (report_id, author_id, title, body_enc, created_at) VALUES (?, ?, ?, ?, ?)',
            [$reportId, $authorId, $title ?: null, Crypto::enc($body), now()]
        );
    }

    public static function forReport(int $reportId): array
    {
        $rows = Database::all(
            'SELECT n.*, u.name AS author_name FROM case_notes n LEFT JOIN users u ON u.id = n.author_id WHERE n.report_id = ? ORDER BY n.id DESC',
            [$reportId]
        );
        foreach ($rows as &$r) {
            $r['body'] = Crypto::dec($r['body_enc']) ?? '[Tidak dapat didekripsi]';
        }
        return $rows;
    }
}
