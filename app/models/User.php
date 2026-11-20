<?php
declare(strict_types=1);

final class User
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ?', [self::normalizeEmail($email)]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return (bool) Database::value(
            'SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?',
            [self::normalizeEmail($email), $exceptId ?? 0]
        );
    }

    /** Pure validation for the public sign-up form (uniqueness is checked separately). */
    public static function registrationErrors(array $in): array
    {
        return Validator::make($in, [
            'name' => 'required|max:40',
            'email' => 'required|email',
            'password' => 'required|min:8|max:200',
            'password_confirm' => 'required|same:password',
            'agree' => 'accepted',
        ], [
            'name.required' => 'Tulis nama panggilan. Boleh nama samaran.',
            'email.required' => 'Masukkan email untuk masuk dan pemulihan akun.',
            'password.required' => 'Buat kata sandi minimal 8 karakter.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password_confirm.required' => 'Ketik ulang kata sandimu.',
            'password_confirm.same' => 'Kata sandi tidak sama dengan yang di atas.',
        ]);
    }

    /**
     * Creates a staff or survivor account.
     *
     * `$childTrained` menandai pendamping terlatih kasus anak (Req 4.9). Hanya
     * pendamping yang bisa ditandai: peran lain selalu tersimpan 0, jadi
     * `Assignment::candidates` tidak pernah menawarkan admin pada kasus anak.
     */
    public static function create(string $name, string $email, string $password, string $role, ?string $region = null, bool $childTrained = false): int
    {
        if (!in_array($role, Auth::ROLES, true)) {
            throw new InvalidArgumentException('Peran tidak valid');
        }
        return Database::insert(
            'INSERT INTO users (name, email, password_hash, role, region, child_trained, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [trim($name), self::normalizeEmail($email), password_hash($password, PASSWORD_DEFAULT), $role, $region, (int) ($childTrained && $role === 'companion'), now(), now()]
        );
    }

    /** Penanda pendamping terlatih kasus anak, dipakai Admin > Pengguna (Req 4.9). */
    public static function setChildTrained(int $id, bool $trained): void
    {
        Database::run(
            "UPDATE users SET child_trained = ?, updated_at = ? WHERE id = ? AND role = 'companion'",
            [(int) $trained, now(), $id]
        );
    }

    /** Changing the password also bumps session_version, which signs out every other session. */
    public static function updatePassword(int $id, string $password): int
    {
        Database::run(
            'UPDATE users SET password_hash = ?, session_version = session_version + 1, updated_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), now(), $id]
        );
        return (int) Database::value('SELECT session_version FROM users WHERE id = ?', [$id]);
    }

    public static function bumpSessionVersion(int $id): int
    {
        Database::run('UPDATE users SET session_version = session_version + 1, updated_at = ? WHERE id = ?', [now(), $id]);
        return (int) Database::value('SELECT session_version FROM users WHERE id = ?', [$id]);
    }

    public static function updateProfile(int $id, string $name, string $email, bool $allowEmail, bool $allowWa, ?string $safeTime): void
    {
        Database::run(
            'UPDATE users SET name = ?, email = ?, allow_email = ?, allow_whatsapp = ?, safe_time = ?, updated_at = ? WHERE id = ?',
            [trim($name), self::normalizeEmail($email), (int) $allowEmail, (int) $allowWa, $safeTime ?: null, now(), $id]
        );
    }

    public static function recordLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = ?, delete_requested_at = NULL WHERE id = ?', [now(), $id]);
    }

    public static function rehashIfNeeded(array $user, string $password): void
    {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE users SET is_active = ?, session_version = session_version + 1, updated_at = ? WHERE id = ?', [(int) $active, now(), $id]);
    }

    public static function requestDeletion(int $id): void
    {
        Database::run('UPDATE users SET delete_requested_at = ?, session_version = session_version + 1 WHERE id = ?', [now(), $id]);
    }

    /** Staff list for admin (survivors are counted, never listed). */
    public static function staff(): array
    {
        return Database::all("SELECT id, name, email, role, region, child_trained, is_active, last_login_at FROM users WHERE role IN ('companion','admin') ORDER BY role DESC, is_active DESC, name");
    }

    public static function survivorCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'survivor' AND delete_requested_at IS NULL");
    }

    /** Last successful logins (from the audit log) for the profile page. */
    public static function recentLogins(int $id, int $limit = 3): array
    {
        return Database::all(
            "SELECT created_at, user_agent FROM audit_logs WHERE actor_id = ? AND action = 'auth.login' ORDER BY id DESC LIMIT " . max(1, $limit),
            [$id]
        );
    }
}
