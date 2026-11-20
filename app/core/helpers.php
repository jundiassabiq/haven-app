<?php
declare(strict_types=1);

/* ---------- Output ---------- */

/** Escape for HTML text and attribute context. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** App URL respecting the base path (empty for `php -S`, e.g. /haven-v2/public under Apache). */
function url(string $path = '/', array $query = []): string
{
    $u = App::basePath() . '/' . ltrim($path, '/');
    if ($u === '') {
        $u = '/';
    }
    return $query ? $u . '?' . http_build_query($query) : $u;
}

/** Asset URL with a cache-busting version from the file's mtime. */
function asset(string $path): string
{
    $file = ROOT_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . ltrim($path, '/')) . $v;
}

function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

/** Marks a nav link as current. */
function current_attr(string $key, string $active): string
{
    return $key === $active ? ' aria-current="page"' : '';
}

/* ---------- Request ---------- */

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_bool(string $key): bool
{
    return isset($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** "Chrome · Windows" from a user agent, for login activity lists. */
function ua_summary(?string $ua): string
{
    $ua = (string) $ua;
    $browser = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') => 'Opera',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'Browser lain',
    };
    $os = match (true) {
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Mac OS') => 'macOS',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'perangkat lain',
    };
    return "$browser · $os";
}

/* ---------- Responses ---------- */

function redirect(string $path, int $status = 303): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

/** Redirect back to the form with errors and old input (password fields are never kept). */
function back_with(string $path, array $errors, array $old = []): never
{
    foreach (array_keys($old) as $k) {
        if (str_contains((string) $k, 'password') || $k === '_csrf') {
            unset($old[$k]);
        }
    }
    Session::flash('errors', $errors);
    Session::flash('old', $old);
    redirect($path);
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- Forms ---------- */

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function old(string $key, string $default = ''): string
{
    $old = Session::getFlash('old') ?? [];
    return isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default;
}

function form_errors(): array
{
    return Session::getFlash('errors') ?? [];
}

/** Error paragraph for a field; id matches what app.js uses so it can clear it on input. */
function field_error(string $field): string
{
    $msg = form_errors()[$field] ?? null;
    if ($msg === null) {
        return '';
    }
    return '<p class="error" id="' . e($field) . '-err">'
        . '<svg class="icon" aria-hidden="true" style="width:16px;height:16px;margin-top:2px"><use href="#i-alert"/></svg>'
        . '<span>' . e($msg) . '</span></p>';
}

/** aria attributes for an invalid field. Pass the hint id to keep it described too. */
function field_aria(string $field, ?string $hintId = null): string
{
    $invalid = isset(form_errors()[$field]);
    $ids = array_filter([$hintId, $invalid ? "$field-err" : null]);
    $out = $ids ? ' aria-describedby="' . e(implode(' ', $ids)) . '"' : '';
    return $out . ($invalid ? ' aria-invalid="true"' : '');
}

/**
 * Kolom tidak valid pertama menurut urutan kolom di halaman, atau null bila tidak ada
 * galat. Urutan datang dari view karena pesan galat bisa tiba dalam urutan apa pun.
 *
 * @param list<string> $order nama kolom dalam urutan tampil
 */
function first_invalid_field(array $order): ?string
{
    $errors = form_errors();
    foreach ($order as $field) {
        if (isset($errors[$field])) {
            return $field;
        }
    }
    return null;
}

/**
 * ` autofocus` bila kolom ini adalah kolom tidak valid pertama (Req 8.3). Tanpa
 * JavaScript, atribut ini yang memindahkan fokus ke kolom yang perlu diperbaiki.
 */
function autofocus_if(?string $firstInvalid, string $field): string
{
    return $firstInvalid !== null && $firstInvalid === $field ? ' autofocus' : '';
}

/* ---------- Dates ---------- */

const ID_MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const ID_MONTHS_LONG = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const ID_DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** "20 Nov 2026" or with time "20 Nov 2026 · 08.40". */
function id_date(?string $datetime, bool $withTime = false, bool $long = false): string
{
    if (!$datetime) {
        return '-';
    }
    $t = strtotime($datetime);
    $m = $long ? ID_MONTHS_LONG[(int) date('n', $t)] : ID_MONTHS[(int) date('n', $t)];
    $s = date('j', $t) . ' ' . $m . ' ' . date('Y', $t);
    return $withTime ? $s . ' · ' . date('H.i', $t) : $s;
}

function id_today_long(): string
{
    return ID_DAYS[(int) date('w')] . ', ' . id_date(date('Y-m-d'), false, true);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Masks an email for display: ra***@demo.id */
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($local, 0, 2) . '***@' . $domain;
}

/* ---------- Case labels ---------- */

function status_badge(string $status, string $suffix = ''): string
{
    return '<span class="badge" data-status="' . e($status) . '">' . e((Report::STATUS[$status] ?? $status) . $suffix) . '</span>';
}

function urgency_badge(string $urgency, string $prefix = ''): string
{
    return '<span class="badge" data-urgency="' . e($urgency) . '">' . e($prefix . (Report::URGENCY[$urgency] ?? $urgency)) . '</span>';
}

/** "Dewi Anggraini" → "Dewi A." (what survivors see of their companion). */
function short_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return count($parts) > 1 ? $parts[0] . ' ' . mb_strtoupper(mb_substr($parts[1], 0, 1)) . '.' : $parts[0];
}

function initials(string $name): string
{
    return mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)), 0, 2))));
}

/* ---------- Bahasa dan mode tampilan (Req 8) ---------- */

/**
 * Kamus kedua bahasa, dibaca sekali per permintaan.
 *
 * `I18n` sengaja murni, jadi pemuatan berkas ada di sini.
 *
 * @return array<string,array<string,string>>
 */
