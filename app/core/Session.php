<?php

/**
 * Sessão baseada em banco — cookie opaco, dados persistidos em `sessions`.
 * Autenticação de 30 dias conforme escopo (cookie HttpOnly + Secure + SameSite=Lax).
 */
class Session
{
    private static bool $started = false;
    private static array $data = [];
    private static ?string $sid = null;
    private static bool $dirty = false;

    /** Indica que destroy() foi chamado neste request. Evita que Flash::info()
     *  pós-logout recrie sessão guest indesejada. */
    private static bool $destroyed = false;

    public static function start(): void
    {
        if (self::$started) return;
        self::$started = true;

        // Único shutdown handler do request — cobre tanto sessão pré-existente
        // (carregada pelo cookie) quanto sessão guest criada lazy via set().
        register_shutdown_function([self::class, 'flush']);

        $sid = $_COOKIE[SESSION_COOKIE] ?? null;
        if (!$sid || !preg_match('/^[a-f0-9]{64}$/', $sid)) {
            return;
        }

        $row = Database::fetch(
            "SELECT id, user_id, data, expires_at FROM sessions WHERE id = ? AND expires_at > datetime('now')",
            [$sid]
        );
        if (!$row) {
            self::clearCookie();
            return;
        }

        self::$sid = $sid;
        self::$data = json_decode($row['data'] ?? '{}', true) ?: [];

        // Renova last_seen e estende cookie pra manter a sessão "deslizante".
        Database::execute(
            "UPDATE sessions SET last_seen_at = datetime('now'), expires_at = datetime('now', '+30 days') WHERE id = ?",
            [$sid]
        );
        self::setCookie($sid);

        // GC probabilístico
        if (random_int(1, 200) === 1) {
            self::gc();
        }
    }

    /** Persiste mutações da sessão ao fim do request (registrado em start). */
    public static function flush(): void
    {
        if (!self::$dirty || !self::$sid) return;
        try {
            Database::execute(
                'UPDATE sessions SET data = ? WHERE id = ?',
                [json_encode(self::$data, JSON_UNESCAPED_UNICODE), self::$sid]
            );
        } catch (Throwable $e) {
            error_log('Session save: ' . $e->getMessage());
        }
    }

    public static function create(int $userId): string
    {
        // Promove sessão guest existente (que pode ter flashes/CSRF) em vez de
        // criar uma nova — preserva mensagens que iam ser exibidas pós-login.
        if (self::$sid) {
            self::$data['user_id'] = $userId;
            Database::execute(
                'UPDATE sessions SET user_id = ?, expires_at = datetime(\'now\', \'+30 days\'),
                    user_agent = ?, ip_address = ?, data = ? WHERE id = ?',
                [
                    $userId,
                    substr(Request::userAgent(), 0, 255),
                    Request::ip(),
                    json_encode(self::$data, JSON_UNESCAPED_UNICODE),
                    self::$sid,
                ]
            );
            self::setCookie(self::$sid);
            return self::$sid;
        }

        $sid = bin2hex(random_bytes(32));
        Database::execute(
            'INSERT INTO sessions (id, user_id, expires_at, user_agent, ip_address, data)
             VALUES (?, ?, datetime(\'now\', \'+30 days\'), ?, ?, ?)',
            [
                $sid,
                $userId,
                substr(Request::userAgent(), 0, 255),
                Request::ip(),
                json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE),
            ]
        );

        self::$sid = $sid;
        self::$data = ['user_id' => $userId];
        self::setCookie($sid);
        return $sid;
    }

    /**
     * Cria sessão "guest" (user_id NULL) sob demanda quando algo é gravado em
     * sessão sem haver cookie ainda. Permite que Flash::error() em fluxos
     * públicos (POST /, /verify, /cadastrar) persista entre redirects sem
     * forçar todo request a abrir sessão.
     *
     * Não recria sessão se destroy() já rodou neste request — caso contrário
     * `Flash::info()` pós-logout faria ressuscitar uma sessão recém-apagada.
     */
    private static function ensureSession(): void
    {
        if (self::$sid !== null) return;
        if (self::$destroyed) return;

        $sid = bin2hex(random_bytes(32));
        Database::execute(
            'INSERT INTO sessions (id, user_id, expires_at, user_agent, ip_address, data)
             VALUES (?, NULL, datetime(\'now\', \'+30 days\'), ?, ?, ?)',
            [
                $sid,
                substr(Request::userAgent(), 0, 255),
                Request::ip(),
                json_encode(self::$data, JSON_UNESCAPED_UNICODE),
            ]
        );

        self::$sid = $sid;
        self::setCookie($sid);
    }

    public static function destroy(): void
    {
        if (self::$sid) {
            Database::execute('DELETE FROM sessions WHERE id = ?', [self::$sid]);
            self::$sid = null;
        }
        self::$data = [];
        self::$dirty = false;
        self::$destroyed = true;
        self::clearCookie();
    }

    public static function get(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::$data[$key] = $value;
        self::$dirty = true;
        self::ensureSession();
    }

    public static function forget(string $key): void
    {
        unset(self::$data[$key]);
        self::$dirty = true;
        // Não cria sessão só pra apagar — se nem existe, nada a fazer.
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function userId(): ?int
    {
        return isset(self::$data['user_id']) ? (int) self::$data['user_id'] : null;
    }

    public static function isAuthenticated(): bool
    {
        return self::userId() !== null;
    }

    public static function id(): ?string
    {
        return self::$sid;
    }

    public static function regenerate(): void
    {
        if (!self::$sid) return;

        $newSid = bin2hex(random_bytes(32));
        Database::execute('UPDATE sessions SET id = ? WHERE id = ?', [$newSid, self::$sid]);
        self::$sid = $newSid;
        self::setCookie($newSid);
    }

    private static function setCookie(string $sid): void
    {
        setcookie(SESSION_COOKIE, $sid, [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => COOKIE_SECURE || Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[SESSION_COOKIE] = $sid;
    }

    private static function clearCookie(): void
    {
        setcookie(SESSION_COOKIE, '', [
            'expires'  => 1,
            'path'     => '/',
            'secure'   => COOKIE_SECURE || Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[SESSION_COOKIE]);
    }

    public static function gc(): void
    {
        Database::execute("DELETE FROM sessions WHERE expires_at <= datetime('now')");
        Database::execute("DELETE FROM auth_codes WHERE expires_at <= datetime('now', '-1 day')");
    }
}
