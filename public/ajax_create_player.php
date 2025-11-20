<?php
// public/ajax_create_player.php
// Crea un jugador y lo asocia inmediatamente al partido (si se proporciona match_id)
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!csrf_check($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

$team_id = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
$number = trim($_POST['number'] ?? '');
$name = trim($_POST['name'] ?? '');
$match_id = filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT);

if (!$team_id || !$number) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos: equipo y número son obligatorios']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Crear o reutilizar jugador en `players`
    $player_id = null;

    // ¿Existe ya un jugador con ese dorsal en el equipo?
    $stmt = $pdo->prepare("SELECT id FROM players WHERE team_id = ? AND number = ?");
    $stmt->execute([$team_id, $number]);
    $existing = $stmt->fetch();
    if ($existing) {
        $player_id = (int)$existing['id'];
    } else {
        // Crear nuevo jugador
        $first = null; $last = null;
        if ($name) {
            $parts = preg_split('/\s+/', $name, 2);
            $first = $parts[0] ?? null;
            $last = $parts[1] ?? null;
        }
        $stmt = $pdo->prepare("INSERT INTO players (team_id, number, first_name, last_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$team_id, $number, $first, $last]);
        $player_id = (int)$pdo->lastInsertId();
    }

    // 2. Si se da match_id, asociarlo a match_players
    if ($match_id) {
        // Verificar que el partido existe y que el equipo pertenece a él
        $stmt = $pdo->prepare("SELECT home_team_id, away_team_id FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch();
        if (!$match) {
            throw new Exception('Partido no encontrado');
        }

        $is_home = ((int)$match['home_team_id'] === (int)$team_id);
        $is_away = ((int)$match['away_team_id'] === (int)$team_id);

        if (!$is_home && !$is_away) {
            throw new Exception('El equipo no pertenece a este partido');
        }

        // Evitar duplicados en match_players
        $stmt = $pdo->prepare("SELECT id FROM match_players WHERE match_id = ? AND player_id = ?");
        $stmt->execute([$match_id, $player_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO match_players (match_id, player_id, team_id, is_starter) VALUES (?, ?, ?, 0)");
            $stmt->execute([$match_id, $player_id, $team_id]);
        }
    }

    $pdo->commit();

    // Preparar etiqueta para UI
    $stmt = $pdo->prepare("SELECT number, first_name, last_name FROM players WHERE id = ?");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch();
    $label = trim(($player['number'] ? $player['number'] . ' - ' : '') .
                  ($player['last_name'] ? $player['last_name'] . ', ' : '') .
                  ($player['first_name'] ?? ''));

    echo json_encode([
        'success' => true,
        'player' => [
            'id' => $player_id,
            'label' => $label
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error en ajax_create_player: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al crear o asociar jugador']);
}