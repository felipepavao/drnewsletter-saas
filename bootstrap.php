<?php

require_once __DIR__ . '/config.php';

// Autoloader manual — core → controllers → models → services
spl_autoload_register(function (string $class): void {
    $paths = [
        APP_ROOT . '/app/core/'        . $class . '.php',
        APP_ROOT . '/app/controllers/' . $class . '.php',
        APP_ROOT . '/app/models/'      . $class . '.php',
        APP_ROOT . '/app/services/'    . $class . '.php',
    ];
    foreach ($paths as $p) {
        if (is_file($p)) {
            require_once $p;
            return;
        }
    }
});

require_once APP_ROOT . '/app/lib/helpers.php';

// Erros
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'local' ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
ini_set('error_log', LOG_DIR . '/app.log');

// Banco + migrations
Database::runMigrations();

// Headers de segurança (não-CLI)
if (PHP_SAPI !== 'cli') {
    $isProd  = APP_ENV !== 'local';
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || (TRUST_PROXY && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if ($isProd && !$isHttps && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $host = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST);
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }

    if ($isProd && $isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    // CSP: tudo same-origin. Frontend é JS vanilla servido pelo próprio app.
    // NENHUMA conexão direta do browser pra api.anthropic.com — connect-src 'self'.
    header(
        "Content-Security-Policy: default-src 'self'; "
      . "img-src 'self' data:; "
      . "style-src 'self' 'unsafe-inline'; "
      . "font-src 'self'; "
      . "script-src 'self'; "
      . "connect-src 'self'; "
      . "frame-ancestors 'none'; "
      . "base-uri 'self'; "
      . "form-action 'self'"
    );

    Session::start();
}
