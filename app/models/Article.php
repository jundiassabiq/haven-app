<?php
declare(strict_types=1);

/** Module 7: education articles. */
final class Article
{
    /** Built-in cover images admins can pick without uploading. */
    public const STOCK_COVERS = [
        'img/reaching-hands.jpg' => 'Dua tangan saling meraih',
        'img/holding-hands.jpg' => 'Dua orang saling menggenggam tangan',
        'img/counseling.jpg' => 'Sesi konseling',
        'img/support-circle.jpg' => 'Kelompok dukungan duduk melingkar',
        'img/phone.jpg' => 'Perempuan memegang ponsel',
        'img/laptop.jpg' => 'Tangan mengetik di laptop',
        'img/sunset.jpg' => 'Anak meraih matahari terbenam',
    ];

    public static function categories(): array
    {
        return Database::all('SELECT * FROM article_categories ORDER BY id');
    }

    public static function coverUrl(?string $path): string
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            return url('/' . $path);
        }
        return asset($path ?: 'img/reaching-hands.jpg');
    }

    /** Published articles, optional category slug and search text. */
    public static function published(?string $category = null, string $q = '', int $limit = 50): array
    {
        $w = ["a.status = 'terbit'"];
        $p = [];
        if ($category) {
            $w[] = 'c.slug = ?';
            $p[] = $category;
        }
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
            $w[] = '(a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return Database::all(
            'SELECT a.id, a.title, a.slug, a.excerpt, a.cover_path, a.cover_alt, a.read_minutes, a.published_at, c.slug AS cat_slug, c.name AS cat_name
             FROM articles a JOIN article_categories c ON c.id = a.category_id WHERE ' . implode(' AND ', $w) . '
             ORDER BY a.published_at DESC, a.id DESC LIMIT ' . max(1, $limit),
            $p
        );
    }

    public static function findPublished(string $slug): ?array
    {
        return Database::one(
            "SELECT a.*, c.slug AS cat_slug, c.name AS cat_name, u.name AS author_name
             FROM articles a JOIN article_categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.author_id
             WHERE a.slug = ? AND a.status = 'terbit'",
            [$slug]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT a.*, c.slug AS cat_slug FROM articles a JOIN article_categories c ON c.id = a.category_id WHERE a.id = ?', [$id]);
    }

    public static function related(array $a, int $limit = 3): array
    {
        return Database::all(
            "SELECT id, title, slug FROM articles WHERE status = 'terbit' AND id <> ? ORDER BY (category_id = ?) DESC, published_at DESC LIMIT " . max(1, $limit),
            [(int) $a['id'], (int) $a['category_id']]
        );
    }

    public static function adminList(): array
    {
        return Database::all('SELECT a.id, a.title, a.slug, a.status, a.updated_at, c.name AS cat_name FROM articles a JOIN article_categories c ON c.id = a.category_id ORDER BY a.updated_at DESC');
    }

    public static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim(substr((string) $s, 0, 150), '-');
    }

    public static function slugTaken(string $slug, ?int $exceptId): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM articles WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]);
    }

    public static function validationErrors(array $in, ?int $id): array
    {
        $catIds = array_map('strval', array_column(self::categories(), 'id'));
        $e = Validator::make($in, [
            'title' => 'required|max:160',
            'excerpt' => 'required|max:200',
            'body' => 'required|min:100',
            'sources' => 'required|max:3000',
            'category_id' => 'required|in:' . implode(',', $catIds),
            'slug' => ['required', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => 'required|in:draf,terbit',
            'read_minutes' => 'between:1,60',
            'cover_alt' => 'max:200',
        ], [
            'title.required' => 'Tulis judul artikel.',
            'excerpt.required' => 'Tulis ringkasan singkat untuk kartu artikel.',
            'body.required' => 'Isi artikel tidak boleh kosong.',
            'body.min' => 'Isi artikel minimal 100 karakter.',
            'sources.required' => 'Setiap artikel wajib mencantumkan sumber rujukan.',
            'slug.regex' => 'Gunakan huruf kecil, angka, dan tanda hubung saja.',
        ]);
        if (!isset($e['slug']) && self::slugTaken($in['slug'], $id)) {
            $e['slug'] = 'Alamat ini sudah dipakai artikel lain.';
        }
        if (!isset($e['sources'])) {
            foreach (Markup::sources($in['sources']) as $s) {
                if ($s['url'] === null) {
                    $e['sources'] = 'Tulis setiap sumber sebagai "Judul | https://alamat".';
                    break;
                }
            }
        }
        return $e;
    }

    public static function save(array $in, ?int $id, ?string $coverPath): int
    {
        $published = $in['status'] === 'terbit';
        $minutes = $in['read_minutes'] !== '' ? (int) $in['read_minutes'] : Markup::readingMinutes($in['body']);
        $vals = [(int) $in['category_id'], trim($in['title']), $in['slug'], trim($in['excerpt']), trim($in['body']), trim($in['sources']), $coverPath, trim($in['cover_alt']), $minutes, $in['status'], now()];
        if ($id === null) {
            return Database::insert(
                'INSERT INTO articles (category_id, title, slug, excerpt, body, sources, cover_path, cover_alt, read_minutes, status, updated_at, author_id, published_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$vals, Auth::id(), $published ? now() : null, now()]
            );
        }
        // Keep the first publication date; set it when an article is published for the first time.
        Database::run(
            'UPDATE articles SET category_id = ?, title = ?, slug = ?, excerpt = ?, body = ?, sources = ?, cover_path = ?, cover_alt = ?, read_minutes = ?, status = ?, updated_at = ?,
               published_at = CASE WHEN ? = 1 AND published_at IS NULL THEN ? ELSE published_at END WHERE id = ?',
            [...$vals, (int) $published, now(), $id]
        );
        return $id;
    }

    public static function delete(int $id): void
    {
        $a = self::find($id);
        Database::run('DELETE FROM articles WHERE id = ?', [$id]);
        if ($a && $a['cover_path'] && str_starts_with($a['cover_path'], 'uploads/covers/')) {
            @unlink(ROOT_PATH . '/public/' . $a['cover_path']);
        }
    }

    /** Stores an uploaded cover (already checked with Upload::check) under public/uploads/covers. */
    public static function storeCover(string $tmp): string
    {
        $dir = ROOT_PATH . '/public/uploads/covers';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = Upload::TYPES[Upload::mime($tmp)];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $ok = PHP_SAPI === 'cli' ? rename($tmp, "$dir/$name") : move_uploaded_file($tmp, "$dir/$name");
        if (!$ok) {
            throw new RuntimeException('Gagal menyimpan sampul.');
        }
        return 'uploads/covers/' . $name;
    }
}
