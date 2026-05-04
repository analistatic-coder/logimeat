<?php
declare(strict_types=1);

/**
 * Utilidades de seguridad: sesión, cabeceras HTTP, contraseñas y CSRF.
 */

function lm_seguridad_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return $fwd === 'https';
}

/**
 * Debe llamarse antes de session_start() (una vez por petición).
 */
function lm_seguridad_session_before_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = lm_seguridad_https();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }
}

function lm_seguridad_headers_enviar(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function lm_csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function lm_csrf_validar(?string $token): bool
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['_csrf'], $token);
}

function lm_csrf_field(): string
{
    $t = htmlspecialchars(lm_csrf_token(), ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}
