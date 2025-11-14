<?php
// src/helpers.php
// Helpers varios: sesión segura, CSRF, flash messages, login check.
// NOTA: ya no arrancamos session_start() inmediatamente aquí; use secure_session_start().

function secure_session_start($config = []) {
    $secure = $config['session_cookie_secure'] ?? false;
    $httponly = true;
    ini_set('session.use_only_cookies', 1);
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . (function_exists('url') ? url('login.php') : '/login.php'));
        exit;
    }
}

function current_user_display() {
    return $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
}

// CSRF token helpers
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

function flash_set($key, $msg) {
    $_SESSION['flash'][$key] = $msg;
}

function flash_get($key) {
    $v = $_SESSION['flash'][$key] ?? null;
    if (isset($_SESSION['flash'][$key])) {
        unset($_SESSION['flash'][$key]);
    }
    return $v;
}