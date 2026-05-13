<?php

class HomeController
{
    /**
     * Landing. Se logado, vai pro painel; senão, mostra form de login.
     */
    public function index(): void
    {
        if (is_logged_in()) {
            redirect_after_auth();
            return;
        }
        View::render('auth/login', ['pageTitle' => 'Entrar — ' . APP_NAME], 'public');
    }
}
