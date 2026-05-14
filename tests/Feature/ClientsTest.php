<?php

beforeEach(fn() => boot_app());

test('GET /clientes mostra empty state', function () {
    login_as();
    $r = request('GET', '/clientes');
    expect($r['status'])->toBe(200)
        ->and($r['body'])->toContain('Você ainda não tem clientes');
});

test('GET /clientes/novo renderiza form', function () {
    login_as();
    $r = request('GET', '/clientes/novo');
    expect($r['body'])->toContain('Novo cliente')
        ->and($r['body'])->toContain('name="name"');
});

test('POST /clientes cria cliente válido', function () {
    login_as();
    $r = request('POST', '/clientes', [
        'name'    => 'Joalheria Aurum',
        'email'   => 'contato@aurum.com',
        'segment' => 'joalheria',
        'notes'   => 'Cliente piloto',
        '_csrf_auto' => true,
    ]);
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/clientes/1')
        ->and(Database::fetchColumn('SELECT name FROM clients WHERE id = 1'))
        ->toBe('Joalheria Aurum');
});

test('POST /clientes rejeita nome vazio', function () {
    login_as();
    $r = request('POST', '/clientes', ['name' => '', '_csrf_auto' => true]);
    expect($r['body'])->toContain('Informe o nome do cliente.')
        ->and((int) Database::fetchColumn('SELECT COUNT(*) FROM clients'))->toBe(0);
});

test('POST /clientes rejeita segmento inválido', function () {
    login_as();
    $r = request('POST', '/clientes', [
        'name' => 'X', // also too short
        'segment' => 'hacker',
        'email' => 'naoeh',
        '_csrf_auto' => true,
    ]);
    expect($r['body'])->toContain('Segmento inválido')
        ->and($r['body'])->toContain('Email inválido')
        ->and($r['body'])->toContain('Informe o nome do cliente.');
});

test('GET /clientes/{id} mostra detalhe', function () {
    $uid = login_as();
    $cid = create_client($uid, ['name' => 'Aurum', 'segment' => 'joalheria']);
    $r = request('GET', "/clientes/{$cid}");
    expect($r['body'])->toContain('Aurum')
        ->and($r['body'])->toContain('Voz da Marca')
        ->and($r['body'])->toContain('Arquivo de Emails')
        ->and($r['body'])->toContain('Planejamento mensal');
});

test('POST /clientes/{id} atualiza nome', function () {
    $uid = login_as();
    $cid = create_client($uid, ['name' => 'Antigo']);
    $r = request('POST', "/clientes/{$cid}", [
        'name' => 'Novo Nome',
        '_csrf_auto' => true,
    ]);
    expect($r['status'])->toBe(302)
        ->and(Database::fetchColumn('SELECT name FROM clients WHERE id = ?', [$cid]))->toBe('Novo Nome');
});

test('POST /clientes/{id}/excluir arquiva o cliente', function () {
    $uid = login_as();
    $cid = create_client($uid);
    request('POST', "/clientes/{$cid}/excluir", ['_csrf_auto' => true]);
    expect(Database::fetchColumn('SELECT status FROM clients WHERE id = ?', [$cid]))->toBe('archived');
});

test('IDOR: user 2 não acessa cliente do user 1', function () {
    $u1 = login_as('user1@x.com');
    $cid = create_client($u1, ['name' => 'Cliente de User 1']);
    Session::destroy();

    $u2 = login_as('user2@x.com');
    $r = request('GET', "/clientes/{$cid}");
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/clientes');
});

test('IDOR: user 2 não consegue editar cliente do user 1', function () {
    $u1 = login_as('user1@x.com');
    $cid = create_client($u1, ['name' => 'Original']);
    Session::destroy();

    $u2 = login_as('user2@x.com');
    request('POST', "/clientes/{$cid}", ['name' => 'Hackeado', '_csrf_auto' => true]);

    // Nome original preservado
    expect(Database::fetchColumn('SELECT name FROM clients WHERE id = ?', [$cid]))->toBe('Original');
});
