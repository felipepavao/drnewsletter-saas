<?php

// =============================================================
//  helpers globais — funções utilitárias usadas em controllers e views
//  Mantenha enxuto. Lógica de domínio vai em service/model, não aqui.
// =============================================================

// --- Escape / HTML ---

function e($v): string {
    if ($v === null) return '';
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// --- URLs ---

function url(string $path = ''): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function is_current(string $path): bool {
    return rtrim(Request::uri(), '/') === rtrim('/' . ltrim($path, '/'), '/');
}

function redirect(string $path, int $code = 302): void {
    // Em testes, redirect lança exceção capturada pelo runner. Mantenho
    // a semântica de "sai do request" sem matar o processo PHP.
    if (defined('APP_ENV') && APP_ENV === 'test' && class_exists('RedirectException', false)) {
        throw new RedirectException($path, $code);
    }
    Response::redirect($path, $code);
}

// --- Validações simples ---

function normalize_email(string $email): string {
    return strtolower(trim($email));
}

function is_valid_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// --- Random ---

function uuid4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function random_code(int $digits = 6): string {
    $min = (int) str_pad('1', $digits, '0');
    $max = (int) str_pad('9', $digits, '9');
    return (string) random_int($min, $max);
}

// --- Auth helpers ---

function current_user(): ?array {
    $uid = Session::userId();
    if (!$uid) return null;
    return Database::fetch(
        "SELECT * FROM users WHERE id = ? AND status != 'deleted'",
        [$uid]
    );
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && (int) $u['is_admin'] === 1;
}

/**
 * Exige usuário autenticado. Se não estiver, redireciona pra /.
 * Use no início de toda action protegida.
 */
function require_auth(): void {
    if (!is_logged_in()) {
        Flash::info('Entre para continuar.');
        redirect('/');
    }
    // Status suspenso → derruba sessão.
    $u = current_user();
    if ($u && $u['status'] === 'suspended') {
        Session::destroy();
        Flash::error('Sua conta está suspensa. Entre em contato com a equipe.');
        redirect('/');
    }
}

function require_admin(): void {
    require_auth();
    if (!is_admin()) {
        Flash::error('Acesso restrito.');
        redirect('/painel');
    }
}

/** Para onde o usuário vai após autenticar. */
function redirect_after_auth(): void {
    redirect('/painel');
}

// --- Form helpers ---

function old(string $key, $default = '', array $data = []): string {
    $v = $data[$key] ?? $_POST[$key] ?? $default;
    return is_scalar($v) ? (string) $v : $default;
}

function checked(bool $cond): string {
    return $cond ? 'checked' : '';
}

function selected(bool $cond): string {
    return $cond ? 'selected' : '';
}
