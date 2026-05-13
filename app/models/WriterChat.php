<?php

/**
 * Conversa do escritor de emails: 1 chat por draft, N mensagens.
 */
class WriterChat
{
    public static function getOrCreateForDraft(int $draftId): int
    {
        $row = Database::fetch('SELECT id FROM email_writer_chats WHERE email_draft_id = ?', [$draftId]);
        if ($row) return (int) $row['id'];
        Database::execute(
            'INSERT INTO email_writer_chats (email_draft_id) VALUES (?)',
            [$draftId]
        );
        return (int) Database::lastInsertId();
    }

    public static function appendMessage(int $chatId, string $role, string $content, int $tokensIn = 0, int $tokensOut = 0): int
    {
        Database::execute(
            'INSERT INTO email_writer_messages (chat_id, role, content, tokens_in, tokens_out)
             VALUES (?, ?, ?, ?, ?)',
            [$chatId, $role, $content, $tokensIn, $tokensOut]
        );
        return (int) Database::lastInsertId();
    }

    public static function messagesForChat(int $chatId): array
    {
        return Database::fetchAll(
            'SELECT id, role, content, tokens_in, tokens_out, created_at
               FROM email_writer_messages
              WHERE chat_id = ?
              ORDER BY id ASC',
            [$chatId]
        );
    }

    /**
     * Devolve as mensagens em formato Claude API: [['role'=>..,'content'=>..], …].
     * Pula mensagens de sistema (esses vão como system prompt separado).
     */
    public static function asClaudeMessages(int $chatId): array
    {
        $rows = self::messagesForChat($chatId);
        $out = [];
        foreach ($rows as $r) {
            if ($r['role'] === 'system') continue;
            if (!in_array($r['role'], ['user', 'assistant'], true)) continue;
            $out[] = ['role' => $r['role'], 'content' => $r['content']];
        }
        return $out;
    }
}
