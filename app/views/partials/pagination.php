<?php
/** @var array $pg from paginate()  @var int $shown rows on this page */
if ($pg['total'] === 0) {
    return;
}
$from = $pg['offset'] + 1;
$to = $pg['offset'] + $shown;
$pages = [];
for ($i = max(1, $pg['page'] - 2); $i <= min($pg['pages'], $pg['page'] + 2); $i++) {
    $pages[] = $i;
}
if ($pages[0] > 1) {
    array_unshift($pages, 1);
}
if (end($pages) < $pg['pages']) {
    $pages[] = $pg['pages'];
}
?>
<nav class="pagination" aria-label="Halaman">
  <span>Menampilkan <span class="mono"><?= $from ?> sampai <?= $to ?></span> dari <span class="mono"><?= number_format($pg['total'], 0, ',', '.') ?></span></span>
  <?php if ($pg['pages'] > 1): ?>
    <ul>
      <li><a href="<?= e(query_with(['halaman' => $pg['page'] - 1])) ?>"<?= $pg['page'] <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= icon('arrow-left') ?><span class="sr-only">Sebelumnya</span></a></li>
      <?php foreach ($pages as $p): ?>
        <li><?= $p === $pg['page'] ? '<span aria-current="page">' . $p . '</span>' : '<a href="' . e(query_with(['halaman' => $p])) . '">' . $p . '</a>' ?></li>
      <?php endforeach; ?>
      <li><a href="<?= e(query_with(['halaman' => $pg['page'] + 1])) ?>"<?= $pg['page'] >= $pg['pages'] ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= icon('arrow-right') ?><span class="sr-only">Berikutnya</span></a></li>
    </ul>
  <?php endif; ?>
</nav>
