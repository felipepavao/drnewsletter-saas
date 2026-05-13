<?php

/**
 * Planejamento mensal de newsletters por cliente.
 * Cada plano tem N temas estruturados em themes_json (JSON array).
 */
class MonthlyPlan
{
    public const STATUSES = ['draft', 'approved', 'archived'];

    public const TYPES = [
        'educação'    => 'Educação',
        'história'    => 'História',
        'bastidor'    => 'Bastidor',
        'estilo'      => 'Estilo de vida',
        'convite'     => 'Convite',
    ];

    public const INTENSITIES = [
        'suave' => 'Suave',
        'média' => 'Média',
        'firme' => 'Firme',
    ];

    public static function findForUser(int $userId, int $planId): ?array
    {
        $row = Database::fetch(
            'SELECT p.* FROM monthly_plans p
              WHERE p.id = ? AND p.user_id = ?',
            [$planId, $userId]
        );
        if ($row) {
            $parsed = $row['themes_json']
                ? (json_decode($row['themes_json'], true) ?: [])
                : [];
            // $row['themes'] = lista de temas (array indexado).
            // $row['plan_data'] = estrutura completa (title/summary/strategy/themes/...).
            $row['plan_data'] = $parsed;
            $row['themes']    = is_array($parsed['themes'] ?? null) ? array_values($parsed['themes']) : [];
        }
        return $row;
    }

    public static function listForClient(int $clientId, int $limit = 30): array
    {
        return Database::fetchAll(
            'SELECT id, year, month, email_count, status, created_at
               FROM monthly_plans
              WHERE client_id = ?
              ORDER BY year DESC, month DESC, id DESC
              LIMIT ?',
            [$clientId, $limit]
        );
    }

    public static function create(
        int $userId,
        int $clientId,
        int $year,
        int $month,
        int $emailCount,
        string $contextNotes,
        array $parsed,
        array $usage
    ): int {
        Database::execute(
            'INSERT INTO monthly_plans
                (client_id, user_id, year, month, email_count, context_notes,
                 themes_json, status, tokens_in, tokens_out, cost_usd, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            [
                $clientId,
                $userId,
                $year,
                $month,
                $emailCount,
                $contextNotes,
                json_encode($parsed, JSON_UNESCAPED_UNICODE),
                'draft',
                $usage['tokens_in']  ?? null,
                $usage['tokens_out'] ?? null,
                $usage['cost_usd']   ?? null,
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function approve(int $userId, int $planId): int
    {
        return Database::execute(
            "UPDATE monthly_plans SET status = 'approved', updated_at = CURRENT_TIMESTAMP
              WHERE id = ? AND user_id = ?",
            [$planId, $userId]
        );
    }

    /** Substitui só o array de temas dentro de themes_json, preservando título/summary/etc. */
    public static function updateThemes(int $userId, int $planId, array $themes): int
    {
        $plan = self::findForUser($userId, $planId);
        if (!$plan) return 0;
        $structure = $plan['plan_data'] ?? [];
        $structure['themes'] = array_values($themes);
        return Database::execute(
            'UPDATE monthly_plans
                SET themes_json = ?, updated_at = CURRENT_TIMESTAMP
              WHERE id = ? AND user_id = ?',
            [json_encode($structure, JSON_UNESCAPED_UNICODE), $planId, $userId]
        );
    }
}
