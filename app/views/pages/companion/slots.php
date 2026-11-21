<?php /** @var array $slots */ ?>
<div class="app-page-head">
  <div><h1 class="app-title">Jadwal &amp; slot</h1><p class="app-sub">Slot yang kamu buka bisa dipesan oleh penyintas di kasusmu. Slot yang sudah dipesan tidak bisa dipesan orang lain.</p></div>
</div>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><h2 class="panel-title">Slot mendatang</h2></div>
    <?php if ($slots): ?>
      <div class="table-wrap table-cards" style="border:0;border-radius:0 0 6px 6px">
        <table class="table">
          <thead><tr><th scope="col">Tanggal</th><th scope="col">Waktu</th><th scope="col">Cara</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
          <tbody>
            <?php foreach ($slots as $sl): $t = strtotime($sl['starts_at']); ?>
              <tr>
                <td data-label="Tanggal" class="mono"><?= e(mb_strtoupper(mb_substr(ID_DAYS[(int) date('w', $t)], 0, 3) . ' ' . date('j', $t) . ' ' . ID_MONTHS[(int) date('n', $t)])) ?></td>
                <td data-label="Waktu" class="mono"><?= date('H.i', $t) ?> sampai <?= date('H.i', $t + (int) $sl['duration_min'] * 60) ?></td>
                <td data-label="Cara"><?= e(Slot::MODES[$sl['mode']]) ?><?= $sl['location'] ? '<span class="cell-sub">' . e($sl['location']) . '</span>' : '' ?></td>
                <td data-label="Status"><?= $sl['booking_id'] ? '<span class="badge" data-status="didampingi">Dipesan · ' . e($sl['report_code']) . '</span>' : '<span class="badge badge-plain">Terbuka</span>' ?></td>
                <td class="actions" data-label="Aksi">
                  <?php if ($sl['booking_id']): ?>
                    <button class="btn btn-ghost btn-xs" type="button" data-open-dialog="dlg-b-<?= (int) $sl['booking_id'] ?>">Batalkan sesi</button>
                    <dialog class="modal" id="dlg-b-<?= (int) $sl['booking_id'] ?>" aria-labelledby="dlg-b-<?= (int) $sl['booking_id'] ?>-t">
                      <form method="post" action="<?= e(url('/pendamping/sesi/' . $sl['booking_id'] . '/batal')) ?>">
                        <?= csrf_field() ?>
                        <div class="modal-body" style="text-align:left"><h2 id="dlg-b-<?= (int) $sl['booking_id'] ?>-t">Batalkan sesi <?= e(Slot::label($sl)) ?>?</h2><p>Penyintas <span class="mono"><?= e($sl['report_code']) ?></span> akan diberi tahu. Sebaiknya kirim pesan dan tawarkan waktu lain.</p></div>
                        <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Tetap jalan</button><button class="btn btn-danger btn-sm" type="submit">Batalkan sesi</button></div>
                      </form>
                    </dialog>
                  <?php else: ?>
                    <button class="btn btn-ghost btn-xs" type="button" data-open-dialog="dlg-s-<?= (int) $sl['id'] ?>"><?= icon('trash') ?> Hapus</button>
                    <dialog class="modal" id="dlg-s-<?= (int) $sl['id'] ?>" aria-labelledby="dlg-s-<?= (int) $sl['id'] ?>-t">
                      <form method="post" action="<?= e(url('/pendamping/slot/' . $sl['id'] . '/hapus')) ?>">
                        <?= csrf_field() ?>
                        <div class="modal-body" style="text-align:left"><h2 id="dlg-s-<?= (int) $sl['id'] ?>-t">Hapus slot <?= e(Slot::label($sl)) ?>?</h2><p>Slot yang belum dipesan langsung hilang dari pilihan penyintas.</p></div>
                        <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Batal</button><button class="btn btn-danger btn-sm" type="submit">Hapus slot</button></div>
                      </form>
                    </dialog>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="panel-body small muted">Belum ada slot. Buka slot di samping agar penyintas bisa memesan waktu bicara.</p>
    <?php endif; ?>
  </section>

  <form class="panel" method="post" action="<?= e(url('/pendamping/slot')) ?>" novalidate data-validate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2 class="panel-title">Buka slot baru</h2></div>
    <div class="panel-body form-stack">
      <?php if ($e = form_errors()['slot'] ?? null): ?><div class="alert alert-danger" role="alert"><?= icon('alert') ?><div><?= e($e) ?></div></div><?php endif; ?>
      <div class="field"><label class="label" for="d">Tanggal</label><input class="input" type="date" id="d" name="tanggal" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', time() + 60 * 86400) ?>" required value="<?= e(old('tanggal')) ?>" data-msg="Pilih tanggal."></div>
      <div class="field-row">
        <div class="field"><label class="label" for="s">Mulai</label><input class="input" type="time" id="s" name="jam" step="900" required value="<?= e(old('jam')) ?>" data-msg="Pilih jam mulai."></div>
        <div class="field"><label class="label" for="e">Durasi</label><select class="select" id="e" name="durasi"><?php foreach (Slot::DURATIONS as $m): ?><option value="<?= $m ?>"<?= selected(old('durasi', '45'), (string) $m) ?>><?= $m ?> menit</option><?php endforeach; ?></select></div>
      </div>
      <fieldset class="field">
        <legend class="label">Cara bertemu</legend>
        <div class="stack-sm">
          <?php foreach (Slot::MODES as $k => $v): ?>
            <label class="choice"><input type="radio" name="cara" value="<?= e($k) ?>"<?= checked(old('cara', 'suara') === $k) ?>><span class="choice-body"><span class="choice-title"><?= e($v) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="field" data-show-when="cara=tatap"><label class="label" for="loc">Lokasi</label><input class="input" id="loc" name="lokasi" maxlength="160" placeholder="Contoh: Ruang konseling UPTD PPA" data-cond-required value="<?= e(old('lokasi')) ?>" data-msg="Tulis lokasi pertemuan."></div>
    </div>
    <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit"><?= icon('plus') ?> Buka slot</button></div>
  </form>
</div>
