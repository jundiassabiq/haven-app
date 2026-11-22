<?php
declare(strict_types=1);

/** Module 7 (admin): article CMS. */
final class AdminArticleController
{
    public function index(): void
    {
        View::show('pages/admin/articles', ['title' => 'Artikel | Admin Haven', 'active' => 'artikel', 'articles' => Article::adminList()], 'app');
    }

    public function create(): void
    {
        View::show('pages/admin/article-form', ['title' => 'Tulis artikel | Admin Haven', 'active' => 'artikel', 'article' => null, 'categories' => Article::categories()], 'app');
    }

    private static function loadOr404(int $id): array
    {
        $a = Article::find($id);
        if (!$a) {
            throw new HttpException(404);
        }
        return $a;
    }

    public function edit(array $p): void
    {
        $a = self::loadOr404((int) $p['id']);
        View::show('pages/admin/article-form', ['title' => 'Ubah artikel | Admin Haven', 'active' => 'artikel', 'article' => $a, 'categories' => Article::categories()], 'app');
    }

    private static function input(): array
    {
        $in = [];
        foreach (['title', 'excerpt', 'body', 'sources', 'category_id', 'slug', 'status', 'read_minutes', 'cover_alt', 'stock_cover'] as $k) {
            $in[$k] = $k === 'body' || $k === 'sources' ? trim((string) ($_POST[$k] ?? '')) : input($k);
        }
        if ($in['slug'] === '') {
            $in['slug'] = Article::slugify($in['title']);
        }
        return $in;
    }

    /** Uploaded cover (validated) or a stock image. @return array{0:?string, 1:?string} [path, error] */
    private static function cover(?array $current): array
    {
        $f = Upload::normalize($_FILES['cover'] ?? null)[0] ?? null;
        if ($f) {
            $err = Upload::check((string) $f['tmp_name'], (int) $f['size'], (int) $f['error'], 'Sampul');
            if ($err === null && Upload::mime($f['tmp_name']) === 'application/pdf') {
                $err = 'Sampul harus JPG atau PNG.';
            }
            return $err ? [null, $err] : [Article::storeCover($f['tmp_name']), null];
        }
        $stock = input('stock_cover');
        if ($stock !== '' && isset(Article::STOCK_COVERS[$stock])) {
            return [$stock, null];
        }
        return [$current['cover_path'] ?? 'img/reaching-hands.jpg', null];
    }

    public function store(): void
    {
        $in = self::input();
        $errors = Article::validationErrors($in, null);
        [$cover, $coverErr] = $errors ? [null, null] : self::cover(null);
        if ($coverErr) {
            $errors['cover'] = $coverErr;
        }
        if ($errors) {
            back_with('/admin/artikel/baru', $errors, $in);
        }
        $id = Article::save($in, null, $cover);
        Audit::log('article.save', 'article', (string) $id, 'Menulis: ' . $in['title']);
        Session::flash('success', $in['status'] === 'terbit' ? 'Artikel diterbitkan.' : 'Draf disimpan.');
        redirect('/admin/artikel/' . $id);
    }

    public function update(array $p): void
    {
        $a = self::loadOr404((int) $p['id']);
        $in = self::input();
        $errors = Article::validationErrors($in, (int) $a['id']);
        [$cover, $coverErr] = $errors ? [null, null] : self::cover($a);
        if ($coverErr) {
            $errors['cover'] = $coverErr;
        }
        if ($errors) {
            back_with('/admin/artikel/' . $a['id'], $errors, $in);
        }
        if ($cover !== $a['cover_path'] && $a['cover_path'] && str_starts_with($a['cover_path'], 'uploads/covers/')) {
            @unlink(ROOT_PATH . '/public/' . $a['cover_path']);
        }
        Article::save($in, (int) $a['id'], $cover);
        Audit::log('article.save', 'article', (string) $a['id'], 'Mengubah: ' . $in['title']);
        Session::flash('success', 'Artikel disimpan.' . ($in['status'] === 'terbit' ? '' : ' Statusnya draf, jadi tidak terlihat di publik.'));
        redirect('/admin/artikel/' . $a['id']);
    }

    public function delete(array $p): void
    {
        $a = self::loadOr404((int) $p['id']);
        Article::delete((int) $a['id']);
        Audit::log('article.delete', 'article', (string) $a['id'], 'Menghapus: ' . $a['title']);
        Session::flash('success', 'Artikel "' . $a['title'] . '" dihapus.');
        redirect('/admin/artikel');
    }

    /** Admin preview, also for drafts. */
    public function preview(array $p): void
    {
        $a = self::loadOr404((int) $p['id']);
        $a += ['cat_name' => Database::value('SELECT name FROM article_categories WHERE id = ?', [$a['category_id']]), 'author_name' => null];
        View::show('pages/edu/article', [
            'title' => 'Pratinjau: ' . $a['title'],
            'active' => 'edukasi',
            'a' => $a,
            'html' => Markup::render($a['body']),
            'sources' => Markup::sources($a['sources']),
            'related' => [],
            'preview' => true,
        ]);
    }
}
