<?php
// public/ajax_players.php
// Devuelve JSON con players de un team_id dado.
// Requiere sesión/usuario logueado.

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';

// Validación básica de acceso: requiere sesión (ya hace require_login)
if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
if ($team_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'team_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, number, first_name, last_name FROM players WHERE team_id = ? ORDER BY (number+0) ASC, number ASC, last_name ASC");
    $stmt->execute([$team_id]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $p) {
        $label = trim( (isset($p['number']) && $p['number'] !== '') ? ($p['number'] . ' - ') : '' );
        $name = trim( trim(($p['last_name'] ?? '') . ' ' . ($p['first_name'] ?? '')) );
        if ($name !== '') $label .= $name;
        $out[] = ['id' => (int)$p['id'], 'label' => $label];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
    exit;
}