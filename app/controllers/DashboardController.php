<?php

class DashboardController
{
    public function index(): void
    {
        require_auth();

        $uid = Session::userId();

        $stats = [
            'clients'         => (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM clients WHERE user_id = ? AND status = ?',
                [$uid, 'active']
            ),
            'monthly_plans'   => (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM monthly_plans WHERE user_id = ?',
                [$uid]
            ),
            'email_drafts'    => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM email_drafts d
                 JOIN clients c ON c.id = d.client_id
                 WHERE c.user_id = ?",
                [$uid]
            ),
        ];

        $clients = Database::fetchAll(
            'SELECT id, name, segment, created_at
             FROM clients
             WHERE user_id = ? AND status = ?
             ORDER BY updated_at DESC
             LIMIT 5',
            [$uid, 'active']
        );

        View::render('dashboard/index', [
            'pageTitle' => 'Painel — ' . APP_NAME,
            'stats'     => $stats,
            'clients'   => $clients,
        ], 'app');
    }
}
