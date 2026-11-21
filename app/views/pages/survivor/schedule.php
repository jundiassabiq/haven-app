<?php /** @var array $upcoming  @var array $past */ ?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Jadwal sesi</h1>
    <p class="app-sub">Sesi bicara dengan pendampingmu. Untuk memesan waktu baru, buka tab Jadwal sesi di laporanmu.</p>
  </div>
  <a class="btn btn-ink btn-sm" href="<?= e(url('/penyintas/laporan')) ?>">Pilih laporan</a>
</div>

<div class="grid-halves">
  <section class="panel" aria-labelledby="up">
    <div class="panel-head"><h2 class="panel-title" id="up">Akan datang</h2></div>
    <div class="panel-body">
      <?php if (!$upcoming): ?><p class="small muted">Tidak ada sesi mendatang.</p><?php endif; ?>
      <?php foreach ($upcoming as $b): $t = strtotime($b['starts_at']); ?>
        <div class="session">
          <span class="session-date"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span>
          <div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?> sampai <?= date('H.i', $t + (int) $b['duration_min'] * 60) ?></h3><p><?= e(Slot::MODES[$b['mode']]) ?> dengan <?= e(short_name((string) $b['companion_name'])) ?> · <span class="mono"><?= e($b['report_code']) ?></span></p></div>
          <a class="btn btn-ghost btn-xs" href="<?= e(url('/kasus/' . $b['report_code'] . '#jadwal')) ?>">Kelola</a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="panel" aria-labelledby="past">
    <div class="panel-head"><h2 class="panel-title" id="past">Sudah lewat</h2></div>
    <div class="panel-body">
      <?php if (!$past): ?><p class="small muted">Belum ada riwayat sesi.</p><?php endif; ?>
      <?php foreach (array_slice($past, 0, 20) as $b): $t = strtotime($b['starts_at']); ?>
        <div class="session"><span class="session-date is-past"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span><div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?></h3><p><?= e(Booking::stateLabel($b)) ?> · <?= e(Slot::MODES[$b['mode']]) ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
