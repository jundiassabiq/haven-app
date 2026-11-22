<?php
/**
 * @var array $services
 * @var DateTimeImmutable $today  disuntikkan controller supaya aturan tetap murni
 * @var array $unverifiedCounts   jumlah belum terverifikasi per tipe (Req 1.9)
 */
$today = $today ?? new DateTimeImmutable('now');
$counts = $unverifiedCounts ?? ServiceRules::unverifiedCounts($services);
$unverified = count(array_filter($services, static fn ($s) => !(int) $s['is_verified']));
$stale = count(array_filter($services, static fn ($s) => (int) $s['is_verified'] && ServiceRules::isStale($s['verified_at'] ?? null, $today)));
?>
<div data-filter-list>
  <div class="app-page-head">
    <div><h1 class="app-title">Layanan</h1><p class="app-sub">Direktori untuk publik dan rujukan kasus. Kontak hanya tampil di halaman publik setelah ditandai terverifikasi.</p></div>
    <a class="btn btn-yellow btn-sm" href="<?= e(url('/admin/layanan/baru')) ?>"><?= icon('plus') ?> Tambah layanan</a>
  </div>
  <?php if ($unverified): ?>
    <div class="alert alert-warn" style="margin-bottom:20px">
      <?= icon('alert') ?>
      <div><strong><?= $unverified ?> layanan belum diverifikasi.</strong>Hubungi tiap layanan untuk memastikan nomor dan jam operasional sebelum demo, lalu tandai sebagai terverifikasi.</div>
    </div>
  <?php endif; ?>
  <?php if ($stale): ?>
    <div class="alert alert-info" style="margin-bottom:20px">
      <?= icon('clock') ?>
      <div><strong><?= $stale ?> layanan perlu dicek ulang.</strong>Verifikasinya lebih dari <?= ServiceRules::STALE_DAYS ?> hari lalu. Nomornya masih tampil untuk publik, tetapi sebaiknya ditelepon lagi.</div>
    </div>
  <?php endif; ?>
  <p class="small muted" style="margin-bottom:12px">Belum terverifikasi per jenis:
    <?php foreach ($counts as $type => $n): ?>
      <span class="badge <?= $n ? 'badge-verify' : 'badge-plain' ?>"><?= e(Service::TYPES[$type] ?? $type) ?> <?= (int) $n ?></span>
    <?php endforeach; ?>
  </p>
  <div class="filter-bar">
    <div class="search"><?= icon('search') ?><label class="sr-only" for="q">Cari layanan</label><input class="input" id="q" type="search" placeholder="Cari nama atau kota" data-search></div>
    <div class="field"><label class="label" for="fv">Verifikasi</label><select class="select select-sm" id="fv" data-filter-select="verified"><option value="">Semua</option><option value="ya">Terverifikasi</option><option value="tidak"><?= e(ServiceRules::UNVERIFIED_LABEL) ?></option></select></div>
    <div class="field"><label class="label" for="fs">Umur verifikasi</label><select class="select select-sm" id="fs" data-filter-select="stale"><option value="">Semua</option><option value="ya"><?= e(ServiceRules::STALE_LABEL) ?></option></select></div>
  </div>
  <div class="table-wrap table-cards">
    <table class="table">
      <thead><tr><th scope="col">Layanan</th><th scope="col">Jenis</th><th scope="col">Wilayah</th><th scope="col">Verifikasi</th><th scope="col" class="num">Rujukan</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($services as $s):
            $v = (int) $s['is_verified'];
            $isStale = $v === 1 && ServiceRules::isStale($s['verified_at'] ?? null, $today);
            $date = ServiceRules::formatDate((string) ($s['verified_at'] ?? ''));
        ?>
          <tr data-item data-verified="<?= $v ? 'ya' : 'tidak' ?>" data-stale="<?= $isStale ? 'ya' : 'tidak' ?>">
            <td data-label="Layanan"><strong><?= e($s['name']) ?></strong></td>
            <td data-label="Jenis"><?= e(Service::TYPES[$s['type']] ?? $s['type']) ?></td>
            <td data-label="Wilayah"><?= e($s['province'] === 'Nasional' ? 'Nasional' : $s['city']) ?></td>
            <td data-label="Verifikasi">
              <?php if ($v): ?>
                <span class="badge badge-ok">Terverifikasi</span>
                <?php if ($isStale): ?><span class="badge badge-verify"><?= e(ServiceRules::STALE_LABEL) ?></span><?php endif; ?>
              <?php else: ?>
                <span class="badge badge-verify"><?= e(ServiceRules::UNVERIFIED_LABEL) ?></span>
              <?php endif; ?>
              <?php if ($v && $date !== ''): ?><span class="small muted"><?= e($date) ?></span><?php endif; ?>
            </td>
            <td data-label="Rujukan" class="num"><?= (int) $s['referrals'] ?></td>
            <td class="actions" data-label="Aksi"><a class="btn <?= $v && !$isStale ? 'btn-ghost' : 'btn-ink' ?> btn-xs" href="<?= e(url('/admin/layanan/' . $s['id'])) ?>"><?= $v ? ($isStale ? 'Cek ulang' : 'Ubah') : 'Verifikasi' ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="empty" data-empty hidden style="margin-top:12px"><?= icon('building') ?><h3>Tidak ada layanan</h3><p>Ubah filter atau tambah layanan baru.</p></div>
</div>
