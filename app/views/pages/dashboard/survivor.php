<?php /** @var array $user  @var array $reports  @var array $upcoming  @var array $k */ ?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Halo, <?= e($user['name']) ?>.</h1>
    <p class="app-sub"><?= $k['unread'] || $k['upcoming'] ? 'Kamu punya ' . (int) $k['unread'] . ' pesan baru dan ' . (int) $k['upcoming'] . ' sesi mendatang.' : 'Belum ada pesan baru atau sesi yang dijadwalkan.' ?></p>
  </div>
  <a class="btn btn-yellow" href="<?= e(url('/lapor')) ?>"><?= icon('file-plus') ?> Buat laporan baru</a>
</div>

<dl class="kpis kpis-3" style="margin-bottom:24px">
  <div class="kpi"><dt>Laporan aktif</dt><dd><?= (int) $k['active'] ?></dd></div>
  <div class="kpi"><dt>Sesi mendatang</dt><dd><?= (int) $k['upcoming'] ?></dd></div>
  <div class="kpi"><dt>Pesan belum dibaca</dt><dd><?= (int) $k['unread'] ?></dd></div>
</dl>

<div class="grid-2">
  <div class="stack">
    <?php if ($reports): ?>
      <section class="panel" aria-labelledby="p-lap">
        <div class="panel-head"><h2 class="panel-title" id="p-lap">Laporan terbaru</h2><a class="link-btn" href="<?= e(url('/penyintas/laporan')) ?>">Semua laporan</a></div>
        <div class="panel-body stack">
          <?php foreach ($reports as $r): $unread = Message::unreadFor((int) $r['id'], 'survivor'); ?>
            <div>
              <div class="row-between">
                <div class="case-id"><span class="case-code" style="font-size:1.25rem"><?= e($r['code']) ?></span><?= status_badge($r['status']) ?></div>
                <span class="small muted">Diperbarui <?= e(Notifier::ago($r['updated_at'])) ?></span>
              </div>
              <p style="margin-top:10px;color:var(--ink-2)"><?= $r['companion_name'] ? 'Pendamping: ' . e(short_name($r['companion_name'])) . '.' : 'Admin sedang memilih pendamping.' ?><?= $unread ? ' Ada ' . $unread . ' pesan baru.' : '' ?></p>
              <div class="row" style="margin-top:14px">
                <a class="btn <?= $unread ? 'btn-ink' : 'btn-ghost' ?> btn-sm" href="<?= e(url('/kasus/' . $r['code'] . ($unread ? '#pesan' : ''))) ?>"><?= $unread ? 'Balas pesan' : 'Buka laporan' ?></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php else: ?>
      <div class="empty">
        <?= icon('file') ?>
        <h3>Belum ada laporan di akun ini</h3>
        <p>Laporan yang kamu kirim sambil masuk ke akun ini akan muncul di sini, bersama pesan dan jadwalnya. Laporan anonim lama bisa ditautkan dari halaman Laporan saya.</p>
        <a class="btn btn-ink btn-sm" href="<?= e(url('/lapor')) ?>">Buat laporan</a>
      </div>
    <?php endif; ?>
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Artikel untuk dibaca</h2></div>
      <div class="panel-body stack-sm">
        <a class="text-link" href="<?= e(url('/edukasi/sembilan-bentuk-kekerasan-seksual')) ?>">Sembilan bentuk kekerasan seksual menurut UU TPKS <?= icon('arrow-right') ?></a>
        <a class="text-link" href="<?= e(url('/edukasi/hak-korban')) ?>">Hak-hakmu sebagai korban <?= icon('arrow-right') ?></a>
      </div>
    </section>
  </div>
  <div class="stack">
    <section class="panel" aria-labelledby="p-sesi">
      <div class="panel-head"><h2 class="panel-title" id="p-sesi">Sesi berikutnya</h2></div>
      <div class="panel-body">
        <?php if (!$upcoming): ?><p class="small muted">Belum ada sesi mendatang.</p><?php endif; ?>
        <?php foreach ($upcoming as $b): $t = strtotime($b['starts_at']); ?>
          <div class="session"><span class="session-date"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span><div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?></h3><p><?= e(Slot::MODES[$b['mode']]) ?> dengan <?= e(short_name((string) $b['companion_name'])) ?></p></div></div>
        <?php endforeach; ?>
        <a class="text-link" href="<?= e(url('/penyintas/jadwal')) ?>" style="margin-top:12px">Kelola jadwal <?= icon('arrow-right') ?></a>
      </div>
    </section>
    <?php include APP_PATH . '/views/partials/last-login.php'; ?>
  </div>
</div>
