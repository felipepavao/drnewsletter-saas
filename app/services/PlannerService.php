<?php

/**
 * Orquestra a gera\u00e7\u00e3o de planejamento mensal:
 *   1. monta contexto (voz da marca + arquivo de emails)
 *   2. chama Claude
 *   3. parseia JSON
 *   4. persiste em monthly_plans
 *
 * Lan\u00e7a RuntimeException com mensagem amig\u00e1vel em caso de erro.
 */
class PlannerService
{
    public static function generate(
        int $userId,
        array $client,
        int $year,
        int $month,
        int $emailCount,
        string $extraContext
    ): int {
        $brand = BrandManual::activeForClient((int) $client['id']);
        if (!$brand) {
            throw new RuntimeException('Cadastre a Voz da Marca antes de gerar planejamento.');
        }

        $brandContext = self::formatBrand($brand);
        $archiveContext = EmailArchive::concatenatedForPrompt((int) $client['id']);

        $system = Prompts::plannerSystem();
        $user   = Prompts::plannerUser(
            $client['name'],
            $month,
            $year,
            $emailCount,
            $extraContext,
            $brandContext,
            $archiveContext
        );

        $result = Claude::complete(
            [['role' => 'user', 'content' => $user]],
            $system,
            [
                'user_id'   => $userId,
                'client_id' => (int) $client['id'],
                'purpose'   => 'monthly_plan',
            ]
        );

        $parsed = self::parseJson($result['text']);
        if (!$parsed || empty($parsed['themes']) || !is_array($parsed['themes'])) {
            Log::warn('planner: invalid JSON', [
                'client_id' => $client['id'],
                'sample'    => mb_substr($result['text'], 0, 400),
            ]);
            throw new RuntimeException('A IA não devolveu um planejamento válido. Tente novamente.');
        }

        // Sanitiza themes — garante chaves esperadas
        $themes = [];
        foreach ($parsed['themes'] as $t) {
            if (!is_array($t)) continue;
            $themes[] = [
                'title'         => (string) ($t['title']         ?? ''),
                'type'          => self::normalizeType((string) ($t['type'] ?? '')),
                'goal'          => (string) ($t['goal']          ?? ''),
                'hook'          => (string) ($t['hook']          ?? ''),
                'cta'           => (string) ($t['cta']           ?? ''),
                'cta_intensity' => self::normalizeIntensity((string) ($t['cta_intensity'] ?? '')),
            ];
        }
        $parsed['themes'] = $themes;

        return MonthlyPlan::create(
            $userId,
            (int) $client['id'],
            $year,
            $month,
            $emailCount,
            $extraContext,
            $parsed,
            [
                'tokens_in'  => $result['tokens_in']  ?? null,
                'tokens_out' => $result['tokens_out'] ?? null,
                'cost_usd'   => $result['cost_usd']   ?? null,
            ]
        );
    }

    /** Resumo textual da voz da marca para injetar no prompt. */
    private static function formatBrand(array $brand): string
    {
        $p = $brand['parsed'] ?? [];
        if (!$p) {
            // Falha de parse anterior — usa raw mesmo (mais barato e seguro)
            return mb_substr($brand['raw_content'] ?? '', 0, 4000);
        }
        $lines = [];
        $add = function (string $label, $value) use (&$lines) {
            if (!$value) return;
            if (is_array($value)) {
                $value = array_filter(array_map('trim', array_map('strval', $value)));
                if (!$value) return;
                $lines[] = "- {$label}: " . implode(' · ', $value);
            } else {
                $lines[] = "- {$label}: " . trim((string) $value);
            }
        };
        $add('Resumo',                 $p['summary']              ?? null);
        $add('Público',                $p['target_audience']      ?? null);
        $add('Dores do público',       $p['audience_pains']       ?? null);
        $add('Tom (resumo)',           $p['tone_summary']         ?? null);
        $add('Atributos de tom',       $p['tone_attributes']      ?? null);
        $add('Temas preferidos',       $p['preferred_themes']     ?? null);
        $add('Temas a evitar',         $p['avoid_themes']         ?? null);
        $add('Vocabulário',            $p['vocabulary_signature'] ?? null);
        $add('Faça',                   $p['dos']                  ?? null);
        $add('Não faça',               $p['donts']                ?? null);
        return implode("\n", $lines);
    }

    private static function normalizeType(string $t): string
    {
        $t = mb_strtolower(trim($t));
        // Aceita variações comuns
        $map = [
            'educação' => 'educação', 'educacao' => 'educação',
            'história' => 'história', 'historia' => 'história', 'histórias' => 'história',
            'bastidor' => 'bastidor', 'bastidores' => 'bastidor',
            'estilo'   => 'estilo',   'estilo de vida' => 'estilo',
            'convite'  => 'convite',  'promocional' => 'convite', 'comercial' => 'convite',
        ];
        return $map[$t] ?? $t;
    }

    private static function normalizeIntensity(string $i): string
    {
        $i = mb_strtolower(trim($i));
        if ($i === '') return 'suave';
        if (in_array($i, ['suave', 'média', 'media', 'firme'], true)) {
            return $i === 'media' ? 'média' : $i;
        }
        return 'suave';
    }

    public static function parseJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $text, $m)) {
            $text = $m[1];
        } elseif (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
