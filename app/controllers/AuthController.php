<?php

/**
 * Magic-link authentication.
 *
 * Fluxo:
 *  1. POST /             → recebe email, cria user se não existe,
 *                          gera código de 6 dígitos, envia por email.
 *  2. GET  /verify       → tela com input do código.
 *  3. POST /verify       → valida hash + TTL + tries, abre sessão.
 *  4. GET  /sair         → encerra sessão.
 *
 * Diferença vs Enteogenistas: aqui é SaaS aberto a self-signup —
 * se o email não existe, criamos o user (status='active'). Em
 * Enteogenistas, o user precisa ter sido pré-cadastrado por admin.
 */
class AuthController
{
    /** POST / — gera e envia código. */
    public function requestCode(): void
    {
        Csrf::verify();

        $email = normalize_email(Request::string('email'));
        if (!is_valid_email($email)) {
            Flash::error('Por favor, informe um email válido.');
            redirect('/');
        }

        $ip = Request::ip();

        // Rate limit por IP — defesa contra scraping/spam de códigos
        if (!RateLimiter::hit("req_code:ip:{$ip}", AUTH_CODE_PER_IP)) {
            Flash::error('Muitas tentativas deste dispositivo. Tente novamente em alguns minutos.');
            redirect('/');
        }

        // Self-signup: cria user se não existe.
        $user = Database::fetch(
            "SELECT id, status FROM users WHERE email = ? AND status != 'deleted'",
            [$email]
        );
        if (!$user) {
            Database::execute(
                'INSERT INTO users (email, status) VALUES (?, ?)',
                [$email, 'active']
            );
            $userId = (int) Database::getInstance()->lastInsertId();
            $user = ['id' => $userId, 'status' => 'active'];

            // Promove o primeiro user ou um ADMIN_EMAIL pré-definido para admin.
            $totalUsers = (int) Database::fetchColumn('SELECT COUNT(*) FROM users');
            $shouldBeAdmin = $totalUsers === 1
                || (ADMIN_EMAIL !== '' && $email === normalize_email(ADMIN_EMAIL));
            if ($shouldBeAdmin) {
                Database::execute('UPDATE users SET is_admin = 1 WHERE id = ?', [$userId]);
            }
        }

        if ($user['status'] === 'suspended') {
            Flash::error('Esta conta está suspensa. Entre em contato com a equipe.');
            redirect('/');
        }

        // Rate limit por email (3 códigos em 15min). Se exceder, mostra
        // mensagem genérica e segue para /verify — não revela se gerou ou não.
        $emailOk = RateLimiter::hit("req_code:email:{$email}", AUTH_CODE_PER_EMAIL);
        if ($emailOk) {
            $code    = random_code(6);
            $hash    = password_hash($code, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + AUTH_CODE_TTL);

            Database::execute(
                'INSERT INTO auth_codes (user_id, code_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)',
                [$user['id'], $hash, $expires, $ip]
            );

            Mailer::sendAuthCode($email, $code);
        }

        Flash::info('Em instantes você receberá um código de 6 dígitos por email.');
        redirect('/verify?email=' . urlencode($email));
    }

    /** GET /verify */
    public function showVerify(): void
    {
        if (is_logged_in()) { redirect_after_auth(); return; }
        $email = normalize_email(Request::string('email'));
        View::render('auth/verify', [
            'pageTitle' => 'Código de acesso — ' . APP_NAME,
            'email'     => $email,
        ], 'public');
    }

    /** POST /verify */
    public function verifyCode(): void
    {
        Csrf::verify();

        $email = normalize_email(Request::string('email'));
        $code  = preg_replace('/\D/', '', Request::string('code')) ?? '';

        $ip = Request::ip();
        if (!RateLimiter::hit("verify:ip:{$ip}", RATE_LIMIT_LOGIN_IP)) {
            Flash::error('Muitas tentativas deste dispositivo. Aguarde alguns minutos.');
            redirect('/');
        }

        if (!is_valid_email($email) || strlen($code) !== 6) {
            Flash::error('Código inválido.');
            redirect('/verify?email=' . urlencode($email));
        }

        $user = Database::fetch(
            "SELECT id, status FROM users WHERE email = ? AND status != 'deleted'",
            [$email]
        );
        if (!$user) {
            Flash::error('Código inválido ou expirado.');
            redirect('/');
        }

        $row = Database::fetch(
            "SELECT id, code_hash, tries FROM auth_codes
             WHERE user_id = ? AND used_at IS NULL AND expires_at > datetime('now')
             ORDER BY id DESC LIMIT 1",
            [$user['id']]
        );
        if (!$row) {
            Flash::error('Código expirado. Solicite um novo.');
            redirect('/');
        }

        if ((int) $row['tries'] >= AUTH_CODE_MAX_TRIES) {
            Database::execute(
                "UPDATE auth_codes SET used_at = datetime('now') WHERE id = ?",
                [$row['id']]
            );
            Flash::error('Limite de tentativas atingido para este código. Solicite um novo.');
            redirect('/');
        }

        Database::execute('UPDATE auth_codes SET tries = tries + 1 WHERE id = ?', [$row['id']]);

        if (!password_verify($code, $row['code_hash'])) {
            Flash::error('Código incorreto. Verifique e tente novamente.');
            redirect('/verify?email=' . urlencode($email));
        }

        // Sucesso — consome todos os códigos ativos do user e abre sessão.
        Database::execute(
            "UPDATE auth_codes SET used_at = datetime('now') WHERE user_id = ? AND used_at IS NULL",
            [$user['id']]
        );
        Database::execute(
            "UPDATE users SET last_login_at = datetime('now') WHERE id = ?",
            [$user['id']]
        );

        Session::create((int) $user['id']);
        Flash::success('Bem-vindo.');
        redirect_after_auth();
    }

    /** GET /sair */
    public function logout(): void
    {
        Session::destroy();
        Flash::info('Você saiu.');
        redirect('/');
    }
}
