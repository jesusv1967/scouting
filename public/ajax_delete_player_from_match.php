<?php
// public/ajax_delete_player_from_match.php
// Elimina un jugador del partido (match_players), no de la base global de jugadores, salvo que sea "anónimo"
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

$match_id = filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT);
$dorsal = trim($_POST['dorsal'] ?? '');
$team = in_array($_POST['team'] ?? '', ['home', 'away']) ? $_POST['team'] : null;

if (!$match_id || !$dorsal || !$team) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    exit;
}

try {
    // 1. Obtener ID del jugador por dorsal y equipo en este partido
    $team_id_field = $team === 'home' ? 'home_team_id' : 'away_team_id';
    $stmt = $pdo->prepare("
        SELECT p.id, p.first_name, p.last_name, mp.id AS mp_id
        FROM match_players mp
        LEFT JOIN players p ON mp.player_id = p.id
        LEFT JOIN matches m ON mp.match_id = m.id
        WHERE mp.match_id = ? AND p.number = ? AND (
            (m.home_team_id = p.team_id AND ? = 'home') OR
            (m.away_team_id = p.team_id AND ? = 'away')
        )
        LIMIT 1
    ");
    $stmt->execute([$match_id, $dorsal, $team, $team]);
    $player = $stmt->fetch();

    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Jugador no encontrado en el partido']);
        exit;
    }

    $player_id = (int)$player['id'];
    $mp_id = (int)$player['mp_id'];

    // 2. Eliminar de match_players
    $stmt = $pdo->prepare("DELETE FROM match_players WHERE id = ?");
    $stmt->execute([$mp_id]);

    // 3. (Opcional) Si el jugador no tiene nombre y solo existe por este dorsal, eliminarlo también de players
    $is_anonymous = empty($player['first_name']) && empty($player['last_name']);
    if ($is_anonymous) {
        // Verificar que no esté en otro partido
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE player_id = ? AND match_id != ?");
        $stmt->execute([$player_id, $match_id]);
        if ($stmt->fetchColumn() == 0) {
            // No está en otros partidos → borrar de players
            $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
            $stmt->execute([$player_id]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error al eliminar jugador del partido: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al eliminar jugador']);
}