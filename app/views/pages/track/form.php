<?php
/**
 * Halaman lacak laporan (Modul 3) dengan bahasa dan aksesibilitas (Req 8).
 *
 * Nomor darurat tampil langsung di panel samping, dalam kedua bahasa dan kedua
 * mode, tanpa perlu menekan apa pun (Req 8.12). Galat kode dan PIN terhubung ke
 * kolomnya lewat `field_aria`, dan kolom tidak valid pertama mendapat
 * `autofocus` supaya fokus pindah ke sana tanpa JavaScript (Req 8.3).
 *
 * @var string|null $formError
 */
$firstInvalid = first_invalid_field(['kode', 'pin']);
?>
<main id="konten" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1 class="h2"><?= te('track.title') ?></h1>
      <p class="lede"><?= te('track.lede') ?></p>
    </div>
  </section>

  <section class="section-sm">
    <div class="container grid-2">
      <form class="panel panel-body form-stack" style="padding:clamp(24px,4vw,40px)" method="post" action="<?= e(url('/lacak')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <?php if (!empty($formError)): ?>
          <div class="alert alert-danger" role="alert"><?= icon('alert') ?><div><?= e($formError) ?></div></div>
        <?php endif; ?>
        <div class="field">
          <label class="label" for="kode"><?= te('track.code.label') ?></label>
          <input class="input mono" id="kode" name="kode" placeholder="HV-XXXXXX" autocomplete="off" spellcheck="false" maxlength="9" required pattern="HV-[A-Z0-9]{6}" data-uppercase value="<?= e(old('kode')) ?>" data-msg="Masukkan kode laporanmu." data-msg-pattern="Kode diawali HV- lalu 6 huruf atau angka, contoh HV-9B2X6P."<?= autofocus_if($firstInvalid, 'kode') ?><?= field_aria('kode') ?>>
          <?= field_error('kode') ?>
        </div>
        <div class="field">
          <label class="label" for="pin"><?= te('track.pin.label') ?></label>
          <div class="input-group">
            <input class="input mono" id="pin" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" required pattern="[0-9]{6}" data-digits data-msg="Masukkan PIN 6 digit." data-msg-pattern="PIN terdiri dari 6 angka."<?= autofocus_if($firstInvalid, 'pin') ?><?= field_aria('pin') ?>>
            <button class="reveal" type="button" data-reveal="pin" aria-pressed="false"><?= icon('eye') ?><span class="sr-only"><?= te('a11y.reveal') ?></span></button>
          </div>
          <?= field_error('pin') ?>
        </div>
        <div class="alert alert-info">
          <?= icon('shield') ?>
          <div><?= te('track.lock.note') ?></div>
        </div>
        <button class="btn btn-ink btn-lg" type="submit"><?= te('track.submit') ?> <?= icon('arrow-right') ?></button>
      </form>

      <aside class="stack">
        <?php include APP_PATH . '/views/partials/emergency.php'; ?>
        <div class="panel panel-body stack-sm">
          <h2 class="h4"><?= te('track.forgot.title') ?></h2>
          <p class="small" style="color:var(--ink-2)"><?= te('track.forgot.body') ?></p>
          <a class="text-link" href="<?= e(url('/lapor')) ?>"><?= te('track.forgot.cta') ?> <?= icon('arrow-right') ?></a>
        </div>
        <div class="panel panel-body stack-sm">
          <h2 class="h4"><?= te('track.account.title') ?></h2>
          <p class="small" style="color:var(--ink-2)"><?= te('track.account.body') ?></p>
          <a class="text-link" href="<?= e(url('/masuk')) ?>"><?= te('track.account.cta') ?> <?= icon('arrow-right') ?></a>
        </div>
      </aside>
    </div>
  </section>
</main>
