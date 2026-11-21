<?php
/**
 * Survivor view of one report (anonymous via code+PIN, or the account owner).
 * @var array $report  @var array $logs  @var ?array $companion  @var int $attachCount  @var array $notifs
 * @var array $upcoming  @var array $past  @var array $slots  @var ?array $service  @var int $unread  @var bool $accountView
 * @var ?array $childPanel  panel rujukan anak, hanya terisi untuk kasus anak (Req 4.4)
 * @var int $slaHours  batas waktu balasan untuk kasus ini, dalam jam (Req 6.2)
 */
$code = $report['code'];
$status = $report['status'];
$active = in_array($status, CaseWorkflow::ACTIVE, true);
$reached = [];
foreach ($logs as $l) {
    $reached[$l['to_status']] = $l;
}
$steps = $status === 'ditutup' ? ['diterima', 'ditinjau', 'ditutup'] : Report::TIMELINE;
if ($status === 'selesai' && !isset($reached['dirujuk'])) {
    $steps = array_values(array_diff($steps, ['dirujuk']));
}
$chatOpen = $active;
$chatNote = match (true) {
    $chatOpen => null,
    CaseWorkflow::isClosed($status) => 'Pendampingan sudah ' . mb_strtolower(Report::STATUS[$status]) . '. Riwayat pesan tetap bisa kamu baca.',
    default => 'Kamu bisa membalas setelah admin menugaskan pendamping. Halaman ini memberi tahu saat itu terjadi.',
};
?>
<?php if ($accountView): ?>
  <a class="back-link" href="<?= e(url('/penyintas/laporan')) ?>"><?= icon('arrow-left') ?> Laporan saya</a>
<?php endif; ?>

<div class="case-head">
  <div class="case-id">
    <h1 class="case-code"><?= e($code) ?></h1>
    <?= status_badge($status) ?>
    <div class="case-meta">
      <span><?= icon('calendar') ?>Dilaporkan <?= e(id_date($report['created_at'])) ?></span>
      <span><?= icon('user') ?>Pendamping: <?= $companion ? e(short_name($companion['name'])) : 'belum ditugaskan' ?></span>
      <?php /* Batas waktu balasan untuk kasus ini, dalam jam, dari konfigurasi SLA (Req 6.2). */ ?>
      <span><?= icon('clock') ?><?= te('track.sla', ['jam' => $slaHours]) ?></span>
      <span><?= icon('lock') ?>Terenkripsi</span>
    </div>
  </div>
  <?php if (!$accountView): ?>
    <button class="btn btn-ghost btn-sm" type="button" data-open-dialog="dlg-end">Tutup sesi laporan</button>
  <?php endif; ?>
</div>

<?php /* Panel rujukan anak di luar tab, supaya selalu terlihat tanpa klik (Req 4.4). */ ?>
<?php if (!empty($childPanel)): ?>
  <div style="margin-bottom:24px">
    <?php include APP_PATH . '/views/partials/child-referral.php'; ?>
  </div>
<?php endif; ?>

