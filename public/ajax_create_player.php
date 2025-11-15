<?php
// public/ajax_create_player.php
// Crea un jugador para un team_id (usado por el modal "Gestionar plantilla").
// Devuelve JSON con { success: true, player: { id, number, first_name, last_name, label } } o { success:false, error: '...' }

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
$number = trim((string)($_POST['number'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$csrf = $_POST['csrf_token'] ?? '';

if (!csrf_check($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

if ($team_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'team_id inválido.']);
    exit;
}

if ($name === '' && $number === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rellena al menos nombre o número.']);
    exit;
}

// separar nombre en first/last (primer espacio)
$first = null; $last = null;
if ($name !== '') {
    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0] ?? null;
    $last = $parts[1] ?? null;
}

try {
    // Si se ha puesto número, evitar duplicados en mismo equipo
    if ($number !== '') {
        $q = $pdo->prepare("SELECT id, first_name, last_name FROM players WHERE team_id = ? AND number = ? LIMIT 1");
        $q->execute([$team_id, $number]);
        $exists = $q->fetch();
        if ($exists) {
            // devolver el existente (no crear duplicado)
            $id = (int)$exists['id'];
            $label = trim(($number !== '' ? ($number . ' - ') : '') . ( ($exists['last_name'] ? $exists['last_name'] . ', ' : '') . ($exists['first_name'] ?? '') ));
            echo json_encode(['success' => true, 'player' => ['id' => $id, 'number' => $number, 'first_name' => $exists['first_name'], 'last_name' => $exists['last_name'], 'label' => $label]]);
            exit;
        }
    }

    $ins = $pdo->prepare("INSERT INTO players (team_id, number, first_name, last_name) VALUES (?, ?, ?, ?)");
    $ins->execute([$team_id, $number !== '' ? $number : null, $first ?: null, $last ?: null]);
    $id = (int)$pdo->lastInsertId();

    $label = trim(($number !== '' ? ($number . ' - ') : '') . ($last ? $last . ', ' : '') . ($first ?: ''));
    if ($label === '') $label = 'Jugador ' . $id;

    echo json_encode(['success' => true, 'player' => ['id' => $id, 'number' => $number, 'first_name' => $first, 'last_name' => $last, 'label' => $label]]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error servidor: ' . $e->getMessage()]);
    exit;
}