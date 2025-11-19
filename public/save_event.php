<?php
// public/save_event.php — Guarda eventos de scouting en vivo
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$match_id = filter_var($input['match_id'] ?? null, FILTER_VALIDATE_INT);
$team = in_array($input['team'] ?? '', ['home', 'away']) ? $input['team'] : null;
$dorsal = trim($input['dorsal'] ?? '');
$event_type = trim($input['action'] ?? '');

// Validación
if (!$match_id || !$team || !$dorsal || !$event_type) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos obligatorios']);
    exit;
}

if (!csrf_check($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

// Verificar que el partido exista
$stmt = $pdo->prepare("SELECT id FROM matches WHERE id = ?");
$stmt->execute([$match_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Partido no encontrado']);
    exit;
}

// Guardar evento
try {
    $stmt = $pdo->prepare("INSERT INTO match_events (match_id, team, dorsal, event_type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$match_id, $team, $dorsal, $event_type]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
}