<?php
/**
 * One directory entry. Same markup as serviceItem() in app.js.
 * Satu-satunya jalur tampilan layanan: nomor dan tanggal verifikasi selalu
 * datang dari ServiceRules::publicView, jadi tidak ada halaman yang bisa
 * menampilkan nomor layanan yang belum dicek (Req 1.1, 1.2, 1.8, 1.11).
 *
 * @var array $s  services row
 */
$pub = ServiceRules::publicView($s);
?>
<li class="service">
  <div class="service-title"><h3><?= e($pub['name']) ?></h3><?= $pub['verified'] ? '<span class="badge badge-ok">Terverifikasi</span>' : '<span class="badge badge-verify">' . e(ServiceRules::UNVERIFIED_LABEL) . '</span>' ?></div>
  <div class="service-meta">
    <span><?= icon('building') ?><?= e(Service::TYPES[$pub['type']] ?? $pub['type']) ?></span>
    <span><?= icon('pin') ?><?= e($pub['area']) ?></span>
    <span><?= icon('clock') ?><?= e($pub['hours'] ?: 'Jam layanan belum dicatat') ?></span>
  </div>
  <div class="service-tags"><?php foreach ($pub['tags'] as $t): ?><span class="badge badge-plain"><?= e($t) ?></span><?php endforeach; ?></div>
  <div class="service-actions">
    <?php if ($pub['phone']): ?>
      <a class="btn btn-ink btn-xs" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $pub['phone'])) ?>"><?= icon('phone') ?> Telepon <span class="mono"><?= e($pub['phone']) ?></span></a>
    <?php endif; ?>
    <?php if ($pub['whatsapp']): ?>
      <a class="btn btn-ghost btn-xs" href="https://wa.me/<?= e(preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $pub['whatsapp']))) ?>" rel="noopener noreferrer"><?= icon('message') ?> <span class="mono"><?= e($pub['whatsapp']) ?></span></a>
    <?php endif; ?>
    <?php if ($pub['contact_label'] !== null): ?>
      <span class="small muted"><strong><?= e($pub['contact_label']) ?></strong> · nomornya kami sembunyikan sampai tim menelepon lembaga ini</span>
    <?php elseif ($pub['verified_at'] !== null): ?>
      <span class="small muted">Nomor dicek <?= e($pub['verified_at']) ?></span>
    <?php endif; ?>
  </div>
</li>
