<?php
// public/add_match.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$errors = [];
$success = '';

$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido';
    } else {
        // Update
        if (!empty($_POST['action']) && $_POST['action'] === 'update' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $season_id = $_POST['season_id'] ?: null;
            $category_id = $_POST['category_id'] ?: null;
            $home = $_POST['home_team_id'] ?? null;
            $away = $_POST['away_team_id'] ?? null;
            $date = $_POST['date'] ?? null;
            $competition = trim($_POST['competition'] ?? '');
            $venue = trim($_POST['venue'] ?? '');
            $notes = trim($_POST['notes'] ?? '') ?: null;
            if (!$home || !$away || !$date) {
                $errors[] = 'Home, away y fecha son obligatorios';
            } elseif ($home == $away) {
                $errors[] = 'El equipo local y visitante no pueden ser el mismo';
            } else {
                $stmt = $pdo->prepare("UPDATE matches SET season_id = ?, category_id = ?, home_team_id = ?, away_team_id = ?, date = ?, competition = ?, venue = ?, notes = ? WHERE id = ?");
                $stmt->execute([$season_id, $category_id, $home, $away, $date, $competition, $venue, $notes, $id]);
                $success = 'Partido actualizado';
                $editId = $id;
            }
        }
        // Create
        else {
            $season_id = $_POST['season_id'] ?: null;
            $category_id = $_POST['category_id'] ?: null;
            $home = $_POST['home_team_id'] ?? null;
            $away = $_POST['away_team_id'] ?? null;
            $date = $_POST['date'] ?? null;
            $competition = trim($_POST['competition'] ?? '');
            $venue = trim($_POST['venue'] ?? '');
            $notes = trim($_POST['notes'] ?? '') ?: null;
            if (!$home || !$away || !$date) {
                $errors[] = 'Home, away y fecha son obligatorios';
            } elseif ($home == $away) {
                $errors[] = 'El equipo local y visitante no pueden ser el mismo';
            } else {
                $stmt = $pdo->prepare("INSERT INTO matches (season_id, category_id, home_team_id, away_team_id, date, competition, venue, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$season_id, $category_id, $home, $away, $date, $competition, $venue, $_SESSION['user_id'] ?? null, $notes]);
                $success = 'Partido creado';
            }
        }
    }
}

// Si estamos en modo edición, cargar datos
$matchToEdit = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$editId]);
    $matchToEdit = $stmt->fetch();
    if (!$matchToEdit) {
        $errors[] = 'Partido no encontrado';
        $editId = null;
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
  <title><?= $editId ? 'Editar partido' : 'Añadir partido' ?> - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<div class="container py-4">
  <a href="<?=htmlspecialchars(url('dashboard.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver al dashboard</a>
  <h2><?= $editId ? 'Editar partido' : 'Añadir partido' ?></h2>

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
    <?php if ($editId): ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?=htmlspecialchars($editId)?>">
    <?php endif; ?>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Temporada</label>
        <select name="season_id" class="form-select">
          <option value="">--</option>
          <?php foreach($seasons as $s): ?>
            <option value="<?=htmlspecialchars($s['id'])?>" <?=($matchToEdit && $matchToEdit['season_id'] == $s['id']) ? 'selected' : ''?>><?=htmlspecialchars($s['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Categoría</label>
        <select name="category_id" class="form-select">
          <option value="">--</option>
          <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c['id'])?>" <?=($matchToEdit && $matchToEdit['category_id'] == $c['id']) ? 'selected' : ''?>><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Fecha y hora</label>
        <input type="datetime-local" name="date" class="form-control" required value="<?= $matchToEdit ? date('Y-m-d\TH:i', strtotime($matchToEdit['date'])) : '' ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Local</label>
        <select name="home_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['home_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Visitante</label>
        <select name="away_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['away_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label class="form-label">Competición</label>
        <input name="competition" class="form-control" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['competition']) : '' ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Sede</label>
        <input name="venue" class="form-control" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['venue']) : '' ?>">
      </div>

      <div class="col-12 mb-3">
        <label class="form-label">Notas generales del partido</label>
        <textarea name="notes" class="form-control" rows="4"><?= $matchToEdit ? htmlspecialchars($matchToEdit['notes']) : '' ?></textarea>
        <div class="form-text">Anotaciones generales que quieras guardar sobre el partido.</div>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary"><?= $editId ? 'Actualizar partido' : 'Crear partido' ?></button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>