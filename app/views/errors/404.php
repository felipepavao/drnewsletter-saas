<?php /** @var string $message */ ?>
<div class="container container--narrow">
    <h1>404 — Página não encontrada</h1>
    <p class="muted"><?= e($message ?? 'A página que você procurava não existe.') ?></p>
    <p><a href="<?= url('/') ?>">Voltar para o início</a></p>
</div>
