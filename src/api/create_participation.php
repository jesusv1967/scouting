<?php
// Asociar equipo a temporada+categoría (body JSON: { team_id, category_id, season_id })
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['team_id']) || empty($input['category_id']) || empty($input['season_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'team_id, category_id and season_id required']);
    exit;
}

$team_id = (int)$input['team_id'];
$category_id = (int)$input['category_id'];
$season_id = (int)$input['season_id'];

try {
    // Usamos INSERT IGNORE; la migración crea la UNIQUE KEY para evitar duplicados
    $sql = "INSERT IGNORE INTO team_participations (team_id, category_id, season_id) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param('iii', $team_id, $category_id, $season_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        http_response_code(201);
        echo json_encode(['message' => 'created']);
    } else {
        echo json_encode(['message' => 'already_exists']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db error', 'message' => $e->getMessage()]);
}