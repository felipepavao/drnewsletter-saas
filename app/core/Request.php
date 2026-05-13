<?php

class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '\\' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        return '/' . trim($uri, '/');
    }

    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function string(string $key, string $default = ''): string
    {
        $v = self::input($key, $default);
        if (is_array($v)) return $default;
        return trim((string) $v);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function array(string $key): array
    {
        $v = self::input($key, []);
        return is_array($v) ? $v : [];
    }

    public static function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public static function json(): ?array
    {
        $body = file_get_contents('php://input');
        if (!$body) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public static function ip(): string
    {
        if (TRUST_PROXY) {
            $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
            if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($xff) {
                foreach (array_map('trim', explode(',', $xff)) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
                }
            }

            $real = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if ($real && filter_var($real, FILTER_VALIDATE_IP)) return $real;
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return $remote && filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
        if (($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        if (TRUST_PROXY && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
        return false;
    }
}
