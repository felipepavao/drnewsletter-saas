<?php

class BrandManualController
{
    /** GET /clientes/{id}/voz */
    public function show(string $id): void
    {
        require_auth();
        $client = $this->ensureOwnership((int) $id);

        $current = BrandManual::activeForClient((int) $client['id']);
        $history = BrandManual::historyForClient((int) $client['id']);

        View::render('clients/voice/show', [
            'pageTitle' => 'Voz da Marca — ' . $client['name'],
            'client'    => $client,
            'current'   => $current,
            'history'   => $history,
        ], 'app');
    }

    /** POST /clientes/{id}/voz */
    public function upload(string $id): void
    {
        require_auth();
        Csrf::verify();
        $client = $this->ensureOwnership((int) $id);
        $uid = (int) Session::userId();

        // Rate limit de upload por usuário
        if (!RateLimiter::hit("upload:user:{$uid}", RATE_LIMIT_UPLOAD_USER)) {
            Flash::error('Muitos uploads recentes. Aguarde alguns minutos.');
            redirect('/clientes/' . $client['id'] . '/voz');
        }

        // Lê e valida o arquivo
        try {
            $file = Upload::readTextFile($_FILES['file'] ?? []);
        } catch (InvalidArgumentException $e) {
            Flash::error($e->getMessage());
            redirect('/clientes/' . $client['id'] . '/voz');
        }

        // Chama Claude para estruturar
        try {
            $result = Claude::complete(
                [['role' => 'user', 'content' => Prompts::parseBrandVoiceUser($file['content'])]],
                Prompts::parseBrandVoiceSystem(),
                [
                    'user_id'   => $uid,
                    'client_id' => (int) $client['id'],
                    'purpose'   => 'parse_brand_manual',
                ]
            );
        } catch (Throwable $e) {
            Log::error('Claude parse_brand_manual failed', [
                'client_id' => $client['id'],
                'error'     => $e->getMessage(),
            ]);
            Flash::error('Não consegui processar agora: ' . $e->getMessage());
            redirect('/clientes/' . $client['id'] . '/voz');
        }

        // Parseia o JSON
        $parsed = self::parseJsonResponse($result['text']);
        if (!$parsed) {
            Log::warn('Claude returned non-JSON for parse_brand_manual', [
                'client_id'   => $client['id'],
                'text_sample' => mb_substr($result['text'], 0, 400),
            ]);
            Flash::error('A IA não devolveu um formato válido. Tente novamente.');
            redirect('/clientes/' . $client['id'] . '/voz');
        }

        BrandManual::createNewVersion(
            (int) $client['id'],
            $file['filename'],
            $file['content'],
            $parsed
        );

        Flash::success('Voz da Marca registrada (nova versão ativa).');
        redirect('/clientes/' . $client['id'] . '/voz');
    }

    /** Verifica que o cliente é do user, redireciona se não. */
    private function ensureOwnership(int $clientId): array
    {
        $client = Client::findForUser((int) Session::userId(), $clientId);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }
        return $client;
    }

    /**
     * Tenta extrair um bloco JSON da resposta do modelo.
     * Aceita tanto JSON puro quanto JSON envolvido em ```json…```.
     */
    private static function parseJsonResponse(string $text): ?array
    {
        $text = trim($text);

        // Remove cercas de código se houver
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $text, $m)) {
            $text = $m[1];
        } elseif (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
