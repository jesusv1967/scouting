<?php
// public/get_starter_status.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$match_id = (int)($_GET['match_id'] ?? 0);
$team = in_array($_GET['team'] ?? '', ['home','away']) ? $_GET['team'] : null;
$dorsal = trim($_GET['dorsal'] ?? '');

header('Content-Type: application/json');

if (!$match_id || !$team || !$dorsal) {
    echo json_encode(['is_starter' => false]);
    exit;
}

try {
    $team_id_field = $team === 'home' ? 'home_team_id' : 'away_team_id';
    $stmt = $pdo->prepare("
        SELECT mp.is_starter
        FROM players p
        JOIN match_players mp ON p.id = mp.player_id
        JOIN matches m ON mp.match_id = m.id
        WHERE m.id = ? AND p.number = ? AND (
            (m.home_team_id = p.team_id AND ? = 'home') OR
            (m.away_team_id = p.team_id AND ? = 'away')
        )
        LIMIT 1
    ");
    $stmt->execute([$match_id, $dorsal, $team, $team]);
    $row = $stmt->fetch();

    echo json_encode(['is_starter' => (bool)($row ? $row['is_starter'] : false)]);
} catch (Exception $e) {
    echo json_encode(['is_starter' => false]);
}