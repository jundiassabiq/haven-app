<?php
declare(strict_types=1);

/*
 * Anggaran 100 KB mode ringan (Req 8.9).
 *   php bin/check-lite-size.php                                    pakai base URL yang ditemukan sendiri
 *   php bin/check-lite-size.php --base=http://localhost/haven/public
 *   php bin/check-lite-size.php --rinci                            ukuran tiap berkas per halaman
 *
 * Mengukur data terkirim pada kunjungan pertama dengan cache kosong: HTML
 * halaman ditambah seluruh CSS dan JS yang dirujuknya lewat
 * <link rel="stylesheet"> dan <script src>. Gambar, font, dan berkas unggahan
 * pengguna tidak dihitung (Req 8.9).
 *
 * Mode ringan dinyalakan lewat header `Save-Data: on` (Req 8.10). Tiap
 * permintaan memakai sesi baru yang belum punya pilihan mode, jadi header itu
 * selalu berlaku. Skrip tetap memeriksa penanda `data-lite` pada <body> dan
 * menolak halaman yang ternyata dilayani dalam mode penuh.
 *
 * Yang dihitung adalah byte yang benar-benar dikirim server: bila server
 * memampatkan (mod_deflate, gzip), ukuran terkompresi itulah yang dipakai,
 * sama seperti yang diterima browser. Bila tidak ada satu pun respons yang
 * dimampatkan, skrip menyebutkannya dan menambahkan perkiraan ukuran setara
 * gzip, supaya jelas apakah yang lewat anggaran adalah halamannya atau
 * konfigurasi servernya.
 *
 * Keluar 0 bila semua halaman di dalam anggaran. Keluar 1 bila ada halaman yang
 * lewat 100 KB (disebut nama dan ukurannya) atau ada halaman yang tidak bisa
 * diukur. Skrip hanya mengirim GET, jadi tidak terpengaruh APP_KEY kosong yang
 * membuat POST /lapor gagal.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

/** Batas data terkirim per halaman (Req 8.9). */
const BUDGET_BYTES = 100 * 1024;

const CONNECT_TIMEOUT_SECONDS = 5;
const TIMEOUT_SECONDS = 20;

/**
 * Halaman yang masuk anggaran (Req 8.9): halaman publik, form lapor, dan
 * halaman lacak. Satu halaman artikel ditambahkan saat dijalankan, diambil dari
 * tautan pertama di /edukasi, karena slug-nya ikut isi database.
 */
const PAGES = [
    '/' => 'Beranda',
    '/tentang' => 'Tentang',
    '/tips-aman' => 'Tips aman',
    '/layanan' => 'Layanan',
    '/edukasi' => 'Edukasi',
    '/lapor' => 'Form lapor',
    '/lacak' => 'Lacak',
    '/masuk' => 'Masuk',
    '/daftar' => 'Daftar',
    '/lupa-password' => 'Lupa sandi',
];

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo usage();
    exit(0);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "Ekstensi curl tidak aktif. Hapus titik koma pada baris ;extension=curl di C:\\xampp\\php\\php.ini.\n");
    exit(1);
}

$detail = in_array('--rinci', $argv, true);
$base = resolve_base(arg_value($argv, '--base'));

echo "Base URL     : $base\n";
echo 'Anggaran     : ' . kb(BUDGET_BYTES) . " per halaman (HTML + CSS + JS)\n";
echo 'Mode ringan  : header ' . Mode::SAVE_DATA_HEADER . ": on pada sesi baru\n\n";

/** @var list<array{0:string,1:string}> $queue jalur dan label halaman yang belum diukur */
$queue = [];
foreach (PAGES as $path => $label) {
    $queue[] = [$path, $label];
}

/** @var list<array<string,mixed>> $results */
$results = [];
/** @var list<string> $warnings */
$warnings = [];
$articleQueued = false;

