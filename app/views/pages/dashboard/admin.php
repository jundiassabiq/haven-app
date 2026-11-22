<?php
/**
 * Statistik admin (Req 7). Halaman ini tidak pernah menampilkan isi laporan.
 *
 * @var array|null  $range       rentang terpilih, null bila rentangnya tidak sah
 * @var string|null $rangeError  pesan rentang tidak sah (Req 7.12)
 * @var array|null  $agg         hasil Stats::aggregate, null bila rentangnya tidak sah
 * @var array       $k           angka operasional antrean
 * @var array       $workload
 */
$num = static fn (int $n) => number_format($n, 0, ',', '.');
$from = $range['from'] ?? input('dari');
$to = $range['to'] ?? input('sampai');
$src = $range === null ? '' : e(url('/api/statistik', ['dari' => $range['from'], 'sampai' => $range['to']]));

/** Satu tabel rincian, atau pesan kenapa barisnya tidak ada (Req 7.13). */
$breakdown = static function (array $section, string $head, array $agg) use ($num): string {
    if ($section['rows'] === []) {
        $msg = $agg['empty'] ? Aggregator::EMPTY_MESSAGE : Aggregator::INSUFFICIENT_MESSAGE;
        return '<p class="panel-body small muted">' . e($msg) . '</p>';
    }
    $out = '<div class="table-wrap" style="border:0;border-radius:0 0 6px 6px"><table class="table">'
        . '<thead><tr><th scope="col">' . e($head) . '</th><th scope="col" class="num">Kasus</th></tr></thead><tbody>';
    foreach ($section['rows'] as $label => $n) {
        $out .= '<tr><td>' . e($label) . '</td><td class="num">' . $num((int) $n) . '</td></tr>';
    }
    return $out . '</tbody></table></div>';
};
?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Statistik</h1>
    <p class="app-sub">Data agregat tanpa isi laporan. Kelompok dengan kurang dari <?= Stats::MIN_GROUP ?> kasus digabung ke "<?= e(Aggregator::OTHER_LABEL) ?>" agar penyintas tidak bisa dikenali, dan jumlah tiap kelompok tetap sama dengan total laporan.</p>
  </div>
  <form method="get" action="<?= e(url('/admin')) ?>" class="field-row-3" style="align-items:end;gap:12px;min-width:320px">
    <div class="field"><label class="label" for="dari">Dari</label><input class="input input-sm" type="date" id="dari" name="dari" max="<?= date('Y-m-d') ?>" value="<?= e($from) ?>"></div>
    <div class="field"><label class="label" for="sampai">Sampai</label><input class="input input-sm" type="date" id="sampai" name="sampai" max="<?= date('Y-m-d') ?>" value="<?= e($to) ?>"></div>
    <button class="btn btn-ink btn-sm" type="submit">Terapkan</button>
  </form>
</div>

<?php if ($rangeError !== null): ?>
  <div class="alert alert-danger" role="alert" style="margin-bottom:24px">
    <?= icon('alert') ?>
    <div><strong>Rentang tanggal tidak bisa dipakai.</strong><?= e($rangeError) ?> Statistik dan ekspor tidak dibuat sampai rentangnya benar.</div>
  </div>
