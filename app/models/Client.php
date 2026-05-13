<?php

/**
 * Repositório de clientes — toda operação é escopada pelo user_id
 * passado explicitamente, nunca em sessão direta. Isso impede
 * IDOR (Insecure Direct Object Reference) por construção.
 */
class Client
{
    public const SEGMENTS = [
        'joalheria'   => 'Joalheria',
        'restaurante' => 'Restaurante',
        'clinica'     => 'Clínica',
        'otica'       => 'Ótica',
        'outro'       => 'Outro',
    ];

    /** Lista clientes ativos do usuário, mais recentes primeiro. */
    public static function listForUser(int $userId, string $status = 'active'): array
    {
        return Database::fetchAll(
            'SELECT id, name, email, segment, notes, status, created_at, updated_at
               FROM clients
              WHERE user_id = ? AND status = ?
              ORDER BY updated_at DESC',
            [$userId, $status]
        );
    }

    /** Busca um cliente do user, ou null se não existir / não for dele. */
    public static function findForUser(int $userId, int $clientId): ?array
    {
        return Database::fetch(
            'SELECT id, user_id, name, email, segment, notes, status, created_at, updated_at
               FROM clients
              WHERE id = ? AND user_id = ?',
            [$clientId, $userId]
        );
    }

    /** Cria um cliente e retorna o ID. */
    public static function create(int $userId, array $data): int
    {
        Database::execute(
            'INSERT INTO clients (user_id, name, email, segment, notes, status, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            [
                $userId,
                $data['name'],
                $data['email']   ?: null,
                $data['segment'] ?: null,
                $data['notes']   ?: null,
                'active',
            ]
        );
        return (int) Database::lastInsertId();
    }

    /** Atualiza um cliente. Retorna número de linhas afetadas. */
    public static function update(int $userId, int $clientId, array $data): int
    {
        return Database::execute(
            "UPDATE clients
                SET name = ?, email = ?, segment = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
              WHERE id = ? AND user_id = ?",
            [
                $data['name'],
                $data['email']   ?: null,
                $data['segment'] ?: null,
                $data['notes']   ?: null,
                $clientId,
                $userId,
            ]
        );
    }

    /**
     * Arquiva o cliente (soft delete). Mantém os dados pra histórico,
     * mas some das listagens. Hard delete só via SQL administrativo.
     */
    public static function archive(int $userId, int $clientId): int
    {
        return Database::execute(
            "UPDATE clients SET status = 'archived', updated_at = CURRENT_TIMESTAMP
              WHERE id = ? AND user_id = ?",
            [$clientId, $userId]
        );
    }

    /**
     * Valida payload de form. Retorna [data_normalizado, errors].
     * Não persiste — usa em create/update.
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $data = [
            'name'    => trim((string) ($input['name'] ?? '')),
            'email'   => normalize_email((string) ($input['email'] ?? '')),
            'segment' => trim((string) ($input['segment'] ?? '')),
            'notes'   => trim((string) ($input['notes'] ?? '')),
        ];

        if ($data['name'] === '' || mb_strlen($data['name']) < 2) {
            $errors['name'] = 'Informe o nome do cliente.';
        }
        if (mb_strlen($data['name']) > 120) {
            $errors['name'] = 'Nome muito longo (máx 120 caracteres).';
        }
        if ($data['email'] !== '' && !is_valid_email($data['email'])) {
            $errors['email'] = 'Email inválido.';
        }
        if ($data['segment'] !== '' && !array_key_exists($data['segment'], self::SEGMENTS)) {
            $errors['segment'] = 'Segmento inválido.';
        }
        if (mb_strlen($data['notes']) > 2000) {
            $errors['notes'] = 'Anotações muito longas (máx 2000 caracteres).';
        }

        return [$data, $errors];
    }
}
