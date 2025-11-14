<?php
// public/teams.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$errors = [];
$success = '';

// Detectar modo edit/delete
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido';
    } else {
        // Delete
        if (!empty($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['id'])) {
            $deleteId = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$deleteId]);
            $success = 'Equipo eliminado';
        }
        // Update
        elseif (!empty($_POST['action']) && $_POST['action'] === 'update' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $category_id = $_POST['category_id'] ?: null;
            if ($name === '') {
                $errors[] = 'El nombre es obligatorio';
            } else {
                $stmt = $pdo->prepare("UPDATE teams SET name = ?, category_id = ? WHERE id = ?");
                $stmt->execute([$name, $category_id, $id]);
                $success = 'Equipo actualizado';
                $editId = $id;
            }
        }
        // Create
        else {
            $name = trim($_POST['name'] ?? '');
            $category_id = $_POST['category_id'] ?: null;
            if ($name === '') {
                $errors[] = 'El nombre es obligatorio';
            } else {
                $stmt = $pdo->prepare("INSERT INTO teams (name, category_id) VALUES (?, ?)");
                $stmt->execute([$name, $category_id]);
                $success = 'Equipo creado';
            }
        }
    }
}

// Si estamos en GET y editId presente, cargar datos
$teamToEdit = null;
if ($editId) {
    $stmt = $pdo->prepare("SELECT id, name, category_id FROM teams WHERE id = ?");
    $stmt->execute([$editId]);
    $teamToEdit = $stmt->fetch();
    if (!$teamToEdit) {
        $errors[] = 'Equipo no encontrado';
        $editId = null;
    }
}

// Obtener listas
$stmt = $pdo->query("SELECT t.id, t.name, c.name AS category FROM teams t LEFT JOIN categories c ON t.category_id = c.id ORDER BY t.name");
$teams = $stmt->fetchAll();

$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $catStmt->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Equipos - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<div class="container py-4">
  <a href="<?=htmlspecialchars(url('dashboard.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver al dashboard</a>
  <h2>Equipos</h2>

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
      <?php if ($teamToEdit): ?>
        <h5>Editar equipo</h5>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?=htmlspecialchars($teamToEdit['id'])?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required value="<?=htmlspecialchars($teamToEdit['name'])?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Categoría</label>
            <select name="category_id" class="form-select">
              <option value="">--</option>
              <?php foreach($categories as $c): ?>
                <option value="<?=htmlspecialchars($c['id'])?>" <?=($teamToEdit['category_id'] == $c['id']) ? 'selected' : ''?>><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <a href="<?=htmlspecialchars(url('teams.php'))?>" class="btn btn-outline-secondary">Cancelar</a>
          </div>
        </form>

        <form method="post" class="mt-3" onsubmit="return confirm('¿Eliminar este equipo? Esta acción no es reversible.');">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?=htmlspecialchars($teamToEdit['id'])?>">
          <button class="btn btn-outline-danger">Eliminar equipo</button>
        </form>
      <?php else: ?>
        <h5>Añadir equipo</h5>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Categoría</label>
            <select name="category_id" class="form-select">
              <option value="">--</option>
              <?php foreach($categories as $c): ?>
                <option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary">Crear</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="col-md-6">
      <h5>Listado</h5>
      <table class="table table-sm">
        <thead><tr><th>Nombre</th><th>Categoría</th><th></th></tr></thead>
        <tbody>
        <?php foreach($teams as $t): ?>
          <tr>
            <td><?=htmlspecialchars($t['name'])?></td>
            <td><?=htmlspecialchars($t['category'])?></td>
            <td class="text-end">
              <a href="<?=htmlspecialchars(url('teams.php') . '?id=' . $t['id'])?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>