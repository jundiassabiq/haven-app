<?php
declare(strict_types=1);

/** Module 11 (admin): read-only audit log. */
final class AuditController
{
    public function index(): void
    {
        $f = ['q' => mb_substr(input('q'), 0, 60), 'aksi' => input('aksi')];
        $pg = paginate(AuditLog::count($f), 30);
        View::show('pages/admin/audit', [
            'title' => 'Audit log | Admin Haven',
            'active' => 'audit',
            'rows' => AuditLog::search($f, $pg['per'], $pg['offset']),
            'pg' => $pg,
            'f' => $f,
        ], 'app');
    }
}
