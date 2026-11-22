<?php
/** @var ?array $service  @var int $referrals */
$v = static fn (string $k, string $d = '') => old($k, $service !== null ? (string) ($service[$k] ?? $d) : $d);
$isVerified = old('_sent') !== '' ? old('is_verified') === '1' : (bool) ($service['is_verified'] ?? false);
$is24 = old('_sent') !== '' ? old('is_24h') === '1' : (bool) ($service['is_24h'] ?? false);
$action = $service ? url('/admin/layanan/' . $service['id']) : url('/admin/layanan');
?>
<a class="back-link" href="<?= e(url('/admin/layanan')) ?>"><?= icon('arrow-left') ?> Semua layanan</a>
<form method="post" action="<?= e($action) ?>" novalidate data-validate>
  <?= csrf_field() ?>
  <input type="hidden" name="_sent" value="1">
  <div class="app-page-head">
    <h1 class="app-title"><?= $service ? e($service['name']) : 'Tambah layanan' ?></h1>
    <button class="btn btn-yellow btn-sm" type="submit">Simpan layanan</button>
  </div>
  <div class="grid-2">
    <div class="panel panel-body form-stack">
      <div class="field"><label class="label" for="n">Nama layanan</label><input class="input" id="n" name="name" required maxlength="160" value="<?= e($v('name')) ?>" data-msg="Tulis nama layanan."<?= field_aria('name') ?>><?= field_error('name') ?></div>
      <div class="field-row">
        <div class="field"><label class="label" for="j">Jenis</label><select class="select" id="j" name="type"<?= field_aria('type') ?>><?php foreach (Service::TYPES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($v('type', 'uptd'), $k) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('type') ?></div>
        <div class="field"><label class="label" for="h">Jam layanan</label><input class="input" id="h" name="hours" maxlength="80" placeholder="Contoh: Senin sampai Jumat, 08.00 sampai 15.00" value="<?= e($v('hours')) ?>"<?= field_aria('hours') ?>><?= field_error('hours') ?></div>
      </div>
      <label class="check"><input type="checkbox" name="is_24h" value="1"<?= checked($is24) ?>> <span>Buka 24 jam</span></label>
      <div class="field-row">
        <div class="field"><label class="label" for="p">Provinsi</label><select class="select" id="p" name="province"<?= field_aria('province') ?>><option value="Nasional"<?= selected($v('province', 'Jawa Tengah'), 'Nasional') ?>>Nasional</option><?php foreach (Report::PROVINCES as $pr): ?><option<?= selected($v('province', 'Jawa Tengah'), $pr) ?>><?= e($pr) ?></option><?php endforeach; ?></select><?= field_error('province') ?></div>
        <div class="field"><label class="label" for="k">Kota/kabupaten</label><input class="input" id="k" name="city" required maxlength="80" value="<?= e($v('city')) ?>" placeholder="Isi Nasional untuk layanan nasional" data-msg="Tulis kota atau kabupaten."<?= field_aria('city') ?>><?= field_error('city') ?></div>
      </div>
      <div class="field"><label class="label" for="a">Alamat <span class="opt">(opsional)</span></label><textarea class="textarea textarea-sm" id="a" name="address" maxlength="500" placeholder="Isi setelah diverifikasi langsung ke layanan"><?= e($v('address')) ?></textarea></div>
      <div class="field-row">
        <div class="field"><label class="label" for="tel">Telepon</label><input class="input mono" id="tel" name="phone" type="tel" maxlength="30" value="<?= e($v('phone')) ?>"<?= field_aria('phone') ?>><?= field_error('phone') ?></div>
        <div class="field"><label class="label" for="wa">WhatsApp <span class="opt">(opsional)</span></label><input class="input mono" id="wa" name="whatsapp" type="tel" maxlength="30" value="<?= e($v('whatsapp')) ?>"<?= field_aria('whatsapp') ?>><?= field_error('whatsapp') ?></div>
      </div>
      <div class="field"><label class="label" for="tg">Jenis bantuan</label><input class="input" id="tg" name="tags" maxlength="255" value="<?= e($v('tags')) ?>" placeholder="Pendampingan, Psikologis, Hukum" aria-describedby="tg-hint"><p class="hint" id="tg-hint">Pisahkan dengan koma.</p></div>
    </div>
    <aside class="stack">
      <div class="panel panel-body form-stack">
        <fieldset class="field">
          <legend class="label">Verifikasi</legend>
          <label class="check"><input type="checkbox" name="is_verified" value="1"<?= checked($isVerified) ?>> <span>Kontak dan jam layanan sudah dicek langsung ke lembaga</span></label>
        </fieldset>
        <div class="field" data-show-when="is_verified=1"><label class="label" for="vd">Tanggal verifikasi</label><input class="input" id="vd" name="verified_at" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($v('verified_at', date('Y-m-d'))) ?>" data-cond-required data-msg="Isi tanggal verifikasi."<?= field_aria('verified_at') ?>><?= field_error('verified_at') ?></div>
        <div class="field" data-show-when="is_verified=1"><label class="label" for="vb">Cara verifikasi</label><input class="input" id="vb" name="verified_note" maxlength="255" placeholder="Contoh: telepon ke kantor, 20 Nov" value="<?= e($v('verified_note')) ?>" data-cond-required data-msg="Catat cara verifikasinya."<?= field_aria('verified_note') ?>><?= field_error('verified_note') ?></div>
        <div class="alert alert-warn"><?= icon('alert') ?><div>Nomor yang salah bisa membuat penyintas kehilangan kesempatan mendapat bantuan. Jangan tandai terverifikasi sebelum dicek.</div></div>
      </div>
      <?php if ($service): ?>
        <div class="panel panel-body stack-sm">
          <p class="small muted">Dipakai di <?= (int) $referrals ?> rujukan kasus.</p>
          <?php if ($referrals === 0): ?>
            <button class="btn btn-ghost btn-sm" type="button" data-open-dialog="dlg-del" style="justify-self:start"><?= icon('trash') ?> Hapus layanan</button>
          <?php else: ?>
            <p class="small muted">Layanan yang sudah dipakai untuk rujukan tidak bisa dihapus.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</form>
<?php if ($service && $referrals === 0): ?>
  <dialog class="modal" id="dlg-del" aria-labelledby="dlg-del-t">
    <form method="post" action="<?= e(url('/admin/layanan/' . $service['id'] . '/hapus')) ?>">
      <?= csrf_field() ?>
      <div class="modal-body"><h2 id="dlg-del-t">Hapus <?= e($service['name']) ?>?</h2><p>Layanan hilang dari direktori publik dan pilihan rujukan.</p></div>
      <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Hapus layanan</button></div>
    </form>
  </dialog>
<?php endif; ?>