while ($queue !== []) {
    [$path, $label] = array_shift($queue);
    $page = measure_page($base, $path, $label);
    $results[] = $page;

    if ($path !== '/edukasi' || $articleQueued || $page['html'] === null) {
        continue;
    }

    // Halaman artikel punya isi paling panjang, jadi perlu ikut diukur.
    $article = first_article_path($page['body']);
    if ($article !== null) {
        $queue[] = [$article, 'Artikel edukasi'];
        $articleQueued = true;
    } else {
        $warnings[] = 'Tidak ada tautan artikel di /edukasi, halaman artikel tidak diukur.'
            . ' Muat database/seed-content.sql lebih dulu.';
    }
}

report($results, $warnings, $detail);

$failed = array_values(array_filter($results, static fn (array $p): bool => $p['errors'] !== [] || over_budget($p)));
exit($failed === [] ? 0 : 1);

/* ---------- Pengukuran ---------- */

/**
 * Satu halaman dalam mode ringan: ukuran HTML, CSS, JS, dan daftar galat bila
 * ada bagian yang tidak bisa diukur.
 *
 * @return array{path:string, label:string, html:?int, css:int, js:int, total:int,
 *     gzip:int, uncompressed:int, body:string, assets:list<array{url:string,kind:string,bytes:int}>,
 *     errors:list<string>}
 */
function measure_page(string $base, string $path, string $label): array
{
    $page = [
        'path' => $path, 'label' => $label, 'html' => null, 'css' => 0, 'js' => 0, 'total' => 0,
        'gzip' => 0, 'uncompressed' => 0, 'body' => '', 'assets' => [], 'errors' => [],
    ];

    $url = $base . $path;
    $response = http_get($url, lite_headers());

    if ($response['error'] !== null) {
        $page['errors'][] = 'Permintaan gagal: ' . $response['error'];
        return $page;
    }
    if ($response['status'] !== 200) {
        $page['errors'][] = 'HTTP ' . $response['status'] . ' (bukan 200), halaman tidak bisa diukur.';
        return $page;
    }
    if (!is_lite_html($response['body'])) {
        $page['errors'][] = 'Dilayani dalam mode penuh: penanda data-lite tidak ada pada <body>.';
        return $page;
    }

    $page['html'] = $response['bytes'];
    $page['total'] = $response['bytes'];
    $page['gzip'] = gzip_bytes($response);
    $page['uncompressed'] = $response['compressed'] ? 0 : 1;
    $page['body'] = $response['body'];

    foreach (page_assets($response['body'], $url) as $asset) {
        [$assetUrl, $kind] = $asset;
        // Aset statis tidak melihat sesi, tapi header tetap dikirim supaya
        // server yang melayani CSS atau JS khusus mode ringan tetap konsisten.
        $fetched = http_get($assetUrl, lite_headers());

        if ($fetched['error'] !== null) {
            $page['errors'][] = "Gagal mengambil $assetUrl: " . $fetched['error'];
            continue;
        }
        if ($fetched['status'] !== 200) {
            $page['errors'][] = "HTTP {$fetched['status']} untuk $assetUrl, ukurannya tidak terhitung.";
            continue;
        }

        $page[$kind === 'css' ? 'css' : 'js'] += $fetched['bytes'];
        $page['total'] += $fetched['bytes'];
        $page['gzip'] += gzip_bytes($fetched);
        $page['uncompressed'] += $fetched['compressed'] ? 0 : 1;
        $page['assets'][] = ['url' => $assetUrl, 'kind' => $kind, 'bytes' => $fetched['bytes']];
    }

    return $page;
}

/** @param array<string,mixed> $page */
function over_budget(array $page): bool
{
    return $page['html'] !== null && $page['total'] > BUDGET_BYTES;
}

/** @return list<string> */
function lite_headers(): array
{
    return [Mode::SAVE_DATA_HEADER . ': on', 'Accept-Language: id'];
}

/** Penanda mode ringan yang ditulis layout (Req 8.8). */
function is_lite_html(string $html): bool
{
    return preg_match('/<body[^>]*\bdata-lite\b/i', $html) === 1;
}

