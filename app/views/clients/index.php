<?php /** @var array $clients */ ?>
<div class="container">
    <header class="page-header">
        <div>
            <h1>Clientes</h1>
            <p class="muted">Negócios que você opera nesta plataforma.</p>
        </div>
        <a class="btn btn--primary" href="<?= url('/clientes/novo') ?>">Novo cliente</a>
    </header>

    <?php if (!$clients): ?>
        <div class="card empty-state">
            <h2>Você ainda não tem clientes</h2>
            <p class="muted">
                Cadastre o primeiro cliente para começar a planejar e escrever
                newsletters.
            </p>
            <p>
                <a class="btn btn--primary" href="<?= url('/clientes/novo') ?>">Criar primeiro cliente</a>
            </p>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Segmento</th>
                        <th>Criado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td>
                                <a href="<?= url('/clientes/' . (int) $c['id']) ?>">
                                    <strong><?= e($c['name']) ?></strong>
                                </a>
                                <?php if ($c['email']): ?>
                                    <div class="small muted"><?= e($c['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['segment']): ?>
                                    <span class="tag"><?= e(Client::SEGMENTS[$c['segment']] ?? $c['segment']) ?></span>
                                <?php else: ?>
                                    <span class="muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small muted"><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></td>
                            <td class="text-right">
                                <a href="<?= url('/clientes/' . (int) $c['id']) ?>">Abrir →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
