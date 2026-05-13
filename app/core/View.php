<?php

class View
{
    public static function render(string $view, array $data = [], string $layout = 'public'): void
    {
        $data['pageTitle'] = $data['pageTitle'] ?? APP_NAME;
        extract($data, EXTR_SKIP);

        $viewFile = VIEWS_PATH . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
        if (is_file($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function partial(string $partial, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = VIEWS_PATH . '/partials/' . $partial . '.php';
        if (is_file($file)) require $file;
    }

    public static function escape($value): string
    {
        if ($value === null) return '';
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        return APP_URL . '/assets/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        return APP_URL . '/' . ltrim($path, '/');
    }
}
