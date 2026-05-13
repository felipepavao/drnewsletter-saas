<?php

/**
 * Orquestra a escrita de emails: monta system prompt com voz + arquivo,
 * envia histórico do chat para a Claude, persiste resposta e tenta
 * extrair subject/preview/body do texto para popular o draft.
 */
class WriterService
{
    public static function reply(int $userId, array $draft, string $userMessage): array
    {
        $client = Client::findForUser($userId, (int) $draft['client_id']);
        if (!$client) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        $brand = BrandManual::activeForClient((int) $client['id']);
        $brandContext   = $brand ? PlannerService::formatBrand($brand) : '';
        $archiveContext = EmailArchive::concatenatedForPrompt((int) $client['id']);

        $chatId = WriterChat::getOrCreateForDraft((int) $draft['id']);

        // Persiste a mensagem do user
        WriterChat::appendMessage($chatId, 'user', $userMessage);

        // Chama a IA com o histórico completo
        $messages = WriterChat::asClaudeMessages($chatId);
        $system   = Prompts::writerSystem($brandContext, $archiveContext);

        $result = Claude::complete(
            $messages,
            $system,
            [
                'user_id'   => $userId,
                'client_id' => (int) $client['id'],
                'purpose'   => 'writer',
            ]
        );

        WriterChat::appendMessage(
            $chatId,
            'assistant',
            $result['text'],
            (int) ($result['tokens_in'] ?? 0),
            (int) ($result['tokens_out'] ?? 0)
        );

        // Tenta extrair subject/preview/body — se a IA seguiu o formato
        $extracted = self::extractEmail($result['text']);
        if ($extracted) {
            EmailDraft::updateContent(
                (int) $draft['id'],
                $extracted['subject'] ?? null,
                $extracted['preview'] ?? null,
                $extracted['body']    ?? null,
                (int) ($result['tokens_in']  ?? 0),
                (int) ($result['tokens_out'] ?? 0),
                (float) ($result['cost_usd'] ?? 0)
            );
        }

        return [
            'text'      => $result['text'],
            'extracted' => $extracted,
        ];
    }

    /**
     * Faz a primeira mensagem do chat baseada no tema do plano,
     * dispara a IA, e tudo persistido.
     */
    public static function startFromTheme(int $userId, array $client, int $planId, int $themeIndex): int
    {
        $plan = MonthlyPlan::findForUser($userId, $planId);
        if (!$plan || (int) $plan['client_id'] !== (int) $client['id']) {
            throw new RuntimeException('Planejamento inválido.');
        }
        $themes = $plan['themes'] ?? [];
        if (!isset($themes[$themeIndex])) {
            throw new RuntimeException('Tema não encontrado no planejamento.');
        }

        $draftId = EmailDraft::create((int) $client['id'], $planId, $themeIndex);
        $draft = EmailDraft::findForUser($userId, $draftId);

        $initialMsg = Prompts::writerInitialUserMessage($themes[$themeIndex]);
        self::reply($userId, $draft, $initialMsg);

        return $draftId;
    }

    /**
     * Tenta extrair subject(s)/preview/body do texto da IA.
     * Aceita o formato do prompt:
     *   SUBJECT (3 opções...): 1. ... 2. ... 3. ...
     *   PREVIEW: ...
     *   BODY: ...
     *   P.S.: ... (opcional)
     */
    public static function extractEmail(string $text): ?array
    {
        $out = ['subject' => null, 'preview' => null, 'body' => null];

        // SUBJECT — pega a 1ª opção
        if (preg_match('/SUBJECT[^:\n]*:\s*(.+?)(?=\n\s*PREVIEW\b|\n\s*BODY\b|\z)/is', $text, $m)) {
            $block = $m[1];
            if (preg_match('/^\s*1[\.\)]\s*(.+?)(?=\n\s*2[\.\)]|\z)/ms', $block, $opt)) {
                $out['subject'] = trim(preg_replace('/\s*\(\d+\s*caracteres?\)\s*$/i', '', $opt[1]));
            } else {
                $out['subject'] = trim(explode("\n", trim($block))[0]);
            }
        }

        if (preg_match('/PREVIEW[^:\n]*:\s*(.+?)(?=\n\s*BODY\b|\z)/is', $text, $m)) {
            $out['preview'] = trim($m[1]);
        }

        if (preg_match('/BODY\s*:\s*(.+?)(?=\n\s*P\.?S\.?\s*:|\z)/is', $text, $m)) {
            $body = trim($m[1]);
            // Captura também o P.S. se houver
            if (preg_match('/(P\.?S\.?\s*:.+)/is', $text, $ps)) {
                $body .= "\n\n" . trim($ps[1]);
            }
            $out['body'] = $body;
        }

        // Se não achou nada, devolve null pra sinalizar que a IA não seguiu o formato
        if (!$out['subject'] && !$out['body']) {
            return null;
        }
        return $out;
    }
}
