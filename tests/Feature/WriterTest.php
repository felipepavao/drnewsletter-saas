<?php

beforeEach(fn() => boot_app());

test('WriterService.extractEmail extrai subject + preview + body', function () {
    $text = <<<TXT
SUBJECT (3 opções, 30-50 caracteres cada):
1. Márcia chegou com três anéis (29 caracteres)
2. O anel que voltou pra reforma (29 caracteres)
3. Antes de aceitar um trabalho (28 caracteres)

PREVIEW (50-90 caracteres):
Como histórias de peças voltam depois de uma década.

BODY:
Márcia chegou com três anéis na bolsa e uma pergunta.

Eram da mãe dela.

P.S.:
A peça mais antiga que reformamos tinha 67 anos.
TXT;

    $e = WriterService::extractEmail($text);
    expect($e['subject'])->toBe('Márcia chegou com três anéis')
        ->and($e['preview'])->toBe('Como histórias de peças voltam depois de uma década.')
        ->and($e['body'])->toContain('Márcia chegou com três anéis na bolsa')
        ->and($e['body'])->toContain('P.S.:');
});

test('WriterService.extractEmail devolve null quando IA só faz pergunta', function () {
    $e = WriterService::extractEmail('Boa! Antes de escrever, qual é o tom desejado?');
    expect($e)->toBeNull();
});

test('POST /planos/{id}/temas/{i}/escrever cria draft + chat com mock', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);
    $pid = create_monthly_plan($uid, $cid, [
        ['title' => 'Tema A', 'type' => 'história', 'goal' => 'G', 'hook' => 'H', 'cta' => 'C', 'cta_intensity' => 'suave'],
    ]);

    mock_claude([
        'text' => "SUBJECT (3 opções):\n1. Tema A versão 1 (15 caracteres)\n\nPREVIEW (50-90 caracteres):\npreview text\n\nBODY:\nCorpo do email teste.",
        'tokens_in'  => 800,
        'tokens_out' => 200,
        'cost_usd'   => 0.005,
    ]);

    $r = request('POST', "/planos/{$pid}/temas/0/escrever", ['_csrf_auto' => true]);

    expect($r['status'])->toBe(302)
        ->and($r['location'])->toBe('/drafts/1');

    $draft = EmailDraft::findForUser($uid, 1);
    expect((int) $draft['monthly_plan_id'])->toBe($pid)
        ->and((int) $draft['theme_index'])->toBe(0)
        ->and($draft['subject'])->toBe('Tema A versão 1')
        ->and($draft['body_text'])->toContain('Corpo do email teste');

    $msgCount = (int) Database::fetchColumn('SELECT COUNT(*) FROM email_writer_messages');
    expect($msgCount)->toBe(2); // user + assistant
});

test('POST /drafts/{id}/mensagem itera draft via chat', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);
    $pid = create_monthly_plan($uid, $cid);

    mock_claude([
        'text' => "SUBJECT:\n1. v1 (5 caracteres)\n\nPREVIEW:\np v1\n\nBODY:\nbody v1",
        'tokens_in' => 100, 'tokens_out' => 50, 'cost_usd' => 0.001,
    ]);
    request('POST', "/planos/{$pid}/temas/0/escrever", ['_csrf_auto' => true]);

    // Segunda volta — feedback do user
    mock_claude([
        'text' => "SUBJECT:\n1. v2 melhorada (15 caracteres)\n\nPREVIEW:\np v2\n\nBODY:\nbody v2 reescrito",
        'tokens_in' => 120, 'tokens_out' => 80, 'cost_usd' => 0.002,
    ]);
    request('POST', '/drafts/1/mensagem', [
        'content' => 'Reescreva mais curto e direto.',
        '_csrf_auto' => true,
    ]);

    $draft = EmailDraft::findForUser($uid, 1);
    expect($draft['subject'])->toBe('v2 melhorada')
        ->and($draft['body_text'])->toBe('body v2 reescrito')
        ->and((int) $draft['version'])->toBeGreaterThan(1);

    // 4 mensagens: 2 do user, 2 do assistant
    expect((int) Database::fetchColumn('SELECT COUNT(*) FROM email_writer_messages'))->toBe(4);
});

test('POST /drafts/{id}/aprovar muda status', function () {
    $uid = login_as();
    $cid = create_client($uid);
    $pid = create_monthly_plan($uid, $cid);
    EmailDraft::create($cid, $pid, 0);
    request('POST', '/drafts/1/aprovar', ['_csrf_auto' => true]);
    expect(Database::fetchColumn('SELECT status FROM email_drafts WHERE id = 1'))->toBe('approved');
});

test('themes avulsos: mock anexa temas ao plano', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);
    $pid = create_monthly_plan($uid, $cid, [
        ['title' => 'Original 1', 'type' => 'história', 'goal' => 'G', 'hook' => 'H', 'cta' => 'C', 'cta_intensity' => 'suave'],
    ]);

    mock_claude([
        'text' => json_encode([
            'themes' => [
                ['title' => 'Novo 1', 'type' => 'bastidor', 'goal' => 'G', 'hook' => 'H', 'cta' => 'C', 'cta_intensity' => 'média'],
                ['title' => 'Novo 2', 'type' => 'estilo',   'goal' => 'G', 'hook' => 'H', 'cta' => 'C', 'cta_intensity' => 'suave'],
            ],
        ], JSON_UNESCAPED_UNICODE),
    ]);

    request('POST', "/clientes/{$cid}/temas", [
        'plan_id' => $pid, 'count' => 2, 'extra_context' => 'bastidor',
        '_csrf_auto' => true,
    ]);

    $plan = MonthlyPlan::findForUser($uid, $pid);
    expect($plan['themes'])->toHaveCount(3)
        ->and($plan['themes'][0]['title'])->toBe('Original 1')
        ->and($plan['themes'][1]['title'])->toBe('Novo 1')
        ->and($plan['themes'][2]['title'])->toBe('Novo 2');
});

test('claude_calls registra cada chamada com tokens e custo', function () {
    $uid = login_as();
    $cid = create_client($uid);
    create_brand_manual($cid);

    mock_claude(['text' => '{"themes":[]}', 'tokens_in' => 100, 'tokens_out' => 50, 'cost_usd' => 0.003]);
    $pid = create_monthly_plan($uid, $cid);

    request('POST', "/clientes/{$cid}/temas", [
        'plan_id' => $pid, 'count' => 1, '_csrf_auto' => true,
    ]);

    $log = Database::fetchAll('SELECT purpose, tokens_in, tokens_out, cost_usd, success FROM claude_calls');
    expect($log)->toHaveCount(1)
        ->and($log[0]['purpose'])->toBe('theme')
        ->and((int) $log[0]['tokens_in'])->toBe(100)
        ->and((float) $log[0]['cost_usd'])->toBe(0.003)
        ->and((int) $log[0]['success'])->toBe(1);
});
