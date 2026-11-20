<?php
declare(strict_types=1);

function demo_router(): Router
{
    $r = new Router();
    $r->get('/', 'home');
    $r->get('/masuk', 'showLogin', ['guest']);
    $r->post('/masuk', 'login');
    $r->get('/kasus/{code}', 'case');
    $r->post('/admin/pengguna/{id}/status', 'status', ['role:admin']);
    $r->get('/a-b.c', 'dots');
    return $r;
}

test('matches the root path', function () {
    assert_same('home', demo_router()->match('GET', '/')['handler']);
});

test('ignores trailing and doubled slashes', function () {
    assert_same('showLogin', demo_router()->match('GET', '/masuk/')['handler']);
    assert_same('showLogin', demo_router()->match('GET', '//masuk')['handler']);
});

test('chooses the handler by method', function () {
    assert_same('login', demo_router()->match('POST', '/masuk')['handler']);
});

test('treats HEAD like GET', function () {
    assert_same('showLogin', demo_router()->match('HEAD', '/masuk')['handler']);
});

test('extracts named parameters', function () {
    $m = demo_router()->match('GET', '/kasus/HV-9B2X6P');
    assert_same(['code' => 'HV-9B2X6P'], $m['params']);
    $m = demo_router()->match('POST', '/admin/pengguna/12/status');
    assert_same('12', $m['params']['id']);
    assert_same(['role:admin'], $m['middleware']);
});

test('a parameter never spans a slash', function () {
    assert_throws(HttpException::class, fn () => demo_router()->match('GET', '/kasus/a/b'), fn ($e) => assert_same(404, $e->status));
});

test('unknown path is 404', function () {
    assert_throws(HttpException::class, fn () => demo_router()->match('GET', '/tidak-ada'), fn ($e) => assert_same(404, $e->status));
});

test('known path with wrong method is 405', function () {
    assert_throws(HttpException::class, fn () => demo_router()->match('DELETE', '/masuk'), fn ($e) => assert_same(405, $e->status));
});

test('regex characters in paths are literal', function () {
    assert_same('dots', demo_router()->match('GET', '/a-b.c')['handler']);
    assert_throws(HttpException::class, fn () => demo_router()->match('GET', '/a-bxc'));
});

test('the real route table loads and resolves', function () {
    $r = new Router();
    (require APP_PATH . '/routes.php')($r);
    assert_same([AuthController::class, 'login'], $r->match('POST', '/masuk')['handler']);
    assert_same(['role:admin'], $r->match('GET', '/admin/pengguna')['middleware']);
});
