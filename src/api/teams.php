<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

// GET params: season, category
$season = isset($_GET['season']) ? (int)$_GET['season'] : 0;
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

if (!$season || !$category) {
    http_response_code(400);
    echo json_encode(['error' => 'season and category query params required']);
    exit;
}

try {
    $sql = "
      SELECT t.id, t.name, t.short_name, t.slug
      FROM teams t
      JOIN team_participations p ON p.team_id = t.id
      WHERE p.season_id = ? AND p.category_id = ?
      ORDER BY t.name
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param('ii', $season, $category);
    $stmt->execute();

    // get_result requires mysqlnd; if no get_result, fallback to bind_result loop
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt->bind_result($id, $name, $short_name, $slug);
        $rows = [];
        while ($stmt->fetch()) {
            $rows[] = ['id' => $id, 'name' => $name, 'short_name' => $short_name, 'slug' => $slug];
        }
    }

    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db error', 'message' => $e->getMessage()]);
}