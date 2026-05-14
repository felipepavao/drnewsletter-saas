<?php
/** @var array $plan; @var array $client; @var array $parsed */
$themes  = $parsed['themes'] ?? [];
$isDraft = $plan['status'] === 'draft';
?>
<div class="container">
    <p class="breadcrumb">
        <a href="<?= url('/clientes/' . (int) $client['id']) ?>">← <?= e($client['name']) ?></a>
    </p>

    <header class="page-header">
        <div>
            <h1>
                <?= sprintf('%02d/%d', (int) $plan['month'], (int) $plan['year']) ?>
                <span class="tag tag--<?= e($plan['status']) ?>"><?= e($plan['status']) ?></span>
            </h1>
            <p class="muted">
                <?= (int) $plan['email_count'] ?> emails ·
                gerado em <?= e(date('d/m/Y H:i', strtotime($plan['created_at']))) ?>
                <?php if ($plan['cost_usd'] !== null): ?>
                    · custo IA: US$ <?= number_format((float) $plan['cost_usd'], 4, '.', '') ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="form-actions">
            <a class="btn" href="<?= url('/planos/' . (int) $plan['id'] . '/export') ?>">Exportar TXT</a>
            <?php if ($isDraft): ?>
                <form method="post" action="<?= url('/planos/' . (int) $plan['id'] . '/aprovar') ?>" style="display:inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--primary">Aprovar</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($parsed['title']) || !empty($parsed['summary'])): ?>
        <section class="card">
            <?php if (!empty($parsed['title'])): ?>
                <h2 style="margin-top:0"><?= e($parsed['title']) ?></h2>
            <?php endif; ?>
            <?php if (!empty($parsed['summary'])): ?>
                <p><?= e($parsed['summary']) ?></p>
            <?php endif; ?>
            <?php if (!empty($parsed['strategy'])): ?>
                <h3 class="small-h">Estratégia</h3>
                <p class="muted"><?= e($parsed['strategy']) ?></p>
            <?php endif; ?>
            <?php if (!empty($parsed['month_context'])): ?>
                <h3 class="small-h">Contexto do mês</h3>
                <p class="muted"><?= e($parsed['month_context']) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($plan['status'] !== 'archived'): ?>
        <section class="card">
            <h2>Adicionar temas avulsos</h2>
            <p class="muted small">
                Gera mais temas para somar a este planejamento. Sem repetir o que já existe.
            </p>
            <form method="post" action="<?= url('/clientes/' . (int) $client['id'] . '/temas') ?>"
                  class="stack" data-confirm="Gerar mais temas? Vai usar tokens da IA.">
                <?= Csrf::field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <div class="grid-2">
                    <div class="field">
                        <label for="count">Quantos</label>
                        <input type="number" id="count" name="count" class="input" min="1" max="10" value="2" required>
                    </div>
                    <div class="field">
                        <label for="extra_context">Foco <span class="muted small">(opcional)</span></label>
                        <input type="text" id="extra_context" name="extra_context" class="input"
                               placeholder="Ex: temas de bastidor, ou agenda do mês">
                    </div>
                </div>
                <button type="submit" class="btn" data-loading="Gerando temas… (10-20s)">
                    Gerar mais temas
                </button>
            </form>
        </section>
    <?php endif; ?>

    <section class="themes">
        <?php foreach ($themes as $i => $t): $n = $i + 1; ?>
            <article class="theme-card">
                <header class="theme-card__head">
                    <span class="theme-card__num">#<?= $n ?></span>
                    <span class="tag tag--type"><?= e(MonthlyPlan::TYPES[$t['type']] ?? $t['type']) ?></span>
                </header>
                <h3 class="theme-card__title"><?= e($t['title']) ?></h3>
                <?php if (!empty($t['hook'])): ?>
                    <p class="theme-card__hook">“<?= e($t['hook']) ?>”</p>
                <?php endif; ?>
                <dl class="theme-card__meta">
                    <?php if (!empty($t['goal'])): ?>
                        <dt>Objetivo</dt><dd><?= e($t['goal']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($t['cta'])): ?>
                        <dt>CTA</dt>
                        <dd>
                            <?= e($t['cta']) ?>
                            <span class="muted small">(<?= e($t['cta_intensity'] ?? '') ?>)</span>
                        </dd>
                    <?php endif; ?>
                </dl>
                <footer class="theme-card__footer">
                    <form method="post"
                          action="<?= url('/planos/' . (int) $plan['id'] . '/temas/' . $i . '/escrever') ?>">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn" data-loading="Escrevendo email… (15-30s)">
                            Escrever email →
                        </button>
                    </form>
                </footer>
            </article>
        <?php endforeach; ?>
    </section>
</div>
