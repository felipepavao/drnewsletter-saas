<?php /** @var string $email */ ?>
<div class="auth-card">
    <h1>Digite o código</h1>
    <p class="muted">
        Enviamos um código de 6 dígitos para<br>
        <strong><?= e($email ?: 'seu email') ?></strong>.
    </p>

    <form method="post" action="<?= url('/verify') ?>" class="stack" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <div class="field">
            <label for="code">Código de 6 dígitos</label>
            <input
                type="text" name="code" id="code"
                class="input input--code" required
                inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                autocomplete="one-time-code" autofocus
                placeholder="000000">
            <p class="help">O código expira em 15 minutos.</p>
        </div>
        <button type="submit" class="btn btn--primary btn--block">Entrar</button>
    </form>

    <p class="auth-card__alt">
        <a href="<?= url('/') ?>">Usar outro email</a>
    </p>
</div>
