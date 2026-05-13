<?php

class ClientsController
{
    /** GET /clientes */
    public function index(): void
    {
        require_auth();
        $uid = Session::userId();
        $clients = Client::listForUser($uid);
        View::render('clients/index', [
            'pageTitle' => 'Clientes — ' . APP_NAME,
            'clients'   => $clients,
        ], 'app');
    }

    /** GET /clientes/novo */
    public function new(): void
    {
        require_auth();
        View::render('clients/form', [
            'pageTitle' => 'Novo cliente — ' . APP_NAME,
            'client'    => null,
            'errors'    => [],
            'data'      => ['name' => '', 'email' => '', 'segment' => '', 'notes' => ''],
        ], 'app');
    }

    /** POST /clientes */
    public function create(): void
    {
        require_auth();
        Csrf::verify();

        [$data, $errors] = Client::validate(Request::all());

        if ($errors) {
            View::render('clients/form', [
                'pageTitle' => 'Novo cliente — ' . APP_NAME,
                'client'    => null,
                'errors'    => $errors,
                'data'      => $data,
            ], 'app');
            return;
        }

        $clientId = Client::create((int) Session::userId(), $data);
        Flash::success('Cliente criado.');
        redirect('/clientes/' . $clientId);
    }

    /** GET /clientes/{id} */
    public function show(string $id): void
    {
        require_auth();
        $client = Client::findForUser((int) Session::userId(), (int) $id);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }

        // Indicadores de configuração — voz da marca e arquivo de emails
        $hasVoice = (bool) Database::fetchColumn(
            'SELECT 1 FROM brand_manuals WHERE client_id = ? AND is_active = 1',
            [$client['id']]
        );
        $archiveCount = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM email_archives WHERE client_id = ?',
            [$client['id']]
        );
        $planCount = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM monthly_plans WHERE client_id = ?',
            [$client['id']]
        );

        $recentPlans = Database::fetchAll(
            'SELECT id, year, month, email_count, status, created_at
               FROM monthly_plans
              WHERE client_id = ?
              ORDER BY year DESC, month DESC
              LIMIT 5',
            [$client['id']]
        );

        View::render('clients/show', [
            'pageTitle'    => $client['name'] . ' — ' . APP_NAME,
            'client'       => $client,
            'hasVoice'     => $hasVoice,
            'archiveCount' => $archiveCount,
            'planCount'    => $planCount,
            'recentPlans'  => $recentPlans,
        ], 'app');
    }

    /** GET /clientes/{id}/editar */
    public function edit(string $id): void
    {
        require_auth();
        $client = Client::findForUser((int) Session::userId(), (int) $id);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }
        View::render('clients/form', [
            'pageTitle' => 'Editar ' . $client['name'] . ' — ' . APP_NAME,
            'client'    => $client,
            'errors'    => [],
            'data'      => [
                'name'    => $client['name'],
                'email'   => $client['email']   ?? '',
                'segment' => $client['segment'] ?? '',
                'notes'   => $client['notes']   ?? '',
            ],
        ], 'app');
    }

    /** POST /clientes/{id} */
    public function update(string $id): void
    {
        require_auth();
        Csrf::verify();

        $uid = (int) Session::userId();
        $cid = (int) $id;

        $client = Client::findForUser($uid, $cid);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }

        [$data, $errors] = Client::validate(Request::all());

        if ($errors) {
            View::render('clients/form', [
                'pageTitle' => 'Editar ' . $client['name'] . ' — ' . APP_NAME,
                'client'    => $client,
                'errors'    => $errors,
                'data'      => $data,
            ], 'app');
            return;
        }

        Client::update($uid, $cid, $data);
        Flash::success('Cliente atualizado.');
        redirect('/clientes/' . $cid);
    }

    /** POST /clientes/{id}/excluir — arquiva (soft delete). */
    public function delete(string $id): void
    {
        require_auth();
        Csrf::verify();

        $uid = (int) Session::userId();
        $cid = (int) $id;

        $client = Client::findForUser($uid, $cid);
        if (!$client) {
            Flash::error('Cliente não encontrado.');
            redirect('/clientes');
        }

        Client::archive($uid, $cid);
        Flash::info('Cliente arquivado.');
        redirect('/clientes');
    }
}
