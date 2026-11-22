<?php /** @var array $articles */ ?>
<div data-filter-list>
  <div class="app-page-head">
    <div><h1 class="app-title">Artikel</h1><p class="app-sub">Konten edukasi untuk halaman publik. Artikel berstatus Draf tidak terlihat oleh pengunjung.</p></div>
    <a class="btn btn-yellow btn-sm" href="<?= e(url('/admin/artikel/baru')) ?>"><?= icon('plus') ?> Tulis artikel</a>
  </div>
  <div class="filter-bar">
    <div class="search"><?= icon('search') ?><label class="sr-only" for="q">Cari artikel</label><input class="input" id="q" type="search" placeholder="Cari judul" data-search></div>
    <div class="field"><label class="label" for="fs">Status</label><select class="select select-sm" id="fs" data-filter-select="state"><option value="">Semua</option><option value="terbit">Terbit</option><option value="draf">Draf</option></select></div>
  </div>
  <div class="table-wrap table-cards">
    <table class="table">
      <thead><tr><th scope="col">Judul</th><th scope="col">Kategori</th><th scope="col">Status</th><th scope="col">Diperbarui</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
          <tr data-item data-state="<?= e($a['status']) ?>">
            <td data-label="Judul"><a class="row-link" href="<?= e(url('/admin/artikel/' . $a['id'])) ?>"><?= e($a['title']) ?></a><span class="cell-sub mono">/edukasi/<?= e($a['slug']) ?></span></td>
            <td data-label="Kategori"><?= e($a['cat_name']) ?></td>
            <td data-label="Status"><?= $a['status'] === 'terbit' ? '<span class="badge badge-ok">Terbit</span>' : '<span class="badge" data-status="ditinjau">Draf</span>' ?></td>
            <td data-label="Diperbarui" class="mono"><?= e(mb_strtoupper(id_date($a['updated_at']))) ?></td>
            <td class="actions" data-label="Aksi"><div class="row">
              <a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/artikel/' . $a['id'])) ?>"><?= icon('edit') ?> Ubah</a>
              <button class="btn btn-ghost btn-xs" type="button" data-open-dialog="dlg-del-<?= (int) $a['id'] ?>"><?= icon('trash') ?><span class="sr-only">Hapus <?= e($a['title']) ?></span></button>
            </div>
            <dialog class="modal" id="dlg-del-<?= (int) $a['id'] ?>" aria-labelledby="dlg-del-<?= (int) $a['id'] ?>-t">
              <form method="post" action="<?= e(url('/admin/artikel/' . $a['id'] . '/hapus')) ?>">
                <?= csrf_field() ?>
                <div class="modal-body" style="text-align:left"><h2 id="dlg-del-<?= (int) $a['id'] ?>-t">Hapus "<?= e($a['title']) ?>"?</h2><p>Artikel hilang dari halaman publik dan tautan ke artikel ini akan menampilkan halaman 404.</p></div>
                <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Hapus artikel</button></div>
              </form>
            </dialog></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="empty" data-empty hidden style="margin-top:12px"><?= icon('book') ?><h3>Tidak ada artikel</h3><p>Ubah filter atau tulis artikel baru.</p></div>
</div>
