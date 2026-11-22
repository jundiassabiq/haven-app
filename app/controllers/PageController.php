<?php
declare(strict_types=1);

/** Public content pages. */
final class PageController
{
    public function home(): void
    {
        View::show('pages/home', [
            'active' => 'beranda',
            'numbers' => Stats::publicNumbers(),
            'articles' => Article::published(null, '', 3),
        ]);
    }

    public function about(): void
    {
        View::show('pages/static/tentang', ['active' => 'tentang', 'title' => 'Tentang Haven & SDGs 5 | Haven']);
    }

    public function safety(): void
    {
        View::show('pages/static/tips-aman', ['title' => 'Tips menjelajah aman | Haven']);
    }
}
