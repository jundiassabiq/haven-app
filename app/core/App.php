<?php
declare(strict_types=1);

/** Web request lifecycle: headers, session, CSRF, middleware, dispatch, error pages. */
final class App
{
    private static string $basePath = '';

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function setBasePath(string $base): void
    {
        self::$basePath = rtrim($base, '/');
    }

    public static function run(Router $router): void
    {
        self::setBasePath(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
        self::sendSecurityHeaders();

        try {
            Session::start();

            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (self::$basePath !== '' && str_starts_with($path, self::$basePath)) {
                $path = substr($path, strlen(self::$basePath));
            }
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            $route = $router->match($method, $path);

            if ($method === 'POST') {
                // PHP drops the whole body when it exceeds post_max_size; say so instead of failing CSRF.
                $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
                if ($len > 0 && !$_POST && !$_FILES && $len > self::iniBytes((string) ini_get('post_max_size'))) {
                    throw new HttpException(413, 'Ukuran kiriman terlalu besar. Kirim maksimal ' . Upload::MAX_FILES . ' file bukti, masing-masing paling besar 5 MB.');
                }
                Csrf::verifyRequest();
            }
            foreach ($route['middleware'] as $mw) {
                self::middleware($mw);
            }

            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            $handler($route['params']);
        } catch (HttpException $e) {
            self::renderError($e->status, $e->getMessage());
        } catch (Throwable $e) {
            $ref = 'ERR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            Logger::error($ref . ' ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            // Setup problems are safe to explain; everything else stays generic.
            $setup = $e instanceof RuntimeException && preg_match('/^(Tidak bisa terhubung ke database|APP_KEY)/', $e->getMessage()) ? $e->getMessage() : '';
            self::renderError(500, $setup, $ref, Config::bool('APP_DEBUG') ? $e : null);
        }
    }

    /** Middleware names: auth, guest, role:<survivor|companion|admin>[,<role>…] */
    private static function middleware(string $mw): void
    {
        [$name, $arg] = array_pad(explode(':', $mw, 2), 2, null);
        match ($name) {
            'auth' => Auth::requireLogin(),
            'guest' => Auth::check() ? redirect(Auth::homePath(Auth::user())) : null,
            'role' => Auth::requireRole(...explode(',', (string) $arg)),
            default => throw new RuntimeException("Unknown middleware $name"),
        };
    }

    public static function renderError(int $status, string $message = '', string $ref = '', ?Throwable $debug = null): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $message ?: self::errorTitle($status), 'status' => $status]);
            return;
        }
        try {
            echo View::render('pages/error', [
                'title' => self::errorTitle($status) . ' | Haven',
                'status' => $status,
                'message' => $message,
                'ref' => $ref,
                'debug' => $debug,
            ], 'public');
        } catch (Throwable) {
            echo '<h1>' . $status . '</h1><p>Terjadi kendala. Hubungi 112 atau SAPA 129 jika kamu dalam bahaya.</p>';
        }
    }

    public static function errorTitle(int $status): string
    {
        return match ($status) {
            403 => 'Akses ditolak',
            404 => 'Halaman tidak ditemukan',
            405 => 'Metode tidak diizinkan',
            419 => 'Sesi formulir kedaluwarsa',
            413 => 'Kiriman terlalu besar',
            429 => 'Terlalu banyak percobaan',
            default => 'Terjadi kendala',
        };
    }

    private static function iniBytes(string $v): int
    {
        $n = (int) $v;
        return match (strtoupper(substr(trim($v), -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
    }

    private static function sendSecurityHeaders(): void
    {
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store, private');
    }
}
