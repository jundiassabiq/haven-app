<?php /** @var array $articles  @var array $categories  @var string $cat  @var string $q */ ?>
<main id="konten" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1 class="h2">Artikel tentang kekerasan seksual dan hak korban</h1>
      <p class="lede">Setiap artikel bisa dibaca dalam 4 sampai 7 menit, dan menjelaskan isi UU No. 12 Tahun 2022 (UU TPKS) dengan contoh sehari-hari. Sumbernya tercantum di akhir setiap artikel.</p>
    </div>
  </section>

  <section class="section-sm">
    <div class="container">
      <form class="filter-bar" method="get" action="<?= e(url('/edukasi')) ?>" role="search" style="align-items:center">
        <nav class="chips" aria-label="Kategori">
          <a class="chip" href="<?= e(url('/edukasi', array_filter(['q' => $q]))) ?>"<?= $cat === '' ? ' aria-current="page"' : '' ?>>Semua</a>
          <?php foreach ($categories as $c): ?>
            <a class="chip" href="<?= e(url('/edukasi', array_filter(['kategori' => $c['slug'], 'q' => $q]))) ?>"<?= $cat === $c['slug'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php if ($cat): ?><input type="hidden" name="kategori" value="<?= e($cat) ?>"><?php endif; ?>
        <div class="search">
          <?= icon('search') ?>
          <label class="sr-only" for="cari">Cari artikel</label>
          <input class="input" id="cari" name="q" type="search" placeholder="Cari artikel, misalnya consent" value="<?= e($q) ?>">
        </div>
        <button class="btn btn-ink btn-sm" type="submit">Cari</button>
      </form>
      <p class="result-count" aria-live="polite" style="margin-bottom:32px"><?= count($articles) ?> artikel<?= $q !== '' ? ' untuk "' . e($q) . '"' : '' ?></p>

      <?php if ($articles): ?>
        <h2 class="sr-only">Daftar artikel</h2>
        <ul class="article-grid">
          <?php foreach ($articles as $a): include APP_PATH . '/views/partials/article-card.php'; endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty">
          <?= icon('search') ?>
          <h3>Belum ada artikel yang cocok</h3>
          <p>Coba kata yang lebih umum, misalnya "bukti" atau "kampus".</p>
          <a class="btn btn-ink btn-sm" href="<?= e(url('/edukasi')) ?>">Tampilkan semua artikel</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
