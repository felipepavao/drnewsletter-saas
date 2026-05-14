<?php
/**
 * Helpers para testes Pest.
 *
 * Define funções globais:
 *   - boot_app()       — reset por teste: DB nova, includes do app.
 *   - login_as($email) — cria user no banco e abre sessão.
 *   - create_client()  — cria cliente para o user logado.
 *   - mock_claude()    — substitui Claude::complete por um fake.
 *   - request($m,$u,$d)— invoca o router como se fosse um request HTTP.
 */

/**
 * Reseta o estado do app para cada teste:
 *  - apaga DB anterior, cria nova
 *  - reseta variáveis globais ($_SESSION, $_COOKIE, $_POST, $_FILES, $_SERVER)
 *  - carrega bootstrap.php do app
 */
function boot_app(): void
{
    // 1ª chamada por processo: carrega app + define DB_PATH único do processo.
    static $loaded = false;
    if (!$loaded) {
        $dbFile = $_ENV['_TEST_DB_DIR'] . '/db_' . getmypid() . '.sqlite';
        foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm'] as $f) {
            if (file_exists($f)) unlink($f);
        }
        putenv('DB_PATH=' . $dbFile);
        require_once dirname(__DIR__) . '/bootstrap.php';
        require_once APP_ROOT . '/app/lib/routes.php';
        $loaded = true;
    }

    // Reset de globais HTTP entre testes
    $_GET = $_POST = $_FILES = $_COOKIE = $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/';
    $_SERVER['HTTP_HOST']      = 'localhost';
    $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Pest/1.0';

    // Reset Session static state
    $ref = new ReflectionClass('Session');
    foreach (['started' => false, 'data' => [], 'sid' => null, 'dirty' => false, 'destroyed' => false] as $name => $default) {
        if ($ref->hasProperty($name)) {
            $p = $ref->getProperty($name);
            $p->setAccessible(true);
            $p->setValue(null, $default);
        }
    }

    // Reset mocks
    Claude::fake(null);

    // Limpar dados de todas as tabelas (mais rápido que recriar DB).
    // Mantém as tabelas e o esquema; zera autoincrement.
    $db = Database::getInstance();
    $tables = [
        'email_writer_messages', 'email_writer_chats', 'feedback_history',
        'email_drafts', 'monthly_plans', 'email_archives', 'brand_manuals',
        'clients', 'auth_codes', 'sessions', 'rate_limits',
        'claude_calls', 'users',
    ];
    $db->exec('PRAGMA foreign_keys = OFF');
    foreach ($tables as $t) {
        $db->exec("DELETE FROM {$t}");
        $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$t}'");
    }
    $db->exec('PRAGMA foreign_keys = ON');

    // Re-inicia sessão limpa
    Session::start();
}

/**
 * Cria um user e abre sessão. Devolve o ID.
 */
function login_as(string $email = 'op@test.local'): int
{
    Database::execute(
        'INSERT INTO users (email, status, is_admin) VALUES (?, ?, ?)',
        [$email, 'active', 0]
    );
    $uid = (int) Database::lastInsertId();
    Session::create($uid);
    return $uid;
}

function create_client(int $userId, array $overrides = []): int
{
    $data = array_merge([
        'name'    => 'Cliente Teste',
        'email'   => null,
        'segment' => 'joalheria',
        'notes'   => null,
    ], $overrides);

    Database::execute(
        'INSERT INTO clients (user_id, name, email, segment, notes, status, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
        [$userId, $data['name'], $data['email'], $data['segment'], $data['notes'], 'active']
    );
    return (int) Database::lastInsertId();
}

