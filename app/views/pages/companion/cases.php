<?php /** @var array $rows  @var array $f */ ?>
<div class="app-page-head">
  <div><h1 class="app-title">Kasus saya</h1><p class="app-sub">Hanya kasus yang ditugaskan kepadamu. Setiap kali detail kasus dibuka, aksesnya tercatat.</p></div>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/pendamping/kasus')) ?>" data-autosubmit role="search">
  <div class="search"><?= icon('search') ?><label class="sr-only" for="q">Cari kode</label><input class="input" id="q" name="q" type="search" placeholder="Cari kode laporan" value="<?= e($f['q']) ?>"></div>
  <div class="field"><label class="label" for="fs">Status</label><select class="select select-sm" id="fs" name="status"><option value="">Semua status</option><?php foreach (['didampingi', 'dirujuk', 'selesai'] as $k): ?><option value="<?= $k ?>"<?= selected($f['status'], $k) ?>><?= e(Report::STATUS[$k]) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="label" for="fu">Urgensi</label><select class="select select-sm" id="fu" name="urgensi"><option value="">Semua urgensi</option><?php foreach (Report::URGENCY as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($f['urgensi'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ink btn-sm" type="submit">Terapkan</button>
</form>
<p class="result-count" style="margin-bottom:12px"><?= count($rows) ?> kasus</p>

<?php if ($rows): ?>
  <div class="table-wrap table-cards">
    <table class="table">
      <thead><tr><th scope="col">Kode</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col">Urgensi</th><th scope="col">Wilayah</th><th scope="col">Aktivitas terakhir</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $hot = (int) $r['unread'] > 0; $byWitness = (string) ($r['reporter_type'] ?? 'korban') === ConsentGuard::WITNESS; ?>
          <tr class="<?= $hot ? 'is-unread-row' : '' ?>">
            <td data-label="Kode" class="mono"><a class="row-link" href="<?= e(url('/pendamping/kasus/' . $r['code'])) ?>"><?= e($r['code']) ?></a><?php if ($hot): ?><span class="cell-sub"><?= (int) $r['unread'] ?> pesan belum dibaca</span><?php endif; ?><?php if ($byWitness): ?><span class="cell-sub">Dilaporkan oleh saksi</span><?php endif; ?></td>
            <td data-label="Jenis"><?= e(Report::CATEGORIES[$r['category']]) ?></td>
            <td data-label="Status"><?= status_badge($r['status']) ?></td>
            <td data-label="Urgensi"><?= urgency_badge($r['urgency']) ?></td>
            <td data-label="Wilayah"><?= e($r['city'] ?: ($r['province'] ?: '-')) ?></td>
            <td data-label="Aktivitas" class="mono"><?= e(mb_strtoupper(Notifier::ago($r['last_activity']))) ?></td>
            <td class="actions" data-label="Aksi"><a class="btn <?= $hot ? 'btn-ink' : 'btn-ghost' ?> btn-xs" href="<?= e(url('/pendamping/kasus/' . $r['code'])) ?>">Buka</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="empty">
    <?= icon('inbox') ?>
    <h3><?= array_filter($f) ? 'Tidak ada kasus yang cocok' : 'Belum ada kasus yang ditugaskan' ?></h3>
    <p><?= array_filter($f) ? 'Ubah filter untuk melihat kasus lain.' : 'Saat admin menugaskan laporan kepadamu, kamu akan menerima notifikasi.' ?></p>
    <?php if (array_filter($f)): ?><a class="btn btn-ink btn-sm" href="<?= e(url('/pendamping/kasus')) ?>">Hapus filter</a><?php endif; ?>
  </div>
<?php endif; ?>
