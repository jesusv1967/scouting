<?php
// public/save_observation.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

header('Content-Type: application/json');

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leer JSON
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Validar campos
$match_id = (int)($data['match_id'] ?? 0);
$team = in_array($data['team'] ?? '', ['home', 'away']) ? $data['team'] : null;
$dorsal = trim($data['dorsal'] ?? '');
$category = trim($data['category'] ?? '');
$value = trim($data['value'] ?? '');

if (!$match_id || !$team || !$dorsal || !$category || $value === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos: match_id, team, dorsal, category, value son obligatorios']);
    exit;
}

// Validar CSRF
if (!csrf_check($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

// Verificar que el partido existe
$stmt = $pdo->prepare("SELECT id FROM matches WHERE id = ?");
$stmt->execute([$match_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Partido no encontrado']);
    exit;
}

// --- ¡IMPORTANTE! ---
// NO eliminamos duplicados aquí. 
// Cada clic debe crear un registro, y el frontend se encarga de borrar el anterior si es necesario.
// (Esto es esencial para categorías "únicas" como mano o interior)

try {
    $stmt = $pdo->prepare("
        INSERT INTO match_player_observations (match_id, team, dorsal, category, value)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$match_id, $team, $dorsal, $category, $value]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log("save_observation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
}