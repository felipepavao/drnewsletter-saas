<?php

class WriterController
{
    /** POST /planos/{planId}/temas/{themeIndex}/escrever — cria draft e dispara IA */
    public function create(string $planId, string $themeIndex): void
    {
        require_auth();
        Csrf::verify();
        $uid = (int) Session::userId();

        if (!RateLimiter::hit("ai:user:{$uid}", RATE_LIMIT_AI_USER_DAY, 24 * 3600)) {
            Flash::error('Limite diário de gerações de IA atingido.');
            redirect('/painel');
        }

        $plan = MonthlyPlan::findForUser($uid, (int) $planId);
        if (!$plan) {
            Flash::error('Planejamento não encontrado.');
            redirect('/clientes');
        }
        $client = Client::findForUser($uid, (int) $plan['client_id']);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }

        try {
            $draftId = WriterService::startFromTheme($uid, $client, (int) $planId, (int) $themeIndex);
        } catch (Throwable $e) {
            Log::error('writer start failed', [
                'plan_id' => $planId,
                'theme'   => $themeIndex,
                'error'   => $e->getMessage(),
            ]);
            Flash::error($e->getMessage());
            redirect('/planos/' . (int) $planId);
        }

        Flash::success('Rascunho criado.');
        redirect('/drafts/' . $draftId);
    }

    /** GET /drafts/{draftId} */
    public function show(string $draftId): void
    {
        require_auth();
        $uid = (int) Session::userId();
        $draft = EmailDraft::findForUser($uid, (int) $draftId);
        if (!$draft) {
            Flash::error('Rascunho não encontrado.');
            redirect('/clientes');
        }

        $chatId = WriterChat::getOrCreateForDraft((int) $draft['id']);
        $messages = WriterChat::messagesForChat($chatId);

        View::render('writer/show', [
            'pageTitle' => 'Escrever email — ' . $draft['client_name'],
            'draft'     => $draft,
            'messages'  => $messages,
        ], 'app');
    }

    /** POST /drafts/{draftId}/mensagem — feedback iterativo */
    public function message(string $draftId): void
    {
        require_auth();
        Csrf::verify();
        $uid = (int) Session::userId();

        if (!RateLimiter::hit("ai:user:{$uid}", RATE_LIMIT_AI_USER_DAY, 24 * 3600)) {
            Flash::error('Limite diário de gerações de IA atingido.');
            redirect('/drafts/' . (int) $draftId);
        }

        $draft = EmailDraft::findForUser($uid, (int) $draftId);
        if (!$draft) {
            Flash::error('Rascunho não encontrado.');
            redirect('/clientes');
        }

        $content = trim((string) Request::post('content'));
        if ($content === '' || mb_strlen($content) > 4000) {
            Flash::error('Mensagem inválida (1–4000 caracteres).');
            redirect('/drafts/' . (int) $draftId);
        }

        try {
            WriterService::reply($uid, $draft, $content);
        } catch (Throwable $e) {
            Log::error('writer reply failed', ['draft_id' => $draftId, 'error' => $e->getMessage()]);
            Flash::error($e->getMessage());
            redirect('/drafts/' . (int) $draftId);
        }

        redirect('/drafts/' . (int) $draftId);
    }

    /** POST /drafts/{draftId}/aprovar */
    public function approve(string $draftId): void
    {
        require_auth();
        Csrf::verify();
        $uid = (int) Session::userId();
        $draft = EmailDraft::findForUser($uid, (int) $draftId);
        if (!$draft) {
            Flash::error('Rascunho não encontrado.');
            redirect('/clientes');
        }
        EmailDraft::approve($uid, (int) $draftId);
        Flash::success('Email aprovado.');
        redirect('/drafts/' . (int) $draftId);
    }

    /** GET /drafts/{draftId}/export */
    public function exportTxt(string $draftId): void
    {
        require_auth();
        $uid = (int) Session::userId();
        $draft = EmailDraft::findForUser($uid, (int) $draftId);
        if (!$draft) {
            Flash::error('Rascunho não encontrado.');
            redirect('/clientes');
        }

        $subject = $draft['subject']      ?? '';
        $preview = $draft['preview_text'] ?? '';
        $body    = $draft['body_text']    ?? '';

        $out  = "Cliente: {$draft['client_name']}\n";
        $out .= "Status: {$draft['status']} (v{$draft['version']})\n";
        $out .= str_repeat('=', 60) . "\n\n";
        if ($subject) $out .= "SUBJECT:\n{$subject}\n\n";
        if ($preview) $out .= "PREVIEW:\n{$preview}\n\n";
        if ($body)    $out .= "BODY:\n{$body}\n";

        $filename = sprintf('email_%d_v%d.txt', $draft['id'], $draft['version']);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $out;
    }
}
