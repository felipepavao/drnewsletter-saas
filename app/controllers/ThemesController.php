<?php

class ThemesController
{
    /**
     * POST /clientes/{id}/temas
     * Espera plan_id, count, extra_context no body.
     */
    public function generate(string $id): void
    {
        require_auth();
        Csrf::verify();

        $uid = (int) Session::userId();
        $client = Client::findForUser($uid, (int) $id);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }

        if (!RateLimiter::hit("ai:user:{$uid}", RATE_LIMIT_AI_USER_DAY, 24 * 3600)) {
            Flash::error('Limite diário de gerações de IA atingido.');
            redirect('/clientes/' . $client['id']);
        }

        $planId = (int) Request::post('plan_id');
        $count  = (int) Request::post('count');
        $extra  = trim((string) Request::post('extra_context', ''));

        if ($planId < 1)              { Flash::error('Plano inválido.');        redirect('/clientes/' . $client['id']); }
        if ($count < 1 || $count > 10){ Flash::error('Quantidade entre 1 e 10.'); redirect('/planos/' . $planId); }
        if (mb_strlen($extra) > 2000) { Flash::error('Contexto muito longo.');  redirect('/planos/' . $planId); }

        try {
            $added = ThemesService::generateAndAppend($uid, $client, $planId, $count, $extra);
        } catch (Throwable $e) {
            Log::error('themes generate failed', [
                'client_id' => $client['id'],
                'plan_id'   => $planId,
                'error'     => $e->getMessage(),
            ]);
            Flash::error($e->getMessage());
            redirect('/planos/' . $planId);
        }

        Flash::success("Adicionados {$added} tema(s) ao planejamento.");
        redirect('/planos/' . $planId);
    }
}
