<?php /** @var array $user  @var array $cases  @var array $today  @var array $k */ ?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Halo, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
    <p class="app-sub"><?= e(id_today_long()) ?><?= $user['region'] ? ' · Wilayah kerja ' . e($user['region']) : '' ?><?= $k['unanswered'] ? '. Ada ' . (int) $k['unanswered'] . ' kasus yang belum kamu sapa.' : '' ?></p>
  </div>
  <a class="btn btn-ink btn-sm" href="<?= e(url('/pendamping/slot')) ?>"><?= icon('calendar') ?> Buka slot jadwal</a>
</div>

<dl class="kpis" style="margin-bottom:24px">
  <div class="kpi"><dt>Kasus aktif</dt><dd><?= (int) $k['active'] ?></dd></div>
  <div class="kpi"><dt>Belum disapa</dt><dd><?= (int) $k['unanswered'] ?></dd></div>
  <div class="kpi"><dt>Sesi hari ini</dt><dd><?= (int) $k['today'] ?></dd></div>
  <div class="kpi"><dt>Pesan belum dibaca</dt><dd><?= (int) $k['unread'] ?></dd></div>
</dl>

<div class="grid-2">
  <?php if ($cases): ?>
    <section class="panel" aria-labelledby="need">
      <div class="panel-head"><h2 class="panel-title" id="need">Kasus aktif</h2><a class="link-btn" href="<?= e(url('/pendamping/kasus')) ?>">Semua kasus</a></div>
      <div class="table-wrap table-cards" style="border:0;border-radius:0 0 6px 6px">
        <table class="table">
          <thead><tr><th scope="col">Kode</th><th scope="col">Status</th><th scope="col">Urgensi</th><th scope="col">Catatan</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($cases, 0, 6) as $r): ?>
              <tr class="<?= (int) $r['unread'] ? 'is-unread-row' : '' ?>">
                <td data-label="Kode" class="mono"><a class="row-link" href="<?= e(url('/pendamping/kasus/' . $r['code'])) ?>"><?= e($r['code']) ?></a></td>
                <td data-label="Status"><?= status_badge($r['status']) ?></td>
                <td data-label="Urgensi"><?= urgency_badge($r['urgency']) ?></td>
                <td data-label="Catatan"><?= (int) $r['unread'] ? (int) $r['unread'] . ' pesan belum dibalas' : 'Aktivitas ' . e(Notifier::ago($r['last_activity'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php else: ?>
    <div class="empty">
      <?= icon('inbox') ?>
      <h3>Belum ada kasus aktif</h3>
      <p>Kasus muncul di sini setelah admin menugaskannya kepadamu. Kamu juga akan mendapat notifikasi di lonceng.</p>
    </div>
  <?php endif; ?>
  <div class="stack">
    <section class="panel" aria-labelledby="today">
      <div class="panel-head"><h2 class="panel-title" id="today">Sesi hari ini</h2></div>
      <div class="panel-body">
        <?php if (!$today): ?><p class="small muted">Tidak ada sesi hari ini.</p><?php endif; ?>
        <?php foreach ($today as $s): $t = strtotime($s['starts_at']); ?>
          <div class="session"><span class="session-date"><b><?= date('H.i', $t) ?></b><small>WIB</small></span><div><h3 class="mono"><?= e($s['report_code']) ?></h3><p><?= e(Slot::MODES[$s['mode']]) ?> · <?= (int) $s['duration_min'] ?> menit</p></div></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php include APP_PATH . '/views/partials/last-login.php'; ?>
  </div>
</div>
