<?php

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $pattern, string $controller, string $action, array $middleware = []): void
    {
        $this->add('GET', $pattern, $controller, $action, $middleware);
    }

    public function post(string $pattern, string $controller, string $action, array $middleware = []): void
    {
        $this->add('POST', $pattern, $controller, $action, $middleware);
    }

    private function add(string $method, string $pattern, string $controller, string $action, array $mw): void
    {
        $this->routes[] = compact('method', 'pattern', 'controller', 'action') + ['middleware' => $mw];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = $this->match($route['pattern'], $uri);
            if ($params === false) continue;

            foreach ($route['middleware'] as $mw) {
                if (is_callable($mw)) {
                    $mw();
                } elseif (is_string($mw) && function_exists($mw)) {
                    $mw();
                }
            }

            $class = $route['controller'];
            if (!class_exists($class)) {
                $this->fail(500, "Controller {$class} não encontrado");
                return;
            }

            $controller = new $class();
            $action = $route['action'];

            if (!method_exists($controller, $action)) {
                $this->fail(500, "Ação {$action} não encontrada em {$class}");
                return;
            }

            call_user_func_array([$controller, $action], $params);
            return;
        }

        $this->fail(404, 'Página não encontrada');
    }

    private function match(string $pattern, string $uri): array|false
    {
        $pattern = '/' . trim($pattern, '/');
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) return false;

        $params = [];
        foreach ($matches as $k => $v) {
            if (is_string($k)) $params[] = $v;
        }
        return $params;
    }

    private function fail(int $code, string $message): void
    {
        http_response_code($code);
        if ($code === 404 && file_exists(VIEWS_PATH . '/errors/404.php')) {
            View::render('errors/404', ['message' => $message], 'public');
        } else {
            echo View::escape($message);
        }
    }
}
