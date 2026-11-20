<?php
/**
 * Kepala halaman publik.
 *
 * Tautan "Lewati ke konten" adalah elemen pertama yang bisa difokuskan di setiap
 * halaman dan menuju `<main id="konten" tabindex="-1">`, sehingga fokus papan
 * tombol benar-benar pindah ke konten utama saat tautan itu ditekan (Req 8.2).
 * Label navigasi memakai kamus supaya ikut bahasa aktif (Req 8.5).
 *
 * @var string $active
 */
$active ??= '';
$nav = [
    ['lapor', '/lapor', 'nav.report'],
    ['lacak', '/lacak', 'nav.track'],
    ['edukasi', '/edukasi', 'nav.edu'],
    ['layanan', '/layanan', 'nav.services'],
    ['tentang', '/tentang', 'nav.about'],
];
$user = Auth::user();
$links = '';
foreach ($nav as [$k, $href, $key]) {
    $links .= '<li><a href="' . e(url($href)) . '"' . current_attr($k, $active) . '>' . te($key) . '</a></li>';
}
$accountHref = $user ? url(Auth::homePath($user)) : url('/masuk');
$accountLabel = t($user ? 'nav.account' : 'nav.login');
?>
<a class="skip-link" href="#konten"><?= te('a11y.skip') ?></a>
<header class="site-header" data-header>
  <div class="container header-inner">
    <a class="wordmark" href="<?= e(url('/')) ?>" aria-label="<?= te('a11y.home') ?>">haven</a>
    <nav class="primary-nav" aria-label="<?= te('a11y.nav.primary') ?>"><ul><?= $links ?></ul></nav>
    <div class="header-actions">
      <a class="link-quiet" href="<?= e($accountHref) ?>"><?= e($accountLabel) ?></a>
      <a class="btn btn-yellow btn-sm" href="<?= e(url('/lapor')) ?>"><?= te('nav.cta') ?></a>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" data-menu-toggle
              data-label-open="<?= te('a11y.menu.open') ?>" data-label-close="<?= te('a11y.menu.close') ?>">
        <span class="sr-only"><?= te('a11y.menu.open') ?></span>
        <?= icon('menu', 'icon icon-menu') ?><?= icon('close', 'icon icon-close') ?>
      </button>
    </div>
  </div>
  <nav class="mobile-nav" id="mobile-nav" aria-label="<?= te('a11y.nav.mobile') ?>" hidden data-mobile-nav>
    <ul><?= $links ?><li><a href="<?= e($accountHref) ?>"><?= e($accountLabel) ?></a></li></ul>
    <a class="btn btn-yellow btn-block" href="<?= e(url('/lapor')) ?>"><?= te('nav.cta') ?></a>
  </nav>
</header>
