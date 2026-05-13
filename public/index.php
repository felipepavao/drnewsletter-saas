<?php

require_once __DIR__ . '/../bootstrap.php';

if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

// --- Landing / Auth ---
$router->get('/',  'HomeController', 'index');
$router->post('/', 'AuthController', 'requestCode');

$router->get('/verify',  'AuthController', 'showVerify');
$router->post('/verify', 'AuthController', 'verifyCode');

$router->get('/sair', 'AuthController', 'logout');

// --- Dashboard ---
$router->get('/painel', 'DashboardController', 'index');

// --- Clientes ---
$router->get('/clientes',                  'ClientsController', 'index');
$router->get('/clientes/novo',             'ClientsController', 'new');
$router->post('/clientes',                 'ClientsController', 'create');
$router->get('/clientes/{id}',             'ClientsController', 'show');
$router->get('/clientes/{id}/editar',      'ClientsController', 'edit');
$router->post('/clientes/{id}',            'ClientsController', 'update');
$router->post('/clientes/{id}/excluir',    'ClientsController', 'delete');

// --- Voz da Marca ---
$router->get('/clientes/{id}/voz',         'BrandManualController', 'show');
$router->post('/clientes/{id}/voz',        'BrandManualController', 'upload');

// --- Arquivo de Emails ---
$router->get('/clientes/{id}/arquivo',     'EmailArchiveController', 'index');
$router->post('/clientes/{id}/arquivo',    'EmailArchiveController', 'upload');
$router->post('/clientes/{id}/arquivo/{archiveId}/excluir', 'EmailArchiveController', 'delete');

// --- Planejador Mensal ---
$router->get('/clientes/{id}/planejador',       'PlannerController', 'index');
$router->post('/clientes/{id}/planejador',      'PlannerController', 'generate');
$router->get('/planos/{planId}',                'PlannerController', 'show');
$router->post('/planos/{planId}/aprovar',       'PlannerController', 'approve');
$router->get('/planos/{planId}/export',         'PlannerController', 'exportTxt');

// --- Temas avulsos ---
$router->post('/clientes/{id}/temas',           'ThemesController', 'generate');

// --- Escritor de Emails ---
$router->get('/drafts/{draftId}',                'WriterController', 'show');
$router->post('/planos/{planId}/temas/{themeIndex}/escrever', 'WriterController', 'create');
$router->post('/drafts/{draftId}/mensagem',      'WriterController', 'message');
$router->post('/drafts/{draftId}/aprovar',       'WriterController', 'approve');
$router->get('/drafts/{draftId}/export',         'WriterController', 'exportTxt');

// --- Ajuda ---
$router->get('/ajuda', 'PageController', 'help');

$router->dispatch(Request::method(), Request::uri());
