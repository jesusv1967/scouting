<?php
// public/seasons.php
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
        $name = trim($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?: null;
        $end = $_POST['end_date'] ?: null;
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio';
        } else {
            $stmt = $pdo->prepare("INSERT INTO seasons (name, start_date, end_date) VALUES (?, ?, ?)");
            $stmt->execute([$name, $start, $end]);
            $success = 'Temporada creada';
        }
    }
}

$stmt = $pdo->query("SELECT id, name, start_date, end_date FROM seasons ORDER BY start_date DESC");
$seasons = $stmt->fetchAll();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Temporadas - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <a href="/dashboard.php" class="btn btn-link">&larr; Volver</a>
  <h2>Temporadas</h2>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-6">
      <h5>Añadir temporada</h5>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Fecha inicio</label>
          <input type="date" name="start_date" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Fecha fin</label>
          <input type="date" name="end_date" class="form-control">
        </div>
        <button class="btn btn-primary">Crear</button>
      </form>
    </div>

    <div class="col-md-6">
      <h5>Listado</h5>
      <table class="table table-sm">
        <thead><tr><th>Nombre</th><th>Inicio</th><th>Fin</th></tr></thead>
        <tbody>
        <?php foreach($seasons as $s): ?>
          <tr>
            <td><?=htmlspecialchars($s['name'])?></td>
            <td><?=htmlspecialchars($s['start_date'])?></td>
            <td><?=htmlspecialchars($s['end_date'])?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>