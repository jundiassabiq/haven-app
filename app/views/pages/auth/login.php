<?php /** @var string|null $formError */ ?>
<main id="konten" tabindex="-1" class="auth">
  <div class="auth-media">
    <?php if (!lite()): ?><div class="media"><img src="<?= asset('img/holding-hands.jpg') ?>" alt="" width="1000" height="1500"></div><?php endif; ?>
    <p class="auth-quote">Pesan dari pendampingmu menunggu di dalam.</p>
  </div>
  <div class="auth-panel">
    <div class="auth-form">
      <h1 class="h2">Masuk ke Haven</h1>
      <p>Untuk penyintas yang punya akun, pendamping, dan admin. Laporan anonim dibuka lewat halaman Lacak laporan.</p>

      <?php if (!empty($formError)): ?>
        <div class="alert alert-danger" role="alert" style="margin-top:24px">
          <?= icon('alert') ?><div><?= e($formError) ?></div>
        </div>
      <?php endif; ?>

      <form class="form-stack" method="post" action="<?= e(url('/masuk')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="email">Email</label>
          <input class="input" type="email" id="email" name="email" autocomplete="email" required value="<?= e(old('email')) ?>" data-msg="Masukkan email akunmu."<?= field_aria('email') ?>>
          <?= field_error('email') ?>
        </div>
        <div class="field">
          <div class="row-between"><label class="label" for="password">Kata sandi</label><a class="link-btn" href="<?= e(url('/lupa-password')) ?>">Lupa kata sandi?</a></div>
          <div class="input-group">
            <input class="input" type="password" id="password" name="password" autocomplete="current-password" required data-msg="Masukkan kata sandimu."<?= field_aria('password') ?>>
            <button class="reveal" type="button" data-reveal="password" aria-pressed="false"><?= icon('eye') ?><span class="sr-only">Tampilkan</span></button>
          </div>
          <?= field_error('password') ?>
        </div>
        <button class="btn btn-ink btn-lg btn-block" type="submit">Masuk</button>
      </form>

      <?php if (Config::bool('APP_DEMO')): ?>
        <div class="panel panel-cream panel-body demo-accounts">
          <p class="small"><strong>Akun demo</strong>, semua dengan kata sandi <span class="mono">haven123</span>:</p>
          <table>
            <tr><td>Penyintas</td><td>rani@demo.id</td></tr>
            <tr><td>Pendamping</td><td>dewi@demo.id</td></tr>
            <tr><td>Admin</td><td>admin@demo.id</td></tr>
          </table>
        </div>
      <?php endif; ?>
      <p class="auth-alt">Belum punya akun? <a href="<?= e(url('/daftar')) ?>">Daftar</a>. Melapor tidak butuh akun: <a href="<?= e(url('/lapor')) ?>">buat laporan anonim</a>.</p>
    </div>
  </div>
</main>
