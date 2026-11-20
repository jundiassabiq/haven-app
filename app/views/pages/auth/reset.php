<?php /** @var string $state ok|expired|invalid  @var string $token  @var string $email  @var bool $invite */ ?>
<main id="konten" tabindex="-1" class="auth">
  <div class="auth-media">
    <?php if (!lite()): ?><div class="media"><img src="<?= asset('img/reaching-hands.jpg') ?>" alt="" width="1600" height="1067"></div><?php endif; ?>
  </div>
  <div class="auth-panel">
    <div class="auth-form">
      <?php if ($state === 'ok'): ?>
        <h1 class="h2"><?= $invite ? 'Buat kata sandi' : 'Buat kata sandi baru' ?></h1>
        <p>Untuk akun <span class="mono"><?= e(mask_email($email)) ?></span>. Setelah kamu menyimpan, perangkat lain yang masih masuk ke akun ini akan dikeluarkan.</p>
        <form class="form-stack" method="post" action="<?= e(url('/reset-password')) ?>" novalidate data-validate>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="field">
            <label class="label" for="password">Kata sandi baru</label>
            <div class="input-group">
              <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required data-msg="Buat kata sandi minimal 8 karakter."<?= field_aria('password', 'pw-hint') ?>>
              <button class="reveal" type="button" data-reveal="password" aria-pressed="false"><?= icon('eye') ?><span class="sr-only">Tampilkan</span></button>
            </div>
            <p class="hint" id="pw-hint">Minimal 8 karakter.</p>
            <?= field_error('password') ?>
          </div>
          <div class="field">
            <label class="label" for="password_confirm">Ulangi kata sandi baru</label>
            <input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required data-match="password" data-msg="Ketik ulang kata sandi barumu."<?= field_aria('password_confirm') ?>>
            <?= field_error('password_confirm') ?>
          </div>
          <button class="btn btn-ink btn-lg btn-block" type="submit">Simpan kata sandi</button>
        </form>
      <?php elseif ($state === 'expired'): ?>
        <h1 class="h2">Tautan kedaluwarsa</h1>
        <div class="alert alert-danger" style="margin-top:24px">
          <?= icon('clock') ?>
          <div><strong>Tautan ini sudah lebih dari <?= PasswordReset::RESET_MINUTES ?> menit.</strong>Tautan pemulihan hanya berlaku <?= PasswordReset::RESET_MINUTES ?> menit supaya email lama di kotak masukmu tidak bisa dipakai orang lain. <a href="<?= e(url('/lupa-password')) ?>">Minta tautan baru</a>.</div>
        </div>
      <?php else: ?>
        <h1 class="h2">Tautan tidak berlaku</h1>
        <div class="alert alert-danger" style="margin-top:24px">
          <?= icon('alert') ?>
          <div><strong>Tautan ini sudah dipakai atau tidak lengkap.</strong>Setiap tautan hanya bisa dipakai sekali, dan meminta tautan baru membatalkan yang lama. Buka email yang paling baru. <a href="<?= e(url('/lupa-password')) ?>">Minta tautan baru</a>.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
