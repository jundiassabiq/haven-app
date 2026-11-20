<?php
declare(strict_types=1);

/**
 * Fixed-window attempt counter stored in table rate_limits (rl_key, hits, reset_at).
 * Plain SQL only, so tests can run it on SQLite.
 */
final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function default(): self
    {
        return new self(Database::pdo());
    }

    private static function k(string $key): string
    {
        return hash('sha256', $key);
    }

    private function row(string $key): ?array
    {
        $st = $this->pdo->prepare('SELECT hits, reset_at FROM rate_limits WHERE rl_key = ?');
        $st->execute([self::k($key)]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r === false || (int) $r['reset_at'] <= time()) {
            return null;
        }
        return $r;
    }

    public function attempts(string $key): int
    {
        return (int) ($this->row($key)['hits'] ?? 0);
    }

    public function tooManyAttempts(string $key, int $max): bool
    {
        return $this->attempts($key) >= $max;
    }

    /** Count one attempt. The window starts at the first attempt. Returns the new count. */
    public function hit(string $key, int $decaySeconds): int
    {
        $row = $this->row($key);
        if ($row === null) {
            $this->pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([self::k($key)]);
            $this->pdo->prepare('INSERT INTO rate_limits (rl_key, hits, reset_at) VALUES (?, 1, ?)')
                ->execute([self::k($key), time() + $decaySeconds]);
            return 1;
        }
        $this->pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?')->execute([self::k($key)]);
        return (int) $row['hits'] + 1;
    }

    public function availableIn(string $key): int
    {
        $row = $this->row($key);
        return $row === null ? 0 : max(0, (int) $row['reset_at'] - time());
    }

    public function clear(string $key): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([self::k($key)]);
    }

    /** "15 menit" style wait text. */
    public static function waitText(int $seconds): string
    {
        return $seconds < 60 ? 'beberapa detik' : (int) ceil($seconds / 60) . ' menit';
    }
}
