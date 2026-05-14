<?php

beforeEach(fn() => boot_app());

test('GET / mostra form de login quando deslogado', function () {
    $r = request('GET', '/');
    expect($r['body'])->toContain('Receber código de acesso')
        ->and($r['body'])->toContain('name="email"');
});

test('GET / redireciona para /painel quando logado', function () {
    login_as('felipe@test.local');
    $r = request('GET', '/');
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toContain('/painel');
});

test('POST / com email inválido volta para /', function () {
    $r = request('POST', '/', ['email' => 'nao-eh-email', '_csrf_auto' => true]);
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/');
});

test('POST / com email válido cria user e redireciona pra /verify', function () {
    $r = request('POST', '/', ['email' => 'novo@x.com', '_csrf_auto' => true]);
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toContain('/verify?email=novo')
        ->and(Database::fetchColumn('SELECT email FROM users WHERE email = ?', ['novo@x.com']))
        ->toBe('novo@x.com');
});

test('primeiro user vira admin automaticamente', function () {
    request('POST', '/', ['email' => 'admin@x.com', '_csrf_auto' => true]);
    $u = Database::fetch('SELECT email, is_admin FROM users LIMIT 1');
    expect((int) $u['is_admin'])->toBe(1);
});

test('segundo user NÃO vira admin', function () {
    request('POST', '/', ['email' => 'first@x.com', '_csrf_auto' => true]);
    request('POST', '/', ['email' => 'second@x.com', '_csrf_auto' => true]);
    $u = Database::fetch('SELECT is_admin FROM users WHERE email = ?', ['second@x.com']);
    expect((int) $u['is_admin'])->toBe(0);
});

test('código correto autentica e redireciona pra /painel', function () {
    Database::execute('INSERT INTO users (email, status) VALUES (?, ?)', ['op@x.com', 'active']);
    $uid = (int) Database::lastInsertId();
    $code = '123456';
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $exp  = date('Y-m-d H:i:s', time() + 900);
    Database::execute(
        'INSERT INTO auth_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)',
        [$uid, $hash, $exp]
    );

    $r = request('POST', '/verify', [
        'email' => 'op@x.com',
        'code'  => $code,
        '_csrf_auto' => true,
    ]);
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toContain('/painel')
        ->and(Session::userId())->toBe($uid);
});

test('código errado não autentica e incrementa tries', function () {
    Database::execute('INSERT INTO users (email, status) VALUES (?, ?)', ['op@x.com', 'active']);
    $uid = (int) Database::lastInsertId();
    Database::execute(
        'INSERT INTO auth_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)',
        [$uid, password_hash('111111', PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 900)]
    );

    request('POST', '/verify', ['email' => 'op@x.com', 'code' => '999999', '_csrf_auto' => true]);

    expect(Session::userId())->toBeNull()
        ->and((int) Database::fetchColumn('SELECT tries FROM auth_codes LIMIT 1'))->toBe(1);
});

test('código expirado é rejeitado', function () {
    Database::execute('INSERT INTO users (email, status) VALUES (?, ?)', ['op@x.com', 'active']);
    $uid = (int) Database::lastInsertId();
    Database::execute(
        'INSERT INTO auth_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)',
        [$uid, password_hash('123456', PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() - 60)]
    );

    request('POST', '/verify', ['email' => 'op@x.com', 'code' => '123456', '_csrf_auto' => true]);

    expect(Session::userId())->toBeNull();
});

test('logout limpa sessão', function () {
    login_as('op@x.com');
    expect(Session::userId())->not->toBeNull();

    request('GET', '/sair');

    expect(Session::userId())->toBeNull();
});

test('/painel exige autenticação', function () {
    $r = request('GET', '/painel');
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/');
});
