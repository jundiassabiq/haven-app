<?php
/** @var array $staff  @var int $survivors  @var array $me */
?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Pengguna</h1>
    <p class="app-sub">Akun pendamping dan admin dibuat di sini. Penyintas mendaftar sendiri dan datanya tidak bisa dilihat dari halaman ini.</p>
  </div>
</div>

<div class="grid-2">
  <div data-filter-list>
    <div class="filter-bar">
      <div class="search"><?= icon('search') ?><label class="sr-only" for="q">Cari pengguna</label><input class="input" id="q" type="search" placeholder="Cari nama atau email" data-search></div>
      <div class="field"><label class="label" for="fr">Peran</label>
        <select class="select select-sm" id="fr" data-filter-select="role"><option value="">Semua peran</option><option value="companion">Pendamping</option><option value="admin">Admin</option></select>
      </div>
    </div>
    <div class="table-wrap table-cards">
      <table class="table">
        <thead><tr><th scope="col">Nama</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Terakhir masuk</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
        <tbody>
          <?php foreach ($staff as $s): $self = (int) $s['id'] === (int) $me['id']; $isCompanion = $s['role'] === 'companion'; $trained = $isCompanion && (int) $s['child_trained']; ?>
            <tr data-item data-role="<?= e($s['role']) ?>">
              <td data-label="Nama"><strong><?= e($s['name']) ?></strong><span class="cell-sub"><?= e($s['email']) ?><?= $s['region'] ? ' · ' . e($s['region']) : '' ?></span></td>
              <td data-label="Peran"><?= e(Auth::ROLE_LABEL[$s['role']]) ?><?php if ($trained): ?><span class="cell-sub">Terlatih kasus anak</span><?php endif; ?></td>
              <td data-label="Status"><?= (int) $s['is_active'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge" data-status="ditutup">Nonaktif</span>' ?></td>
              <td data-label="Terakhir masuk" class="mono"><?= e($s['last_login_at'] ? mb_strtoupper(id_date($s['last_login_at'], true)) : 'BELUM PERNAH') ?></td>
              <td class="actions" data-label="Aksi">
                <?php if ($self): ?>
                  <span class="small muted">Akun kamu</span>
                <?php else: ?>
                  <div class="row">
                    <form method="post" action="<?= e(url('/admin/pengguna/' . $s['id'] . '/reset')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-xs" type="submit">Reset sandi</button></form>
                    <?php if ($isCompanion): ?>
                      <form method="post" action="<?= e(url('/admin/pengguna/' . $s['id'] . '/terlatih-anak')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="trained" value="<?= $trained ? '0' : '1' ?>">
                        <button class="btn btn-ghost btn-xs" type="submit"><?= $trained ? 'Cabut terlatih anak' : 'Tandai terlatih anak' ?></button>
                      </form>
                    <?php endif; ?>
                    <?php if ((int) $s['is_active']): ?>
                      <button class="btn btn-ghost btn-xs" type="button" data-open-dialog="dlg-off-<?= (int) $s['id'] ?>">Nonaktifkan</button>
                    <?php else: ?>
                      <form method="post" action="<?= e(url('/admin/pengguna/' . $s['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="active" value="1"><button class="btn btn-ghost btn-xs" type="submit">Aktifkan</button></form>
                    <?php endif; ?>
                  </div>
                  <?php if ((int) $s['is_active']): ?>
                    <dialog class="modal" id="dlg-off-<?= (int) $s['id'] ?>" aria-labelledby="dlg-off-<?= (int) $s['id'] ?>-t">
                      <form method="post" action="<?= e(url('/admin/pengguna/' . $s['id'] . '/status')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="active" value="0">
                        <div class="modal-body" style="text-align:left"><h2 id="dlg-off-<?= (int) $s['id'] ?>-t">Nonaktifkan <?= e($s['name']) ?>?</h2><p>Pengguna langsung dikeluarkan dan tidak bisa masuk lagi. Kasus aktifnya perlu ditugaskan ulang ke pendamping lain.</p></div>
                        <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Nonaktifkan</button></div>
                      </form>
                    </dialog>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="empty" data-empty hidden style="margin-top:12px"><?= icon('users') ?><h3>Tidak ada pengguna yang cocok</h3><p>Ubah kata kunci atau peran.</p></div>
    <p class="small muted" style="margin-top:14px"><?= (int) $survivors ?> akun penyintas terdaftar. Identitas mereka tidak ditampilkan di sini.</p>
  </div>

  <form class="panel" method="post" action="<?= e(url('/admin/pengguna')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2 class="panel-title">Tambah pendamping atau admin</h2></div>
    <div class="panel-body form-stack">
      <div class="field"><label class="label" for="name">Nama lengkap</label><input class="input" id="name" name="name" required maxlength="80" value="<?= e(old('name')) ?>" data-msg="Tulis nama lengkap."<?= field_aria('name') ?>><?= field_error('name') ?></div>
      <div class="field"><label class="label" for="email">Email</label><input class="input" type="email" id="email" name="email" required value="<?= e(old('email')) ?>" data-msg="Masukkan email untuk undangan."<?= field_aria('email') ?>><?= field_error('email') ?></div>
      <div class="field"><label class="label" for="role">Peran</label>
        <select class="select" id="role" name="role"<?= field_aria('role') ?>>
          <option value="companion"<?= old('role') === 'admin' ? '' : ' selected' ?>>Pendamping</option>
          <option value="admin"<?= old('role') === 'admin' ? ' selected' : '' ?>>Admin</option>
        </select><?= field_error('role') ?>
      </div>
      <div class="field"><label class="label" for="region">Wilayah kerja <span class="opt">(opsional)</span></label><input class="input" id="region" name="region" maxlength="100" value="<?= e(old('region')) ?>" placeholder="Contoh: Banyumas"></div>
      <div class="field" data-show-when="role=companion">
        <label class="check"><input type="checkbox" id="child_trained" name="child_trained" value="1"<?= checked(old('child_trained') === '1') ?>> <span>Terlatih menangani kasus anak</span></label>
        <p class="hint">Kasus anak hanya ditawarkan ke pendamping bertanda ini yang wilayah kerjanya sama dengan wilayah laporan. Penanda ini bisa diubah nanti dari daftar di samping.</p>
      </div>
      <p class="hint">Pengguna baru menerima tautan untuk membuat kata sandi sendiri, berlaku 3 hari.</p>
    </div>
    <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Kirim undangan</button></div>
  </form>
</div>
