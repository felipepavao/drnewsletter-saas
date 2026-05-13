<?php

/**
 * CSRF com dupla fonte (sessão + cookie) — permite formulários em telas públicas
 * (ex.: cadastro via link de submissão) sem sessão autenticada.
 */
class Csrf
{
    /** Tempo de vida do cookie CSRF — 7 dias.
     *  Antes era session-cookie (`expires => 0`), e usuários que deixavam o form
     *  aberto por horas/dias batiam em 403 ao submeter quando o browser limpava
     *  cookies de sessão. */
    private const COOKIE_LIFETIME = 7 * 24 * 3600;

    public static function token(): string
    {
        $token = Session::get(CSRF_TOKEN_NAME);
        if ($token) return $token;

        if (!empty($_COOKIE['_csrf_cookie'])) {
            Session::set(CSRF_TOKEN_NAME, $_COOKIE['_csrf_cookie']);
            return $_COOKIE['_csrf_cookie'];
        }

        $token = bin2hex(random_bytes(32));
        Session::set(CSRF_TOKEN_NAME, $token);
        setcookie('_csrf_cookie', $token, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'secure'   => COOKIE_SECURE || Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $_COOKIE['_csrf_cookie'] = $token;
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(): void
    {
        $expected = Session::get(CSRF_TOKEN_NAME) ?? ($_COOKIE['_csrf_cookie'] ?? '');
        $provided = (string) Request::post(CSRF_TOKEN_NAME, '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            self::renderExpiredPage();
            exit;
        }
    }

    /**
     * Página amigável quando o token CSRF expira/some — em vez de um echo seco.
     * Lê o referer pra oferecer um link "voltar" útil.
     */
    private static function renderExpiredPage(): void
    {
        http_response_code(403);
        $back = $_SERVER['HTTP_REFERER'] ?? '/';
        $safeBack = htmlspecialchars($back, ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars(defined('APP_NAME') ? APP_NAME : '', ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sessão expirada — {$appName}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Raleway:wght@400;500;600&display=swap">
<style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
           font-family: Raleway, system-ui, sans-serif; background: #FAF8F3; color: #2A2A28; padding: 24px; }
    .box { max-width: 540px; background: #fff; border: 1px solid #EEEAE0; border-radius: 16px;
           padding: 32px; box-shadow: 0 2px 8px rgba(42,42,40,.06); }
    h1 { font-family: Lora, Georgia, serif; font-size: 1.5rem; font-weight: 600; margin: 0 0 12px; line-height: 1.3; }
    p { color: #4A4A45; line-height: 1.55; margin: 0 0 16px; }
    .tip { background: #F7F5F0; border-left: 3px solid #3E5836; padding: 12px 14px;
           border-radius: 8px; margin: 20px 0; font-size: 14px; color: #2A2A28; }
    a { color: #3E5836; text-decoration: underline; text-underline-offset: 3px; }
    .btn { display: inline-block; margin-top: 16px; padding: 12px 20px; background: #3E5836;
           color: #fff; border-radius: 10px; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
    <main class="box" role="main">
        <h1>Sua sessão de envio expirou</h1>
        <p>O formulário ficou aberto por muito tempo e o token de segurança não é
           mais válido. Os dados que você preencheu nesta página podem ainda estar
           no formulário, mas precisam ser reenviados.</p>
        <div class="tip">
            <strong>Antes de recarregar:</strong> selecione e <strong>copie</strong>
            os textos longos (bio, descrição, etc.) que você preencheu, pra colar
            de volta caso o navegador apague.
        </div>
        <p>Quando estiver pronto, recarregue o formulário e envie de novo.</p>
        <a class="btn" href="{$safeBack}">Voltar e tentar novamente</a>
    </main>
</body>
</html>
HTML;
    }
}
