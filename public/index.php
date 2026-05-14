<?php

require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/app/lib/routes.php';

if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
register_routes($router);
$router->dispatch(Request::method(), Request::uri());
