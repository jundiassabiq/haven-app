<?php
/**
 * Panel rujukan anak, tampil di halaman terkirim dan halaman laporan untuk
 * kasus anak (Req 4.4, 4.5, 4.6). Selalu tiga entri; entri yang nomornya belum
 * dicek tim tampil berlabel "Perlu verifikasi" tanpa nomor.
 *
 * @var list<array{key:string, name:string, verified:bool, contact:?string,
 *     contact_kind:?string, verified_at:?string, label:?string}> $childPanel
 */
?>
<section class="panel" aria-labelledby="rujukan-anak-judul">
  <div class="panel-head"><h2 class="panel-title" id="rujukan-anak-judul">Rujukan khusus anak</h2></div>
  <div class="panel-body">
    <p class="small muted">Laporan ini menyangkut korban di bawah 18 tahun, jadi lembaga perlindungan anak di bawah bisa dihubungi langsung, tanpa menunggu pendamping.</p>
    <ul class="service-list" style="margin-top:16px">
      <?php foreach ($childPanel as $ref): ?>
        <li class="service">
          <div class="service-title">
            <h3><?= e($ref['name']) ?></h3>
            <?= $ref['verified'] ? '<span class="badge badge-ok">Terverifikasi</span>' : '<span class="badge badge-verify">' . e($ref['label']) . '</span>' ?>
          </div>
          <div class="service-actions">
            <?php if ($ref['contact'] === null): ?>
              <span class="small muted">Nomor belum tersedia. Hubungi SAPA 129 atau 112.</span>
            <?php elseif ($ref['contact_kind'] === 'phone'): ?>
              <a class="btn btn-ink btn-xs" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ref['contact'])) ?>"><?= icon('phone') ?> Telepon <span class="mono"><?= e($ref['contact']) ?></span></a>
            <?php else: ?>
              <a class="btn btn-ghost btn-xs" href="https://wa.me/<?= e(preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $ref['contact']))) ?>" rel="noopener noreferrer"><?= icon('message') ?> WhatsApp <span class="mono"><?= e($ref['contact']) ?></span></a>
            <?php endif; ?>
            <?php if ($ref['verified_at'] !== null): ?>
              <span class="small muted">Nomor dicek <?= e($ref['verified_at']) ?></span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
