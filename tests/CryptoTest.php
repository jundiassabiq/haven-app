<?php
declare(strict_types=1);

function crypto(): Crypto
{
    static $c;
    return $c ??= new Crypto(random_bytes(32));
}

test('round-trips text, including multi-byte characters', function () {
    $plain = "Kronologi: kejadian di kampus.\nBaris kedua — ✓ ünïcödé";
    assert_same($plain, crypto()->decrypt(crypto()->encrypt($plain)));
});

test('ciphertext does not contain the plain text', function () {
    $c = crypto()->encrypt('rahasia sekali');
    assert_false(str_contains(base64_decode($c), 'rahasia'));
});

test('same input encrypts differently every time (random IV)', function () {
    assert_true(crypto()->encrypt('sama') !== crypto()->encrypt('sama'));
});

test('tampered ciphertext is rejected', function () {
    $raw = base64_decode(crypto()->encrypt('isi laporan'));
    $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
    assert_same(null, crypto()->decrypt(base64_encode($raw)));
});

test('tampered tag is rejected', function () {
    $raw = base64_decode(crypto()->encrypt('isi laporan'));
    $raw[14] = chr(ord($raw[14]) ^ 1);
    assert_same(null, crypto()->decrypt(base64_encode($raw)));
});

test('another key cannot decrypt', function () {
    $other = new Crypto(random_bytes(32));
    assert_same(null, $other->decrypt(crypto()->encrypt('isi laporan')));
});

test('garbage input returns null instead of throwing', function () {
    assert_same(null, crypto()->decrypt('bukan-base64!!'));
    assert_same(null, crypto()->decrypt(base64_encode('pendek')));
});

test('rejects keys that are not 32 bytes', function () {
    assert_throws(InvalidArgumentException::class, fn () => new Crypto('pendek'));
});

test('parses a generated APP_KEY', function () {
    $key = Crypto::generateKey();
    assert_true(str_starts_with($key, 'base64:'));
    assert_same(32, strlen(Crypto::parseKey($key)));
    assert_throws(RuntimeException::class, fn () => Crypto::parseKey(''));
    assert_throws(RuntimeException::class, fn () => Crypto::parseKey('base64:' . base64_encode('16-bytes-only!!!')));
});