<?php else: ?>
  <div class="app-page-head" style="margin-bottom:16px">
    <p class="small muted">Rentang terpilih: <?= e(Stats::rangeText($range)) ?>.</p>
    <form method="post" action="<?= e(url('/admin/statistik/ekspor')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="dari" value="<?= e($range['from']) ?>">
      <input type="hidden" name="sampai" value="<?= e($range['to']) ?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?= icon('download') ?> Unduh CSV agregat</button>
    </form>
  </div>

  <?php if ($agg['empty']): ?>
    <div class="alert alert-info" role="status" style="margin-bottom:24px">
      <?= icon('info') ?>
      <div><strong><?= e(Aggregator::EMPTY_MESSAGE) ?></strong>Pilih rentang tanggal lain untuk melihat angkanya.</div>
    </div>
  <?php elseif ($agg['insufficient']): ?>
    <div class="alert alert-info" role="status" style="margin-bottom:24px">
      <?= icon('shield') ?>
      <div><strong><?= e(Aggregator::INSUFFICIENT_MESSAGE) ?></strong>Rentang ini hanya memuat <?= $num($agg['total']) ?> laporan. Rincian per jenis, provinsi, bulan, dan status ditahan karena dengan sesedikit itu satu baris bisa menunjuk ke satu orang.</div>
    </div>
  <?php endif; ?>

  <dl class="kpis" style="margin-bottom:24px">
    <div class="kpi"><dt>Laporan dalam rentang</dt><dd><?= $num($agg['total']) ?></dd><span class="kpi-note"><?= e(Stats::rangeText($range)) ?></span></div>
    <div class="kpi"><dt>Menunggu ditinjau</dt><dd><?= $num($k['waiting']) ?></dd><span class="kpi-note"><?= $k['oldestWaiting'] ? 'Tertua ' . e(Notifier::ago($k['oldestWaiting'])) : 'Antrean bersih' ?></span></div>
    <div class="kpi"><dt>Sedang didampingi</dt><dd><?= $num($k['active']) ?></dd><span class="kpi-note">oleh <?= $num($k['companions']) ?> pendamping</span></div>
    <div class="kpi">
      <dt>Median sampai dibalas</dt>
      <?php if ($agg['insufficient']): ?>
        <dd style="font-size:1.125rem;line-height:1.3"><?= e(Stats::INSUFFICIENT_SHORT) ?></dd>
      <?php elseif ($agg['median'] === null): ?>
        <dd style="font-size:1.125rem;line-height:1.3"><?= e(Aggregator::NO_MEDIAN_MESSAGE) ?></dd>
      <?php else: ?>
        <dd><?= $num($agg['median']) ?> j</dd>
      <?php endif; ?>
      <span class="kpi-note">
        <?php if ($agg['sla'] === null): ?>
          Batas balasan <?= e(sla_duration($agg['sla_hours']['danger'])) ?> untuk kasus bahaya, <?= e(sla_duration($agg['sla_hours']['default'])) ?> untuk lainnya
        <?php else: ?>
          <?= e(str_replace('.', ',', (string) $agg['sla'])) ?>% dari <?= $num($agg['replied']) ?> kasus dibalas dalam batas SLA
        <?php endif; ?>
      </span>
    </div>
  </dl>

  <div class="grid-2" style="margin-bottom:24px">
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Laporan per bulan</h2><span class="small muted">Bulan kalender</span></div>
      <div class="panel-body">
        <?php if ($agg['sections']['month']['rows'] === []): ?>
          <p class="small muted"><?= e($agg['empty'] ? Aggregator::EMPTY_MESSAGE : Aggregator::INSUFFICIENT_MESSAGE) ?></p>
        <?php elseif (lite()): ?>
          <?= $breakdown($agg['sections']['month'], 'Bulan', $agg) ?>
        <?php else: ?>
          <div class="chart"><canvas role="img" aria-label="Grafik laporan per bulan, sedang dimuat" data-chart-src="<?= $src ?>" data-chart-key="trend"></canvas></div>
        <?php endif; ?>
      </div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Jenis kejadian</h2><span class="small muted"><?= e(Stats::rangeText($range)) ?></span></div>
      <div class="panel-body">
        <?php if ($agg['sections']['category']['rows'] === []): ?>
          <p class="small muted"><?= e($agg['empty'] ? Aggregator::EMPTY_MESSAGE : Aggregator::INSUFFICIENT_MESSAGE) ?></p>
        <?php elseif (lite()): ?>
          <?= $breakdown($agg['sections']['category'], 'Jenis kejadian', $agg) ?>
        <?php else: ?>
          <div class="chart"><canvas role="img" aria-label="Grafik jenis kejadian, sedang dimuat" data-chart-src="<?= $src ?>" data-chart-key="categories"></canvas></div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="grid-halves" style="margin-bottom:24px">
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Provinsi</h2><span class="small muted">Laporan dalam rentang</span></div>
      <?= $breakdown($agg['sections']['province'], 'Provinsi', $agg) ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Status kasus</h2><span class="small muted">Laporan dalam rentang</span></div>
      <?= $breakdown($agg['sections']['status'], 'Status', $agg) ?>
    </section>
  </div>

  <section class="panel" style="margin-bottom:24px">
    <div class="panel-head"><h2 class="panel-title"><?= e(Stats::CROSS_LABEL) ?></h2><span class="small muted">Kombinasi di bawah <?= Stats::MIN_GROUP ?> kasus digabung</span></div>
    <?= $breakdown($agg['cross'], Stats::CROSS_LABEL, $agg) ?>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2 class="panel-title">Beban pendamping</h2><a class="link-btn" href="<?= e(url('/admin/pengguna')) ?>">Kelola</a></div>
  <?php if ($workload): ?>
    <div class="table-wrap" style="border:0;border-radius:0 0 6px 6px">
      <table class="table">
        <thead><tr><th scope="col">Pendamping</th><th scope="col" class="num">Aktif</th><th scope="col" class="num">Baru bulan ini</th></tr></thead>
        <tbody>
          <?php foreach ($workload as $w): ?>
            <tr><td><a class="row-link" href="<?= e(url('/admin/antrean', ['pendamping' => $w['id']])) ?>"><?= e($w['name']) ?></a><?= $w['region'] ? '<span class="cell-sub">' . e($w['region']) . '</span>' : '' ?></td><td class="num"><?= (int) $w['active'] ?></td><td class="num"><?= (int) $w['this_month'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="panel-body small muted">Belum ada pendamping aktif. Laporan baru tidak bisa ditugaskan sampai ada. <a href="<?= e(url('/admin/pengguna')) ?>">Undang pendamping</a>.</p>
  <?php endif; ?>
</section>
