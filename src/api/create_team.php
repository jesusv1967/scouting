<?php
// Crear equipo (admin). body JSON: { name, short_name?, slug? }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty(trim($input['name'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['error' => 'name required']);
    exit;
}

$name = trim($input['name']);
$short = isset($input['short_name']) ? trim($input['short_name']) : null;
$slug = isset($input['slug']) && $input['slug'] !== '' ? trim($input['slug']) : null;

if (!$slug) {
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    $slug = trim($slug, '-');
}

try {
    $sql = "INSERT INTO teams (name, short_name, slug) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param('sss', $name, $short, $slug);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception($stmt->error);
    $id = $mysqli->insert_id;

    $stmt = $mysqli->prepare("SELECT id, name, short_name, slug FROM teams WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $team = $result->fetch_assoc();

    http_response_code(201);
    echo json_encode($team);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db error', 'message' => $e->getMessage()]);
}