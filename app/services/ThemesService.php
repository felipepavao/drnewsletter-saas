<?php

/**
 * Geração de temas avulsos para somar a um planejamento existente.
 */
class ThemesService
{
    public static function generateAndAppend(
        int $userId,
        array $client,
        int $planId,
        int $count,
        string $extraContext
    ): int {
        $plan = MonthlyPlan::findForUser($userId, $planId);
        if (!$plan) {
            throw new RuntimeException('Planejamento não encontrado.');
        }
        if ((int) $plan['client_id'] !== (int) $client['id']) {
            throw new RuntimeException('Planejamento não pertence a este cliente.');
        }

        $brand = BrandManual::activeForClient((int) $client['id']);
        if (!$brand) {
            throw new RuntimeException('Cadastre a Voz da Marca antes.');
        }

        $brandContext = PlannerService::formatBrand($brand);
        $existingThemes = self::formatExistingThemes($plan['themes'] ?? []);

        $system = Prompts::themesSystem();
        $user   = Prompts::themesUser($client['name'], $count, $extraContext, $brandContext, $existingThemes);

        $result = Claude::complete(
            [['role' => 'user', 'content' => $user]],
            $system,
            [
                'user_id'   => $userId,
                'client_id' => (int) $client['id'],
                'purpose'   => 'theme',
            ]
        );

        $parsed = PlannerService::parseJson($result['text']);
        if (!$parsed || empty($parsed['themes']) || !is_array($parsed['themes'])) {
            throw new RuntimeException('A IA não devolveu temas válidos.');
        }

        // Anexa aos temas existentes
        $existing = $plan['themes'] ?? [];
        $new = [];
        foreach ($parsed['themes'] as $t) {
            if (!is_array($t)) continue;
            $new[] = [
                'title'         => (string) ($t['title'] ?? ''),
                'type'          => PlannerService::normalizeType((string) ($t['type'] ?? '')),
                'goal'          => (string) ($t['goal'] ?? ''),
                'hook'          => (string) ($t['hook'] ?? ''),
                'cta'           => (string) ($t['cta'] ?? ''),
                'cta_intensity' => PlannerService::normalizeIntensity((string) ($t['cta_intensity'] ?? '')),
            ];
        }

        $combined = array_merge($existing, $new);
        MonthlyPlan::updateThemes($userId, $planId, $combined);

        return count($new);
    }

    private static function formatExistingThemes(array $themes): string
    {
        $out = [];
        foreach ($themes as $i => $t) {
            $n = $i + 1;
            $out[] = "{$n}. [{$t['type']}] {$t['title']}";
        }
        return implode("\n", $out);
    }
}


