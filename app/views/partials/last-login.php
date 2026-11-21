<?php
/** @var array $user  @var array|null $lastLogin */
$lastLogin ??= null;
?>
<div class="alert alert-info">
  <?= icon('lock') ?>
  <div><strong>Aktivitas akun.</strong>
    <?php if ($lastLogin): ?>
      Masuk terakhir <?= e(id_date($lastLogin['created_at'], true)) ?> dari <?= e(ua_summary($lastLogin['user_agent'])) ?>.
    <?php endif; ?>
    Bukan kamu? <a href="<?= e(url('/akun#kata-sandi')) ?>">Ganti kata sandi</a>.
  </div>
</div>
