<?php
/** @var array $client; @var bool $hasVoice; @var array $recentPlans; @var int $defaultYear; @var int $defaultMonth */
?>
<div class="container container--narrow">
    <p class="breadcrumb">
        <a href="<?= url('/clientes/' . (int) $client['id']) ?>">← <?= e($client['name']) ?></a>
    </p>

    <header class="page-header">
        <div>
            <h1>Planejador mensal</h1>
            <p class="muted">
                A IA gera 4 a 8 temas estruturados por mês, com base na Voz da Marca
                e nos exemplos de emails do cliente.
            </p>
        </div>
    </header>

    <?php if (!$hasVoice): ?>
        <div class="card empty-state">
            <h2>Falta a Voz da Marca</h2>
            <p class="muted">
                Antes de gerar planejamento, suba o documento de Voz da Marca deste cliente.
            </p>
            <p>
                <a class="btn btn--primary" href="<?= url('/clientes/' . (int) $client['id'] . '/voz') ?>">
                    Configurar Voz da Marca
                </a>
            </p>
        </div>
    <?php else: ?>
        <section class="card">
            <h2>Novo planejamento</h2>
            <form method="post" action="<?= url('/clientes/' . (int) $client['id'] . '/planejador') ?>"
                  class="stack" data-confirm="Gerar planejamento? Vai usar tokens da IA.">
                <?= Csrf::field() ?>

                <div class="grid-2">
                    <div class="field">
                        <label for="month">Mês</label>
                        <select id="month" name="month" class="select" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= selected($m === $defaultMonth) ?>>
                                    <?= sprintf('%02d', $m) ?> — <?= e(Prompts::monthName($m)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="year">Ano</label>
                        <select id="year" name="year" class="select" required>
                            <?php for ($y = (int) date('Y'); $y <= (int) date('Y') + 2; $y++): ?>
                                <option value="<?= $y ?>" <?= selected($y === $defaultYear) ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="email_count">Quantos emails neste mês?</label>
                    <input type="number" id="email_count" name="email_count" class="input"
                           min="1" max="31" value="4" required>
                    <p class="help">Entre 1 e 31. Recomendamos 4 (1 por semana) para começar.</p>
                </div>

                <div class="field">
                    <label for="extra_context">Contexto adicional <span class="muted small">(opcional)</span></label>
                    <textarea id="extra_context" name="extra_context" class="textarea" maxlength="2000"
                              placeholder="Lançamentos, datas importantes, sazonalidade específica deste cliente, foco do mês…"></textarea>
                </div>

                <button type="submit" class="btn btn--primary">Gerar com IA</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($recentPlans): ?>
        <section class="card">
            <h2>Planejamentos recentes</h2>
            <ul class="list">
                <?php foreach ($recentPlans as $p): ?>
                    <li class="list__item">
                        <a href="<?= url('/planos/' . (int) $p['id']) ?>" class="list__link">
                            <strong><?= sprintf('%02d/%d', (int) $p['month'], (int) $p['year']) ?></strong>
                            <span class="muted">— <?= (int) $p['email_count'] ?> emails ·
                                <span class="tag"><?= e($p['status']) ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
