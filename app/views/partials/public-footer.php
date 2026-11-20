<?php
/**
 * Kaki halaman publik.
 *
 * Teksnya memakai kamus supaya ikut bahasa aktif (Req 8.5). Baris paling bawah
 * memuat pernyataan batas layanan dari `service_limit_html()`, bukan kalimat
 * "tidak dipantau 24 jam" yang dulu, sehingga pengguna tahu kapan laporannya
 * dibalas dan ke mana menelepon bila keadaan mendesak (Req 9.6).
 */
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <a class="wordmark wordmark-light" href="<?= e(url('/')) ?>" aria-label="<?= te('a11y.home') ?>">haven</a>
      <p class="footer-emergency"><?= te('emergency.title') ?> <a href="tel:112" class="mono">112</a> · SAPA <a href="tel:129" class="mono">129</a></p>
    </div>
    <div class="footer-cols">
      <nav aria-label="<?= te('footer.services') ?>"><h2 class="footer-h"><?= te('footer.services') ?></h2><ul>
        <li><a href="<?= e(url('/lapor')) ?>"><?= te('nav.cta') ?></a></li>
        <li><a href="<?= e(url('/lacak')) ?>"><?= te('nav.track') ?></a></li>
        <li><a href="<?= e(url('/layanan')) ?>"><?= te('footer.directory') ?></a></li>
      </ul></nav>
      <nav aria-label="<?= te('footer.learn') ?>"><h2 class="footer-h"><?= te('footer.learn') ?></h2><ul>
        <li><a href="<?= e(url('/edukasi')) ?>"><?= te('nav.edu') ?></a></li>
        <li><a href="<?= e(url('/tips-aman')) ?>"><?= te('footer.safety_tips') ?></a></li>
      </ul></nav>
      <nav aria-label="<?= te('footer.about') ?>"><h2 class="footer-h"><?= te('footer.about') ?></h2><ul>
        <li><a href="<?= e(url('/tentang')) ?>"><?= te('footer.about_sdg') ?></a></li>
        <li><a href="<?= e(url('/masuk')) ?>"><?= te('nav.login') ?></a></li>
        <li><a href="<?= e(url('/daftar')) ?>"><?= te('nav.register') ?></a></li>
      </ul></nav>
      <div><h2 class="footer-h"><?= te('footer.privacy') ?></h2>
        <p class="footer-note"><?= te('footer.privacy_note') ?></p>
      </div>
    </div>
    <?php include APP_PATH . '/views/partials/prefs.php'; ?>
    <div class="footer-bottom">
      <p><?= te('footer.credit', ['tahun' => date('Y')]) ?></p>
      <p class="footer-limit"><?= service_limit_html() ?></p>
    </div>
  </div>
</footer>
