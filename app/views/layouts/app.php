<?php
/**
 * Signed-in layout: compact header, role sidebar, content.
 * @var string $content  @var string|null $active  sidebar key
 */
$user = Auth::user();
$role = $user['role'];
$active ??= '';

$menus = [
    'survivor' => [
        ['Utama', [
            ['dashboard', '/penyintas', 'home', 'Beranda saya'],
            ['laporan', '/penyintas/laporan', 'file', 'Laporan saya'],
            ['jadwal', '/penyintas/jadwal', 'calendar', 'Jadwal sesi'],
            ['notifikasi', '/notifikasi', 'bell', 'Notifikasi'],
            ['profil', '/akun', 'settings', 'Profil &amp; keamanan'],
        ]],
        ['Bantuan', [
            ['lapor', '/lapor', 'file-plus', 'Buat laporan baru'],
            ['edukasi', '/edukasi', 'book', 'Edukasi'],
            ['layanan', '/layanan', 'pin', 'Layanan bantuan'],
        ]],
    ],
    'companion' => [
        ['Kerja', [
            ['dashboard', '/pendamping', 'home', 'Ringkasan'],
            ['kasus', '/pendamping/kasus', 'inbox', 'Kasus saya'],
            ['slot', '/pendamping/slot', 'calendar', 'Jadwal &amp; slot'],
            ['notifikasi', '/notifikasi', 'bell', 'Notifikasi'],
            ['profil', '/akun', 'settings', 'Profil &amp; keamanan'],
        ]],
        ['Rujukan', [
            ['layanan', '/layanan', 'pin', 'Direktori layanan'],
            ['edukasi', '/edukasi', 'book', 'Materi edukasi'],
        ]],
    ],
    'admin' => [
        ['Kasus', [
            ['dashboard', '/admin', 'chart', 'Statistik'],
            ['antrean', '/admin/antrean', 'inbox', 'Antrean kasus'],
            ['pengguna', '/admin/pengguna', 'users', 'Pengguna'],
        ]],
        ['Konten', [
            ['artikel', '/admin/artikel', 'book', 'Artikel'],
            ['layanan', '/admin/layanan', 'building', 'Layanan'],
        ]],
        ['Keamanan', [
            ['audit', '/admin/audit', 'log', 'Audit log'],
            ['profil', '/akun', 'settings', 'Profil &amp; keamanan'],
        ]],
    ],
];
$roleLabel = Auth::ROLE_LABEL[$role];
$initials = initials($user['name']);
// Keluar cepat hanya untuk penyintas; halaman pendamping dan admin tidak memuatnya (Req 8.15).
$quickExit = $role === 'survivor' ? ($quickExit ?? 'icon') : 'none';
$liteLayout = lite();
// Sidebar counters (only shown when > 0).
$counts = ['notifikasi' => Notifier::unreadCount((int) $user['id'])];
if ($role === 'companion') {
    $counts['kasus'] = Report::activeCountFor((int) $user['id']);
}
if ($role === 'admin') {
    $counts['antrean'] = (int) Database::value("SELECT COUNT(*) FROM reports WHERE status = 'diterima'");
}
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?php include APP_PATH . '/views/partials/head.php'; ?>
</head>
<body class="app-body<?= $liteLayout ? ' is-lite' : '' ?>"<?= $liteLayout ? ' data-lite' : '' ?>>
<?php include APP_PATH . '/views/partials/icons.php'; ?>
<a class="skip-link" href="#konten"><?= te('a11y.skip') ?></a>
<header class="app-header">
  <div class="app-header-inner">
    <button class="icon-btn sidebar-toggle" type="button" aria-expanded="false" aria-controls="sidebar" data-sidebar-toggle>
      <?= icon('menu') ?><span class="sr-only">Buka navigasi</span>
    </button>
    <a class="wordmark" href="<?= e(url(Auth::homePath($user))) ?>" aria-label="Haven, ke beranda <?= e(mb_strtolower($roleLabel)) ?>">haven</a>
    <span class="role-chip"><?= e($roleLabel) ?></span>
    <div class="app-header-actions">
      <div class="dropdown" data-dropdown>
        <button class="icon-btn" type="button" aria-expanded="false" aria-controls="notif-panel" data-dropdown-toggle data-notif-bell data-endpoint="<?= e(url('/api/notifikasi')) ?>">
          <?= icon('bell') ?><span class="sr-only">Notifikasi</span>
        </button>
        <div class="dropdown-panel" id="notif-panel" hidden>
          <div class="dropdown-head"><strong>Notifikasi</strong><button type="button" class="link-btn" data-mark-read data-endpoint="<?= e(url('/api/notifikasi/baca')) ?>">Tandai semua dibaca</button></div>
          <ul class="notif-list" data-notif-list>
            <li><p class="small muted" style="padding:18px">Belum ada notifikasi.</p></li>
          </ul>
          <a class="dropdown-foot" href="<?= e(url('/notifikasi')) ?>">Lihat semua notifikasi <?= icon('arrow-right') ?></a>
        </div>
      </div>
      <div class="dropdown" data-dropdown>
        <button class="user-btn" type="button" aria-expanded="false" aria-controls="user-panel" data-dropdown-toggle>
          <span class="avatar" aria-hidden="true"><?= e($initials) ?></span><span class="user-name"><?= e($user['name']) ?></span><?= icon('chevron-down') ?>
        </button>
        <div class="dropdown-panel dropdown-sm" id="user-panel" hidden>
          <ul class="menu-list">
            <li><a href="<?= e(url('/akun')) ?>"><?= icon('settings') ?> Profil &amp; keamanan</a></li>
            <li><a href="<?= e(url('/')) ?>"><?= icon('globe') ?> Situs publik</a></li>
            <li>
              <form method="post" action="<?= e(url('/keluar')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="menu-btn"><?= icon('exit') ?> Keluar dari akun</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</header>
<?php if ($quickExit !== 'none') include APP_PATH . '/views/partials/quick-exit.php'; ?>
<?php include APP_PATH . '/views/partials/flash.php'; ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Navigasi <?= e(mb_strtolower($roleLabel)) ?>" data-sidebar>
    <?php foreach ($menus[$role] as [$label, $items]): ?>
      <div class="nav-group">
        <p class="nav-label"><?= e($label) ?></p>
        <ul>
          <?php foreach ($items as [$k, $href, $ic, $text]): ?>
            <li><a class="nav-item" href="<?= e(url($href)) ?>"<?= current_attr($k, $active) ?>><?= icon($ic) ?><span><?= $text ?></span><?php if (!empty($counts[$k])): ?><span class="nav-count"><?= (int) $counts[$k] ?><span class="sr-only"> baru</span></span><?php endif; ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
    <div class="sidebar-foot">
      <p><?= icon('lock') ?> Sesi otomatis berakhir setelah <?= Config::int('SESSION_IDLE_MINUTES', 30) ?> menit tanpa aktivitas.</p>
      <?php $prefsLight = true; include APP_PATH . '/views/partials/prefs.php'; ?>
    </div>
  </aside>
  <div class="sidebar-scrim" data-sidebar-scrim hidden></div>
  <main id="konten" tabindex="-1" class="app-main">
    <?= $content ?>
  </main>
</div>
</body>
</html>
