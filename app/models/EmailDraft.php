<?php

/**
 * Rascunho de email — gerado a partir de um tema de um planejamento,
 * iterado em chat com a IA até a aprovação.
 */
class EmailDraft
{
    public const STATUSES = ['draft', 'approved', 'sent', 'archived'];

    public static function create(int $clientId, int $planId, int $themeIndex): int
    {
        Database::execute(
            'INSERT INTO email_drafts (client_id, monthly_plan_id, theme_index, status, version, updated_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            [$clientId, $planId, $themeIndex, 'draft', 1]
        );
        return (int) Database::lastInsertId();
    }

    public static function findForUser(int $userId, int $draftId): ?array
    {
        return Database::fetch(
            'SELECT d.*, c.user_id AS owner_user_id, c.name AS client_name
               FROM email_drafts d
               JOIN clients c ON c.id = d.client_id
              WHERE d.id = ? AND c.user_id = ?',
            [$draftId, $userId]
        );
    }

    public static function updateContent(int $draftId, ?string $subject, ?string $preview, ?string $body, int $tokensIn, int $tokensOut, float $cost): void
    {
        // version++ se houve mudança de conteúdo
        $current = Database::fetch('SELECT version FROM email_drafts WHERE id = ?', [$draftId]);
        $next = (int) ($current['version'] ?? 1) + 1;
        Database::execute(
            'UPDATE email_drafts
                SET subject = ?, preview_text = ?, body_text = ?,
                    version = ?, tokens_in = COALESCE(tokens_in, 0) + ?,
                    tokens_out = COALESCE(tokens_out, 0) + ?,
                    cost_usd = COALESCE(cost_usd, 0) + ?,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?',
            [$subject, $preview, $body, $next, $tokensIn, $tokensOut, $cost, $draftId]
        );
    }

    public static function approve(int $userId, int $draftId): int
    {
        return Database::execute(
            "UPDATE email_drafts SET status = 'approved', updated_at = CURRENT_TIMESTAMP
               WHERE id = ? AND client_id IN (SELECT id FROM clients WHERE user_id = ?)",
            [$draftId, $userId]
        );
    }
}
