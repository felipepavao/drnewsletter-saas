<div class="auth-card">
    <h1>Entrar</h1>
    <p class="muted">
        Plataforma de planejamento e escrita de newsletters para negócios
        presenciais premium.
    </p>

    <form method="post" action="<?= url('/') ?>" class="stack" novalidate>
        <?= Csrf::field() ?>
        <div class="field">
            <label for="email">Seu email</label>
            <input
                type="email" name="email" id="email"
                class="input" required
                autocomplete="email" autocapitalize="off" spellcheck="false"
                placeholder="voce@empresa.com.br">
            <p class="help">Enviaremos um código de 6 dígitos para acessar.</p>
        </div>
        <button type="submit" class="btn btn--primary btn--block">
            Receber código de acesso
        </button>
    </form>
</div>
