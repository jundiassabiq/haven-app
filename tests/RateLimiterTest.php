<?php
declare(strict_types=1);

function limiter(): array
{
    $pdo = sqlite('CREATE TABLE rate_limits (rl_key TEXT PRIMARY KEY, hits INTEGER NOT NULL, reset_at INTEGER NOT NULL)');
    return [new RateLimiter($pdo), $pdo];
}

test('counts attempts and blocks at the limit', function () {
    [$rl] = limiter();
    for ($i = 1; $i <= 5; $i++) {
        assert_false($rl->tooManyAttempts('lacak:1.2.3.4', 5), "attempt $i should be allowed");
        assert_same($i, $rl->hit('lacak:1.2.3.4', 900));
    }
    assert_true($rl->tooManyAttempts('lacak:1.2.3.4', 5));
});

test('keys are independent', function () {
    [$rl] = limiter();
    $rl->hit('a', 900);
    $rl->hit('a', 900);
    assert_same(2, $rl->attempts('a'));
    assert_same(0, $rl->attempts('b'));
});

test('window expiry resets the counter', function () {
    [$rl, $pdo] = limiter();
    for ($i = 0; $i < 5; $i++) {
        $rl->hit('k', 900);
    }
    $pdo->exec('UPDATE rate_limits SET reset_at = ' . (time() - 1));
    assert_false($rl->tooManyAttempts('k', 5));
    assert_same(1, $rl->hit('k', 900));
});

test('clear removes the counter', function () {
    [$rl] = limiter();
    $rl->hit('k', 900);
    $rl->clear('k');
    assert_same(0, $rl->attempts('k'));
});

test('availableIn reports the remaining wait', function () {
    [$rl] = limiter();
    assert_same(0, $rl->availableIn('k'));
    $rl->hit('k', 900);
    $left = $rl->availableIn('k');
    assert_true($left > 890 && $left <= 900);
    assert_same('15 menit', RateLimiter::waitText($left));
});

test('stores a hash, not the raw key (keys may contain emails or IPs)', function () {
    [$rl, $pdo] = limiter();
    $rl->hit('login:email:rani@demo.id', 900);
    $key = $pdo->query('SELECT rl_key FROM rate_limits')->fetchColumn();
    assert_same(64, strlen($key));
    assert_false(str_contains($key, 'rani'));
});
