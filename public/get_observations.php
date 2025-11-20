<?php
// public/get_observations.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$match_id = filter_var($_GET['match_id'] ?? null, FILTER_VALIDATE_INT);
$team = in_array($_GET['team'] ?? '', ['home','away']) ? $_GET['team'] : null;
$dorsal = trim($_GET['dorsal'] ?? '');

if (!$match_id || !$team || !$dorsal) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT category, value
    FROM match_player_observations
    WHERE match_id = ? AND team = ? AND dorsal = ?
    ORDER BY created_at ASC
");
$stmt->execute([$match_id, $team, $dorsal]);
echo json_encode($stmt->fetchAll());