<div data-tabs>
  <div class="subtabs" role="tablist" aria-label="Bagian laporan">
    <button role="tab" id="tab-progres" aria-controls="progres" aria-selected="true">Perkembangan</button>
    <button role="tab" id="tab-pesan" aria-controls="pesan" aria-selected="false" tabindex="-1">Pesan<?= $unread ? ' <span class="tab-count">' . $unread . '</span><span class="sr-only"> belum dibaca</span>' : '' ?></button>
    <button role="tab" id="tab-jadwal" aria-controls="jadwal" aria-selected="false" tabindex="-1">Jadwal sesi</button>
    <button role="tab" id="tab-rujukan" aria-controls="rujukan" aria-selected="false" tabindex="-1">Rujukan</button>
  </div>

  <!-- Perkembangan -->
  <section class="subpanel" role="tabpanel" id="progres" aria-labelledby="tab-progres">
    <div class="grid-2">
      <div class="panel">
        <div class="panel-head"><h2 class="panel-title">Perjalanan laporan</h2></div>
        <div class="panel-body">
          <ol class="timeline">
            <?php foreach ($steps as $s):
                $log = $reached[$s] ?? null;
                $state = $s === $status ? 'is-current' : ($log ? 'is-done' : 'is-pending');
                if ($s === $status && CaseWorkflow::isClosed($s)) {
                    $state = 'is-done';
                }
                $body = $log['note'] ?? null;
                if ($s === 'didampingi' && $log && $companion && !$body) {
                    $body = short_name($companion['name']) . ' ditugaskan sebagai pendampingmu.';
                }
                $body ??= Report::STATUS_HELP[$s];
            ?>
              <li class="<?= $state ?>">
                <span class="tl-dot"><?= $state === 'is-done' ? icon('check') : '' ?></span>
                <div>
                  <p class="tl-title"><?= e(Report::STATUS[$s]) ?></p>
                  <?php if ($log): ?><p class="tl-meta"><?= e(mb_strtoupper(id_date($log['created_at'], true))) ?></p><?php endif; ?>
                  <p class="tl-body"><?= nl2br(e($body)) ?></p>
                  <?php if ($s === 'didampingi' && $state === 'is-current'): ?>
                    <a class="text-link" href="#pesan" data-open-tab="pesan" style="margin-top:12px">Buka pesan <?= icon('arrow-right') ?></a>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
      <div class="stack">
        <div class="panel">
          <div class="panel-head"><h2 class="panel-title">Ringkasan</h2></div>
          <div class="panel-body">
            <dl class="kv">
              <div><dt>Jenis</dt><dd><?= e(Report::CATEGORIES[$report['category']]) ?></dd></div>
              <div><dt>Wilayah</dt><dd><?= e(Report::area($report)) ?></dd></div>
              <div><dt>Kontak</dt><dd><?= e(Report::channelLabels($report)) ?></dd></div>
              <div><dt>Bukti</dt><dd><?= $attachCount ? $attachCount . ' file' : 'Tidak ada' ?></dd></div>
            </dl>
          </div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2 class="panel-title">Pemberitahuan</h2></div>
          <?php if ($notifs): ?>
            <ul class="notif-list">
              <?php foreach ($notifs as $n): $anchor = (string) parse_url($n['link'], PHP_URL_FRAGMENT); ?>
                <li><a href="#<?= e($anchor ?: 'progres') ?>"<?= $anchor ? ' data-open-tab="' . e($anchor) . '"' : '' ?> class="<?= $n['read_at'] === null ? 'is-unread' : '' ?>"><span class="notif-dot" aria-hidden="true"></span><span><span class="notif-title"><?= e($n['title']) ?><?= $n['read_at'] === null ? '<span class="sr-only"> (baru)</span>' : '' ?></span><span class="notif-meta"><?= e(Notifier::ago($n['created_at'])) ?></span></span></a></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="panel-body small muted">Belum ada pemberitahuan. Perubahan status, pesan baru, dan jadwal akan tercatat di sini.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Pesan -->
  <section class="subpanel" role="tabpanel" id="pesan" aria-labelledby="tab-pesan" hidden>
    <div class="chat" data-chat data-endpoint="<?= e(url('/api/kasus/' . $code . '/pesan')) ?>">
      <div class="chat-head">
        <div class="chat-who">
          <span class="avatar" aria-hidden="true"><?= $companion ? e(initials($companion['name'])) : '?' ?></span>
          <div><strong><?= $companion ? e(short_name($companion['name'])) : 'Menunggu pendamping' ?></strong><small><?= $companion ? 'Pendampingmu untuk laporan ini' : 'Admin belum menugaskan pendamping' ?></small></div>
        </div>
        <span class="sync" data-sync><?= icon('refresh') ?> Memuat…</span>
      </div>
      <div class="chat-log" aria-live="polite" aria-label="Riwayat pesan" tabindex="0"></div>
      <?php if ($chatOpen): ?>
        <form class="composer" novalidate>
          <label class="sr-only" for="msg">Tulis pesan</label>
          <textarea class="textarea" id="msg" rows="1" maxlength="<?= Message::MAX_LEN ?>" placeholder="Tulis pesan… (Enter untuk kirim, Shift+Enter untuk baris baru)"></textarea>
          <button class="btn btn-ink" type="submit"><?= icon('send') ?><span>Kirim</span></button>
        </form>
      <?php endif; ?>
      <p class="chat-note error" data-chat-error hidden role="alert"></p>
      <p class="chat-note"><?= icon('lock') ?><?= e($chatNote ?? 'Pesan dienkripsi. Admin tidak bisa membacanya.') ?></p>
    </div>
  </section>

  <!-- Jadwal -->
  <section class="subpanel" role="tabpanel" id="jadwal" aria-labelledby="tab-jadwal" hidden>
    <div class="grid-2">
      <?php if ($active && $companion): ?>
        <form class="panel" method="post" action="<?= e(url('/kasus/' . $code . '/jadwal')) ?>" data-slots>
          <?= csrf_field() ?>
          <div class="panel-head"><h2 class="panel-title">Pilih waktu bicara</h2><span class="small muted">Waktu dalam WIB</span></div>
          <div class="panel-body slot-days">
            <?php if ($slots):
                $byDay = [];
                foreach ($slots as $s) {
                    $byDay[substr($s['starts_at'], 0, 10)][] = $s;
                }
                foreach ($byDay as $day => $items): $t = strtotime($day); ?>
                  <fieldset class="slot-day" style="border:0;padding:0;margin:0">
                    <legend style="padding:0"><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> <span class="mono"><?= e(date('j', $t) . ' ' . mb_strtoupper(ID_MONTHS[(int) date('n', $t)])) ?></span></h3></legend>
                    <div class="slot-list">
                      <?php foreach ($items as $s): ?>
                        <label class="slot"><input type="radio" name="slot_id" value="<?= (int) $s['id'] ?>"><span><?= e(date('H.i', strtotime($s['starts_at']))) ?><small><?= e(Slot::MODES[$s['mode']]) ?><?= $s['location'] ? ' · ' . e($s['location']) : '' ?></small></span></label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                <?php endforeach; ?>
            <?php else: ?>
              <p class="small muted">Pendampingmu belum membuka waktu kosong. Tulis di tab Pesan jam berapa kamu bisa.</p>
            <?php endif; ?>
          </div>
          <div class="panel-foot row-between">
            <span class="small muted">Bisa dibatalkan hingga <?= Booking::SURVIVOR_CANCEL_HOURS ?> jam sebelum sesi.</span>
            <button class="btn btn-yellow btn-sm" type="submit" data-book<?= $slots ? ' disabled' : ' disabled hidden' ?>>Pesan sesi</button>
          </div>
        </form>
      <?php else: ?>
        <div class="empty">
          <?= icon('calendar') ?>
          <h3><?= CaseWorkflow::isClosed($status) ? 'Pendampingan sudah ' . e(mb_strtolower(Report::STATUS[$status])) : 'Jadwal dibuka setelah pendamping ditugaskan' ?></h3>
          <p><?= CaseWorkflow::isClosed($status) ? 'Riwayat sesimu tetap tersimpan di sebelah.' : 'Setelah pendamping ditugaskan, waktu kosongnya muncul di sini.' ?></p>
        </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-head"><h2 class="panel-title">Sesi kamu</h2></div>
        <div class="panel-body">
          <?php if (!$upcoming && !$past): ?>
            <p class="small muted">Belum ada sesi.</p>
          <?php endif; ?>
          <?php foreach ($upcoming as $b): $t = strtotime($b['starts_at']); ?>
            <div class="session">
              <span class="session-date"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span>
              <div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?> sampai <?= date('H.i', $t + (int) $b['duration_min'] * 60) ?></h3><p><?= e(Slot::MODES[$b['mode']]) ?> dengan <?= e(short_name((string) $b['companion_name'])) ?><?= $b['location'] ? ' · ' . e($b['location']) : '' ?></p></div>
              <button class="btn btn-ghost btn-xs" type="button" data-open-dialog="dlg-cancel-<?= (int) $b['id'] ?>">Batalkan</button>
            </div>
            <dialog class="modal" id="dlg-cancel-<?= (int) $b['id'] ?>" aria-labelledby="dlg-cancel-<?= (int) $b['id'] ?>-t">
              <form method="post" action="<?= e(url('/kasus/' . $code . '/jadwal/' . $b['id'] . '/batal')) ?>">
                <?= csrf_field() ?>
                <div class="modal-body"><h2 id="dlg-cancel-<?= (int) $b['id'] ?>-t">Batalkan sesi <?= e(Slot::label($b)) ?>?</h2><p>Pendampingmu akan diberi tahu, dan waktu ini bisa dipesan lagi.</p></div>
                <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Tetap ikut sesi</button><button class="btn btn-danger btn-sm" type="submit">Batalkan sesi</button></div>
              </form>
            </dialog>
          <?php endforeach; ?>
          <?php foreach (array_slice($past, 0, 5) as $b): $t = strtotime($b['starts_at']); ?>
            <div class="session">
              <span class="session-date is-past"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span>
              <div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?></h3><p><?= e(Booking::stateLabel($b)) ?> · <?= e(Slot::MODES[$b['mode']]) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Rujukan -->
  <section class="subpanel" role="tabpanel" id="rujukan" aria-labelledby="tab-rujukan" hidden>
    <?php if ($service): ?>
      <div class="grid-2">
        <ul class="service-list">
          <?php $s = $service; include APP_PATH . '/views/partials/service-item.php'; ?>
        </ul>
        <div class="alert alert-info">
          <?= icon('info') ?>
          <div><strong>Kamu tidak wajib datang.</strong>Jika mau, pendampingmu bisa menelepon lebih dulu atau menemanimu datang. Bicarakan lewat tab Pesan.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="empty">
        <?= icon('building') ?>
        <h3>Belum ada rujukan</h3>
        <p>Jika kamu butuh psikolog, pemeriksaan medis, atau bantuan hukum, pendampingmu akan menuliskan lembaganya di sini. Rujukan hanya dibuat setelah kamu setuju.</p>
        <a class="text-link" href="<?= e(url('/layanan')) ?>">Lihat direktori layanan <?= icon('arrow-right') ?></a>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if (!$accountView): ?>
  <dialog class="modal" id="dlg-end" aria-labelledby="dlg-end-t">
    <form method="post" action="<?= e(url('/kasus/' . $code . '/tutup')) ?>">
      <?= csrf_field() ?>
      <div class="modal-body"><h2 id="dlg-end-t">Tutup sesi laporan di perangkat ini?</h2><p>Laporan tidak terhapus. Browser ini hanya lupa bahwa kamu sudah memasukkan PIN, jadi untuk membukanya lagi kamu perlu kode dan PIN.</p></div>
      <div class="modal-actions"><button class="btn btn-ghost btn-sm" type="button" data-close-dialog>Tetap di sini</button><button class="btn btn-ink btn-sm" type="submit">Tutup sesi</button></div>
    </form>
  </dialog>
<?php endif; ?>
