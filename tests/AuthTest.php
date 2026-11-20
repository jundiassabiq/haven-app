<?php
declare(strict_types=1);

function valid_registration(array $override = []): array
{
    return $override + ['name' => 'Rani', 'email' => 'rani@demo.id', 'password' => 'kata-sandi-aman', 'password_confirm' => 'kata-sandi-aman', 'agree' => '1'];
}

test('registration: valid input has no errors', function () {
    assert_same([], User::registrationErrors(valid_registration()));
});

test('registration: every field is checked', function () {
    $e = User::registrationErrors(['name' => '', 'email' => 'bukan-email', 'password' => 'pendek', 'password_confirm' => 'lain', 'agree' => '']);
    assert_same(['name', 'email', 'password', 'password_confirm', 'agree'], array_keys($e));
    assert_same('Kata sandi minimal 8 karakter.', $e['password']);
});

test('registration: confirmation must match', function () {
    $e = User::registrationErrors(valid_registration(['password_confirm' => 'kata-sandi-beda']));
    assert_same('Kata sandi tidak sama dengan yang di atas.', $e['password_confirm']);
});

test('registration: nickname is limited to 40 characters', function () {
    assert_true(isset(User::registrationErrors(valid_registration(['name' => str_repeat('a', 41)]))['name']));
});

test('emails are normalized before lookup and storage', function () {
    assert_same('rani@demo.id', User::normalizeEmail('  Rani@Demo.ID '));
});

test('roles: each role reaches only its own area', function () {
    $survivor = ['role' => 'survivor', 'is_active' => 1];
    $companion = ['role' => 'companion', 'is_active' => 1];
    $admin = ['role' => 'admin', 'is_active' => 1];
    assert_true(Auth::canAccess($survivor, ['survivor']));
    assert_false(Auth::canAccess($survivor, ['companion']));
    assert_false(Auth::canAccess($survivor, ['admin']));
    assert_false(Auth::canAccess($companion, ['admin']));
    assert_true(Auth::canAccess($companion, ['companion', 'admin']));
    assert_true(Auth::canAccess($admin, ['admin']));
});

test('roles: guests and deactivated accounts are refused', function () {
    assert_false(Auth::canAccess(null, ['survivor']));
    assert_false(Auth::canAccess(['role' => 'admin', 'is_active' => 0], ['admin']));
});

test('roles: home path per role', function () {
    assert_same('/penyintas', Auth::homePath(['role' => 'survivor']));
    assert_same('/pendamping', Auth::homePath(['role' => 'companion']));
    assert_same('/admin', Auth::homePath(['role' => 'admin']));
    assert_same('/', Auth::homePath(null));
});

test('intended path after login only allows local paths', function () {
    $_SESSION['intended'] = '//evil.example/x';
    assert_same('/penyintas', Auth::intendedPath(['role' => 'survivor']));
    $_SESSION['intended'] = 'https://evil.example';
    assert_same('/penyintas', Auth::intendedPath(['role' => 'survivor']));
    $_SESSION['intended'] = '/akun';
    assert_same('/akun', Auth::intendedPath(['role' => 'survivor']));
    assert_same(null, $_SESSION['intended'] ?? null, 'intended is consumed');
});

test('reset tokens: malformed tokens are rejected without a database query', function () {
    assert_same('invalid', PasswordReset::lookup('not-a-token')['status']);
    assert_same('invalid', PasswordReset::lookup(str_repeat('g', 64))['status']);
});
