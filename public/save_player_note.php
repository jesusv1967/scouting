<?php
// public/save_player_note.php
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
$note_type = trim($data['note_type'] ?? '');
$note_value = trim($data['note_value'] ?? '');

if (!$match_id || !$team || !$dorsal || !$note_type || !$note_value) {
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
    $stmt = $pdo->prepare("INSERT INTO match_player_notes (match_id, player_dorsal, team, note_type, note_value) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$match_id, $dorsal, $team, $note_type, $note_value]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
}