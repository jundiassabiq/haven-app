<?php
/**
 * Form lapor (Modul 2) dengan bahasa, mode ringan, dan aksesibilitas (Req 8).
 *
 * Mode penuh menampilkan empat langkah yang dibuka satu per satu oleh `app.js`.
 * Mode ringan membuka keempat langkah sekaligus: tidak ada skrip yang menata
 * langkah, tidak ada panel ringkasan yang perlu JavaScript, dan tombol Kirim
 * langsung terlihat, sehingga laporan tetap bisa dikirim dengan JavaScript mati
 * dan halaman konfirmasinya sama saja (Req 8.11). Browser tanpa JavaScript di
 * mode penuh mendapat perlakuan yang sama lewat blok `<noscript>` di `head.php`.
 *
 * Setiap pesan galat terhubung ke kolomnya lewat `field_aria` (`aria-describedby`
 * dan `aria-invalid`), dan kolom tidak valid pertama mendapat `autofocus`
 * sehingga fokus pindah ke sana tanpa JavaScript (Req 8.3).
 *
 * @var bool $accountAllowed  @var string $defaultIdentity
 */
$errs = form_errors();
$identity = old('identitas', $defaultIdentity);
$lite = lite();
// Urutan kolom seperti yang dibaca pengguna, dipakai untuk memilih satu autofocus.
$firstInvalid = first_invalid_field([
    'peran', 'jenis', 'usia', 'bahaya', 'kronologi', 'berlangsung', 'tanggal', 'tempat',
    'provinsi', 'kota', 'pelaku', 'identitas', 'email', 'wa', 'kontak_korban', 'bukti', 'setuju',
]);
$steps = ['report.step1', 'report.step2', 'report.step3', 'report.step4'];
// Teks pilihan dari kamus, dengan cadangan label di `ReportIntake` bila kuncinya belum ada.
$reporterKeys = ['korban' => 'report.reporter.victim', 'saksi' => 'report.reporter.witness'];
$ageKeys = ['ya' => 'report.age.yes', 'tidak' => 'report.age.no', 'tidak-yakin' => 'report.age.unsure'];
?>
<main id="konten" tabindex="-1">
  <section class="section-sm" style="background:var(--cream)">
    <div class="container grid-2">
      <form class="panel panel-body" style="padding:clamp(24px,4vw,48px)" method="post" action="<?= e(url('/lapor')) ?>" enctype="multipart/form-data"<?= $lite ? '' : ' novalidate data-stepper' ?> aria-label="<?= te('report.form.aria') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= Upload::MAX_BYTES ?>">
        <?php if (!$lite): ?>
          <ol class="stepper" aria-label="<?= te('report.steps.aria') ?>">
            <?php foreach ($steps as $key): ?><li><span><?= te($key) ?></span></li><?php endforeach; ?>
          </ol>
          <p class="step-count" data-step-count aria-live="polite"><?= te('report.steps.count', ['sekarang' => 1, 'total' => count($steps)]) ?></p>
        <?php endif; ?>

        <?php if ($errs): ?>
          <div class="alert alert-danger" role="alert" style="margin-bottom:28px">
            <?= icon('alert') ?>
            <div><strong><?= te('common.errors.title') ?></strong><?= te('common.errors.body') ?></div>
          </div>
        <?php endif; ?>

        <!-- STEP 1 -->
        <section data-step>
          <h1 class="step-title"><?= te('report.what.title') ?></h1>
          <p class="step-lede"><?= te('report.what.lede') ?></p>
          <div class="step-body">
            <fieldset class="field">
              <legend class="label"><?= te('report.reporter.legend') ?></legend>
              <div class="choice-grid choice-grid-2">
                <?php $first = true; foreach (ReportIntake::REPORTER_LABELS as $k => $label): ?>
                  <label class="choice"><input type="radio" name="peran" value="<?= e($k) ?>"<?= checked(old('peran') === $k) ?><?= $first ? ' required data-msg="Pilih peranmu: korban atau saksi. Pilihan ini wajib."' : '' ?><?= $first ? autofocus_if($firstInvalid, 'peran') : '' ?><?= field_aria('peran') ?>><span class="choice-body"><span class="choice-title"><?= e(t_or($reporterKeys[$k] ?? '', $label)) ?></span></span></label>
                <?php $first = false; endforeach; ?>
              </div>
              <?= field_error('peran') ?>
            </fieldset>

            <fieldset class="field">
              <legend class="label"><?= te('report.category.legend') ?></legend>
              <div class="choice-grid choice-grid-2">
                <?php $first = true; foreach (Report::categoryChoices() as $k => $label): ?>
                  <label class="choice"><input type="radio" name="jenis" value="<?= e($k) ?>"<?= checked(old('jenis') === $k) ?><?= $first ? ' required data-msg="Pilih jenis kejadian, atau pilih &quot;Belum yakin&quot;."' : '' ?><?= $first ? autofocus_if($firstInvalid, 'jenis') : '' ?><?= field_aria('jenis') ?>><span class="choice-body"><span class="choice-title"><?= e($label) ?></span><span class="choice-desc"><?= e(Report::CATEGORY_DESC[$k]) ?></span></span></label>
                <?php $first = false; endforeach; ?>
              </div>
              <?= field_error('jenis') ?>
            </fieldset>

            <fieldset class="field">
              <legend class="label"><?= te('report.age.legend') ?></legend>
              <p class="hint" id="usia-hint"><?= te('report.age.hint') ?></p>
              <div class="choice-grid">
                <?php $first = true; foreach (ReportIntake::AGE_LABELS as $k => $label): ?>
                  <label class="choice"><input type="radio" name="usia" value="<?= e($k) ?>"<?= checked(old('usia') === $k) ?><?= $first ? ' required data-msg="Pilih salah satu jawaban tentang usia korban. Jawaban ini wajib."' : '' ?><?= $first ? autofocus_if($firstInvalid, 'usia') : '' ?><?= field_aria('usia', 'usia-hint') ?>><span class="choice-body"><span class="choice-title"><?= e(t_or($ageKeys[$k] ?? '', $label)) ?></span></span></label>
                <?php $first = false; endforeach; ?>
              </div>
              <?= field_error('usia') ?>
            </fieldset>

            <fieldset class="field">
              <legend class="label"><?= te('report.danger.legend') ?></legend>
              <div class="choice-grid choice-grid-2">
                <label class="choice"><input type="radio" name="bahaya" value="ya" required<?= checked(old('bahaya') === 'ya') ?><?= autofocus_if($firstInvalid, 'bahaya') ?><?= field_aria('bahaya') ?>><span class="choice-body"><span class="choice-title"><?= te('report.danger.yes') ?></span></span></label>
                <label class="choice"><input type="radio" name="bahaya" value="tidak"<?= checked(old('bahaya') === 'tidak') ?><?= field_aria('bahaya') ?>><span class="choice-body"><span class="choice-title"><?= te('report.danger.no') ?></span></span></label>
              </div>
              <?= field_error('bahaya') ?>
            </fieldset>
            <div class="alert alert-danger" data-show-when="bahaya=ya" role="alert">
              <?= icon('phone') ?>
              <div><strong><?= te('report.danger.alert.title') ?></strong><?= service_limit_html() ?> <?= te('report.danger.alert.body') ?></div>
            </div>
          </div>
        </section>

        <!-- STEP 2 -->
        <section data-step<?= $lite ? '' : ' hidden' ?>>
          <h2 class="step-title"><?= te('report.story.title') ?></h2>
          <p class="step-lede"><?= te('report.story.lede') ?></p>
          <div class="step-body">
            <div class="field">
              <label class="label" for="kronologi"><?= te('report.story.label') ?></label>
              <p class="hint" id="kronologi-hint"><?= te('report.story.hint') ?></p>
              <textarea class="textarea" id="kronologi" name="kronologi" rows="8" minlength="30" maxlength="5000" required data-counter="kronologi-count" data-msg="Ceritakan sedikit tentang kejadiannya, minimal satu atau dua kalimat."<?= autofocus_if($firstInvalid, 'kronologi') ?><?= field_aria('kronologi', 'kronologi-hint') ?>><?= e(old('kronologi')) ?></textarea>
              <span class="counter" id="kronologi-count" aria-live="off"></span>
              <?= field_error('kronologi') ?>
            </div>
            <fieldset class="field">
              <legend class="label"><?= te('report.ongoing.legend') ?></legend>
              <div class="choice-grid">
                <?php $first = true; foreach (Report::ONGOING as $k => $label): ?>
                  <label class="choice"><input type="radio" name="berlangsung" value="<?= e($k) ?>"<?= $first ? ' required' : '' ?><?= checked(old('berlangsung') === $k) ?><?= $first ? autofocus_if($firstInvalid, 'berlangsung') : '' ?><?= field_aria('berlangsung') ?>><span class="choice-body"><span class="choice-title"><?= e($label) ?></span></span></label>
                <?php $first = false; endforeach; ?>
              </div>
              <?= field_error('berlangsung') ?>
            </fieldset>
          </div>
        </section>

        <!-- STEP 3 -->
        <section data-step<?= $lite ? '' : ' hidden' ?>>
          <h2 class="step-title"><?= te('report.where.title') ?></h2>
          <p class="step-lede"><?= te('report.where.lede') ?></p>
          <div class="step-body">
            <div class="field-row">
              <div class="field">
                <label class="label" for="tanggal"><?= te('report.date.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
                <input class="input" type="date" id="tanggal" name="tanggal" max="<?= date('Y-m-d') ?>" value="<?= e(old('tanggal')) ?>"<?= autofocus_if($firstInvalid, 'tanggal') ?><?= field_aria('tanggal') ?>>
                <?= field_error('tanggal') ?>
              </div>
              <div class="field">
                <label class="label" for="tempat"><?= te('report.place.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
                <select class="select" id="tempat" name="tempat"<?= autofocus_if($firstInvalid, 'tempat') ?><?= field_aria('tempat') ?>>
                  <option value=""><?= te('report.place.choose') ?></option>
                  <?php foreach (Report::PLACES as $p): ?><option<?= selected(old('tempat'), $p) ?>><?= e($p) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('tempat') ?>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label class="label" for="provinsi"><?= te('report.province.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
                <select class="select" id="provinsi" name="provinsi"<?= autofocus_if($firstInvalid, 'provinsi') ?><?= field_aria('provinsi') ?>>
                  <option value=""><?= te('report.province.choose') ?></option>
                  <?php foreach (Report::PROVINCES as $p): ?><option<?= selected(old('provinsi'), $p) ?>><?= e($p) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('provinsi') ?>
              </div>
              <div class="field">
                <label class="label" for="kota"><?= te('report.city.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
                <input class="input" id="kota" name="kota" maxlength="80" placeholder="<?= te('report.city.placeholder') ?>" autocomplete="off" value="<?= e(old('kota')) ?>"<?= autofocus_if($firstInvalid, 'kota') ?><?= field_aria('kota') ?>>
                <?= field_error('kota') ?>
              </div>
            </div>
            <div class="field">
              <label class="label" for="pelaku"><?= te('report.relation.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
              <select class="select" id="pelaku" name="pelaku"<?= autofocus_if($firstInvalid, 'pelaku') ?><?= field_aria('pelaku') ?>>
                <option value=""><?= te('common.choose') ?></option>
                <?php foreach (Report::RELATIONS as $p): ?><option<?= selected(old('pelaku'), $p) ?>><?= e($p) ?></option><?php endforeach; ?>
              </select>
              <?= field_error('pelaku') ?>
            </div>
          </div>
        </section>

        <!-- STEP 4 -->
        <section data-step<?= $lite ? '' : ' hidden' ?>>
          <h2 class="step-title"><?= te('report.contact.title') ?></h2>
          <p class="step-lede"><?= te('report.contact.lede') ?></p>
          <div class="step-body">
            <fieldset class="field">
              <legend class="label"><?= te('report.identity.legend') ?></legend>
              <div class="choice-grid choice-grid-2">
                <label class="choice"><input type="radio" name="identitas" value="anonim"<?= checked($identity !== 'akun') ?><?= autofocus_if($firstInvalid, 'identitas') ?><?= field_aria('identitas') ?>><span class="choice-body"><span class="choice-title"><?= te('report.identity.anon') ?></span><span class="choice-desc"><?= te('report.identity.anon.desc') ?></span></span></label>
                <?php if ($accountAllowed): ?>
                  <label class="choice"><input type="radio" name="identitas" value="akun"<?= checked($identity === 'akun') ?><?= field_aria('identitas') ?>><span class="choice-body"><span class="choice-title"><?= te('report.identity.account', ['nama' => Auth::user()['name']]) ?></span><span class="choice-desc"><?= te('report.identity.account.desc') ?></span></span></label>
                <?php endif; ?>
              </div>
              <?= field_error('identitas') ?>
            </fieldset>
            <?php if (!$accountAllowed): ?>
              <div class="alert alert-info">
                <?= icon('info') ?>
                <div><?= t('report.identity.signin', ['masuk' => e(url('/masuk')), 'daftar' => e(url('/daftar'))]) ?></div>
              </div>
            <?php endif; ?>

            <fieldset class="field">
              <legend class="label"><?= te('report.channel.legend') ?></legend>
              <div class="check-list">
                <label class="check"><input type="checkbox" checked disabled> <span><?= te('report.channel.inapp') ?></span></label>
                <label class="check"><input type="checkbox" name="kontak_email" value="1"<?= checked(old('kontak_email') === '1') ?>> <span><?= te('report.channel.email') ?></span></label>
                <label class="check"><input type="checkbox" name="kontak_wa" value="1"<?= checked(old('kontak_wa') === '1') ?>> <span><?= te('report.channel.wa') ?></span></label>
              </div>
            </fieldset>
            <div class="field" data-show-when="kontak_email=1">
              <label class="label" for="email"><?= te('report.email.label') ?></label>
              <input class="input" type="email" id="email" name="email" autocomplete="email" placeholder="nama@email.com" data-cond-required value="<?= e(old('email')) ?>" data-msg="Tulis email, atau hapus centang Email."<?= autofocus_if($firstInvalid, 'email') ?><?= field_aria('email', 'email-hint') ?>>
              <p class="hint" id="email-hint"><?= te('report.email.hint') ?></p>
              <?= field_error('email') ?>
            </div>
            <div class="field" data-show-when="kontak_wa=1">
              <label class="label" for="wa"><?= te('report.wa.label') ?></label>
              <input class="input" type="tel" id="wa" name="wa" inputmode="tel" placeholder="08xx-xxxx-xxxx" pattern="[0-9+\- ]{9,16}" data-msg-pattern="Tulis nomor 9 sampai 16 digit, contoh 0812-3456-7890." data-cond-required value="<?= e(old('wa')) ?>" data-msg="Tulis nomor WhatsApp, atau hapus centang WhatsApp."<?= autofocus_if($firstInvalid, 'wa') ?><?= field_aria('wa', 'wa-hint') ?>>
              <p class="hint" id="wa-hint"><?= te('report.wa.hint') ?></p>
              <?= field_error('wa') ?>
            </div>

            <div class="field" data-show-when="peran=saksi">
              <label class="label" for="kontak_korban"><?= te('report.victim_contact.label') ?> <span class="opt"><?= te('common.optional') ?></span></label>
              <input class="input" id="kontak_korban" name="kontak_korban" maxlength="<?= Report::VICTIM_CONTACT_MAX ?>" autocomplete="off" value="<?= e(old('kontak_korban')) ?>"<?= autofocus_if($firstInvalid, 'kontak_korban') ?><?= field_aria('kontak_korban', 'kontak_korban-hint') ?>>
              <p class="hint" id="kontak_korban-hint"><?= te('report.victim_contact.hint', ['maks' => Report::VICTIM_CONTACT_MAX]) ?></p>
              <?= field_error('kontak_korban') ?>
            </div>

            <div class="field">
              <span class="label" id="bukti-label"><?= te('report.files.label') ?> <span class="opt"><?= te('common.optional') ?></span></span>
              <label class="dropzone" data-dropzone="bukti-list">
                <input type="file" name="bukti[]" multiple accept=".jpg,.jpeg,.png,.pdf" aria-labelledby="bukti-label"<?= autofocus_if($firstInvalid, 'bukti') ?><?= field_aria('bukti', 'bukti-hint') ?>>
                <?= icon('upload') ?>
                <strong><?= te('report.files.drop') ?></strong>
                <span class="hint" id="bukti-hint"><?= te('report.files.hint', ['file' => Upload::MAX_FILES]) ?></span>
              </label>
              <ul class="file-list" id="bukti-list" aria-live="polite"></ul>
              <?= field_error('bukti') ?>
            </div>

            <?php if (!$lite): ?>
              <div class="panel panel-cream panel-body">
                <h3 class="h4" style="margin-bottom:14px"><?= te('report.review.title') ?></h3>
                <dl class="kv">
                  <div><dt><?= te('report.summary.reporter') ?></dt><dd data-summary="peran"></dd></div>
                  <div><dt><?= te('report.summary.category') ?></dt><dd data-summary="jenis"></dd></div>
                  <div><dt><?= te('report.summary.age') ?></dt><dd data-summary="usia"></dd></div>
                  <div><dt><?= te('report.summary.area') ?></dt><dd data-summary="provinsi"></dd></div>
                  <div><dt><?= te('report.summary.date') ?></dt><dd data-summary="tanggal"></dd></div>
                  <div><dt><?= te('report.summary.relation') ?></dt><dd data-summary="pelaku"></dd></div>
                  <div class="full"><dt><?= te('report.summary.story') ?></dt><dd data-summary="kronologi"></dd></div>
                </dl>
              </div>
            <?php endif; ?>

            <div class="field">
              <label class="check">
                <input type="checkbox" id="setuju" name="setuju" value="1" required data-msg="Centang ini agar kami bisa memproses laporanmu."<?= checked(old('setuju') === '1') ?><?= autofocus_if($firstInvalid, 'setuju') ?><?= field_aria('setuju') ?>>
                <span><?= te('report.consent.label') ?></span>
              </label>
              <?= field_error('setuju') ?>
            </div>
          </div>
        </section>

        <div class="step-actions">
          <?php if (!$lite): ?>
            <button class="btn btn-ghost" type="button" data-prev hidden><?= icon('arrow-left') ?> <?= te('common.back') ?></button>
          <?php endif; ?>
          <span class="spacer"></span>
          <?php if (!$lite): ?>
            <button class="btn btn-ink" type="button" data-next><?= te('common.next') ?> <?= icon('arrow-right') ?></button>
          <?php endif; ?>
          <button class="btn btn-yellow" type="submit" data-submit<?= $lite ? '' : ' hidden' ?>><span><?= te('report.submit') ?></span> <?= icon('send') ?></button>
        </div>
      </form>

      <aside class="stack">
        <div class="panel panel-body stack-sm">
          <h2 class="h4"><?= te('report.aside.title') ?></h2>
          <ul class="stack-sm small" style="color:var(--ink-2)">
            <li class="row" style="flex-wrap:nowrap;align-items:flex-start"><?= icon('clock') ?><span><?= te('report.aside.time') ?></span></li>
            <li class="row" style="flex-wrap:nowrap;align-items:flex-start"><?= icon('lock') ?><span><?= te('report.aside.privacy') ?></span></li>
            <li class="row" style="flex-wrap:nowrap;align-items:flex-start"><?= icon('exit') ?><span><?= te('report.aside.exit') ?></span></li>
          </ul>
        </div>
        <?php include APP_PATH . '/views/partials/emergency.php'; ?>
      </aside>
    </div>
  </section>
</main>
