<?php

beforeEach(fn() => boot_app());

test('planejador exige Voz da Marca', function () {
    $uid = login_as();
    $cid = create_client($uid);
    $r = request('GET', "/clientes/{$cid}/planejador");
    expect($r['body'])->toContain('Falta a Voz da Marca');
});

test('planejador com voz mostra form', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);
    $r = request('GET', "/clientes/{$cid}/planejador");
    expect($r['body'])->toContain('Novo planejamento')
        ->and($r['body'])->toContain('Gerar com IA');
});

test('POST planejador valida intervalo de mês', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);
    $r = request('POST', "/clientes/{$cid}/planejador", [
        'year' => 2026, 'month' => 13, 'email_count' => 4,
        '_csrf_auto' => true,
    ]);
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe("/clientes/{$cid}/planejador");
});

test('POST planejador gera plano com mock do Claude', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);

    mock_claude([
        'text' => json_encode([
            'title'         => 'Junho 2026',
            'summary'       => 'Estratégia de junho',
            'strategy'      => 'Mês equilibrado',
            'month_context' => 'Dia dos Namorados',
            'themes'        => [
                ['title' => 'Tema 1', 'type' => 'história', 'goal' => 'G1', 'hook' => 'H1', 'cta' => 'C1', 'cta_intensity' => 'suave'],
                ['title' => 'Tema 2', 'type' => 'bastidor', 'goal' => 'G2', 'hook' => 'H2', 'cta' => 'C2', 'cta_intensity' => 'média'],
            ],
        ], JSON_UNESCAPED_UNICODE),
        'tokens_in'  => 1500,
        'tokens_out' => 500,
        'cost_usd'   => 0.012,
    ]);

    $r = request('POST', "/clientes/{$cid}/planejador", [
        'year' => 2026, 'month' => 6, 'email_count' => 2,
        'extra_context' => 'foco DN',
        '_csrf_auto' => true,
    ]);

    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/planos/1');

    $plan = MonthlyPlan::findForUser($uid, 1);
    expect((int) $plan['year'])->toBe(2026)
        ->and((int) $plan['month'])->toBe(6)
        ->and((int) $plan['email_count'])->toBe(2)
        ->and($plan['themes'])->toHaveCount(2)
        ->and($plan['themes'][0]['title'])->toBe('Tema 1')
        ->and($plan['themes'][1]['type'])->toBe('bastidor')
        ->and((int) $plan['tokens_in'])->toBe(1500);
});

test('GET /planos/{id} renderiza temas', function () {
    $uid = login_as();
    $cid = create_client($uid);
    $pid = create_monthly_plan($uid, $cid, [
        ['title' => 'O anel que voltou', 'type' => 'história', 'goal' => 'autoridade', 'hook' => 'Márcia chegou…', 'cta' => 'Responda', 'cta_intensity' => 'suave'],
    ]);

    $r = request('GET', "/planos/{$pid}");
    expect($r['body'])->toContain('O anel que voltou')
        ->and($r['body'])->toContain('Márcia chegou')
        ->and($r['body'])->toContain('autoridade')
        ->and($r['body'])->toContain('Aprovar');
});

test('POST /planos/{id}/aprovar muda status', function () {
    $uid = login_as();
    $cid = create_client($uid);
    $pid = create_monthly_plan($uid, $cid);
    request('POST', "/planos/{$pid}/aprovar", ['_csrf_auto' => true]);
    expect(Database::fetchColumn('SELECT status FROM monthly_plans WHERE id = ?', [$pid]))->toBe('approved');
});

test('GET /planos/{id}/export devolve TXT bem formatado', function () {
    $uid = login_as();
    $cid = create_client($uid, ['name' => 'Aurum']);
    $pid = create_monthly_plan($uid, $cid);
    $r = request('GET', "/planos/{$pid}/export");
    expect($r['body'])->toContain('PLANEJAMENTO — Aurum')
        ->and($r['body'])->toContain('Junho de 2026')
        ->and($r['body'])->toContain('TEMAS');
});

test('PlannerService rejeita resposta sem JSON válido', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);

    mock_claude(['text' => 'isso não é JSON nenhum, só prosa']);

    $r = request('POST', "/clientes/{$cid}/planejador", [
        'year' => 2026, 'month' => 6, 'email_count' => 4, '_csrf_auto' => true,
    ]);

    // Volta para o form com mensagem de erro
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe("/clientes/{$cid}/planejador")
        ->and((int) Database::fetchColumn('SELECT COUNT(*) FROM monthly_plans'))->toBe(0);
});

test('IDOR: user 2 não acessa plano do user 1', function () {
    $u1 = login_as('u1@x.com');
    $cid = create_client($u1);
    $pid = create_monthly_plan($u1, $cid);
    Session::destroy();

    login_as('u2@x.com');
    $r = request('GET', "/planos/{$pid}");
    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/clientes');
});
