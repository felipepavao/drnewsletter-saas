<?php
/** @var array $client; @var ?array $current; @var array $history */
$parsed = $current['parsed'] ?? null;
?>
<div class="container container--narrow">
    <p class="breadcrumb">
        <a href="<?= url('/clientes/' . (int) $client['id']) ?>">← <?= e($client['name']) ?></a>
    </p>

    <header class="page-header">
        <div>
            <h1>Voz da Marca</h1>
            <p class="muted">
                Documento que descreve tom, público, valores e estilo. A IA
                usa essa estrutura ao gerar planejamentos e emails.
            </p>
        </div>
    </header>

    <section class="card">
        <header class="card__header">
            <h2><?= $current ? 'Voz atual (v' . (int) $current['version'] . ')' : 'Subir Voz da Marca' ?></h2>
            <?php if ($current): ?>
                <span class="muted small">
                    de <?= e($current['source_filename'] ?: '—') ?> ·
                    <?= e(date('d/m/Y H:i', strtotime($current['created_at']))) ?>
                </span>
            <?php endif; ?>
        </header>

        <?php if ($parsed): ?>
            <div class="voice-grid">
                <?php if (!empty($parsed['summary'])): ?>
                    <div class="voice-block">
                        <h3>Resumo</h3>
                        <p><?= e($parsed['summary']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($parsed['target_audience'])): ?>
                    <div class="voice-block">
                        <h3>Público-alvo</h3>
                        <p><?= e($parsed['target_audience']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($parsed['tone_summary']) || !empty($parsed['tone_attributes'])): ?>
                    <div class="voice-block">
                        <h3>Tom</h3>
                        <?php if (!empty($parsed['tone_summary'])): ?>
                            <p><?= e($parsed['tone_summary']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($parsed['tone_attributes'])): ?>
                            <ul class="taglist">
                                <?php foreach ((array) $parsed['tone_attributes'] as $t): ?>
                                    <li class="tag"><?= e($t) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ([
                    'audience_pains'       => 'Dores do público',
                    'preferred_themes'     => 'Temas preferidos',
                    'avoid_themes'         => 'Temas a evitar',
                    'vocabulary_signature' => 'Vocabulário característico',
                    'dos'                  => 'Faça',
                    'donts'                => 'Não faça',
                ] as $key => $label): ?>
                    <?php if (!empty($parsed[$key])): ?>
                        <div class="voice-block">
                            <h3><?= e($label) ?></h3>
                            <ul>
                                <?php foreach ((array) $parsed[$key] as $item): ?>
                                    <li><?= e($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif ($current): ?>
            <p class="muted">A IA não devolveu uma estrutura parseada na última versão.</p>
        <?php else: ?>
            <p class="muted">
                Você ainda não cadastrou a Voz da Marca deste cliente.
                Suba um TXT descrevendo:
            </p>
            <ul class="muted">
                <li>tom de voz e atributos</li>
                <li>público-alvo e dores</li>
                <li>valores e princípios</li>
                <li>temas preferidos e temas a evitar</li>
                <li>vocabulário característico</li>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2><?= $current ? 'Atualizar (nova versão)' : 'Enviar TXT' ?></h2>
        <p class="muted small">
            Arquivo .txt em UTF-8, até <?= round(UPLOAD_MAX_BYTES / 1024 / 1024, 0) ?>MB.
            A IA vai estruturar automaticamente.
            <?php if ($current): ?>
                A versão atual ficará no histórico, sem perda.
            <?php endif; ?>
        </p>

        <form method="post" action="<?= url('/clientes/' . (int) $client['id'] . '/voz') ?>"
              enctype="multipart/form-data" class="stack">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="file">Arquivo</label>
                <input type="file" id="file" name="file" class="input" accept=".txt,text/plain" required>
            </div>
            <button type="submit" class="btn btn--primary">
                Enviar e processar
            </button>
        </form>
    </section>

    <?php if (count($history) > 1): ?>
        <section class="card">
            <h2>Histórico</h2>
            <ul class="list">
                <?php foreach ($history as $h): ?>
                    <li class="list__item">
                        <strong>v<?= (int) $h['version'] ?></strong>
                        <?php if ((int) $h['is_active'] === 1): ?>
                            <span class="tag">ativa</span>
                        <?php endif; ?>
                        <span class="muted small">
                            — <?= e($h['source_filename'] ?: '—') ?>,
                            <?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
