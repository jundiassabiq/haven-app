<?php
/**
 * Keluar cepat (Req 8.15 sampai 8.20).
 *
 * `$quickExit` menentukan bentuknya, dan layout yang mengisinya:
 * - `labeled`: ikon dengan label terlihat, untuk form lapor, halaman kasus, dan
 *   halaman lacak (Req 8.17).
 * - `icon`: ikon 44x44 saja; labelnya muncul saat kursor di atasnya dan saat
 *   fokus papan tombol (Req 8.18).
 * Layout pendamping dan admin tidak memuat partial ini sama sekali (Req 8.15).
 *
 * Elemennya tautan, bukan tombol, supaya tetap bisa dipakai saat JavaScript
 * mati (Req 8.20). Dengan JavaScript, `app.js` mencegah navigasi biasa dan
 * memakai `location.replace`, sehingga tombol Kembali tidak membawa pengguna
 * balik ke Haven (Req 8.16). Nama aksesibelnya datang dari `aria-label`, jadi
 * sama dalam kedua bentuk, termasuk saat labelnya belum terlihat (Req 8.19).
 */
$quickExitLabeled = ($quickExit ?? 'icon') === 'labeled';
$quickExitLabel = t('exit.button');
?>
<a class="quick-exit <?= $quickExitLabeled ? 'quick-exit-labeled' : 'quick-exit-icon' ?>"
   href="https://www.google.com/search?q=prakiraan+cuaca+hari+ini"
   rel="noopener noreferrer nofollow"
   title="<?= e($quickExitLabel . '. ' . t('exit.hint')) ?>"
   aria-label="<?= e($quickExitLabel) ?>"
   data-quick-exit>
  <?= icon('exit') ?><span class="quick-exit-label"><?= e($quickExitLabel) ?></span>
</a>
