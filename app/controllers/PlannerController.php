<?php

class PlannerController
{
    /** GET /clientes/{id}/planejador */
    public function index(string $id): void
    {
        require_auth();
        $client = $this->ensureOwnership((int) $id);
        $hasVoice = (bool) Database::fetchColumn(
            'SELECT 1 FROM brand_manuals WHERE client_id = ? AND is_active = 1',
            [$client['id']]
        );
        $recent = MonthlyPlan::listForClient((int) $client['id'], 10);

        // Default: mês seguinte
        $defaultYear  = (int) date('Y');
        $defaultMonth = (int) date('n') + 1;
        if ($defaultMonth > 12) { $defaultMonth = 1; $defaultYear++; }

        View::render('planner/index', [
            'pageTitle'    => 'Planejador — ' . $client['name'],
            'client'       => $client,
            'hasVoice'     => $hasVoice,
            'recentPlans'  => $recent,
            'defaultYear'  => $defaultYear,
            'defaultMonth' => $defaultMonth,
        ], 'app');
    }

    /** POST /clientes/{id}/planejador */
    public function generate(string $id): void
    {
        require_auth();
        Csrf::verify();
        $client = $this->ensureOwnership((int) $id);
        $uid = (int) Session::userId();

        if (!RateLimiter::hit("ai:user:{$uid}", RATE_LIMIT_AI_USER_DAY, 24 * 3600)) {
            Flash::error('Limite diário de gerações de IA atingido. Tente amanhã.');
            redirect('/clientes/' . $client['id'] . '/planejador');
        }

        $year  = (int) Request::post('year');
        $month = (int) Request::post('month');
        $count = (int) Request::post('email_count');
        $extra = trim((string) Request::post('extra_context', ''));

        if ($year < 2024 || $year > 2030)            { Flash::error('Ano inválido.');            redirect('/clientes/' . $client['id'] . '/planejador'); }
        if ($month < 1 || $month > 12)               { Flash::error('Mês inválido.');             redirect('/clientes/' . $client['id'] . '/planejador'); }
        if ($count < 1 || $count > 31)               { Flash::error('Quantidade de emails fora do intervalo (1–31).'); redirect('/clientes/' . $client['id'] . '/planejador'); }
        if (mb_strlen($extra) > 2000)                { Flash::error('Contexto adicional muito longo.'); redirect('/clientes/' . $client['id'] . '/planejador'); }

        try {
            $planId = PlannerService::generate($uid, $client, $year, $month, $count, $extra);
        } catch (Throwable $e) {
            Log::error('planner generate failed', [
                'client_id' => $client['id'],
                'error'     => $e->getMessage(),
            ]);
            Flash::error($e->getMessage());
            redirect('/clientes/' . $client['id'] . '/planejador');
        }

        Flash::success('Planejamento gerado.');
        redirect('/planos/' . $planId);
    }

    /** GET /planos/{planId} */
    public function show(string $planId): void
    {
        require_auth();
        $uid  = (int) Session::userId();
        $plan = MonthlyPlan::findForUser($uid, (int) $planId);
        if (!$plan) {
            Flash::error('Planejamento não encontrado.');
            redirect('/clientes');
        }
        $client = Client::findForUser($uid, (int) $plan['client_id']);
        $parsed = $plan['themes_json'] ? json_decode($plan['themes_json'], true) : [];

        View::render('planner/show', [
            'pageTitle' => 'Plano ' . sprintf('%02d/%d', $plan['month'], $plan['year']) . ' — ' . APP_NAME,
            'plan'      => $plan,
            'client'    => $client,
            'parsed'    => $parsed,
        ], 'app');
    }

    /** POST /planos/{planId}/aprovar */
    public function approve(string $planId): void
    {
        require_auth();
        Csrf::verify();
        $uid = (int) Session::userId();
        $plan = MonthlyPlan::findForUser($uid, (int) $planId);
        if (!$plan) {
            Flash::error('Planejamento não encontrado.');
            redirect('/clientes');
        }
        MonthlyPlan::approve($uid, (int) $planId);
        Flash::success('Planejamento aprovado.');
        redirect('/planos/' . (int) $planId);
    }

    /** GET /planos/{planId}/export — texto puro */
    public function exportTxt(string $planId): void
    {
        require_auth();
        $uid  = (int) Session::userId();
        $plan = MonthlyPlan::findForUser($uid, (int) $planId);
        if (!$plan) {
            Flash::error('Planejamento não encontrado.');
            redirect('/clientes');
        }
        $client = Client::findForUser($uid, (int) $plan['client_id']);
        $parsed = $plan['themes_json'] ? json_decode($plan['themes_json'], true) : [];

        $monthName = Prompts::monthName((int) $plan['month']);
        $out  = "PLANEJAMENTO — {$client['name']}\n";
        $out .= "{$monthName} de {$plan['year']} · {$plan['email_count']} emails\n";
        $out .= str_repeat('=', 60) . "\n\n";

        if (!empty($parsed['title']))         $out .= "TÍTULO\n{$parsed['title']}\n\n";
        if (!empty($parsed['summary']))       $out .= "RESUMO\n{$parsed['summary']}\n\n";
        if (!empty($parsed['strategy']))      $out .= "ESTRATÉGIA\n{$parsed['strategy']}\n\n";
        if (!empty($parsed['month_context'])) $out .= "CONTEXTO DO MÊS\n{$parsed['month_context']}\n\n";

        $out .= str_repeat('-', 60) . "\nTEMAS\n" . str_repeat('-', 60) . "\n\n";
        foreach ($parsed['themes'] ?? [] as $i => $t) {
            $n = $i + 1;
            $type = MonthlyPlan::TYPES[$t['type']] ?? ($t['type'] ?? '');
            $out .= "#{$n}  [{$type}]  {$t['title']}\n";
            if (!empty($t['goal'])) $out .= "    Objetivo: {$t['goal']}\n";
            if (!empty($t['hook'])) $out .= "    Gancho:   {$t['hook']}\n";
            if (!empty($t['cta']))  $out .= "    CTA:      {$t['cta']} ({$t['cta_intensity']})\n";
            $out .= "\n";
        }

        $filename = sprintf(
            'plano_%s_%04d-%02d.txt',
            preg_replace('/[^a-zA-Z0-9]+/', '_', mb_strtolower($client['name'])),
            $plan['year'],
            $plan['month']
        );
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $out;
    }

    private function ensureOwnership(int $clientId): array
    {
        $client = Client::findForUser((int) Session::userId(), $clientId);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }
        return $client;
    }
}