/**
 * CSS dan JS yang diminta browser saat memuat halaman ini, tanpa duplikat.
 * Gambar dan font tidak termasuk: anggaran dihitung dari HTML + CSS + JS.
 *
 * @return list<array{0:string,1:string}> URL absolut dan jenisnya (`css` atau `js`)
 */
function page_assets(string $html, string $pageUrl): array
{
    $assets = [];
    $seen = [];

    $add = static function (?string $href, string $kind) use (&$assets, &$seen, $pageUrl): void {
        $url = absolute_url($pageUrl, (string) $href);
        if ($url === null || isset($seen[$url])) {
            return;
        }
        $seen[$url] = true;
        $assets[] = [$url, $kind];
    };

    preg_match_all('/<link\b[^>]*>/i', $html, $links);
    foreach ($links[0] as $tag) {
        $rel = (string) tag_attr($tag, 'rel');
        if (preg_match('/\bstylesheet\b/i', $rel) === 1) {
            $add(tag_attr($tag, 'href'), 'css');
        }
    }

    preg_match_all('/<script\b[^>]*>/i', $html, $scripts);
    foreach ($scripts[0] as $tag) {
        $src = tag_attr($tag, 'src');
        if ($src !== null) {
            $add($src, 'js');
        }
    }

    return $assets;
}

/** Nilai atribut HTML, dengan atau tanpa tanda kutip. */
function tag_attr(string $tag, string $name): ?string
{
    $pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*("[^"]*"|\'[^\']*\'|[^\s">]+)/i';
    if (preg_match($pattern, $tag, $m) !== 1) {
        return null;
    }

    return trim(trim($m[1]), '"\'');
}

/** URL absolut dari atribut href atau src, atau null bila tidak perlu diunduh. */
function absolute_url(string $pageUrl, string $href): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'data:')) {
        return null;
    }
    if (preg_match('#^https?://#i', $href) === 1) {
        return $href;
    }
    if (str_starts_with($href, '//')) {
        return 'https:' . $href;
    }

    $parts = parse_url($pageUrl);
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($href, '/')) {
        return $origin . $href;
    }

    $dir = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');

    return $origin . $dir . '/' . $href;
}

/** Jalur artikel pertama yang ditautkan di indeks edukasi, atau null bila belum ada artikel. */
function first_article_path(string $html): ?string
{
    preg_match_all('/<a\b[^>]*>/i', $html, $anchors);
    foreach ($anchors[0] as $tag) {
        $href = (string) tag_attr($tag, 'href');
        $path = (string) parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH);
        if (preg_match('#/edukasi/([^/]+)$#', $path, $m) === 1) {
            return '/edukasi/' . $m[1];
        }
    }

    return null;
}

/* ---------- HTTP ---------- */

/**
 * Satu GET. `bytes` adalah jumlah byte isi respons seperti yang dikirim server:
 * terkompresi bila server memampatkan, mentah bila tidak.
 *
 * @param list<string> $headers
 * @return array{status:int, body:string, bytes:int, compressed:bool, error:?string}
 */
function http_get(string $url, array $headers = []): array
{
    $encoding = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        // Browser pun meminta kompresi; ini yang membuat `bytes` jadi ukuran terkirim.
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT_SECONDS,
        CURLOPT_TIMEOUT => TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'haven-check-lite-size',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$encoding): int {
            if (stripos($header, 'content-encoding:') === 0) {
                $encoding = trim(substr($header, strlen('content-encoding:')));
            }
            return strlen($header);
        },
    ]);

    $body = curl_exec($ch);
    $error = curl_errno($ch) === 0 ? null : curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    // Byte di kabel: ukuran terkompresi bila respons dimampatkan.
    $wire = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    curl_close($ch);

    $compressed = $encoding !== '' && strtolower($encoding) !== 'identity';

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'bytes' => $wire,
        'compressed' => $compressed,
        'error' => $error,
    ];
}

