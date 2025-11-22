<?php
// public/toggle_starter.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$match_id = (int)($data['match_id'] ?? 0);
$team = in_array($data['team'] ?? '', ['home','away']) ? $data['team'] : null;
$dorsal = trim($data['dorsal'] ?? '');
$is_starter = filter_var($data['is_starter'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$match_id || !$team || !$dorsal) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos']);
    exit;
}

if (!csrf_check($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

try {
    // 1. Obtener player_id desde el dorsal y el partido
    $team_id_field = $team === 'home' ? 'home_team_id' : 'away_team_id';
    $stmt = $pdo->prepare("
        SELECT p.id, mp.id AS mp_id
        FROM players p
        JOIN match_players mp ON p.id = mp.player_id
        JOIN matches m ON mp.match_id = m.id
        WHERE m.id = ? AND p.number = ? AND (
            (m.home_team_id = p.team_id AND ? = 'home') OR
            (m.away_team_id = p.team_id AND ? = 'away')
        )
        LIMIT 1
    ");
    $stmt->execute([$match_id, $dorsal, $team, $team]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Jugador no encontrado en el partido']);
        exit;
    }

    // 2. Actualizar is_starter
    $stmt = $pdo->prepare("UPDATE match_players SET is_starter = ? WHERE id = ?");
    $stmt->execute([$is_starter ? 1 : 0, $row['mp_id']]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log("toggle_starter error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al actualizar']);
}