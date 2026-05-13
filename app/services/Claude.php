<?php

/**
 * Cliente HTTP para a API Anthropic (Claude).
 *
 * Regras de segurança inegociáveis:
 *  - A chave (ANTHROPIC_API_KEY) é lida do .env e NUNCA é exposta no
 *    response body, no log, em comentário HTML, em nada que saia daqui.
 *  - Toda chamada é feita server-side via cURL.
 *  - Toda chamada exige user autenticado (verificado pelo controller que chama).
 *  - Toda chamada é logada em `claude_calls` (sem prompt completo, só metadados).
 *  - Daily cap em USD: se ultrapassar, recusa novas chamadas até virar o dia.
 */
class Claude
{
    // Preços (USD por 1M de tokens) para estimar custo.
    // Atualizar se trocar de modelo.
    private const PRICING = [
        'claude-sonnet-4-20250514' => ['in' => 3.00, 'out' => 15.00],
        'claude-3-5-sonnet-latest' => ['in' => 3.00, 'out' => 15.00],
        'claude-3-5-haiku-latest'  => ['in' => 0.80, 'out' => 4.00],
    ];

    /**
     * Faz uma chamada à API messages.
     *
     * @param array  $messages  [['role'=>'user','content'=>'...'], ...]
     * @param string $system    Prompt de sistema (opcional)
     * @param array  $context   ['user_id'=>int, 'client_id'=>?int, 'purpose'=>string]
     * @return array            ['text'=>string, 'tokens_in'=>int, 'tokens_out'=>int, 'cost_usd'=>float]
     * @throws RuntimeException em caso de erro
     */
    public static function complete(array $messages, string $system = '', array $context = []): array
    {
        if (ANTHROPIC_API_KEY === '') {
            throw new RuntimeException('Anthropic API key not configured');
        }

        // Daily cap — somar custo das últimas 24h e abortar se passou.
        $spent = self::spentLast24h();
        if ($spent >= CLAUDE_DAILY_USD_CAP) {
            self::logCall($context, 0, 0, 0, 0, false, 'daily_cap');
            throw new RuntimeException(sprintf(
                'Daily AI spending cap reached (US$%.2f / US$%.2f). Tente novamente amanhã.',
                $spent, CLAUDE_DAILY_USD_CAP
            ));
        }

        $payload = [
            'model'       => CLAUDE_MODEL,
            'max_tokens'  => CLAUDE_MAX_TOKENS,
            'temperature' => CLAUDE_TEMPERATURE,
            'messages'    => $messages,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }

        $start = microtime(true);
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => CLAUDE_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        $duration = (int) ((microtime(true) - $start) * 1000);

        if ($body === false) {
            self::logCall($context, 0, 0, 0, $duration, false, 'curl_error');
            throw new RuntimeException('Falha de rede ao chamar Claude: ' . $err);
        }

        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            $errCode = $data['error']['type'] ?? 'http_' . $status;
            self::logCall($context, 0, 0, 0, $duration, false, $errCode);
            // NOTA: o body pode conter detalhe do erro da Anthropic, mas
            // pode também ecoar o request. Não propagamos pro cliente.
            error_log('Claude error: ' . substr($body, 0, 1000));
            throw new RuntimeException('Claude respondeu erro: ' . $errCode);
        }

        $tokensIn  = (int) ($data['usage']['input_tokens']  ?? 0);
        $tokensOut = (int) ($data['usage']['output_tokens'] ?? 0);
        $cost      = self::costFor(CLAUDE_MODEL, $tokensIn, $tokensOut);

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        self::logCall($context, $tokensIn, $tokensOut, $cost, $duration, true, null);

        return [
            'text'       => $text,
            'tokens_in'  => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd'   => $cost,
        ];
    }

    private static function costFor(string $model, int $tokensIn, int $tokensOut): float
    {
        $p = self::PRICING[$model] ?? ['in' => 3.00, 'out' => 15.00];
        return ($tokensIn / 1_000_000) * $p['in'] + ($tokensOut / 1_000_000) * $p['out'];
    }

    private static function spentLast24h(): float
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(cost_usd), 0) AS total
               FROM claude_calls
              WHERE created_at > datetime('now', '-1 day')
                AND success = 1"
        );
        return (float) ($row['total'] ?? 0);
    }

    private static function logCall(
        array $ctx,
        int $tokensIn,
        int $tokensOut,
        float $cost,
        int $durationMs,
        bool $success,
        ?string $errorCode
    ): void {
        Database::execute(
            "INSERT INTO claude_calls
                (user_id, client_id, purpose, model, tokens_in, tokens_out,
                 cost_usd, success, error_code, duration_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $ctx['user_id']   ?? null,
                $ctx['client_id'] ?? null,
                $ctx['purpose']   ?? 'unknown',
                CLAUDE_MODEL,
                $tokensIn,
                $tokensOut,
                $cost,
                $success ? 1 : 0,
                $errorCode,
                $durationMs,
            ]
        );
    }
}