/**
 * Ukuran setara gzip: ukuran terkirim bila server sudah memampatkan, atau
 * perkiraan hasil gzip bila belum. Dipakai hanya sebagai keterangan, bukan
 * untuk menilai anggaran.
 *
 * @param array{body:string, bytes:int, compressed:bool} $response
 */
function gzip_bytes(array $response): int
{
    if ($response['compressed'] || $response['body'] === '') {
        return $response['bytes'];
    }
    $packed = gzencode($response['body'], 6);

    return $packed === false ? $response['bytes'] : strlen($packed);
}

/**
 * Base URL yang dipakai: argumen --base, lalu HAVEN_BASE_URL, lalu dua pilihan
 * di README (server bawaan PHP dan alias Apache). Keluar 1 bila tidak ada yang
 * menjawab, karena skrip ini butuh aplikasi yang berjalan.
 */
function resolve_base(?string $explicit): string
{
    $candidates = [];
    $env = getenv('HAVEN_BASE_URL');

    if ($explicit !== null && trim($explicit) !== '') {
        $candidates[] = trim($explicit);
    } elseif (is_string($env) && trim($env) !== '') {
        $candidates[] = trim($env);
    } else {
        $candidates[] = 'http://127.0.0.1:8000';
        $candidates[] = 'http://localhost/' . basename(ROOT_PATH) . '/public';
    }

    $tried = [];
    foreach ($candidates as $candidate) {
        $base = rtrim($candidate, '/');
        $probe = http_get($base . '/', lite_headers());
        if ($probe['error'] === null && $probe['status'] === 200) {
            return $base;
        }
        $tried[] = '- ' . $base . ': ' . ($probe['error'] ?? 'HTTP ' . $probe['status']);
    }

    fwrite(STDERR, "Aplikasi tidak menjawab di base URL mana pun:\n" . implode("\n", $tried) . "\n\n");
    fwrite(STDERR, "Jalankan servernya lebih dulu, lalu ulangi:\n");
    fwrite(STDERR, "  C:\\xampp\\php\\php.exe -S 127.0.0.1:8000 -t public\n");
    fwrite(STDERR, "atau sebutkan base URL-nya: php bin/check-lite-size.php --base=http://localhost/haven/public\n");
    exit(1);
}

/* ---------- Laporan ---------- */

/**
 * @param list<array<string,mixed>> $results
 * @param list<string> $warnings
 */
function report(array $results, array $warnings, bool $detail): void
{
    printf("%-42s %10s %10s %10s %10s\n", 'Halaman', 'HTML', 'CSS', 'JS', 'Total');
    echo str_repeat('-', 86) . "\n";

    foreach ($results as $page) {
        $name = substr($page['label'] . ' ' . $page['path'], 0, 42);

        if ($page['html'] === null) {
            printf("%-42s %10s %10s %10s %10s  TIDAK TERUKUR\n", $name, '-', '-', '-', '-');
            continue;
        }

        printf(
            "%-42s %10s %10s %10s %10s%s\n",
            $name,
            kb($page['html']),
            kb($page['css']),
            kb($page['js']),
            kb($page['total']),
            over_budget($page) ? '  LEWAT +' . kb($page['total'] - BUDGET_BYTES) : '  oke'
        );

        if ($detail) {
            foreach ($page['assets'] as $asset) {
                printf("    %-38s %s\n", strtoupper($asset['kind']), kb($asset['bytes']) . '  ' . $asset['url']);
            }
        }
    }

    $measured = array_values(array_filter($results, static fn (array $p): bool => $p['html'] !== null));
    $over = array_values(array_filter($measured, 'over_budget'));
    $broken = array_values(array_filter($results, static fn (array $p): bool => $p['errors'] !== []));

    echo "\n" . count($results) . ' halaman diperiksa, ' . count($measured) . ' terukur, '
        . count($over) . " lewat anggaran.\n";

    $shared = shared_assets($measured);
    if ($shared !== []) {
        echo "\nBerkas yang dimuat berulang (ukuran per halaman, cache kosong):\n";
        foreach ($shared as $url => $asset) {
            echo '- ' . kb($asset['bytes']) . '  ' . $url . '  (' . $asset['pages'] . " halaman)\n";
        }
    }

    if ($over !== []) {
        echo "\nLewat " . kb(BUDGET_BYTES) . " (Req 8.9):\n";
        foreach ($over as $page) {
            echo '- ' . $page['label'] . ' (' . $page['path'] . '): ' . kb($page['total'])
                . ', lebih ' . kb($page['total'] - BUDGET_BYTES) . "\n";
        }
    }

    if ($broken !== []) {
        echo "\nTidak bisa diukur sepenuhnya:\n";
        foreach ($broken as $page) {
            foreach ($page['errors'] as $message) {
                echo '- ' . $page['label'] . ' (' . $page['path'] . '): ' . $message . "\n";
            }
        }
    }

    foreach ($warnings as $message) {
        echo "\nPeringatan: $message\n";
    }

    $partly = array_values(array_filter($measured, static fn (array $p): bool => $p['uncompressed'] > 0));
    if ($partly !== []) {
        $worstGzip = max(array_map(static fn (array $p): int => (int) $p['gzip'], $partly));
        echo "\nAda respons tanpa Content-Encoding di " . count($partly) . ' dari ' . count($measured)
            . " halaman: bagian itu dikirim tanpa kompresi.\n";
        echo 'Bila HTML, CSS, dan JS dikirim ter-gzip, halaman terberat kira-kira ' . kb($worstGzip) . ".\n";
        echo "Nyalakan mod_deflate di Apache (deflate_module dan filter_module di httpd.conf),\n";
        echo "lalu ukur ulang sebelum menyimpulkan halamannya yang terlalu berat.\n";
    }

    echo "\nTidak dihitung: gambar, font, dan berkas unggahan pengguna (Req 8.9).\n";
}

