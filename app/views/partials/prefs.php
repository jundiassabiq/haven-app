<?php
/**
 * Pemilih bahasa dan mode tampilan (Req 8.6, 8.13).
 *
 * Dua formulir POST biasa, jadi keduanya bekerja tanpa JavaScript. Token CSRF
 * diperiksa `App::run`, dan `PrefController` membatasi jumlah pergantian per IP.
 * Kolom "kembali" membawa pengguna ke halaman yang sedang dibuka.
 *
 * Tombol pilihan yang sedang aktif ditandai `aria-current` dan diberi keterangan
 * tambahan untuk pembaca layar, supaya keadaannya tidak hanya terlihat dari
 * warna (Req 8.1).
 */
$prefLang = lang();
$prefMode = display_mode();
$prefBack = current_path();
$prefModes = [Mode::FULL => ['mode.full', 'mode.switch.to_full'], Mode::LITE => ['mode.lite', 'mode.switch.to_lite']];
?>
<div class="prefs<?= !empty($prefsLight) ? ' prefs-light' : '' ?>">
  <form class="pref-group" method="post" action="<?= e(url('/bahasa')) ?>" aria-label="<?= te('lang.switch') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="kembali" value="<?= e($prefBack) ?>">
    <fieldset>
      <legend class="pref-label"><?= te('lang.label') ?></legend>
      <div class="pref-opts">
        <?php foreach (I18n::LANGS as $code => $name): ?>
          <?php $on = $code === $prefLang; ?>
          <button class="pref-btn" type="submit" name="bahasa" value="<?= e($code) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($name) ?><?php if ($on): ?><span class="sr-only"> (<?= te('prefs.active') ?>)</span><?php endif; ?></button>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </form>

  <form class="pref-group" method="post" action="<?= e(url('/mode')) ?>" aria-label="<?= te('mode.label') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="kembali" value="<?= e($prefBack) ?>">
    <fieldset>
      <legend class="pref-label"><?= te('mode.label') ?></legend>
      <div class="pref-opts">
        <?php foreach ($prefModes as $value => [$key, $switchKey]): ?>
          <?php $on = $value === $prefMode; ?>
          <button class="pref-btn" type="submit" name="mode" value="<?= e($value) ?>" title="<?= te($switchKey) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= te($key) ?><?php if ($on): ?><span class="sr-only"> (<?= te('prefs.active') ?>)</span><?php endif; ?></button>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </form>

  <p class="pref-hint"><?= te(lite_by_header() ? 'mode.lite.auto' : 'mode.lite.hint') ?></p>
</div>
