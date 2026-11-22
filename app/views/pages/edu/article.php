<?php /** @var array $a  @var string $html  @var array $sources  @var array $related  @var bool|null $preview */ ?>
<main id="konten" tabindex="-1">
  <article>
    <header class="container article-head">
      <?php if (!empty($preview)): ?>
        <div class="alert alert-warn" style="margin-bottom:24px"><?= icon('eye') ?><div><strong>Pratinjau admin.</strong>Status artikel: <?= e($a['status'] === 'terbit' ? 'terbit' : 'draf, belum terlihat di publik') ?>. <a href="<?= e(url('/admin/artikel/' . $a['id'])) ?>">Kembali ke editor</a></div></div>
      <?php endif; ?>
      <a class="back-link" href="<?= e(url('/edukasi')) ?>"><?= icon('arrow-left') ?> Semua artikel</a>
      <h1 class="h2" style="margin-top:28px"><?= e($a['title']) ?></h1>
      <p class="lede"><?= e($a['excerpt']) ?></p>
      <p class="article-meta">
        <span class="cat"><?= e($a['cat_name']) ?></span>
        <span><?= (int) $a['read_minutes'] ?> menit baca</span>
        <span class="mono">Diperbarui <?= e(mb_strtoupper(id_date($a['updated_at']))) ?></span>
        <span>Ditulis tim Haven</span>
      </p>
      <?php if (!lite()): ?><div class="media article-cover"><img src="<?= e(Article::coverUrl($a['cover_path'])) ?>" alt="<?= e($a['cover_alt']) ?>" width="1600" height="1067"></div><?php endif; ?>
    </header>

    <div class="container article-layout">
      <div class="prose">
        <?= $html ?>
        <section class="sources" aria-labelledby="src-title">
          <h2 id="src-title">Sumber rujukan</h2>
          <ol>
            <?php foreach ($sources as $s): ?>
              <li><?= $s['url'] ? '<a href="' . e($s['url']) . '" rel="noopener noreferrer" target="_blank">' . e($s['title']) . '</a>' : e($s['title']) ?></li>
            <?php endforeach; ?>
          </ol>
        </section>
      </div>

      <aside class="article-aside">
        <div class="panel panel-body stack-sm" style="background:var(--yellow-soft);border-color:#F1DE9A">
          <h2 class="h4">Mengalami hal seperti ini?</h2>
          <p class="small" style="color:var(--ink-2)">Kamu bisa melapor tanpa nama. Setelah admin menugaskan pendamping, ia membalas lewat pesan di Haven.</p>
          <a class="btn btn-ink btn-sm" href="<?= e(url('/lapor')) ?>">Buat laporan</a>
        </div>
        <?php if ($related): ?>
          <div class="panel panel-body stack-sm">
            <h2 class="h4">Bacaan terkait</h2>
            <?php foreach ($related as $r): ?>
              <a class="text-link" href="<?= e(url('/edukasi/' . $r['slug'])) ?>"><?= e($r['title']) ?> <?= icon('arrow-right') ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </article>
</main>
