<?php
declare(strict_types=1);

/*
 * Permanently deletes accounts whose deletion was requested more than 7 days ago,
 * together with their reports (messages, notes, bookings cascade) and uploaded files.
 *   php bin/purge-accounts.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$cutoff = date('Y-m-d H:i:s', time() - 7 * 86400);
$users = Database::all('SELECT id FROM users WHERE delete_requested_at IS NOT NULL AND delete_requested_at < ?', [$cutoff]);

foreach ($users as $u) {
    $files = Database::all(
        'SELECT a.stored_name FROM report_attachments a JOIN reports r ON r.id = a.report_id WHERE r.user_id = ?',
        [$u['id']]
    );
    foreach ($files as $f) {
        @unlink(STORAGE_PATH . '/uploads/' . basename($f['stored_name']));
    }
    Database::run('DELETE FROM reports WHERE user_id = ?', [$u['id']]);
    Database::run('DELETE FROM users WHERE id = ?', [$u['id']]);
    Audit::log('account.purged', 'user', (string) $u['id'], 'Dihapus permanen setelah 7 hari', null, 'Sistem');
}
echo count($users) . " akun dihapus permanen.\n";
