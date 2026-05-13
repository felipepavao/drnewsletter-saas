<?php
/**
 * Backup do SQLite via API oficial (VACUUM INTO).
 * Backup é consistente mesmo com WAL em uso.
 *
 * Uso:
 *   php bin/backup.php
 *
 * IMPORTANTE: este script NÃO inclui `.env`, `uploads/` ou qualquer
 * outro arquivo sensível. É só o banco. Se quiser tudo, faça tar
 * separado do `/data/drnewsletter/` excluindo explicitamente `.env`.
 */

require_once __DIR__ . '/../bootstrap.php';

if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0750, true);
}

$ts   = date('Y-m-d_His');
$dest = BACKUP_DIR . "/database_{$ts}.sqlite";

$db = Database::getInstance();
$db->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");

// Comprime
$gz = $dest . '.gz';
$in  = fopen($dest, 'rb');
$out = gzopen($gz, 'wb9');
while (!feof($in)) {
    gzwrite($out, fread($in, 8192));
}
fclose($in);
gzclose($out);
unlink($dest);

// Retenção: 14 dias
$cutoff = time() - 14 * 86400;
foreach (glob(BACKUP_DIR . '/database_*.sqlite.gz') as $f) {
    if (filemtime($f) < $cutoff) {
        unlink($f);
    }
}

fwrite(STDOUT, "✓ backup: {$gz}\n");
