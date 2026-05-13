<?php
/**
 * Aplica migrations pendentes. Idempotente.
 *
 * Uso:
 *   php bin/migrate.php
 */

require_once __DIR__ . '/../bootstrap.php';

Database::runMigrations();
fwrite(STDOUT, "✓ migrations aplicadas\n");
