<?php

/**
 * Arquivos de emails de referência — TXTs com exemplos de emails do
 * cliente, usados como contexto bruto nos prompts. Máximo 5 por cliente.
 */
class EmailArchive
{
    public const MAX_PER_CLIENT = 5;

    public static function listForClient(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT id, client_id, filename, byte_size, created_at
               FROM email_archives
              WHERE client_id = ?
              ORDER BY created_at DESC',
            [$clientId]
        );
    }

    public static function findForClient(int $clientId, int $archiveId): ?array
    {
        return Database::fetch(
            'SELECT * FROM email_archives WHERE id = ? AND client_id = ?',
            [$archiveId, $clientId]
        );
    }

    public static function countForClient(int $clientId): int
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM email_archives WHERE client_id = ?',
            [$clientId]
        );
    }

    public static function create(int $clientId, string $filename, string $content, int $bytes): int
    {
        Database::execute(
            'INSERT INTO email_archives (client_id, filename, content, byte_size)
             VALUES (?, ?, ?, ?)',
            [$clientId, $filename, $content, $bytes]
        );
        return (int) Database::lastInsertId();
    }

    public static function delete(int $clientId, int $archiveId): int
    {
        return Database::execute(
            'DELETE FROM email_archives WHERE id = ? AND client_id = ?',
            [$archiveId, $clientId]
        );
    }

    /** Conteúdo concatenado dos arquivos, para uso como contexto em prompts. */
    public static function concatenatedForPrompt(int $clientId, int $maxChars = 30000): string
    {
        $files = Database::fetchAll(
            'SELECT filename, content FROM email_archives
              WHERE client_id = ? ORDER BY created_at ASC',
            [$clientId]
        );

        $out = '';
        foreach ($files as $f) {
            $block = "=== {$f['filename']} ===\n{$f['content']}\n\n";
            if (mb_strlen($out . $block) > $maxChars) break;
            $out .= $block;
        }
        return $out;
    }
}
