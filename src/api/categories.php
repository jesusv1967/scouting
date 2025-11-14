<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

try {
    $sql = "SELECT id, name FROM categories ORDER BY name";
    if (!($res = $mysqli->query($sql))) {
        throw new Exception($mysqli->error);
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db error', 'message' => $e->getMessage()]);
}