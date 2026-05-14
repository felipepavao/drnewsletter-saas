<?php
/**
 * Bootstrap dos testes Pest.
 *
 * Estratégia:
 *  - Banco SQLite em arquivo temporário (não :memory: porque queremos reuso
 *    entre conexões dentro do mesmo teste, mas isolado entre testes).
 *  - APP_ENV=test, APP_URL=http://localhost
 *  - Mock global de Claude::complete (sobreposto por teste quando precisar)
 *  - Helpers em tests/Helpers.php (autoload via composer)
 */

// Forçar ambiente de teste ANTES de carregar config.
// SKIP_ENV_FILE evita que .env local sobrescreva estas vars.
putenv('SKIP_ENV_FILE=1');
putenv('APP_ENV=test');
putenv('APP_URL=http://localhost');
putenv('APP_SECRET=test_secret_long_enough_to_be_valid_32_chars');
putenv('ANTHROPIC_API_KEY=sk-ant-test-fake');
putenv('CLAUDE_DAILY_USD_CAP=1000');  // sem cap em testes
putenv('BREVO_API_KEY=');
putenv('SMTP_HOST=');
putenv('TRUST_PROXY=false');
putenv('COOKIE_SECURE=false');

// Database isolada por execução
$testDbDir = sys_get_temp_dir() . '/drnl-tests';
if (!is_dir($testDbDir)) {
    mkdir($testDbDir, 0755, true);
}
$_ENV['_TEST_DB_DIR'] = $testDbDir;

require_once __DIR__ . '/Helpers.php';