function kb(int $bytes): string
{
    return number_format($bytes / 1024, 1) . ' KB';
}

/**
 * Berkas yang sama dipakai lebih dari satu halaman, diurutkan dari yang
 * terbesar. Di sinilah anggaran biasanya habis, jadi lebih berguna dicetak
 * sekali daripada diulang di tiap halaman.
 *
 * @param list<array<string,mixed>> $pages
 * @return array<string,array{bytes:int, pages:int}>
 */
function shared_assets(array $pages): array
{
    $assets = [];
    foreach ($pages as $page) {
        foreach ($page['assets'] as $asset) {
            $url = $asset['url'];
            $assets[$url] ??= ['bytes' => $asset['bytes'], 'pages' => 0];
            $assets[$url]['pages']++;
        }
    }

    $assets = array_filter($assets, static fn (array $a): bool => $a['pages'] > 1);
    uasort($assets, static fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

    return $assets;
}

/* ---------- Argumen ---------- */

/**
 * Nilai argumen panjang, baik `--base=URL` maupun `--base URL`.
 *
 * @param list<string> $argv
 */
function arg_value(array $argv, string $name): ?string
{
    foreach ($argv as $i => $arg) {
        if (str_starts_with($arg, "$name=")) {
            return substr($arg, strlen($name) + 1);
        }
        if ($arg === $name) {
            return $argv[$i + 1] ?? null;
        }
    }

    return null;
}

function usage(): string
{
    return <<<TXT
    Memeriksa anggaran 100 KB mode ringan (Req 8.9).

      php bin/check-lite-size.php [--base=URL] [--rinci]

      --base=URL   base URL aplikasi yang berjalan, misal http://localhost/haven/public.
                   Tanpa argumen ini skrip mencoba HAVEN_BASE_URL, lalu
                   http://127.0.0.1:8000, lalu alias Apache di localhost.
      --rinci      tampilkan ukuran tiap berkas CSS dan JS untuk semua halaman.

    Keluar 0 bila setiap halaman publik, form lapor, dan halaman lacak terkirim
    dengan HTML + CSS + JS tidak lebih dari 100 KB dalam mode ringan. Keluar 1
    bila ada yang lewat atau ada halaman yang tidak bisa diukur.

    TXT;
}
