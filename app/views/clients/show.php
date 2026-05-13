<?php
/** @var array $client; @var bool $hasVoice; @var int $archiveCount; @var int $planCount; @var array $recentPlans */
?>
<div class="container">
    <p class="breadcrumb">
        <a href="<?= url('/clientes') ?>">← Clientes</a>
    </p>

    <header class="page-header">
        <div>
            <h1><?= e($client['name']) ?></h1>
            <p class="muted">
                <?php if ($client['segment']): ?>
                    <?= e(Client::SEGMENTS[$client['segment']] ?? $client['segment']) ?>
                <?php endif; ?>
                <?php if ($client['email']): ?>
                    <?= $client['segment'] ? ' · ' : '' ?><?= e($client['email']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="form-actions">
            <a class="btn" href="<?= url('/clientes/' . (int) $client['id'] . '/editar') ?>">Editar</a>
            <form method="post" action="<?= url('/clientes/' . (int) $client['id'] . '/excluir') ?>"
                  style="display:inline"
                  data-confirm="Arquivar este cliente? Os dados ficam preservados, mas ele some das listagens.">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--danger">Arquivar</button>
            </form>
        </div>
    </header>

    <section class="setup-checklist card">
        <h2>Configuração</h2>
        <ol class="checklist">
            <li class="checklist__item <?= $hasVoice ? 'is-done' : '' ?>">
                <span class="checklist__mark"><?= $hasVoice ? '✓' : '○' ?></span>
                <div>
                    <strong>Voz da Marca</strong>
                    <p class="muted small">
                        <?= $hasVoice ? 'Configurada.' : 'Suba um TXT descrevendo tom, público, valores.' ?>
                    </p>
                </div>
                <a class="btn" href="<?= url('/clientes/' . (int) $client['id'] . '/voz') ?>">
                    <?= $hasVoice ? 'Atualizar' : 'Configurar' ?>
                </a>
            </li>
            <li class="checklist__item <?= $archiveCount > 0 ? 'is-done' : '' ?>">
                <span class="checklist__mark"><?= $archiveCount > 0 ? '✓' : '○' ?></span>
                <div>
                    <strong>Arquivo de Emails</strong>
                    <p class="muted small">
                        <?= $archiveCount > 0
                            ? $archiveCount . ' arquivo(s) — máximo 5.'
                            : 'Opcional. Até 5 TXTs com exemplos do tom do cliente.' ?>
                    </p>
                </div>
                <a class="btn" href="<?= url('/clientes/' . (int) $client['id'] . '/arquivo') ?>">Gerenciar</a>
            </li>
            <li class="checklist__item <?= $planCount > 0 ? 'is-done' : '' ?>">
                <span class="checklist__mark"><?= $planCount > 0 ? '✓' : '○' ?></span>
                <div>
                    <strong>Planejamento mensal</strong>
                    <p class="muted small">
                        <?= $planCount > 0
                            ? $planCount . ' planejamento(s) gerado(s).'
                            : ($hasVoice ? 'Pronto para gerar o primeiro.' : 'Configure a Voz da Marca primeiro.') ?>
                    </p>
                </div>
                <a class="btn <?= $hasVoice ? 'btn--primary' : '' ?>"
                   href="<?= url('/clientes/' . (int) $client['id'] . '/planejador') ?>"
                   <?= $hasVoice ? '' : 'aria-disabled="true"' ?>>
                    Planejador
                </a>
            </li>
        </ol>
    </section>

    <?php if ($client['notes']): ?>
        <section class="card">
            <h2>Anotações</h2>
            <p style="white-space:pre-wrap"><?= e($client['notes']) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($recentPlans): ?>
        <section class="card">
            <h2>Planejamentos recentes</h2>
            <ul class="list">
                <?php foreach ($recentPlans as $p): ?>
                    <li class="list__item">
                        <a href="<?= url('/planos/' . (int) $p['id']) ?>" class="list__link">
                            <strong>
                                <?= sprintf('%02d/%d', (int) $p['month'], (int) $p['year']) ?>
                            </strong>
                            <span class="muted">
                                — <?= (int) $p['email_count'] ?> emails ·
                                <?= e($p['status']) ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
