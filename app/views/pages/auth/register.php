<main id="konten" tabindex="-1" class="auth">
  <div class="auth-media">
    <?php if (!lite()): ?><div class="media"><img src="<?= asset('img/sunset.jpg') ?>" alt="" width="1200" height="1600"></div><?php endif; ?>
    <p class="auth-quote">Laporan anonim yang dulu kamu kirim bisa ditautkan ke akun ini.</p>
  </div>
  <div class="auth-panel">
    <div class="auth-form">
      <h1 class="h2">Buat akun</h1>
      <p>Dengan akun, kamu tidak perlu mengingat kode dan PIN. Semua laporan, pesan, dan jadwalmu tersimpan di satu dasbor.</p>
      <form class="form-stack" method="post" action="<?= e(url('/daftar')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="name">Nama panggilan</label>
          <input class="input" id="name" name="name" autocomplete="nickname" required maxlength="40" value="<?= e(old('name')) ?>" data-msg="Tulis nama panggilan. Boleh nama samaran."<?= field_aria('name', 'name-hint') ?>>
          <p class="hint" id="name-hint">Boleh nama samaran. Hanya pendampingmu yang melihat nama ini.</p>
          <?= field_error('name') ?>
        </div>
        <div class="field">
          <label class="label" for="email">Email</label>
          <input class="input" type="email" id="email" name="email" autocomplete="email" required value="<?= e(old('email')) ?>" data-msg="Masukkan email untuk masuk dan pemulihan akun."<?= field_aria('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <label class="label" for="password">Kata sandi</label>
          <div class="input-group">
            <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required data-msg="Buat kata sandi minimal 8 karakter."<?= field_aria('password', 'pw-hint') ?>>
            <button class="reveal" type="button" data-reveal="password" aria-pressed="false"><?= icon('eye') ?><span class="sr-only">Tampilkan</span></button>
          </div>
          <p class="hint" id="pw-hint">Minimal 8 karakter. Tiga atau empat kata acak, seperti "payung meja kopi", lebih sulit ditebak daripada satu kata pendek yang rumit.</p>
          <?= field_error('password') ?>
        </div>
        <div class="field">
          <label class="label" for="password_confirm">Ulangi kata sandi</label>
          <input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required data-match="password" data-msg="Ketik ulang kata sandimu."<?= field_aria('password_confirm') ?>>
          <?= field_error('password_confirm') ?>
        </div>
        <div class="field">
          <label class="check"><input type="checkbox" id="agree" name="agree" value="1" required data-msg="Centang untuk melanjutkan."<?= old('agree') ? ' checked' : '' ?><?= field_aria('agree') ?>> <span>Saya paham laporan dan pesan saya disimpan terenkripsi, dan saya bisa menghapus akun kapan saja.</span></label>
          <?= field_error('agree') ?>
        </div>
        <button class="btn btn-ink btn-lg btn-block" type="submit">Buat akun</button>
      </form>
      <p class="auth-alt">Sudah punya akun? <a href="<?= e(url('/masuk')) ?>">Masuk</a>.</p>
    </div>
  </div>
</main>
