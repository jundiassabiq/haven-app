<?php
/** @var array $user  @var array $logins */
$isSurvivor = $user['role'] === 'survivor';
?>
<div class="app-page-head">
  <div>
    <h1 class="app-title">Profil &amp; keamanan</h1>
    <p class="app-sub"><?= $isSurvivor ? 'Atur cara pendamping menghubungimu dan amankan akunmu.' : 'Perbarui data akun dan kata sandimu.' ?></p>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <form class="panel" method="post" action="<?= e(url('/akun/profil')) ?>" novalidate data-validate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2 class="panel-title">Profil</h2></div>
      <div class="panel-body form-stack">
        <div class="field-row">
          <div class="field">
            <label class="label" for="name"><?= $isSurvivor ? 'Nama panggilan' : 'Nama' ?></label>
            <input class="input" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" required maxlength="<?= $isSurvivor ? 40 : 80 ?>" autocomplete="nickname" data-msg="Nama tidak boleh kosong."<?= field_aria('name') ?>>
            <?= field_error('name') ?>
          </div>
          <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" required autocomplete="email"<?= field_aria('email') ?>>
            <?= field_error('email') ?>
          </div>
        </div>
        <?php if ($isSurvivor): ?>
          <fieldset class="field">
            <legend class="label">Boleh dihubungi lewat</legend>
            <div class="check-list">
              <label class="check"><input type="checkbox" checked disabled> <span>Pesan aman di Haven (selalu aktif)</span></label>
              <label class="check"><input type="checkbox" name="allow_email" value="1"<?= (int) $user['allow_email'] ? ' checked' : '' ?>> <span>Email (tanpa isi pesan, hanya pemberitahuan)</span></label>
              <label class="check"><input type="checkbox" name="allow_whatsapp" value="1"<?= (int) $user['allow_whatsapp'] ? ' checked' : '' ?>> <span>WhatsApp</span></label>
            </div>
          </fieldset>
          <div class="field">
            <label class="label" for="safe_time">Waktu aman dihubungi <span class="opt">(opsional)</span></label>
            <input class="input" id="safe_time" name="safe_time" maxlength="120" value="<?= e(old('safe_time', (string) $user['safe_time'])) ?>" placeholder="Contoh: hari kerja setelah pukul 19.00"<?= field_aria('safe_time') ?>>
            <?= field_error('safe_time') ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Simpan profil</button></div>
    </form>

    <form class="panel" id="kata-sandi" method="post" action="<?= e(url('/akun/kata-sandi')) ?>" novalidate data-validate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2 class="panel-title">Ganti kata sandi</h2></div>
      <div class="panel-body form-stack">
        <div class="field">
          <label class="label" for="current_password">Kata sandi saat ini</label>
          <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password" data-msg="Masukkan kata sandi saat ini."<?= field_aria('current_password') ?>>
          <?= field_error('current_password') ?>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="label" for="password">Kata sandi baru</label>
            <input class="input" type="password" id="password" name="password" minlength="8" required autocomplete="new-password" data-msg="Minimal 8 karakter."<?= field_aria('password') ?>>
            <?= field_error('password') ?>
          </div>
          <div class="field">
            <label class="label" for="password_confirm">Ulangi kata sandi baru</label>
            <input class="input" type="password" id="password_confirm" name="password_confirm" required data-match="password" autocomplete="new-password" data-msg="Ketik ulang kata sandi baru."<?= field_aria('password_confirm') ?>>
            <?= field_error('password_confirm') ?>
          </div>
        </div>
      </div>
      <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Perbarui kata sandi</button></div>
    </form>
  </div>

  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Aktivitas masuk</h2></div>
      <div class="panel-body">
        <?php if ($logins): ?>
          <dl class="kv kv-1">
            <?php foreach ($logins as $i => $l): ?>
              <div><dt><?= $i === 0 ? 'Terakhir' : 'Sebelumnya' ?></dt><dd><?= e(ua_summary($l['user_agent'])) ?> · <span class="mono"><?= e(mb_strtoupper(id_date($l['created_at'], true))) ?></span></dd></div>
            <?php endforeach; ?>
          </dl>
        <?php else: ?>
          <p class="small muted">Belum ada catatan masuk.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/akun/keluarkan-sesi')) ?>" style="margin-top:20px">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">Keluarkan sesi lain</button>
        </form>
      </div>
    </section>

    <?php if ($isSurvivor): ?>
      <section class="panel" style="border-color:#F0C3BF">
        <div class="panel-head"><h2 class="panel-title">Hapus akun</h2></div>
        <div class="panel-body stack-sm">
          <p class="small" style="color:var(--ink-2)">Akun, laporan, dan pesanmu akan dihapus permanen setelah 7 hari. Selama itu kamu masih bisa membatalkannya dengan masuk kembali.</p>
          <button class="btn btn-danger btn-sm" type="button" data-open-dialog="dlg-del" style="justify-self:start">Hapus akun saya</button>
        </div>
      </section>

      <dialog class="modal" id="dlg-del" aria-labelledby="dlg-del-t">
        <form method="post" action="<?= e(url('/akun/hapus')) ?>" novalidate data-validate>
          <?= csrf_field() ?>
          <div class="modal-body">
            <h2 id="dlg-del-t">Hapus akun dan semua laporanmu?</h2>
            <p>Setelah 7 hari, data tidak bisa dipulihkan, termasuk percakapan dengan pendamping.</p>
            <div class="field"><label class="label" for="confirm">Ketik <span class="mono">HAPUS</span> untuk mengonfirmasi</label><input class="input mono" id="confirm" name="confirm" required pattern="HAPUS" data-uppercase autocomplete="off" data-msg="Ketik HAPUS untuk melanjutkan." data-msg-pattern="Ketik HAPUS dengan huruf besar."></div>
          </div>
          <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Hapus akun</button></div>
        </form>
      </dialog>
    <?php endif; ?>
  </div>
</div>
