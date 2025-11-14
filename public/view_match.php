<?php
// public/view_match.php
require_once __DIR__ . '/../src/db.php';

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$match_id) {
    header("Location: index.php");
    exit;
}

// Obtener info del partido
$stmt = $mysqli->prepare("
    SELECT m.*, t1.name AS home, t2.name AS away
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $match_id);
$stmt->execute();
$res = $stmt->get_result();
$match = $res->fetch_assoc();
if (!$match) {
    echo "Partido no encontrado.";
    exit;
}

// Obtener jugadores de ambos equipos
$stmt = $mysqli->prepare("SELECT p.id, p.name, p.number, t.name AS team_name FROM players p JOIN teams t ON p.team_id = t.id WHERE p.team_id IN (?, ?) ORDER BY t.name, p.number");
$stmt->bind_param('ii', $match['home_team_id'], $match['away_team_id']);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Manejar creación de nota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    $player_id = !empty($_POST['player_id']) ? (int)$_POST['player_id'] : null;
    $quarter = !empty($_POST['quarter']) ? (int)$_POST['quarter'] : null;
    $time_remaining = $_POST['time_remaining'] ?? null;
    $note_text = trim($_POST['note_text']);

    if ($note_text) {
        $stmt = $mysqli->prepare("INSERT INTO notes (match_id, player_id, quarter, time_remaining, note_text) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiss', $match_id, $player_id, $quarter, $time_remaining, $note_text);
        $stmt->execute();
        header("Location: view_match.php?id=" . $match_id);
        exit;
    } else {
        $note_error = "La nota no puede estar vacía.";
    }
}

// Obtener notas del partido
$stmt = $mysqli->prepare("
    SELECT n.*, p.name AS player_name, p.number AS player_number
    FROM notes n
    LEFT JOIN players p ON n.player_id = p.id
    WHERE n.match_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param('i', $match_id);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Ver partido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Partido: <?=htmlspecialchars($match['home'])?> vs <?=htmlspecialchars($match['away'])?></h1>
  <p>Fecha: <?=htmlspecialchars($match['match_date'])?> | Lugar: <?=htmlspecialchars($match['location'])?></p>
  <a class="btn btn-secondary mb-3" href="index.php">Volver al listado</a>

  <h3>Añadir nota</h3>
  <?php if (!empty($note_error)): ?><div class="alert alert-danger"><?=htmlspecialchars($note_error)?></div><?php endif; ?>
  <form method="post" class="mb-4">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Jugador (opcional)</label>
        <select name="player_id" class="form-select">
          <option value="">-- General --</option>
          <?php foreach ($players as $p): ?>
            <option value="<?=$p['id']?>"><?=htmlspecialchars($p['team_name'] . ' - #' . $p['number'] . ' ' . $p['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Cuarto</label>
        <input type="number" min="1" max="10" name="quarter" class="form-control" placeholder="1-4">
      </div>
      <div class="col-md-2">
        <label class="form-label">Tiempo restante (mm:ss)</label>
        <input type="text" name="time_remaining" class="form-control" placeholder="05:23">
      </div>
      <div class="col-md-12">
        <label class="form-label">Nota</label>
        <textarea name="note_text" class="form-control" rows="3" required></textarea>
      </div>
    </div>
    <div class="mt-2">
      <button class="btn btn-primary">Guardar nota</button>
    </div>
  </form>

  <h3>Notas</h3>
  <?php if (empty($notes)): ?>
    <p>No hay notas todavía.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($notes as $n): ?>
        <div class="list-group-item">
          <div class="d-flex w-100 justify-content-between">
            <h6 class="mb-1"><?=htmlspecialchars($n['player_name'] ? $n['player_name'] : 'General')?>
              <?php if ($n['player_number']): ?>#<?=htmlspecialchars($n['player_number'])?><?php endif; ?>
              <?php if ($n['quarter']): ?> — Cuarto <?=htmlspecialchars($n['quarter'])?><?php endif; ?>
            </h6>
            <small><?=htmlspecialchars($n['created_at'])?></small>
          </div>
          <?php if ($n['time_remaining']): ?><small class="text-muted">Tiempo: <?=htmlspecialchars($n['time_remaining'])?></small><?php endif; ?>
          <p class="mb-1 mt-2"><?=nl2br(htmlspecialchars($n['note_text']))?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</body>
</html>