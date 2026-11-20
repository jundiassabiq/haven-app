<?php
declare(strict_types=1);

test('required rejects empty and whitespace-only values', function () {
    $e = Validator::make(['a' => '', 'b' => '   ', 'c' => 'x'], ['a' => 'required', 'b' => 'required', 'c' => 'required']);
    assert_same(['a', 'b'], array_keys($e));
    assert_same('Bagian ini wajib diisi.', $e['a']);
});

test('optional empty fields skip format rules', function () {
    assert_same([], Validator::make(['email' => '', 'tgl' => ''], ['email' => 'email', 'tgl' => 'date']));
});

test('email format', function () {
    assert_same([], Validator::make(['e' => 'nama@email.com'], ['e' => 'email']));
    assert_true(isset(Validator::make(['e' => 'nama@'], ['e' => 'email'])['e']));
});

test('min and max count characters, not bytes', function () {
    assert_same([], Validator::make(['n' => 'ééé'], ['n' => 'max:3']));
    $e = Validator::make(['p' => 'abc'], ['p' => 'min:8']);
    assert_same('Tulis minimal 8 karakter.', $e['p']);
});

test('same compares with another field', function () {
    assert_same([], Validator::make(['a' => 'x1', 'b' => 'x1'], ['b' => 'same:a']));
    assert_true(isset(Validator::make(['a' => 'x1', 'b' => 'x2'], ['b' => 'same:a'])['b']));
});

test('in, regex (array form), date, integer, between', function () {
    $e = Validator::make(
        ['r' => 'root', 'code' => 'HV-ABC123', 'd' => '2026-02-30', 'i' => '4.5', 'n' => '7'],
        ['r' => 'in:survivor,companion,admin', 'code' => ['regex:/^HV-[A-Z0-9]{6}$/'], 'd' => 'date', 'i' => 'integer', 'n' => 'between:1,5']
    );
    assert_same(['r', 'd', 'i', 'n'], array_keys($e));
});

test('accepted is for checkboxes', function () {
    assert_same([], Validator::make(['ok' => '1'], ['ok' => 'accepted']));
    assert_same('Centang untuk melanjutkan.', Validator::make([], ['ok' => 'accepted'])['ok']);
});

test('custom messages by field.rule and by field', function () {
    $e = Validator::make(['a' => '', 'b' => 'x'], ['a' => 'required', 'b' => 'min:3'], ['a.required' => 'Isi A.', 'b' => 'B salah.']);
    assert_same('Isi A.', $e['a']);
    assert_same('B salah.', $e['b']);
});

test('reports only the first failing rule per field', function () {
    $e = Validator::make(['p' => ''], ['p' => 'required|min:8']);
    assert_same('Bagian ini wajib diisi.', $e['p']);
});

test('unknown rules are a programming error', function () {
    assert_throws(InvalidArgumentException::class, fn () => Validator::make(['a' => 'x'], ['a' => 'tidakada']));
});
