<?php
declare(strict_types=1);

/** One-time reset/invite tokens. Only the SHA-256 of the token is stored. */
final class PasswordReset
{
    public const RESET_MINUTES = 30;
    public const INVITE_MINUTES = 72 * 60;

    /** Creates a token, invalidating earlier unused ones. Returns the raw token for the link. */
    public static function issue(int $userId, int $minutes = self::RESET_MINUTES): string
    {
        $token = bin2hex(random_bytes(32));
        Database::run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $userId]);
        Database::run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + $minutes * 60), now()]
        );
        return $token;
    }

    /** @return array{status:'ok'|'expired'|'invalid', reset?:array, user?:array} */
    public static function lookup(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['status' => 'invalid'];
        }
        $row = Database::one(
            'SELECT pr.*, u.email, u.name, u.is_active, u.last_login_at FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ?',
            [hash('sha256', $token)]
        );
        if ($row === null || $row['used_at'] !== null || !(int) $row['is_active']) {
            return ['status' => 'invalid'];
        }
        if (strtotime($row['expires_at']) < time()) {
            return ['status' => 'expired'];
        }
        return ['status' => 'ok', 'reset' => $row];
    }

    public static function markUsed(int $id): void
    {
        Database::run('UPDATE password_resets SET used_at = ? WHERE id = ?', [now(), $id]);
    }
}
