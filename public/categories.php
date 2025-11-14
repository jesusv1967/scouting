<?php
// public/categories.php
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
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio';
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $success = 'Categoría creada';
        }
    }
}

$stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Categorías - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <a href="/dashboard.php" class="btn btn-link">&larr; Volver</a>
  <h2>Categorías</h2>
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
      <h5>Añadir categoría</h5>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descripción</label>
          <textarea name="description" class="form-control"></textarea>
        </div>
        <button class="btn btn-primary">Crear</button>
      </form>
    </div>

    <div class="col-md-6">
      <h5>Listado</h5>
      <table class="table table-sm">
        <thead><tr><th>Nombre</th><th>Descripción</th></tr></thead>
        <tbody>
        <?php foreach($categories as $c): ?>
          <tr>
            <td><?=htmlspecialchars($c['name'])?></td>
            <td><?=htmlspecialchars($c['description'])?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>