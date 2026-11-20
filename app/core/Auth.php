<?php
declare(strict_types=1);

/** Session-based login with role checks. Roles: survivor (penyintas), companion (pendamping), admin. */
final class Auth
{
    public const ROLES = ['survivor', 'companion', 'admin'];

    public const ROLE_LABEL = ['survivor' => 'Penyintas', 'companion' => 'Pendamping', 'admin' => 'Admin'];

    private const HOME = ['survivor' => '/penyintas', 'companion' => '/pendamping', 'admin' => '/admin'];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id !== null) {
                $u = User::find((int) $id);
                // Deactivated, deleted, or password changed elsewhere: this session is no longer valid.
                if ($u === null || !(int) $u['is_active'] || (int) $u['session_version'] !== (int) Session::get('session_version')) {
                    Session::forget('user_id');
                    Session::forget('session_version');
                    $u = null;
                }
                self::$user = $u;
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::user()['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        Session::put('user_id', (int) $user['id']);
        Session::put('session_version', (int) $user['session_version']);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::reset();
        self::$user = null;
        self::$loaded = true;
    }

    /** CLI scripts (bin/seed-demo.php) act as a user so audit logs and status logs name a real actor. */
    public static function actAs(?array $user): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('actAs is only for CLI scripts.');
        }
        self::$user = $user;
        self::$loaded = true;
    }

    /** Pure check, used by middleware and tests. */
    public static function canAccess(?array $user, array $roles): bool
    {
        if ($user === null || !(int) ($user['is_active'] ?? 0)) {
            return false;
        }
        return in_array($user['role'] ?? null, $roles, true);
    }

    public static function homePath(?array $user): string
    {
        return self::HOME[$user['role'] ?? ''] ?? '/';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $path = (string) parse_url($uri, PHP_URL_PATH);
            if (App::basePath() !== '' && str_starts_with($path, App::basePath())) {
                $path = substr($path, strlen(App::basePath()));
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                Session::put('intended', $path);
            }
            if (!Session::getFlash('info')) {
                Session::flash('info', 'Masuk dulu untuk membuka halaman itu.');
            }
            redirect('/masuk');
        }
    }

    /** @throws HttpException 403 */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!self::canAccess(self::user(), $roles)) {
            Audit::log('access.denied', 'route', null, 'Peran ' . self::role() . ' membuka ' . ($_SERVER['REQUEST_URI'] ?? ''));
            throw new HttpException(403);
        }
    }

    /** Where to go after login: a safe, local intended path or the role home. */
    public static function intendedPath(array $user): string
    {
        $p = Session::get('intended');
        Session::forget('intended');
        if (is_string($p) && preg_match('#^/[a-z0-9/_\-]*$#i', $p) && !str_starts_with($p, '//')) {
            return $p;
        }
        return self::homePath($user);
    }
}
