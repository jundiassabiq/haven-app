<?php
/** @var ?array $article  @var array $categories */
$v = static fn (string $k, string $d = '') => old($k, $article !== null ? (string) ($article[$k] ?? $d) : $d);
$action = $article ? url('/admin/artikel/' . $article['id']) : url('/admin/artikel');
?>
<a class="back-link" href="<?= e(url('/admin/artikel')) ?>"><?= icon('arrow-left') ?> Semua artikel</a>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate data-validate>
  <?= csrf_field() ?>
  <div class="app-page-head">
    <h1 class="app-title"><?= $article ? 'Ubah artikel' : 'Tulis artikel' ?></h1>
    <div class="row">
      <?php if ($article): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/artikel/' . $article['id'] . '/pratinjau')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Pratinjau</a><?php endif; ?>
      <button class="btn btn-yellow btn-sm" type="submit">Simpan</button>
    </div>
  </div>
  <div class="grid-2">
    <div class="panel panel-body form-stack">
      <div class="field"><label class="label" for="t">Judul</label><input class="input" id="t" name="title" required maxlength="160" value="<?= e($v('title')) ?>" data-msg="Tulis judul artikel."<?= field_aria('title') ?>><?= field_error('title') ?></div>
      <div class="field">
        <label class="label" for="ex">Ringkasan</label>
        <textarea class="textarea textarea-sm" id="ex" name="excerpt" maxlength="200" required data-counter="ex-c" data-msg="Tulis ringkasan singkat."<?= field_aria('excerpt', 'ex-hint') ?>><?= e($v('excerpt')) ?></textarea>
        <span class="counter" id="ex-c"></span>
        <p class="hint" id="ex-hint">Tampil di kartu artikel dan hasil pencarian.</p>
        <?= field_error('excerpt') ?>
      </div>
      <div class="field">
        <label class="label" for="body">Isi artikel</label>
        <p class="hint" id="body-hint">Baris kosong memisahkan paragraf. <span class="mono">## Judul</span> untuk subjudul, <span class="mono">- </span> untuk daftar, <span class="mono">&gt; </span> untuk kotak sorotan, <span class="mono">**tebal**</span>, dan <span class="mono">[teks](/lapor)</span> untuk tautan. HTML tidak diizinkan.</p>
        <textarea class="textarea" id="body" name="body" rows="18" required minlength="100" data-msg="Isi artikel tidak boleh kosong."<?= field_aria('body', 'body-hint') ?>><?= e($v('body')) ?></textarea>
        <?= field_error('body') ?>
      </div>
      <div class="field">
        <label class="label" for="src">Sumber rujukan</label>
        <textarea class="textarea textarea-sm" id="src" name="sources" required data-msg="Setiap artikel wajib mencantumkan sumber rujukan."<?= field_aria('sources', 'src-hint') ?>><?= e($v('sources')) ?></textarea>
        <p class="hint" id="src-hint">Satu sumber per baris: <span class="mono">Judul | https://alamat</span>.</p>
        <?= field_error('sources') ?>
      </div>
    </div>
    <aside class="stack">
      <div class="panel panel-body form-stack">
        <fieldset class="field">
          <legend class="label">Status</legend>
          <div class="stack-sm">
            <label class="choice"><input type="radio" name="status" value="terbit"<?= checked($v('status', 'draf') === 'terbit') ?>><span class="choice-body"><span class="choice-title">Terbit</span><span class="choice-desc">Terlihat di halaman publik</span></span></label>
            <label class="choice"><input type="radio" name="status" value="draf"<?= checked($v('status', 'draf') === 'draf') ?>><span class="choice-body"><span class="choice-title">Draf</span><span class="choice-desc">Hanya terlihat oleh admin</span></span></label>
          </div>
        </fieldset>
        <div class="field"><label class="label" for="cat">Kategori</label><select class="select" id="cat" name="category_id"<?= field_aria('category_id') ?>><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($v('category_id'), (string) $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?= field_error('category_id') ?></div>
        <div class="field"><label class="label" for="slug">Alamat (slug) <span class="opt">(kosongkan untuk otomatis)</span></label><input class="input mono" id="slug" name="slug" maxlength="150" value="<?= e($v('slug')) ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" data-msg-pattern="Gunakan huruf kecil, angka, dan tanda hubung saja."<?= field_aria('slug') ?>><?= field_error('slug') ?></div>
        <div class="field"><label class="label" for="rt">Waktu baca (menit) <span class="opt">(kosongkan untuk otomatis)</span></label><input class="input mono" id="rt" name="read_minutes" type="number" min="1" max="60" value="<?= e($v('read_minutes')) ?>"<?= field_aria('read_minutes') ?>><?= field_error('read_minutes') ?></div>
      </div>
      <div class="panel panel-body form-stack">
        <span class="label">Gambar sampul</span>
        <div class="media" style="aspect-ratio:3/2;border-radius:4px"><img src="<?= e(Article::coverUrl($article['cover_path'] ?? null)) ?>" alt="Sampul saat ini" width="1600" height="1067"></div>
        <div class="field"><label class="label" for="stock">Pilih dari galeri</label><select class="select" id="stock" name="stock_cover"><option value="">Pertahankan sampul saat ini</option><?php foreach (Article::STOCK_COVERS as $p => $label): ?><option value="<?= e($p) ?>"<?= selected(old('stock_cover'), $p) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <label class="dropzone" data-dropzone="cv-list" style="padding:20px">
          <input type="file" name="cover" accept=".jpg,.jpeg,.png" aria-label="Unggah sampul baru"<?= field_aria('cover') ?>>
          <strong>Atau unggah gambar</strong><span class="hint">JPG atau PNG, maksimal 5 MB. Unggahan menggantikan pilihan galeri.</span>
        </label>
        <ul class="file-list" id="cv-list"></ul>
        <?= field_error('cover') ?>
        <div class="field"><label class="label" for="alt">Teks alternatif</label><input class="input" id="alt" name="cover_alt" maxlength="200" value="<?= e($v('cover_alt')) ?>" placeholder="Jelaskan isi gambar untuk pembaca layar"></div>
      </div>
    </aside>
  </div>
</form>
