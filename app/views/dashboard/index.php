<?php /** @var array $stats; @var array $clients */ ?>
<div class="container">
    <h1>Painel</h1>
    <p class="muted">
        Visão geral da sua operação Dr. Newsletter.
    </p>

    <section class="stats">
        <div class="stat">
            <div class="stat__num"><?= (int) $stats['clients'] ?></div>
            <div class="stat__label">Clientes ativos</div>
        </div>
        <div class="stat">
            <div class="stat__num"><?= (int) $stats['monthly_plans'] ?></div>
            <div class="stat__label">Planejamentos mensais</div>
        </div>
        <div class="stat">
            <div class="stat__num"><?= (int) $stats['email_drafts'] ?></div>
            <div class="stat__label">Emails escritos</div>
        </div>
    </section>

    <section class="card">
        <header class="card__header">
            <h2>Seus clientes</h2>
            <a class="btn btn--primary" href="<?= url('/clientes/novo') ?>">Novo cliente</a>
        </header>

        <?php if (!$clients): ?>
            <p class="muted">
                Você ainda não tem clientes cadastrados.
                <a href="<?= url('/clientes/novo') ?>">Comece criando o primeiro</a>.
            </p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($clients as $c): ?>
                    <li class="list__item">
                        <a href="<?= url('/clientes/' . (int) $c['id']) ?>" class="list__link">
                            <strong><?= e($c['name']) ?></strong>
                            <?php if ($c['segment']): ?>
                                <span class="muted">— <?= e($c['segment']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="card__footer">
                <a href="<?= url('/clientes') ?>">Ver todos os clientes →</a>
            </p>
        <?php endif; ?>
    </section>
</div>
