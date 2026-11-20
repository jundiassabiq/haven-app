<?php
declare(strict_types=1);

test('token is 64 hex characters and stable within a session', function () {
    $t = Csrf::token();
    assert_true(preg_match('/^[a-f0-9]{64}$/', $t) === 1);
    assert_same($t, Csrf::token());
});

test('accepts the session token', function () {
    assert_true(Csrf::valid(Csrf::token()));
});

test('rejects missing, empty, and wrong tokens', function () {
    $t = Csrf::token();
    assert_false(Csrf::valid(null));
    assert_false(Csrf::valid(''));
    assert_false(Csrf::valid(strrev($t)));
});

test('rejects any token when the session has none', function () {
    $_SESSION = [];
    assert_false(Csrf::valid('anything'));
    assert_false(Csrf::valid(''));
});

test('rotate issues a new token and invalidates the old one', function () {
    $old = Csrf::token();
    Csrf::rotate();
    $new = Csrf::token();
    assert_true($old !== $new);
    assert_false(Csrf::valid($old));
});

test('verifyRequest reads the form field or the fetch header', function () {
    $t = Csrf::token();
    $_POST = ['_csrf' => $t];
    Csrf::verifyRequest();
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $t;
    Csrf::verifyRequest();
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    assert_throws(HttpException::class, fn () => Csrf::verifyRequest(), fn ($e) => assert_same(419, $e->status));
});
