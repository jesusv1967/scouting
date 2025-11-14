<?php
// public/add_match.php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/db.php';
secure_session_start(require __DIR__ . '/../src/config.php');
require_login();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido';
    } else {
        $season_id = $_POST['season_id'] ?: null;
        $category_id = $_POST['category_id'] ?: null;
        $home = $_POST['home_team_id'] ?? null;
        $away = $_POST['away_team_id'] ?? null;
        $date = $_POST['date'] ?? null;
        $competition = trim($_POST['competition'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        if (!$home || !$away || !$date) {
            $errors[] = 'Home, away y fecha son obligatorios';
        } elseif ($home == $away) {
            $errors[] = 'El equipo local y visitante no pueden ser el mismo';
        } else {
            $stmt = $pdo->prepare("INSERT INTO matches (season_id, category_id, home_team_id, away_team_id, date, competition, venue, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$season_id, $category_id, $home, $away, $date, $competition, $venue, $_SESSION['user_id'] ?? null]);
            $success = 'Partido creado';
        }
    }
}

// Datos para formularios
$seasons = $pdo->query("SELECT id, name FROM seasons ORDER BY start_date DESC")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Añadir partido - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <a href="/dashboard.php" class="btn btn-link">&larr; Volver</a>
  <h2>Añadir partido</h2>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Temporada</label>
        <select name="season_id" class="form-select">
          <option value="">--</option>
          <?php foreach($seasons as $s): ?>
            <option value="<?=htmlspecialchars($s['id'])?>"><?=htmlspecialchars($s['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Categoría</label>
        <select name="category_id" class="form-select">
          <option value="">--</option>
          <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Fecha y hora</label>
        <input type="datetime-local" name="date" class="form-control" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Local</label>
        <select name="home_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>"><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Visitante</label>
        <select name="away_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>"><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Competición</label>
        <input name="competition" class="form-control">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Sede</label>
        <input name="venue" class="form-control">
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Crear partido</button>
    </div>
  </form>
</div>
</body>
</html>