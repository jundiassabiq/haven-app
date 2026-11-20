<?php
declare(strict_types=1);

/** All routes. POST requests are CSRF-checked in App::run before any handler runs. */
return static function (Router $r): void {
    // Public pages
    $r->get('/', [PageController::class, 'home']);
    $r->get('/tentang', [PageController::class, 'about']);
    $r->get('/tips-aman', [PageController::class, 'safety']);

    // Bahasa dan mode tampilan: endpoint publik, CSRF di App::run, rate limit di controller (Req 8.6, 8.13)
    $r->post('/bahasa', [PrefController::class, 'setLang']);
    $r->post('/mode', [PrefController::class, 'setMode']);

    // Module 1: authentication
    $r->get('/masuk', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/masuk', [AuthController::class, 'login'], ['guest']);
    $r->get('/daftar', [AuthController::class, 'showRegister'], ['guest']);
    $r->post('/daftar', [AuthController::class, 'register'], ['guest']);
    $r->post('/keluar', [AuthController::class, 'logout']);
    $r->get('/lupa-password', [AuthController::class, 'showForgot'], ['guest']);
    $r->post('/lupa-password', [AuthController::class, 'forgot'], ['guest']);
    $r->get('/reset-password', [AuthController::class, 'showReset']);
    $r->post('/reset-password', [AuthController::class, 'reset']);

    // Module 1: account (all roles)
    $r->get('/akun', [AccountController::class, 'show'], ['auth']);
    $r->post('/akun/profil', [AccountController::class, 'updateProfile'], ['auth']);
    $r->post('/akun/kata-sandi', [AccountController::class, 'changePassword'], ['auth']);
    $r->post('/akun/keluarkan-sesi', [AccountController::class, 'logoutOthers'], ['auth']);
    $r->post('/akun/hapus', [AccountController::class, 'requestDeletion'], ['role:survivor']);

    // Role home pages
    $r->get('/penyintas', [DashboardController::class, 'survivor'], ['role:survivor']);
    $r->get('/pendamping', [DashboardController::class, 'companion'], ['role:companion']);
    $r->get('/admin', [DashboardController::class, 'admin'], ['role:admin']);

    // Module 1 (admin): staff accounts
    $r->get('/admin/pengguna', [AdminUserController::class, 'index'], ['role:admin']);
    $r->post('/admin/pengguna', [AdminUserController::class, 'store'], ['role:admin']);
    $r->post('/admin/pengguna/{id}/status', [AdminUserController::class, 'setStatus'], ['role:admin']);
    $r->post('/admin/pengguna/{id}/terlatih-anak', [AdminUserController::class, 'setChildTrained'], ['role:admin']);
    $r->post('/admin/pengguna/{id}/reset', [AdminUserController::class, 'sendReset'], ['role:admin']);

    // Module 2: reporting
    $r->get('/lapor', [ReportController::class, 'create']);
    $r->post('/lapor', [ReportController::class, 'store']);
    $r->get('/lapor/terkirim', [ReportController::class, 'sent']);

    // Module 3: tracking and the survivor case page (code+PIN session or account owner)
    $r->get('/lacak', [TrackController::class, 'form']);
    $r->post('/lacak', [TrackController::class, 'track']);
    $r->get('/kasus/{code}', [CaseController::class, 'show']);
    $r->post('/kasus/{code}/tutup', [TrackController::class, 'close']);
    $r->get('/lampiran/{id}', [CaseController::class, 'attachment']);
    $r->get('/penyintas/laporan', [SurvivorController::class, 'reports'], ['role:survivor']);
    $r->post('/penyintas/laporan/tautkan', [TrackController::class, 'link'], ['role:survivor']);

    // Module 4: case management
    $r->get('/admin/antrean', [AdminCaseController::class, 'queue'], ['role:admin']);
    $r->get('/admin/kasus/{code}', [AdminCaseController::class, 'show'], ['role:admin']);
    $r->post('/admin/kasus/{code}/tugaskan', [AdminCaseController::class, 'assign'], ['role:admin']);
    $r->post('/admin/kasus/{code}/tutup', [AdminCaseController::class, 'close'], ['role:admin']);
    $r->get('/pendamping/kasus', [CompanionCaseController::class, 'index'], ['role:companion']);
    $r->get('/pendamping/kasus/{code}', [CompanionCaseController::class, 'show'], ['role:companion']);
    $r->post('/pendamping/kasus/{code}/status', [CompanionCaseController::class, 'updateStatus'], ['role:companion']);
    // Persetujuan korban untuk laporan saksi, syarat sebelum status "dirujuk" (Req 5.7, 5.8).
    $r->post('/pendamping/kasus/{code}/persetujuan', [CompanionCaseController::class, 'recordConsent'], ['role:companion']);
    $r->post('/pendamping/kasus/{code}/catatan', [CompanionCaseController::class, 'addNote'], ['role:companion']);

    // Module 5: scheduling
    $r->post('/kasus/{code}/jadwal', [CaseController::class, 'book']);
    $r->post('/kasus/{code}/jadwal/{id}/batal', [CaseController::class, 'cancel']);
    $r->get('/penyintas/jadwal', [SurvivorController::class, 'schedule'], ['role:survivor']);
    $r->get('/pendamping/slot', [SlotController::class, 'index'], ['role:companion']);
    $r->post('/pendamping/slot', [SlotController::class, 'store'], ['role:companion']);
    $r->post('/pendamping/slot/{id}/hapus', [SlotController::class, 'delete'], ['role:companion']);
    $r->post('/pendamping/sesi/{id}/batal', [SlotController::class, 'cancelBooking'], ['role:companion']);

    // Module 6: secure messages (JSON)
    $r->get('/api/kasus/{code}/pesan', [MessageController::class, 'list']);
    $r->post('/api/kasus/{code}/pesan', [MessageController::class, 'send']);

    // Module 9: service directory
    $r->get('/layanan', [ServiceController::class, 'index']);
    $r->get('/api/layanan', [ServiceController::class, 'api']);
    $r->get('/admin/layanan', [AdminServiceController::class, 'index'], ['role:admin']);
    $r->get('/admin/layanan/baru', [AdminServiceController::class, 'create'], ['role:admin']);
    $r->post('/admin/layanan', [AdminServiceController::class, 'store'], ['role:admin']);
    $r->get('/admin/layanan/{id}', [AdminServiceController::class, 'edit'], ['role:admin']);
    $r->post('/admin/layanan/{id}', [AdminServiceController::class, 'update'], ['role:admin']);
    $r->post('/admin/layanan/{id}/hapus', [AdminServiceController::class, 'delete'], ['role:admin']);

    // Module 11: notifications and audit
    $r->get('/notifikasi', [NotificationController::class, 'index'], ['auth']);
    $r->post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'], ['auth']);
    $r->get('/notifikasi/{id}', [NotificationController::class, 'open'], ['auth']);
    $r->get('/api/notifikasi', [NotificationController::class, 'apiList'], ['auth']);
    $r->post('/api/notifikasi/baca', [NotificationController::class, 'apiReadAll'], ['auth']);
    $r->get('/admin/audit', [AuditController::class, 'index'], ['role:admin']);

    // Module 7: education
    $r->get('/edukasi', [ArticleController::class, 'index']);
    $r->get('/edukasi/{slug}', [ArticleController::class, 'show']);
    $r->get('/admin/artikel', [AdminArticleController::class, 'index'], ['role:admin']);
    $r->get('/admin/artikel/baru', [AdminArticleController::class, 'create'], ['role:admin']);
    $r->post('/admin/artikel', [AdminArticleController::class, 'store'], ['role:admin']);
    $r->get('/admin/artikel/{id}', [AdminArticleController::class, 'edit'], ['role:admin']);
    $r->post('/admin/artikel/{id}', [AdminArticleController::class, 'update'], ['role:admin']);
    $r->get('/admin/artikel/{id}/pratinjau', [AdminArticleController::class, 'preview'], ['role:admin']);
    $r->post('/admin/artikel/{id}/hapus', [AdminArticleController::class, 'delete'], ['role:admin']);

    // Module 10: statistics (JSON for the admin charts) and the anonymous aggregate export
    $r->get('/api/statistik', [DashboardController::class, 'adminApi'], ['role:admin']);
    // Peran non-admin ditolak 403 di middleware, sebelum satu byte CSV dibuat (Req 7.8).
    $r->post('/admin/statistik/ekspor', [StatsController::class, 'export'], ['role:admin']);
};
