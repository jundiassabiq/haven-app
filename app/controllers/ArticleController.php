<?php
declare(strict_types=1);

/** Module 7 (public): article list with category filter and search, and article pages. */
final class ArticleController
{
    public function index(): void
    {
        $cat = input('kategori');
        $q = mb_substr(input('q'), 0, 80);
        $cats = Article::categories();
        if ($cat !== '' && !in_array($cat, array_column($cats, 'slug'), true)) {
            $cat = '';
        }
        View::show('pages/edu/index', [
            'title' => 'Edukasi | Haven',
            'active' => 'edukasi',
            'articles' => Article::published($cat ?: null, $q),
            'categories' => $cats,
            'cat' => $cat,
            'q' => $q,
        ]);
    }

    public function show(array $p): void
    {
        $a = Article::findPublished($p['slug']);
        if (!$a) {
            throw new HttpException(404);
        }
        View::show('pages/edu/article', [
            'title' => $a['title'] . ' | Haven',
            'description' => $a['excerpt'],
            'active' => 'edukasi',
            'a' => $a,
            'html' => Markup::render($a['body']),
            'sources' => Markup::sources($a['sources']),
            'related' => Article::related($a),
        ]);
    }
}
