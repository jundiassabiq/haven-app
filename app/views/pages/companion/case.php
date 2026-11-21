<?php
/**
 * @var array $report  @var string $chronology  @var array $contact  @var array $attachments  @var array $notes
 * @var array $logs  @var array $upcoming  @var array $services  @var ?array $service  @var int $unread  @var array $next
 * @var ?array $consent
 */
$code = $report['code'];
$s = $report['status'];
$active = in_array($s, CaseWorkflow::ACTIVE, true);
$errs = form_errors();
$noteErr = isset($errs['body']);
// Laporan saksi: korban harus menyetujui tindak lanjut sebelum kasus dirujuk (Req 5.6, 5.8).
$byWitness = (string) ($report['reporter_type'] ?? 'korban') === ConsentGuard::WITNESS;
$needConsent = $byWitness && $consent === null;
?>
<a class="back-link" href="<?= e(url('/pendamping/kasus')) ?>"><?= icon('arrow-left') ?> Kasus saya</a>
<div class="case-head">
  <div class="case-id">
    <h1 class="case-code"><?= e($code) ?></h1>
    <?= status_badge($s) ?>
    <?= urgency_badge($report['urgency'], 'Urgensi ') ?>
    <?php if ((int) $report['in_danger']): ?><span class="badge" data-urgency="tinggi">Menyatakan dalam bahaya</span><?php endif; ?>
    <?php if ($byWitness): ?><span class="badge">Dilaporkan oleh saksi</span><?php endif; ?>
    <div class="case-meta">
      <span><?= icon('user') ?>Penyintas: <?= $report['user_id'] ? 'berakun' : 'anonim' ?></span>
      <span><?= icon('calendar') ?>Dilaporkan <?= e(id_date($report['created_at'])) ?></span>
      <span><?= icon('shield-check') ?>Akses ini tercatat · <?= e(id_date(now(), true)) ?></span>
    </div>
  </div>
</div>

