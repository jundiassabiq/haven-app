<?php /** @var array $items */ ?>
<div data-filter-list>
  <div class="app-page-head">
    <div><h1 class="app-title">Notifikasi</h1><p class="app-sub">Isi pesan tidak ditampilkan di sini, supaya tetap aman jika layarmu terlihat orang lain.</p></div>
    <form method="post" action="<?= e(url('/notifikasi/baca-semua')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Tandai semua dibaca</button></form>
  </div>
  <?php $types = array_unique(array_column($items, 'type')); ?>
  <?php if (count($types) > 1): ?>
    <div class="chips" role="group" aria-label="Saring notifikasi" style="margin-bottom:20px">
      <button class="chip" type="button" data-filter="semua" aria-pressed="true">Semua</button>
      <?php foreach ($types as $t): ?><button class="chip" type="button" data-filter="<?= e($t) ?>" aria-pressed="false"><?= e(Notifier::TYPES[$t] ?? $t) ?></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($items): ?>
    <div class="table-wrap table-cards">
      <table class="table">
        <thead><tr><th scope="col">Pemberitahuan</th><th scope="col">Jenis</th><th scope="col">Waktu</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
        <tbody>
          <?php foreach ($items as $n): $un = $n['read_at'] === null; ?>
            <tr class="<?= $un ? 'is-unread-row' : '' ?>" data-item data-category="<?= e($n['type']) ?>">
              <td data-label="Pemberitahuan"><?= $un ? '<strong>' . e($n['title']) . '</strong> <span class="badge badge-plain">Baru</span>' : e($n['title']) ?></td>
              <td data-label="Jenis"><?= e(Notifier::TYPES[$n['type']] ?? $n['type']) ?></td>
              <td data-label="Waktu" class="mono"><?= e(mb_strtoupper(Notifier::ago($n['created_at']))) ?></td>
              <td class="actions" data-label="Aksi"><a class="btn <?= $un ? 'btn-ink' : 'btn-ghost' ?> btn-xs" href="<?= e(url('/notifikasi/' . $n['id'])) ?>">Buka</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="empty" data-empty hidden style="margin-top:12px"><?= icon('bell') ?><h3>Tidak ada notifikasi di kategori ini</h3><p>Pemberitahuan baru akan muncul di sini dan di ikon lonceng.</p></div>
  <?php else: ?>
    <div class="empty"><?= icon('bell') ?><h3>Belum ada notifikasi</h3><p>Perubahan status, pesan baru, dan jadwal sesi akan muncul di sini dan di ikon lonceng.</p></div>
  <?php endif; ?>
</div>
