<?php
// src/auth.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function login_user($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, password_hash, display_name, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerar id de sesión
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function logout_user() {
    // Borrar sesión
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}