function create_brand_manual(int $clientId, array $parsed = []): int
{
    $parsed = array_merge([
        'summary'           => 'Joalheria autoral',
        'target_audience'   => 'Mulheres 45+ classe A',
        'tone_summary'      => 'sóbrio profissional',
        'tone_attributes'   => ['íntimo'],
        'preferred_themes'  => ['ourivesaria'],
        'avoid_themes'      => ['urgência'],
        'vocabulary_signature' => ['peça de coleção'],
        'dos'               => ['história concreta'],
        'donts'             => ['emojis'],
    ], $parsed);

    Database::execute(
        "INSERT INTO brand_manuals (client_id, version, is_active, source_filename, raw_content, parsed_json)
         VALUES (?, 1, 1, 'voz.txt', 'raw', ?)",
        [$clientId, json_encode($parsed, JSON_UNESCAPED_UNICODE)]
    );
    return (int) Database::lastInsertId();
}

function create_monthly_plan(int $userId, int $clientId, ?array $themes = null, array $overrides = []): int
{
    $themes = $themes ?? [
        ['title' => 'Tema A', 'type' => 'história', 'goal' => 'G', 'hook' => 'H', 'cta' => 'C', 'cta_intensity' => 'suave'],
    ];
    $data = array_merge([
        'year' => 2026, 'month' => 6, 'email_count' => count($themes),
        'plan' => [
            'title'         => 'Plano Teste',
            'summary'       => 'Summary',
            'strategy'      => 'Strategy',
            'month_context' => 'Context',
            'themes'        => $themes,
        ],
    ], $overrides);

    Database::execute(
        'INSERT INTO monthly_plans (client_id, user_id, year, month, email_count, themes_json, status, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
        [
            $clientId, $userId, $data['year'], $data['month'], $data['email_count'],
            json_encode($data['plan'], JSON_UNESCAPED_UNICODE),
            'draft',
        ]
    );
    return (int) Database::lastInsertId();
}

/**
 * Substitui Claude::complete por uma resposta fixa ou função custom.
 *   mock_claude(['text' => '...', 'tokens_in' => 100, 'tokens_out' => 50])
 *   mock_claude(fn($msgs, $sys, $ctx) => [...])
 */
function mock_claude($responseOrFn): void
{
    $fn = is_callable($responseOrFn)
        ? $responseOrFn
        : function () use ($responseOrFn) {
            return array_merge(
                ['text' => '', 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 0],
                $responseOrFn
            );
        };
    Claude::fake($fn);
}

/**
 * Invoca o router como se fosse um request HTTP. Retorna ['status'=>int,
 * 'body'=>string, 'location'=>?string, 'flashes'=>array].
 *
 * Para POSTs com CSRF, passe 'csrf' => true no array de data e o helper
 * busca o token da sessão e injeta como '_csrf'.
 */
function request(string $method, string $uri, array $data = []): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI']    = $uri;
    $_POST = $_GET = [];

    if ($method === 'GET') {
        $qs = parse_url($uri, PHP_URL_QUERY);
        if ($qs) parse_str($qs, $_GET);
    } else {
        $_POST = $data;
        if (!empty($data['_csrf_auto'])) {
            unset($_POST['_csrf_auto']);
            $_POST[CSRF_TOKEN_NAME] = Csrf::token();
        }
    }

    // Reset HTTP status
    http_response_code(200);

    ob_start();
    $status   = 200;
    $location = null;
    try {
        $router = new Router();
        register_routes($router);
        $router->dispatch($method, $uri);
    } catch (RedirectException $e) {
        $status   = $e->status;
        $location = $e->location;
    }
    $body = ob_get_clean() ?: '';

    return [
        'status'   => $location !== null ? $status : http_response_code(),
        'location' => $location,
        'body'     => $body,
        'flashes'  => Flash::pull(),
    ];
}

/**
 * Exception lançada por redirect() em modo teste, captura status + location.
 */
class RedirectException extends RuntimeException
{
    public int $status;
    public string $location;
    public function __construct(string $location, int $status)
    {
        $this->status = $status;
        $this->location = $location;
        parent::__construct("Redirect to {$location} ({$status})");
    }
}
