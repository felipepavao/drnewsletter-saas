<?php

class Response
{
    public static function redirect(string $path, int $code = 302): void
    {
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $path = APP_URL . '/' . ltrim($path, '/');
        }
        header('Location: ' . $path, true, $code);
        exit;
    }

    public static function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref && parse_url($ref, PHP_URL_HOST) === parse_url(APP_URL, PHP_URL_HOST)) {
            self::redirect($ref);
        }
        self::redirect($fallback);
    }

    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $code = 400): void
    {
        self::json(['error' => $message], $code);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
