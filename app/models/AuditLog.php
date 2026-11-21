<?php
declare(strict_types=1);

/** Read side of the audit log (admin page). Writes go through Audit::log. */
final class AuditLog
{
    public const GROUPS = [
        'view' => ['Membuka', ['report.view', 'report.triage', 'attachment.view', 'report.track']],
        'update' => ['Mengubah', ['report.status', 'report.assign', 'report.urgency', 'report.consent', 'note.create', 'user.create', 'user.activate', 'user.deactivate', 'user.child_trained', 'service.save', 'service.delete', 'account.update', 'account.password']],
        'denied' => ['Akses ditolak', ['access.denied', 'auth.failed']],
        'auth' => ['Masuk/keluar', ['auth.login', 'auth.logout', 'auth.register', 'auth.reset', 'auth.reset_request']],
    ];

    public const LABELS = [
        'report.create' => 'Laporan dibuat', 'report.track' => 'Membuka laporan dengan kode + PIN', 'report.view' => 'Membuka detail kasus (kronologi didekripsi)',
        'report.triage' => 'Membuka data pemilahan', 'report.status' => 'Mengubah status', 'report.assign' => 'Menugaskan pendamping',
        'report.urgency' => 'Mengubah urgensi', 'report.link' => 'Menautkan laporan ke akun', 'report.consent' => 'Mencatat persetujuan korban', 'note.create' => 'Menambah catatan internal',
        'attachment.view' => 'Membuka bukti', 'access.denied' => 'Akses ditolak', 'auth.failed' => 'Gagal masuk', 'auth.login' => 'Masuk',
        'auth.logout' => 'Keluar', 'auth.register' => 'Mendaftar', 'auth.reset' => 'Mengatur ulang kata sandi', 'auth.reset_request' => 'Minta tautan reset',
        'account.update' => 'Mengubah profil', 'account.password' => 'Mengganti kata sandi', 'account.sessions' => 'Mengeluarkan sesi lain',
        'account.delete_request' => 'Minta hapus akun', 'account.purged' => 'Akun dihapus permanen', 'user.create' => 'Membuat akun',
        'user.activate' => 'Mengaktifkan akun', 'user.deactivate' => 'Menonaktifkan akun', 'user.reset_link' => 'Mengirim tautan reset',
        'user.child_trained' => 'Mengubah penanda terlatih kasus anak',
        'message.send' => 'Mengirim pesan', 'booking.create' => 'Memesan sesi', 'booking.cancel' => 'Membatalkan sesi',
        'slot.create' => 'Membuka slot', 'slot.delete' => 'Menghapus slot', 'service.save' => 'Menyimpan layanan', 'service.delete' => 'Menghapus layanan',
        'article.save' => 'Menyimpan artikel', 'article.delete' => 'Menghapus artikel',
    ];

    private static function where(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (!empty($f['aksi']) && isset(self::GROUPS[$f['aksi']])) {
            $acts = self::GROUPS[$f['aksi']][1];
            $w[] = 'action IN (' . implode(',', array_fill(0, count($acts), '?')) . ')';
            array_push($p, ...$acts);
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(object_ref LIKE ? OR actor_label LIKE ? OR detail LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return [implode(' AND ', $w), $p];
    }

    public static function count(array $f): int
    {
        [$w, $p] = self::where($f);
        return (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE $w", $p);
    }

    public static function search(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::where($f);
        return Database::all("SELECT * FROM audit_logs WHERE $w ORDER BY id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $p);
    }

    public static function forObject(string $type, string $ref, int $limit = 10): array
    {
        return Database::all('SELECT * FROM audit_logs WHERE object_type = ? AND object_ref = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$type, $ref]);
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
