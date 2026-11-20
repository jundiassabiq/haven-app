<?php
/* Flash messages become toasts via app.js ([data-flash]). They also render inline for no-JS users. */
$flashes = array_filter([
    ['success', Session::getFlash('success')],
    ['info', Session::getFlash('info')],
    ['error', Session::getFlash('error')],
], static fn ($f) => !empty($f[1]));
foreach ($flashes as [$kind, $msg]): ?>
  <div hidden data-flash="<?= e($msg) ?>" data-flash-kind="<?= e($kind) ?>"></div>
<?php endforeach; ?>
<noscript>
  <?php foreach ($flashes as [$kind, $msg]): ?><p class="alert <?= $kind === 'error' ? 'alert-danger' : 'alert-info' ?>" style="margin:16px"><?= e($msg) ?></p><?php endforeach; ?>
</noscript>
