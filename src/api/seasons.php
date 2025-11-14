<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php'; // expone $mysqli

try {
    $sql = "SELECT id, name, start_date, end_date FROM seasons ORDER BY start_date DESC, name";
    if (!($res = $mysqli->query($sql))) {
        throw new Exception($mysqli->error);
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db error', 'message' => $e->getMessage()]);
}