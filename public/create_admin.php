<?php
// public/create_admin.php
// Web helper to create an admin user from the browser.
// Security: This script is intentionally conservative:
// - If src/config.php defines 'admin_creation_token', the token must be provided in the form.
// - Otherwise creation is allowed only if there are currently 0 users in the DB.
// After creating the admin delete this file or disable web creation (see README notes).

require_once __DIR__ . '/../src/config.php';
$config = require __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';

secure_session_start($config);

$errors = [];
$success = '';

/**
 * Determine whether web creation is allowed.
 * - If admin_creation_token is set in config, we allow but require the token on POST.
 * - If not set, allow only when users table is empty.
 */
$requires_token = !empty($config['admin_creation_token']);
$allowed = false;
$deny_reason = '';

try {
    $countStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users");
    $countRow = $countStmt->fetch();
    $userCount = (int) ($countRow['cnt'] ?? 0);
} catch (Exception $e) {
    // If users table doesn't exist or DB error: deny and show message.
    $userCount = null;
    $deny_reason = 'Error al comprobar usuarios en la base de datos. Asegúrate de haber importado sql/schema.sql.';
}

if ($userCount === null) {
    $allowed = false;
} else {
    if ($requires_token) {
        $allowed = true;
    } else {
        // Allow only when there are no users
        $allowed = ($userCount === 0);
        if (!$allowed) {
            $deny_reason = 'Ya existe al menos un usuario
