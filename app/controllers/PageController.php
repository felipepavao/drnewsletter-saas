<?php

class PageController
{
    public function help(): void
    {
        require_auth();
        View::render('pages/help', [
            'pageTitle' => 'Ajuda — ' . APP_NAME,
        ], 'app');
    }
}
