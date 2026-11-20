<?php /** @var bool $sent */ ?>
<main id="konten" tabindex="-1" class="auth">
  <div class="auth-media">
    <?php if (!lite()): ?><div class="media"><img src="<?= asset('img/reaching-hands.jpg') ?>" alt="" width="1600" height="1067"></div><?php endif; ?>
  </div>
  <div class="auth-panel">
    <div class="auth-form">
      <a class="back-link" href="<?= e(url('/masuk')) ?>"><?= icon('arrow-left') ?> Kembali ke halaman masuk</a>
      <h1 class="h2" style="margin-top:24px">Lupa kata sandi?</h1>
      <p>Kami kirim tautan ke email akunmu. Tautannya berlaku <?= PasswordReset::RESET_MINUTES ?> menit dan hanya bisa dipakai sekali.</p>

      <?php if ($sent): ?>
        <div class="alert alert-success" style="margin-top:32px" role="status">
          <?= icon('mail') ?>
          <div><strong>Cek kotak masukmu.</strong>Jika email itu terdaftar, tautannya sudah terkirim. Kami tidak memberi tahu apakah email itu terdaftar, supaya halaman ini tidak bisa dipakai untuk mencari tahu siapa yang punya akun. Belum masuk dalam 5 menit? Periksa folder spam.
            <?php if (Config::bool('APP_DEMO')): ?><br><span class="small">Mode demo: email ditulis ke <span class="mono">storage/mail.log</span>.</span><?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <form class="form-stack" method="post" action="<?= e(url('/lupa-password')) ?>" novalidate data-validate>
          <?= csrf_field() ?>
          <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email" required value="<?= e(old('email')) ?>" data-msg="Masukkan email akunmu."<?= field_aria('email') ?>>
            <?= field_error('email') ?>
          </div>
          <button class="btn btn-ink btn-lg btn-block" type="submit">Kirim tautan</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>
