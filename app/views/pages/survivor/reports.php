<?php /** @var array $reports */ ?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Laporan saya</h1>
    <p class="app-sub">Semua laporan yang kamu kirim dengan akun ini. Laporan anonim bisa ditautkan ke akun lewat kode dan PIN.</p>
  </div>
  <div class="row">
    <button class="btn btn-ghost btn-sm" type="button" data-open-dialog="dlg-link"><?= icon('plus') ?> Tautkan laporan anonim</button>
    <a class="btn btn-yellow btn-sm" href="<?= e(url('/lapor')) ?>">Buat laporan</a>
  </div>
</div>

<?php if ($reports): ?>
  <div class="table-wrap table-cards">
    <table class="table">
      <thead><tr><th scope="col">Kode</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col">Pendamping</th><th scope="col">Diperbarui</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($reports as $r): $open = !CaseWorkflow::isClosed($r['status']); ?>
          <tr>
            <td data-label="Kode" class="mono"><a class="row-link" href="<?= e(url('/kasus/' . $r['code'])) ?>"><?= e($r['code']) ?></a></td>
            <td data-label="Jenis"><?= e(Report::CATEGORIES[$r['category']]) ?></td>
            <td data-label="Status"><?= status_badge($r['status']) ?></td>
            <td data-label="Pendamping"><?= $r['companion_name'] ? e(short_name($r['companion_name'])) : '<span class="muted">Belum ada</span>' ?></td>
            <td data-label="Diperbarui" class="mono"><?= e(mb_strtoupper(id_date($r['updated_at']))) ?></td>
            <td class="actions" data-label="Aksi"><a class="btn <?= $open ? 'btn-ink' : 'btn-ghost' ?> btn-xs" href="<?= e(url('/kasus/' . $r['code'])) ?>"><?= $open ? 'Buka' : 'Lihat' ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="empty">
    <?= icon('file') ?>
    <h3>Belum ada laporan di akun ini</h3>
    <p>Laporan yang dikirim sambil masuk ke akun ini muncul di sini. Jika dulu kamu melapor tanpa akun, tautkan dengan kode dan PIN-nya.</p>
    <a class="btn btn-ink btn-sm" href="<?= e(url('/lapor')) ?>">Buat laporan</a>
  </div>
<?php endif; ?>

<dialog class="modal" id="dlg-link" aria-labelledby="dlg-link-t">
  <form method="post" action="<?= e(url('/penyintas/laporan/tautkan')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="modal-body">
      <h2 id="dlg-link-t">Tautkan laporan anonim</h2>
      <p>Masukkan kode dan PIN laporan yang dulu kamu kirim tanpa akun. Pesan dan jadwalnya akan ikut pindah ke akunmu.</p>
      <div class="field"><label class="label" for="lk">Kode laporan</label><input class="input mono" id="lk" name="kode" placeholder="HV-XXXXXX" required pattern="HV-[A-Z0-9]{6}" data-uppercase maxlength="9" autocomplete="off" data-msg="Masukkan kode laporan." data-msg-pattern="Contoh: HV-9B2X6P."></div>
      <div class="field"><label class="label" for="lp">PIN</label><input class="input mono" id="lp" name="pin" type="password" inputmode="numeric" maxlength="6" required pattern="[0-9]{6}" data-digits autocomplete="off" data-msg="Masukkan PIN 6 digit."></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-ink btn-sm" type="submit">Tautkan</button></div>
  </form>
</dialog>
