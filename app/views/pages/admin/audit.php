<?php /** @var array $rows  @var array $pg  @var array $f */ ?>
<div class="app-page-head">
  <div><h1 class="app-title">Audit log</h1><p class="app-sub">Setiap kali laporan dibuka, diubah, atau gagal diakses, sistem mencatatnya di sini. Log tidak bisa diubah atau dihapus dari aplikasi.</p></div>
</div>
<form class="filter-bar" method="get" action="<?= e(url('/admin/audit')) ?>" data-autosubmit role="search">
  <div class="search"><?= icon('search') ?><label class="sr-only" for="q">Cari log</label><input class="input" id="q" name="q" type="search" placeholder="Cari kode laporan atau pengguna" value="<?= e($f['q']) ?>"></div>
  <div class="field"><label class="label" for="fa">Aksi</label><select class="select select-sm" id="fa" name="aksi"><option value="">Semua aksi</option><?php foreach (AuditLog::GROUPS as $k => [$label]): ?><option value="<?= e($k) ?>"<?= selected($f['aksi'], $k) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ink btn-sm" type="submit">Terapkan</button>
</form>
<?php if ($rows): ?>
  <div class="table-wrap table-cards">
    <table class="table">
      <thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aksi</th><th scope="col">Objek</th><th scope="col">IP</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $denied = in_array($r['action'], ['access.denied', 'auth.failed'], true); ?>
          <tr>
            <td data-label="Waktu" class="mono"><?= e(mb_strtoupper(id_date($r['created_at'])) . ' ' . date('H.i:s', strtotime($r['created_at']))) ?></td>
            <td data-label="Pengguna"><?= e($r['actor_label']) ?></td>
            <td data-label="Aksi"><?= $denied ? urgency_badge('tinggi', '') . ' ' : '' ?><?= e(AuditLog::label($r['action'])) ?><?= $r['detail'] ? '<span class="cell-sub">' . e($r['detail']) . '</span>' : '' ?></td>
            <td data-label="Objek" class="mono"><?= e($r['object_ref'] ?: '-') ?></td>
            <td data-label="IP" class="mono"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php $shown = count($rows); include APP_PATH . '/views/partials/pagination.php'; ?>
<?php else: ?>
  <div class="empty"><?= icon('log') ?><h3>Tidak ada catatan</h3><p>Coba ubah kata kunci atau jenis aksi.</p></div>
<?php endif; ?>
