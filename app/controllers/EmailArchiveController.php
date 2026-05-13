<?php

class EmailArchiveController
{
    /** GET /clientes/{id}/arquivo */
    public function index(string $id): void
    {
        require_auth();
        $client = $this->ensureOwnership((int) $id);

        $archives = EmailArchive::listForClient((int) $client['id']);

        View::render('clients/archive/index', [
            'pageTitle' => 'Arquivo de Emails — ' . $client['name'],
            'client'    => $client,
            'archives'  => $archives,
            'max'       => EmailArchive::MAX_PER_CLIENT,
        ], 'app');
    }

    /** POST /clientes/{id}/arquivo */
    public function upload(string $id): void
    {
        require_auth();
        Csrf::verify();
        $client = $this->ensureOwnership((int) $id);
        $uid = (int) Session::userId();

        if (!RateLimiter::hit("upload:user:{$uid}", RATE_LIMIT_UPLOAD_USER)) {
            Flash::error('Muitos uploads recentes. Aguarde alguns minutos.');
            redirect('/clientes/' . $client['id'] . '/arquivo');
        }

        $current = EmailArchive::countForClient((int) $client['id']);
        if ($current >= EmailArchive::MAX_PER_CLIENT) {
            Flash::error(sprintf(
                'Limite de %d arquivos atingido. Exclua algum antes de subir mais.',
                EmailArchive::MAX_PER_CLIENT
            ));
            redirect('/clientes/' . $client['id'] . '/arquivo');
        }

        try {
            $file = Upload::readTextFile($_FILES['file'] ?? []);
        } catch (InvalidArgumentException $e) {
            Flash::error($e->getMessage());
            redirect('/clientes/' . $client['id'] . '/arquivo');
        }

        EmailArchive::create(
            (int) $client['id'],
            $file['filename'],
            $file['content'],
            $file['bytes']
        );

        Flash::success('Arquivo adicionado.');
        redirect('/clientes/' . $client['id'] . '/arquivo');
    }

    /** POST /clientes/{id}/arquivo/{archiveId}/excluir */
    public function delete(string $id, string $archiveId): void
    {
        require_auth();
        Csrf::verify();
        $client = $this->ensureOwnership((int) $id);

        $archive = EmailArchive::findForClient((int) $client['id'], (int) $archiveId);
        if (!$archive) {
            Flash::error('Arquivo não encontrado.');
            redirect('/clientes/' . $client['id'] . '/arquivo');
        }

        EmailArchive::delete((int) $client['id'], (int) $archiveId);
        Flash::info('Arquivo removido.');
        redirect('/clientes/' . $client['id'] . '/arquivo');
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
