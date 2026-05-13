<?php
/** @var array $client; @var array $archives; @var int $max */
$count = count($archives);
$canUpload = $count < $max;
?>
<div class="container container--narrow">
    <p class="breadcrumb">
        <a href="<?= url('/clientes/' . (int) $client['id']) ?>">← <?= e($client['name']) ?></a>
    </p>

    <header class="page-header">
        <div>
            <h1>Arquivo de Emails</h1>
            <p class="muted">
                Até <?= (int) $max ?> TXTs com exemplos do tom do cliente. A IA usa como
                referência ao escrever novos emails. <?= (int) $count ?> de <?= (int) $max ?> usados.
            </p>
        </div>
    </header>

    <?php if ($archives): ?>
        <section class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Tamanho</th>
                        <th>Enviado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archives as $a): ?>
                        <tr>
                            <td><strong><?= e($a['filename']) ?></strong></td>
                            <td class="small muted"><?= number_format((int) $a['byte_size'] / 1024, 1, ',', '.') ?> KB</td>
                            <td class="small muted"><?= e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                            <td class="text-right">
                                <form method="post"
                                      action="<?= url('/clientes/' . (int) $client['id'] . '/arquivo/' . (int) $a['id'] . '/excluir') ?>"
                                      style="display:inline"
                                      data-confirm="Remover este arquivo?">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn--danger">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Adicionar arquivo</h2>
        <?php if (!$canUpload): ?>
            <p class="muted">
                Limite de <?= (int) $max ?> arquivos atingido. Remova algum acima
                antes de adicionar mais.
            </p>
        <?php else: ?>
            <p class="muted small">
                Arquivo .txt em UTF-8, até <?= round(UPLOAD_MAX_BYTES / 1024 / 1024, 0) ?>MB. Cada arquivo pode conter
                vários emails (assunto + corpo). A IA lê tudo como contexto bruto.
            </p>
            <form method="post" action="<?= url('/clientes/' . (int) $client['id'] . '/arquivo') ?>"
                  enctype="multipart/form-data" class="stack">
                <?= Csrf::field() ?>
                <div class="field">
                    <label for="file">Arquivo</label>
                    <input type="file" id="file" name="file" class="input" accept=".txt,text/plain" required>
                </div>
                <button type="submit" class="btn btn--primary">Adicionar</button>
            </form>
        <?php endif; ?>
    </section>
</div>
