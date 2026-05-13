<?php /** @var string $content */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="layout-public">
    <main class="layout-public__main">
        <div class="brand">
            <a href="<?= url('/') ?>"><?= e(APP_NAME) ?></a>
        </div>

        <?php View::partial('flash'); ?>

        <?= $content ?>
    </main>

    <footer class="layout-public__footer">
        <small>© <?= date('Y') ?> Dr. Newsletter</small>
    </footer>
</body>
</html>