<div class="grid-2">
  <div data-tabs>
    <div class="subtabs" role="tablist" aria-label="Bagian kasus">
      <button role="tab" id="t-r" aria-controls="ringkasan" aria-selected="true">Laporan</button>
      <button role="tab" id="t-p" aria-controls="pesan" aria-selected="false" tabindex="-1">Pesan<?= $unread ? ' <span class="tab-count">' . $unread . '</span><span class="sr-only"> belum dibaca</span>' : '' ?></button>
      <button role="tab" id="t-c" aria-controls="catatan" aria-selected="false" tabindex="-1">Catatan internal</button>
      <button role="tab" id="t-j" aria-controls="riwayat" aria-selected="false" tabindex="-1">Riwayat status</button>
    </div>

    <section class="subpanel stack" role="tabpanel" id="ringkasan" aria-labelledby="t-r">
      <div class="panel">
        <div class="panel-head"><h2 class="panel-title">Isi laporan</h2><span class="small muted row" style="gap:6px"><?= icon('lock') ?>Didekripsi untukmu</span></div>
        <div class="panel-body stack">
          <dl class="kv">
            <div><dt>Jenis</dt><dd><?= e(Report::CATEGORIES[$report['category']]) ?></dd></div>
            <div><dt>Masih berlangsung</dt><dd><?= e(Report::ONGOING[$report['ongoing']]) ?></dd></div>
            <div><dt>Tanggal</dt><dd class="mono"><?= $report['incident_date'] ? '± ' . e(mb_strtoupper(id_date($report['incident_date']))) : 'Tidak disebutkan' ?></dd></div>
            <div><dt>Tempat</dt><dd><?= e($report['place_type'] ?: 'Tidak disebutkan') ?></dd></div>
            <div><dt>Wilayah</dt><dd><?= e(Report::area($report)) ?></dd></div>
            <div><dt>Hubungan dengan pelaku</dt><dd><?= e($report['perpetrator_relation'] ?: 'Tidak disebutkan') ?></dd></div>
          </dl>
          <div>
            <p class="label" style="margin-bottom:8px">Kronologi</p>
            <p class="secret-text"><?= e($chronology) ?></p>
          </div>
          <div>
            <p class="label" style="margin-bottom:8px">Bukti</p>
            <?php if ($attachments): ?>
              <ul class="file-list">
                <?php foreach ($attachments as $i => $a): ?>
                  <li><?= icon('paperclip') ?><span class="name">bukti-<?= $i + 1 ?>.<?= e(Upload::TYPES[$a['mime']] ?? 'bin') ?></span><span class="size"><?= e(number_format($a['size_bytes'] / 1024, 0, ',', '.')) ?> KB</span><a class="link-btn" href="<?= e(url('/lampiran/' . $a['id'])) ?>" target="_blank" rel="noopener noreferrer">Lihat</a></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?><p class="small muted">Tidak ada bukti dilampirkan.</p><?php endif; ?>
          </div>
          <dl class="kv">
            <div><dt>Kontak pilihan</dt><dd><?= e(Report::channelLabels($report)) ?></dd></div>
            <?php if (!empty($contact['email'])): ?><div><dt>Email</dt><dd><?= e($contact['email']) ?></dd></div><?php endif; ?>
            <?php if (!empty($contact['wa'])): ?><div><dt>WhatsApp</dt><dd class="mono"><?= e($contact['wa']) ?></dd></div><?php endif; ?>
          </dl>
        </div>
      </div>
    </section>

    <section class="subpanel" role="tabpanel" id="pesan" aria-labelledby="t-p" hidden>
      <div class="chat" data-chat data-endpoint="<?= e(url('/api/kasus/' . $code . '/pesan')) ?>">
        <div class="chat-head">
          <div class="chat-who"><span class="avatar" aria-hidden="true">P</span><div><strong>Penyintas (<?= $report['user_id'] ? 'berakun' : 'anonim' ?>)</strong><small>Kontak lain: <?= e(Report::channelLabels($report)) ?></small></div></div>
          <span class="sync" data-sync><?= icon('refresh') ?> Memuat…</span>
        </div>
        <div class="chat-log" aria-live="polite" aria-label="Riwayat pesan" tabindex="0"></div>
        <?php if ($active): ?>
          <form class="composer" novalidate>
            <label class="sr-only" for="msg">Tulis balasan</label>
            <textarea class="textarea" id="msg" rows="1" maxlength="<?= Message::MAX_LEN ?>" placeholder="Tulis balasan…"></textarea>
            <button class="btn btn-ink" type="submit"><?= icon('send') ?><span>Kirim</span></button>
          </form>
        <?php endif; ?>
        <p class="chat-note error" data-chat-error hidden role="alert"></p>
        <p class="chat-note"><?= icon('lock') ?><?= $active ? 'Pesan dienkripsi. Penyintas menerima notifikasi tanpa isi pesan.' : 'Kasus sudah ' . e(mb_strtolower(Report::STATUS[$s])) . '. Percakapan hanya bisa dibaca.' ?></p>
      </div>
    </section>

    <section class="subpanel stack" role="tabpanel" id="catatan" aria-labelledby="t-c" hidden>
      <form class="panel" method="post" action="<?= e(url('/pendamping/kasus/' . $code . '/catatan')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="panel-head"><h2 class="panel-title">Tambah catatan</h2><span class="small muted">Tidak terlihat oleh penyintas maupun admin</span></div>
        <div class="panel-body form-stack">
          <div class="field"><label class="label" for="nt-title">Judul <span class="opt">(opsional)</span></label><input class="input" id="nt-title" name="title" maxlength="120" placeholder="Contoh: Sesi 18 Nov" value="<?= e(old('title')) ?>"></div>
          <div class="field">
            <label class="label" for="note">Catatan</label>
            <textarea class="textarea textarea-sm" id="note" name="body" maxlength="3000" required data-msg="Tulis catatan sebelum menyimpan." placeholder="Contoh: Penyintas belum ingin melapor ke kampus. Tawarkan informasi Satgas PPKPT di sesi berikutnya."<?= field_aria('body') ?>><?= e(old('body')) ?></textarea>
            <?= field_error('body') ?>
          </div>
        </div>
        <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Simpan catatan</button></div>
      </form>
      <div class="panel">
        <div class="panel-head"><h2 class="panel-title">Catatan sebelumnya</h2></div>
        <div class="panel-body">
          <?php if ($notes): ?>
            <ol class="timeline">
              <?php foreach ($notes as $n): ?>
                <li class="is-done"><span class="tl-dot"><?= icon('edit') ?></span><div><p class="tl-title"><?= e($n['title'] ?: 'Catatan') ?></p><p class="tl-meta"><?= e(mb_strtoupper(($n['author_name'] ?? 'Pengguna terhapus') . ' · ' . id_date($n['created_at'], true))) ?></p><p class="tl-body"><?= nl2br(e($n['body'])) ?></p></div></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?><p class="small muted">Belum ada catatan.</p><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="subpanel panel" role="tabpanel" id="riwayat" aria-labelledby="t-j" hidden>
      <div class="panel-body">
        <ol class="timeline">
          <?php foreach (array_reverse($logs) as $i => $l): ?>
            <li class="<?= $i === 0 ? 'is-current' : 'is-done' ?>"><span class="tl-dot"><?= $i === 0 ? '' : icon('check') ?></span><div><p class="tl-title"><?= e(Report::STATUS[$l['to_status']]) ?></p><p class="tl-meta"><?= e(mb_strtoupper(id_date($l['created_at'], true) . ' · ' . ($l['actor_name'] ?? 'Sistem'))) ?></p><?php if ($l['note']): ?><p class="tl-body"><?= nl2br(e($l['note'])) ?></p><?php endif; ?></div></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
  </div>

  <aside class="stack">
    <?php if ($byWitness): ?>
      <section class="panel" id="persetujuan">
        <div class="panel-head"><h2 class="panel-title">Persetujuan korban</h2></div>
        <?php if ($consent !== null): ?>
          <div class="panel-body stack-sm">
            <p class="small">Tercatat oleh <strong><?= e($consent['companion_name'] ?? 'pendamping yang akunnya sudah dihapus') ?></strong> pada <?= e(id_date($consent['created_at'], true)) ?>.</p>
            <p class="small muted">Kasus ini sudah bisa dirujuk ke layanan.</p>
          </div>
        <?php else: ?>
          <form method="post" action="<?= e(url('/pendamping/kasus/' . $code . '/persetujuan')) ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="panel-body form-stack">
              <p class="small">Laporan ini dibuat saksi, bukan korban. Hubungi korban lebih dulu. Rujukan ke layanan baru bisa disimpan setelah korban sendiri menyatakan setuju didampingi.</p>
              <div class="field">
                <label class="check"><input type="checkbox" name="konfirmasi" value="1" required data-msg="Centang pernyataan bahwa korban sudah setuju didampingi."<?= field_aria('konfirmasi') ?>> <span>Korban sudah saya hubungi dan menyatakan setuju didampingi.</span></label>
                <?= field_error('konfirmasi') ?>
              </div>
            </div>
            <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Catat persetujuan</button></div>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($next): ?>
      <form class="panel" method="post" action="<?= e(url('/pendamping/kasus/' . $code . '/status')) ?>" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="panel-head"><h2 class="panel-title">Perbarui status</h2></div>
        <div class="panel-body form-stack">
          <fieldset class="field">
            <legend class="label">Status berikutnya</legend>
            <p class="hint">Hanya perpindahan yang diizinkan alur kasus yang ditampilkan.</p>
            <div class="stack-sm">
              <?php $first = true; foreach ($next as $to): ?>
                <label class="choice"><input type="radio" name="status" value="<?= e($to) ?>"<?= $first ? ' required data-msg="Pilih status berikutnya."' : '' ?><?= checked(old('status') === $to) ?><?= field_aria('status') ?>><span class="choice-body"><span class="choice-title"><?= e(Report::STATUS[$to]) ?></span><span class="choice-desc"><?= $to === 'dirujuk' ? 'Hubungkan dengan layanan lanjutan' : 'Pendampingan cukup, disepakati bersama penyintas' ?></span></span></label>
              <?php $first = false; endforeach; ?>
            </div>
            <?= field_error('status') ?>
          </fieldset>
          <?php if (in_array('dirujuk', $next, true)): ?>
            <?php if ($needConsent): ?>
              <div class="alert alert-warn">
                <?= icon('alert') ?>
                <div><strong>Rujukan masih terkunci.</strong>Catat persetujuan korban di panel "Persetujuan korban" dulu. Selama belum tercatat, status "Dirujuk" akan ditolak saat disimpan.</div>
              </div>
            <?php endif; ?>
            <div class="field" data-show-when="status=dirujuk">
              <label class="label" for="svc">Layanan rujukan</label>
              <select class="select" id="svc" name="layanan" data-cond-required data-msg="Pilih layanan tujuan rujukan."<?= field_aria('layanan') ?>>
                <option value="">Pilih layanan</option>
                <?php foreach ($services as $sv): ?>
                  <option value="<?= (int) $sv['id'] ?>"<?= selected(old('layanan'), (string) $sv['id']) ?>><?= e($sv['name']) ?><?= $sv['is_verified'] ? '' : ' (perlu verifikasi)' ?></option>
                <?php endforeach; ?>
              </select>
              <?= field_error('layanan') ?>
              <a class="text-link small" href="<?= e(url('/layanan')) ?>" target="_blank" rel="noopener" style="margin-top:4px">Cari di direktori <?= icon('arrow-right') ?></a>
            </div>
          <?php endif; ?>
          <div class="field">
            <label class="label" for="why">Catatan untuk penyintas <span class="opt">(opsional)</span></label>
            <textarea class="textarea textarea-sm" id="why" name="catatan" maxlength="500" placeholder="Tampil di halaman perkembangan penyintas."><?= e(old('catatan')) ?></textarea>
          </div>
          <div class="field">
            <label class="check"><input type="checkbox" id="sepakat" name="sepakat" value="1" required data-msg="Pastikan penyintas sudah setuju."<?= field_aria('sepakat') ?>> <span>Penyintas sudah menyetujui langkah ini.</span></label>
            <?= field_error('sepakat') ?>
          </div>
        </div>
        <div class="panel-foot row" style="justify-content:flex-end"><button class="btn btn-ink btn-sm" type="submit">Simpan status</button></div>
      </form>
    <?php endif; ?>

    <?php if ($service): ?>
      <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Rujukan</h2></div>
        <ul class="service-list" style="padding:12px"><?php $s2 = $s; $s = $service; include APP_PATH . '/views/partials/service-item.php'; $s = $s2; ?></ul>
        <?php
        // Pendamping tetap bisa menelepon rujukan yang belum diverifikasi. Nomor ini
        // hanya untuk halaman pendamping; tampilan penyintas dan publik memakai
        // ServiceRules::publicView, jadi di sana nomornya tetap tersembunyi (Req 1.1).
        $internal = array_values(array_filter([trim((string) ($service['phone'] ?? '')), trim((string) ($service['whatsapp'] ?? ''))]));
        ?>
        <?php if (!(int) $service['is_verified'] && $internal): ?>
          <div class="panel-body">
            <p class="small muted">Nomor internal, belum diverifikasi tim jadi tidak tampil untuk penyintas:
              <span class="mono"><?= e(implode(' · ', $internal)) ?></span>. Pastikan nomornya benar sebelum kamu teruskan.</p>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2 class="panel-title">Sesi</h2><a class="link-btn" href="<?= e(url('/pendamping/slot')) ?>">Kelola slot</a></div>
      <div class="panel-body">
        <?php if (!$upcoming): ?><p class="small muted">Belum ada sesi mendatang.</p><?php endif; ?>
        <?php foreach ($upcoming as $b): $t = strtotime($b['starts_at']); ?>
          <div class="session"><span class="session-date"><b><?= date('j', $t) ?></b><small><?= e(ID_MONTHS[(int) date('n', $t)]) ?></small></span><div><h3><?= e(ID_DAYS[(int) date('w', $t)]) ?> · <?= date('H.i', $t) ?></h3><p><?= e(Slot::MODES[$b['mode']]) ?> · dikonfirmasi</p></div></div>
        <?php endforeach; ?>
      </div>
    </section>
  </aside>
</div>
