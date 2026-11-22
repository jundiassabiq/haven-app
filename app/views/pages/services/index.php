<?php /** @var array $services  @var array $provinces  @var array $cities  @var array $f */ ?>
<main id="konten" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1 class="h2">Lembaga yang menangani kekerasan seksual</h1>
      <p class="lede">UPTD PPA, rumah sakit, lembaga bantuan hukum, unit PPA kepolisian, dan satgas kampus. Hubungi mereka langsung, atau minta pendampingmu di Haven menelepon lebih dulu.</p>
    </div>
  </section>

  <section class="section-sm" data-directory="public" data-endpoint="<?= e(url('/api/layanan')) ?>">
    <div class="container">
      <div class="alert alert-warn" style="margin-bottom:28px">
        <?= icon('info') ?>
        <div><strong>Nomor telepon ditampilkan setelah tim menelepon lembaganya.</strong>Layanan berlabel Perlu verifikasi belum kami cek, jadi nomornya kami sembunyikan. Dalam keadaan darurat, hubungi <a href="tel:112">112</a> atau SAPA <a href="tel:129">129</a>.</div>
      </div>

      <form class="filter-bar" method="get" action="<?= e(url('/layanan')) ?>" role="search">
        <div class="search">
          <?= icon('search') ?>
          <label class="sr-only" for="q">Cari layanan</label>
          <input class="input" id="q" name="q" type="search" placeholder="Cari nama atau jenis bantuan" value="<?= e($f['q']) ?>">
        </div>
        <div class="field">
          <label class="label" for="f-prov">Provinsi</label>
          <select class="select" id="f-prov" name="provinsi"><option value="">Semua provinsi</option><?php foreach ($provinces as $p): ?><option<?= selected($f['provinsi'], $p) ?>><?= e($p) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
          <label class="label" for="f-kota">Kota/kabupaten</label>
          <select class="select" id="f-kota" name="kota"><option value="">Semua kota/kabupaten</option><?php foreach ($cities as $c): ?><option<?= selected($f['kota'], $c) ?>><?= e($c) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
          <label class="label" for="f-jenis">Jenis layanan</label>
          <select class="select" id="f-jenis" name="jenis"><option value="">Semua jenis</option><?php foreach (Service::TYPES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($f['jenis'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
        </div>
        <label class="check" style="align-self:center;margin-top:20px"><input type="checkbox" name="h24" value="1"<?= checked($f['h24'] !== '') ?>> <span>Buka 24 jam</span></label>
        <noscript><button class="btn btn-ink btn-sm" type="submit">Terapkan</button></noscript>
      </form>
      <p class="result-count" data-result-count aria-live="polite" style="margin-bottom:16px"><?= count($services) ?> layanan</p>

      <ul class="service-list" data-directory-list>
        <?php foreach ($services as $s): include APP_PATH . '/views/partials/service-item.php'; endforeach; ?>
      </ul>
      <div class="empty" data-empty<?= $services ? ' hidden' : '' ?>>
        <?= icon('pin') ?>
        <h3>Belum ada layanan di filter ini</h3>
        <p>Belum ada lembaga di wilayah itu dalam daftar kami. SAPA 129 menerima telepon dari seluruh Indonesia.</p>
        <button class="btn btn-ink btn-sm" type="button" data-reset-filter>Hapus semua filter</button>
      </div>
    </div>
  </section>
</main>
