<?php /** @var string $content */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="layout-app">
    <header class="topbar">
        <div class="topbar__inner">
            <a href="<?= url('/painel') ?>" class="topbar__brand"><?= e(APP_NAME) ?></a>
            <nav class="topbar__nav">
                <a href="<?= url('/painel') ?>" class="<?= is_current('/painel') ? 'is-active' : '' ?>">Painel</a>
                <a href="<?= url('/clientes') ?>" class="<?= is_current('/clientes') ? 'is-active' : '' ?>">Clientes</a>
                <a href="<?= url('/ajuda') ?>" class="<?= is_current('/ajuda') ? 'is-active' : '' ?>">Ajuda</a>
                <a href="<?= url('/sair') ?>">Sair</a>
            </nav>
        </div>
    </header>

    <main class="page">
        <?php View::partial('flash'); ?>
        <?= $content ?>
    </main>
    <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
