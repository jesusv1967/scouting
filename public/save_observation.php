<?php
// public/save_observation.php
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
$category = in_array($data['category'] ?? '', ['mano','ataque','defensa','nota']) ? $data['category'] : null;
$value = trim($data['value'] ?? '');

if (!$match_id || !$team || !$dorsal || !$category || $value === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos']);
    exit;
}

if (!csrf_check($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

// Evitar duplicados en ataque/defensa
if (in_array($category, ['ataque', 'defensa'])) {
    $check = $pdo->prepare("SELECT id FROM match_player_observations WHERE match_id = ? AND team = ? AND dorsal = ? AND category = ? AND value = ?");
    $check->execute([$match_id, $team, $dorsal, $category, $value]);
    if ($check->fetch()) {
        echo json_encode(['ok' => true, 'duplicate' => true]);
        exit;
    }
}

try {
   $stmt = $pdo->prepare("
        INSERT INTO match_player_observations (match_id, team, dorsal, category, value)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$match_id, $team, $dorsal, $category, $value]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
}