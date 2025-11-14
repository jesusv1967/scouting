<?php
// public/view_match.php (muestra categoría y nota general; añade botón para editar)
require_once __DIR__ . '/../src/db.php';

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$match_id) {
    header("Location: index.php");
    exit;
}

// Obtener info del partido incluyendo categoría
$stmt = $mysqli->prepare("
    SELECT m.*, t1.name AS home, t2.name AS away, c.name AS category_name
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    LEFT JOIN categories c ON m.category_id = c.id
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

// Manejar creación de nota (por jugador)
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
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ver partido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Scouting</a>
    </div>
  </nav>

  <main class="container my-4">
    <div class="d-flex align-items-start justify-content-between mb-2">
      <div>
        <h1 class="h5"><?=htmlspecialchars($match['home'])?> vs <?=htmlspecialchars($match['away'])?></h1>
        <p class="text-muted small">
          Fecha: <?=htmlspecialchars($match['match_date'])?> · Lugar: <?=htmlspecialchars($match['location'])?>
          <?php if (!empty($match['category_name'])): ?> · <strong>Categoría:</strong> <?=htmlspecialchars($match['category_name'])?><?php endif; ?>
        </p>
      </div>
      <div class="text-end">
        <a class="btn btn-outline-secondary btn-sm" href="edit_match.php?id=<?= $match_id ?>">Editar partido</a>
        <a class="btn btn-secondary btn-sm" href="index.php">Volver</a>
      </div>
    </div>

    <?php if (!empty($match['notes'])): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="card-title">Nota general</h6>
          <p class="mb-0"><?=nl2br(htmlspecialchars($match['notes']))?></p>
        </div>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-body">
        <h6 class="card-title">Añadir nota</h6>
        <?php if (!empty($note_error)): ?><div class="alert alert-danger"><?=htmlspecialchars($note_error)?></div><?php endif; ?>
        <form method="post" class="row g-2">
          <div class="col-12 col-md-4">
            <label class="form-label">Jugador (opcional)</label>
            <select name="player_id" class="form-select form-select-lg">
              <option value="">-- General --</option>
              <?php foreach ($players as $p): ?>
                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['team_name'] . ' - #' . $p['number'] . ' ' . $p['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label">Cuarto</label>
            <input type="number" min="1" max="10" name="quarter" class="form-control">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Tiempo (mm:ss)</label>
            <input type="text" name="time_remaining" class="form-control" placeholder="05:23">
          </div>
          <div class="col-12">
            <label class="form-label">Nota</label>
            <textarea name="note_text" class="form-control" rows="3" required></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary mt-2">Guardar nota</button>
          </div>
        </form>
      </div>
    </div>

    <h6>Notas</h6>
    <?php if (empty($notes)): ?>
      <p>No hay notas todavía.</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($notes as $n): ?>
          <div class="list-group-item">
            <div class="d-flex w-100 justify-content-between">
              <div>
                <strong><?=htmlspecialchars($n['player_name'] ? $n['player_name'] : 'General')?></strong>
                <?php if ($n['player_number']): ?> <small class="text-muted">#<?=htmlspecialchars($n['player_number'])?></small><?php endif; ?>
                <?php if ($n['quarter']): ?> <small class="text-muted">· C<?=htmlspecialchars($n['quarter'])?></small><?php endif; ?>
              </div>
              <small class="text-muted"><?=htmlspecialchars($n['created_at'])?></small>
            </div>
            <?php if ($n['time_remaining']): ?><div class="text-muted small">Tiempo: <?=htmlspecialchars($n['time_remaining'])?></div><?php endif; ?>
            <p class="mb-0 mt-2"><?=nl2br(htmlspecialchars($n['note_text']))?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>