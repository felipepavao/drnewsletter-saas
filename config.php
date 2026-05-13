<?php

define('APP_ROOT', __DIR__);

// --- .env loader (key=value, # comentários, sem dependência externa) ---
$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        // Sempre sobrescreve: o .env é a fonte única. Sem isso, php-fpm
        // workers ficam com valor cacheado entre requests.
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
    }
}

function env(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    if ($v === 'true')  return true;
    if ($v === 'false') return false;
    return $v;
}

// --- Identidade ---
define('APP_NAME',   env('APP_NAME', 'Dr. Newsletter'));
define('APP_ENV',    env('APP_ENV', 'local'));
define('APP_URL',    rtrim(env('APP_URL', 'http://localhost:8080'), '/'));
define('APP_SECRET', env('APP_SECRET', ''));

// --- Paths ---
define('DB_PATH',     APP_ROOT . '/data/database.sqlite');
define('LOG_DIR',     APP_ROOT . '/data/logs');
define('BACKUP_DIR',  APP_ROOT . '/data/backups');
define('VIEWS_PATH',  APP_ROOT . '/app/views');
define('UPLOAD_PATH', APP_ROOT . '/public/uploads');

// --- Sessão ---
define('SESSION_COOKIE',   'drnl_sid');
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 dias

// --- Magic link ---
define('AUTH_CODE_TTL',       60 * 15);  // 15 min
define('AUTH_CODE_MAX_TRIES', 5);
define('AUTH_CODE_PER_EMAIL', 3);
define('AUTH_CODE_PER_IP',    10);

// --- Rate limit ---
define('RATE_LIMIT_WINDOW',         60 * 15);
define('RATE_LIMIT_LOGIN_IP',       10);
define('RATE_LIMIT_AI_USER_DAY',    50);   // chamadas Claude por usuário/dia
define('RATE_LIMIT_UPLOAD_USER',    20);

// --- Upload ---
define('UPLOAD_MAX_BYTES',  10 * 1024 * 1024);
define('UPLOAD_ALLOWED_MIME', ['text/plain']);

// --- Claude ---
define('ANTHROPIC_API_KEY',  env('ANTHROPIC_API_KEY', ''));
define('CLAUDE_MODEL',       env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'));
define('CLAUDE_MAX_TOKENS',  (int) env('CLAUDE_MAX_TOKENS', 4096));
define('CLAUDE_TEMPERATURE', (float) env('CLAUDE_TEMPERATURE', 1.0));
define('CLAUDE_TIMEOUT',     (int) env('CLAUDE_REQUEST_TIMEOUT', 120));
define('CLAUDE_DAILY_USD_CAP', (float) env('CLAUDE_DAILY_USD_CAP', 10));

// --- Email ---
define('BREVO_API_KEY',   env('BREVO_API_KEY', '')); // opcional; se setado, prefere HTTP API ao SMTP
define('SMTP_HOST',       env('SMTP_HOST', ''));
define('SMTP_PORT',       (int) env('SMTP_PORT', 587));
define('SMTP_USER',       env('SMTP_USER', ''));
define('SMTP_PASS',       env('SMTP_PASS', ''));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls'));
define('FROM_EMAIL',      env('FROM_EMAIL', 'nao-responda@localhost'));
define('FROM_NAME',       env('FROM_NAME', APP_NAME));

// --- Segurança ---
define('CSRF_TOKEN_NAME', '_csrf');
define('TRUST_PROXY',     (bool) env('TRUST_PROXY', false));
define('COOKIE_SECURE',   (bool) env('COOKIE_SECURE', false));

// --- Admin seed ---
define('ADMIN_EMAIL', env('ADMIN_EMAIL', ''));

// Overrides locais opcionais (não commitados)
if (is_file(APP_ROOT . '/config.local.php')) {
    require APP_ROOT . '/config.local.php';
}
