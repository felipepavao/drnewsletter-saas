<?php

/**
 * Voz da Marca por cliente, com versionamento.
 *
 * Modelo: a cada upload, criamos uma nova linha em brand_manuals com
 * version = max(version)+1 e marcamos is_active=1; desativamos
 * (is_active=0) as anteriores. Histórico fica preservado, queries
 * "voz ativa" são triviais.
 *
 * Toda operação exige verificação prévia de que o cliente é do user
 * (ver BrandManualController::ensureOwnership).
 */
class BrandManual
{
    /** Voz atualmente ativa do cliente, ou null. */
    public static function activeForClient(int $clientId): ?array
    {
        $row = Database::fetch(
            'SELECT id, client_id, version, is_active, source_filename, raw_content,
                    parsed_json, created_at
               FROM brand_manuals
              WHERE client_id = ? AND is_active = 1
              LIMIT 1',
            [$clientId]
        );
        if ($row && $row['parsed_json']) {
            $row['parsed'] = json_decode($row['parsed_json'], true) ?: [];
        }
        return $row;
    }

    /** Lista todas as versões (mais recente primeiro). */
    public static function historyForClient(int $clientId): array
    {
        return Database::fetchAll(
            'SELECT id, version, is_active, source_filename, created_at
               FROM brand_manuals
              WHERE client_id = ?
              ORDER BY version DESC',
            [$clientId]
        );
    }

    /**
     * Cria nova versão da Voz da Marca, marca como ativa e
     * desativa as anteriores. Retorna o ID.
     */
    public static function createNewVersion(
        int $clientId,
        string $filename,
        string $rawContent,
        array $parsed
    ): int {
        return Database::transaction(function () use ($clientId, $filename, $rawContent, $parsed) {
            // Próxima versão
            $next = (int) Database::fetchColumn(
                'SELECT COALESCE(MAX(version), 0) + 1 FROM brand_manuals WHERE client_id = ?',
                [$clientId]
            );

            // Desativa anteriores
            Database::execute(
                'UPDATE brand_manuals SET is_active = 0 WHERE client_id = ? AND is_active = 1',
                [$clientId]
            );

            Database::execute(
                'INSERT INTO brand_manuals
                    (client_id, version, is_active, source_filename, raw_content, parsed_json)
                 VALUES (?, ?, 1, ?, ?, ?)',
                [
                    $clientId,
                    $next,
                    $filename,
                    $rawContent,
                    json_encode($parsed, JSON_UNESCAPED_UNICODE),
                ]
            );

            return (int) Database::lastInsertId();
        });
    }
}
