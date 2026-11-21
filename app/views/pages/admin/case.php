<?php
/** @var array $report  @var ?array $companion  @var array $companions  @var array $assignment  @var array $logs  @var array $access  @var int $attachCount */
$s = $report['status'];
$canAssign = in_array($s, ['diterima', 'ditinjau'], true);
$canReassign = in_array($s, CaseWorkflow::ACTIVE, true);
$canClose = in_array($s, ['diterima', 'ditinjau'], true);
// Req 5.6: laporan saksi diberi label jelas, termasuk di halaman pemilahan admin.
$byWitness = (string) ($report['reporter_type'] ?? 'korban') === ConsentGuard::WITNESS;
// Req 4.8 sampai 4.10: kasus anak dan daftar kandidat dari Assignment.
$isChild = (bool) $report['is_child'];
$needsConfirm = !empty($assignment['needsConfirm']);
?>
<a class="back-link" href="<?= e(url('/admin/antrean')) ?>"><?= icon('arrow-left') ?> Antrean kasus</a>
<div class="case-head">
  <div class="case-id">
    <h1 class="case-code"><?= e($report['code']) ?></h1>
    <?= status_badge($s) ?>
    <?= urgency_badge($report['urgency'], 'Urgensi ') ?>
    <?php if ((int) $report['in_danger']): ?><span class="badge" data-urgency="tinggi">Menyatakan dalam bahaya</span><?php endif; ?>
    <?php if ($isChild): ?><span class="badge" data-urgency="tinggi">Anak</span><?php endif; ?>
    <?php if ($byWitness): ?><span class="badge">Dilaporkan oleh saksi</span><?php endif; ?>
    <div class="case-meta">
      <span><?= icon('user') ?><?= $report['user_id'] ? 'Berakun' : 'Anonim' ?></span>
      <span><?= icon('clock') ?>Masuk <?= e(id_date($report['created_at'], true)) ?></span>
      <span><?= icon('shield-check') ?>Akses ini tercatat</span>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Data untuk pemilahan</h2></div>
      <div class="panel-body stack">
        <dl class="kv">
          <div><dt>Jenis</dt><dd><?= e(Report::CATEGORIES[$report['category']]) ?></dd></div>
          <div><dt>Masih berlangsung</dt><dd><?= e(Report::ONGOING[$report['ongoing']]) ?></dd></div>
          <div><dt>Wilayah</dt><dd><?= e(Report::area($report)) ?></dd></div>
          <div><dt>Jenis tempat</dt><dd><?= e($report['place_type'] ?: 'Tidak disebutkan') ?></dd></div>
          <div><dt>Kontak pilihan</dt><dd><?= e(Report::channelLabels($report)) ?></dd></div>
          <div><dt>Bukti</dt><dd><?= $attachCount ? $attachCount . ' file' : 'Tidak ada' ?></dd></div>
          <div><dt>Pendamping</dt><dd><?= $companion ? e($companion['name']) : 'Belum ada' ?></dd></div>
          <?php if ($s === 'ditutup'): ?><div class="full"><dt>Alasan ditutup</dt><dd><?= e((string) $report['closed_reason']) ?></dd></div><?php endif; ?>
        </dl>
        <div class="alert alert-info">
          <?= icon('lock') ?>
          <div><strong>Kronologi dan kontak tidak ditampilkan untuk admin.</strong>Isinya terbuka hanya untuk pendamping yang kamu tugaskan.</div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Riwayat status</h2></div>
      <div class="panel-body">
        <ol class="timeline">
          <?php foreach (array_reverse($logs) as $i => $l): ?>
            <li class="<?= $i === 0 ? 'is-current' : 'is-done' ?>"><span class="tl-dot"><?= $i === 0 ? '' : icon('check') ?></span><div><p class="tl-title"><?= e(Report::STATUS[$l['to_status']]) ?></p><p class="tl-meta"><?= e(mb_strtoupper(id_date($l['created_at'], true))) ?> · <?= e(mb_strtoupper($l['actor_name'] ?? 'Sistem')) ?></p></div></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Riwayat akses</h2><a class="link-btn" href="<?= e(url('/admin/audit', ['q' => $report['code']])) ?>">Audit log</a></div>
      <div class="table-wrap" style="border:0;border-radius:0 0 6px 6px">
        <table class="table">
          <thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($access as $a): ?>
              <tr><td class="mono"><?= e(mb_strtoupper(id_date($a['created_at'], true))) ?></td><td><?= e($a['actor_label']) ?></td><td><?= e(AuditLog::label($a['action'])) ?><?= $a['detail'] ? '<span class="cell-sub">' . e($a['detail']) . '</span>' : '' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <aside class="stack">
    <?php if ($canAssign || $canReassign): ?>
      <form class="panel" method="post" action="<?= e(url('/admin/kasus/' . $report['code'] . '/tugaskan')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="panel-head"><h2 class="panel-title"><?= $canAssign ? 'Tinjau &amp; tugaskan' : 'Tugaskan ulang' ?></h2></div>
        <div class="panel-body form-stack">
          <fieldset class="field">
            <legend class="label">Urgensi</legend>
            <div class="chips">
              <?php foreach (Report::URGENCY as $k => $v): ?>
                <label class="choice"><input type="radio" name="urgensi" value="<?= e($k) ?>"<?= checked(old('urgensi', $report['urgency']) === $k) ?>><span class="choice-body" style="padding-block:10px"><span class="choice-title"><?= e($v) ?></span></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php if (!empty($assignment['warning'])): ?>
            <div class="alert alert-warn" role="alert">
              <?= icon('alert') ?>
              <div><strong>Tidak ada pendamping terlatih kasus anak di wilayah laporan.</strong>Daftar di bawah berisi semua pendamping aktif, yang wilayahnya sama ditampilkan lebih dulu. Penugasan baru tersimpan setelah kamu mencentang konfirmasi.</div>
            </div>
          <?php endif; ?>
          <div class="field">
            <label class="label" for="pd">Pendamping</label>
            <select class="select" id="pd" name="pendamping" required data-msg="Pilih pendamping yang akan menangani."<?= field_aria('pendamping', 'pd-hint') ?>>
              <option value="">Pilih pendamping</option>
              <?php foreach ($companions as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= selected(old('pendamping', (string) $report['assigned_to']), (string) $c['id']) ?>><?= e($c['name']) ?> · <?= (int) $c['active'] ?> kasus aktif<?= $c['region'] ? ' · ' . e($c['region']) : '' ?><?= !empty($c['child_trained']) ? ' · terlatih kasus anak' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint" id="pd-hint">
              <?php if ($isChild && !$needsConfirm): ?>
                Kasus anak hanya ditawarkan ke pendamping terlatih kasus anak di wilayah laporan, diurutkan dari beban paling ringan.
              <?php else: ?>
                Pendamping di wilayah yang sama ditampilkan lebih dulu, lalu yang bebannya paling ringan.
              <?php endif; ?>
            </p>
            <?= field_error('pendamping') ?>
            <?php if (!$companions): ?>
              <p class="small muted">Belum ada pendamping aktif yang bisa ditugaskan. Tambahkan atau aktifkan pendamping di <a href="<?= e(url('/admin/pengguna')) ?>">Pengguna</a> lebih dulu.</p>
            <?php endif; ?>
          </div>
          <?php if ($needsConfirm): ?>
            <div class="field">
              <label class="check"><input type="checkbox" name="confirm" value="1" required data-msg="Centang konfirmasi penugasan kasus anak."> <span>Saya tahu pendamping ini belum terlatih kasus anak di wilayah laporan, dan saya tetap menugaskannya.</span></label>
            </div>
          <?php endif; ?>
          <div class="field">
            <label class="label" for="nt">Catatan untuk pendamping <span class="opt">(opsional)</span></label>
            <textarea class="textarea textarea-sm" id="nt" name="catatan" maxlength="500" placeholder="Contoh: Penyintas menyatakan dalam bahaya. Sapa dalam 1 jam."><?= e(old('catatan')) ?></textarea>
          </div>
        </div>
        <div class="panel-foot row-between">
          <?php if ($canClose): ?><button class="btn btn-ghost btn-sm" type="button" data-open-dialog="dlg-close">Tutup laporan</button><?php else: ?><span></span><?php endif; ?>
          <button class="btn btn-yellow btn-sm" type="submit"><?= $canAssign ? 'Tugaskan' : 'Simpan' ?></button>
        </div>
      </form>
    <?php else: ?>
      <div class="alert alert-info"><?= icon('info') ?><div>Kasus ini sudah <?= e(mb_strtolower(Report::STATUS[$s])) ?>. Tidak ada tindakan admin yang tersisa.</div></div>
    <?php endif; ?>
    <?php if ((int) $report['in_danger']): ?>
      <div class="alert alert-danger">
        <?= icon('alert') ?>
        <div><strong>Prioritaskan.</strong>Penyintas memilih "Ya, saya butuh bantuan segera". Laporan ini ditampilkan paling atas di antrean.</div>
      </div>
    <?php endif; ?>
  </aside>
</div>

<?php if ($canClose): ?>
  <dialog class="modal" id="dlg-close" aria-labelledby="dlg-close-t">
    <form method="post" action="<?= e(url('/admin/kasus/' . $report['code'] . '/tutup')) ?>" novalidate data-validate>
      <?= csrf_field() ?>
      <div class="modal-body">
        <h2 id="dlg-close-t">Tutup laporan tanpa pendampingan?</h2>
        <p>Gunakan hanya bila laporan tidak bisa ditindaklanjuti, misalnya duplikat atau spam. Penyintas akan melihat alasannya.</p>
        <div class="field"><label class="label" for="rs">Alasan</label><textarea class="textarea textarea-sm" id="rs" name="alasan" required minlength="10" maxlength="500" data-msg="Tulis alasan penutupan."></textarea></div>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Tutup laporan</button></div>
    </form>
  </dialog>
<?php endif; ?>