function lang_dict(): array
{
    static $dict = null;
    if ($dict === null) {
        $dict = [];
        foreach (array_keys(I18n::LANGS) as $code) {
            $file = APP_PATH . '/lang/' . $code . '.php';
            $table = is_file($file) ? require $file : [];
            $dict[$code] = is_array($table) ? $table : [];
        }
    }
    return $dict;
}

/** Kode bahasa aktif untuk atribut `lang` dan untuk `t()` (Req 8.6). */
function lang(): string
{
    $choice = Session::get(I18n::SESSION_KEY);
    return I18n::lang(is_string($choice) ? $choice : null);
}

/** Teks antarmuka dalam bahasa aktif, cadangan Bahasa Indonesia (Req 8.5, 8.7). */
function t(string $key, array $vars = []): string
{
    return I18n::t($key, lang(), lang_dict(), $vars);
}

/** `t()` dengan cadangan teks dari kode bila kunci itu belum ada di kamus mana pun. */
function t_or(string $key, string $fallback, array $vars = []): string
{
    $lang = lang();
    $dict = lang_dict();

    return I18n::has($key, $lang, $dict) || I18n::has($key, I18n::DEFAULT_LANG, $dict)
        ? I18n::t($key, $lang, $dict, $vars)
        : $fallback;
}

/** `t()` yang sudah di-escape, untuk dipakai langsung di dalam HTML. */
function te(string $key, array $vars = []): string
{
    return e(t($key, $vars));
}

/** Mode tampilan aktif: `Mode::LITE` atau `Mode::FULL` (Req 8.10, 8.13). */
function display_mode(): string
{
    $choice = Session::get(Mode::SESSION_KEY);
    return Mode::resolve(is_string($choice) ? $choice : null, $_SERVER['HTTP_SAVE_DATA'] ?? null);
}

/** Benar bila permintaan ini dilayani dalam mode ringan (Req 8.8, 8.10, 8.13). */
function lite(): bool
{
    return display_mode() === Mode::LITE;
}

/** Benar bila mode ringan menyala karena header `Save-Data`, bukan karena pilihan pengguna. */
function lite_by_header(): bool
{
    $choice = Session::get(Mode::SESSION_KEY);
    return lite() && !Mode::chosen(is_string($choice) ? $choice : null);
}

/** Jalur permintaan sekarang tanpa base path, untuk kolom "kembali" pemilih bahasa dan mode. */
function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $base = App::basePath();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $query = (string) parse_url($uri, PHP_URL_QUERY);
    return ($path === '' ? '/' : $path) . ($query === '' ? '' : '?' . $query);
}

/* ---------- Batas layanan ---------- */

/** Jam Reply_SLA dari .env lewat Sla::hours, supaya view tidak menulis angkanya sendiri (Req 6.1). */
function sla_hours(bool $inDanger): int
{
    return Sla::hours($inDanger, [
        Sla::ENV_DANGER => Config::get(Sla::ENV_DANGER),
        Sla::ENV_DEFAULT => Config::get(Sla::ENV_DEFAULT),
    ]);
}

/**
 * Benar bila kasus ini sudah melewati batas waktu balasannya (Req 6.8).
 *
 * Keputusannya ada di `Sla::isOverdue`; helper ini hanya mengambil konfigurasi
 * dan waktu sekarang, satu kali per permintaan supaya semua baris antrean
 * dinilai pada saat yang sama.
 *
 * `$case` perlu `in_danger`, `created_at`, dan kolom turunan `last_survivor_at`
 * serta `last_reply_at` seperti yang dipilih `Report::queue`.
 */
function sla_overdue(array $case): bool
{
    static $now = null;
    $now ??= new DateTimeImmutable('now');

    return Sla::isOverdue($case, $now, [
        Sla::ENV_DANGER => Config::get(Sla::ENV_DANGER),
        Sla::ENV_DEFAULT => Config::get(Sla::ENV_DEFAULT),
    ]);
}

/** "2 jam", atau "2x24 jam" untuk kelipatan 24 jam penuh. Kata "jam" ikut bahasa aktif (Req 8.5). */
function sla_duration(int $hours): string
{
    $unit = t('common.hours');
    return $hours >= 24 && $hours % 24 === 0 ? intdiv($hours, 24) . 'x24 ' . $unit : $hours . ' ' . $unit;
}

/**
 * Pernyataan batas layanan untuk beranda, form lapor, dan halaman tentang (Req 9.5, 9.6):
 * Haven bukan layanan darurat, batas waktu balasan untuk kasus biasa dan kasus bahaya,
 * lalu nomor darurat. Angka jam diambil dari konfigurasi SLA, bukan ditulis di view.
 *
 * Kalimatnya ada di kamus `service.limit` supaya kaki halaman, panel darurat, dan
 * halaman publik memakai satu teks yang sama dalam kedua bahasa (Req 8.5, 8.7).
 * Keluarannya HTML: nomor darurat berupa tautan `tel:`.
 */
function service_limit_html(): string
{
    return t('service.limit', [
        'biasa' => e(sla_duration(sla_hours(false))),
        'bahaya' => e(sla_duration(sla_hours(true))),
    ]);
}

/* ---------- Pagination ---------- */

/** @return array{page:int, per:int, offset:int, total:int, pages:int} */
function paginate(int $total, int $per = 20): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = min($pages, max(1, (int) ($_GET['halaman'] ?? 1)));
    return ['page' => $page, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total, 'pages' => $pages];
}

/** Current GET query with some keys replaced (for filter and page links). */
function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '?';
}

function selected(string $a, string $b): string
{
    return $a === $b ? ' selected' : '';
}

function checked(bool $on): string
{
    return $on ? ' checked' : '';
}
