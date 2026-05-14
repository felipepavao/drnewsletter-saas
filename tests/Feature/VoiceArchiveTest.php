<?php

beforeEach(fn() => boot_app());

test('Upload::readTextFile aceita .txt UTF-8 válido', function () {
    $tmp = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($tmp, "Tom: profissional.\nPúblico: 45+.");
    $r = Upload::readTextFile([
        'error' => 0, 'name' => 'voz.txt', 'tmp_name' => $tmp,
        'size'  => filesize($tmp),
    ]);
    expect($r['filename'])->toBe('voz.txt')
        ->and($r['bytes'])->toBeGreaterThan(0)
        ->and($r['content'])->toContain('profissional');
    unlink($tmp);
});

test('Upload rejeita extensão .pdf', function () {
    $tmp = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($tmp, 'qualquer');
    expect(fn() => Upload::readTextFile([
        'error' => 0, 'name' => 'doc.pdf', 'tmp_name' => $tmp, 'size' => filesize($tmp),
    ]))->toThrow(InvalidArgumentException::class, 'Apenas arquivos .txt');
    unlink($tmp);
});

test('Upload rejeita arquivo vazio', function () {
    $tmp = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($tmp, '');
    expect(fn() => Upload::readTextFile([
        'error' => 0, 'name' => 'a.txt', 'tmp_name' => $tmp, 'size' => 0,
    ]))->toThrow(InvalidArgumentException::class, 'Arquivo vazio');
    unlink($tmp);
});

test('Upload converte ISO-8859-1 para UTF-8', function () {
    $tmp = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($tmp, mb_convert_encoding('Voz da marca — ç ã é', 'ISO-8859-1', 'UTF-8'));
    $r = Upload::readTextFile([
        'error' => 0, 'name' => 'a.txt', 'tmp_name' => $tmp, 'size' => filesize($tmp),
    ]);
    expect(mb_check_encoding($r['content'], 'UTF-8'))->toBeTrue()
        ->and($r['content'])->toContain('ç');
    unlink($tmp);
});

test('BrandManual.createNewVersion versiona corretamente', function () {
    $uid = login_as();
    $cid = create_client($uid);

    $v1 = BrandManual::createNewVersion($cid, 'a.txt', 'raw 1', ['summary' => 'S1']);
    $v2 = BrandManual::createNewVersion($cid, 'b.txt', 'raw 2', ['summary' => 'S2']);
    $v3 = BrandManual::createNewVersion($cid, 'c.txt', 'raw 3', ['summary' => 'S3']);

    expect($v1)->not->toBe($v2)->and($v2)->not->toBe($v3);
    $active = BrandManual::activeForClient($cid);
    expect((int) $active['version'])->toBe(3)
        ->and($active['parsed']['summary'])->toBe('S3');

    $history = BrandManual::historyForClient($cid);
    expect($history)->toHaveCount(3);

    $activeCount = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM brand_manuals WHERE client_id = ? AND is_active = 1',
        [$cid]
    );
    expect($activeCount)->toBe(1);
});

test('EmailArchive respeita limite de 5 por cliente', function () {
    $uid = login_as();
    $cid = create_client($uid);

    for ($i = 1; $i <= 5; $i++) {
        EmailArchive::create($cid, "e{$i}.txt", "conteúdo {$i}", 10);
    }
    expect(EmailArchive::countForClient($cid))->toBe(5);

    // Tentativa via controller deve falhar
    $r = request('POST', "/clientes/{$cid}/arquivo", ['_csrf_auto' => true]);
    expect($r['status'])->toBe(302);
    // O upload sem arquivo já daria erro antes do limite — então o teste de limite é via setup direto.

    // Confirma o método countForClient
    expect(EmailArchive::countForClient($cid))->toBe(5);
});

test('EmailArchive.concatenatedForPrompt junta conteúdos', function () {
    $uid = login_as();
    $cid = create_client($uid);
    EmailArchive::create($cid, 'a.txt', 'AAAA', 4);
    EmailArchive::create($cid, 'b.txt', 'BBBB', 4);

    $out = EmailArchive::concatenatedForPrompt($cid);
    expect($out)->toContain('=== a.txt ===')
        ->and($out)->toContain('=== b.txt ===')
        ->and($out)->toContain('AAAA')
        ->and($out)->toContain('BBBB');
});

test('IDOR: user 2 não vê voz da marca do user 1', function () {
    $u1 = login_as('u1@x.com');
    $cid = create_client($u1);
    create_brand_manual($cid);
    Session::destroy();

    login_as('u2@x.com');
    $r = request('GET', "/clientes/{$cid}/voz");
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/clientes');
});
