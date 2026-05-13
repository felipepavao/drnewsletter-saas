<?php
/** @var array $draft; @var array $messages */
$isApproved = $draft['status'] === 'approved';
?>
<div class="container container--narrow writer">
    <p class="breadcrumb">
        <?php if ($draft['monthly_plan_id']): ?>
            <a href="<?= url('/planos/' . (int) $draft['monthly_plan_id']) ?>">← Plano</a>
        <?php else: ?>
            <a href="<?= url('/clientes/' . (int) $draft['client_id']) ?>">← <?= e($draft['client_name']) ?></a>
        <?php endif; ?>
    </p>

    <header class="page-header">
        <div>
            <h1>
                Escrever email
                <span class="tag tag--<?= e($draft['status']) ?>"><?= e($draft['status']) ?></span>
            </h1>
            <p class="muted">
                Cliente: <strong><?= e($draft['client_name']) ?></strong> ·
                v<?= (int) $draft['version'] ?>
                <?php if ($draft['cost_usd'] !== null && (float) $draft['cost_usd'] > 0): ?>
                    · custo IA: US$ <?= number_format((float) $draft['cost_usd'], 4, '.', '') ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="form-actions">
            <?php if ($draft['body_text']): ?>
                <a class="btn" href="<?= url('/drafts/' . (int) $draft['id'] . '/export') ?>">Exportar TXT</a>
            <?php endif; ?>
            <?php if (!$isApproved && $draft['body_text']): ?>
                <form method="post" action="<?= url('/drafts/' . (int) $draft['id'] . '/aprovar') ?>" style="display:inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--primary">Aprovar</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($draft['subject'] || $draft['preview_text'] || $draft['body_text']): ?>
        <section class="card preview-card">
            <h2>Versão atual</h2>
            <?php if ($draft['subject']): ?>
                <h3 class="small-h">Subject</h3>
                <p class="mono"><?= e($draft['subject']) ?></p>
            <?php endif; ?>
            <?php if ($draft['preview_text']): ?>
                <h3 class="small-h">Preview</h3>
                <p class="mono muted"><?= e($draft['preview_text']) ?></p>
            <?php endif; ?>
            <?php if ($draft['body_text']): ?>
                <h3 class="small-h">Corpo</h3>
                <div class="email-body"><?= nl2br(e($draft['body_text'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card chat">
        <h2>Conversa</h2>
        <?php if (!$messages): ?>
            <p class="muted">Nenhuma mensagem ainda.</p>
        <?php else: ?>
            <ol class="chat__list">
                <?php foreach ($messages as $m): ?>
                    <li class="chat__msg chat__msg--<?= e($m['role']) ?>">
                        <div class="chat__role"><?= e($m['role'] === 'user' ? 'Você' : 'IA') ?></div>
                        <div class="chat__body"><?= nl2br(e($m['content'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if (!$isApproved): ?>
        <section class="card">
            <h2>Pedir nova versão / feedback</h2>
            <form method="post" action="<?= url('/drafts/' . (int) $draft['id'] . '/mensagem') ?>" class="stack">
                <?= Csrf::field() ?>
                <div class="field">
                    <textarea name="content" class="textarea" maxlength="4000" required
                              placeholder="Ex: 'Reescreva mais curto e direto.' ou 'Troca o hook por algo concreto sobre o Pedro que veio sábado.' ou 'Subject muito comercial — usa mais sobriedade.'"></textarea>
                </div>
                <button type="submit" class="btn btn--primary">Enviar para IA</button>
            </form>
        </section>
    <?php endif; ?>
</div>
