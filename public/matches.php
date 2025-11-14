<?php
// public/matches.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$errors = [];
$success = '';

// Manejo de borrado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Partido eliminado';
        } else {
            $errors[] = 'ID de partido inválido.';
        }
    }
}

// Obtener listado de partidos con joins (orden descendente por fecha)
$sql = "SELECT m.id, m.date, m.competition, m.venue, m.notes,
               s.name AS season_name, c.name AS category_name,
               th.name AS home_name, ta.name AS away_name,
               u.display_name AS creator
        FROM matches m
        LEFT JOIN seasons s ON m.season_id = s.id
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN teams th ON m.home_team_id = th.id
        LEFT JOIN teams ta ON m.away_team_id = ta.id
        LEFT JOIN users u ON m.created_by = u.id
        ORDER BY m.date DESC
        LIMIT 100"; // límite por ahora

$stmt = $pdo->query($sql);
$matches = $stmt->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Partidos - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?=htmlspecialchars(url('dashboard.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver al dashboard</a>

    <h2>Partidos</h2>
    <div>
      <a href="<?=htmlspecialchars(url('add_match.php'))?>" class="btn btn-primary">Nuevo partido</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>

  <?php if (count($matches) === 0): ?>
    <div class="alert alert-info">No hay partidos grabados todavía.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Temporada / Categoría</th>
            <th>Local - Visitante</th>
            <th>Competición</th>
            <th>Sede</th>
            <th>Notas</th>
            <th>Creado por</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matches as $m): ?>
            <tr>
              <td><?=htmlspecialchars(date('Y-m-d H:i', strtotime($m['date'])))?></td>
              <td>
                <?=htmlspecialchars($m['season_name'] ?? '-')?>
                <br>
                <small class="text-muted"><?=htmlspecialchars($m['category_name'] ?? '-')?></small>
              </td>
              <td>
                <strong><?=htmlspecialchars($m['home_name'] ?? '—')?></strong>
                <span class="text-muted"> vs </span>
                <strong><?=htmlspecialchars($m['away_name'] ?? '—')?></strong>
              </td>
              <td><?=htmlspecialchars($m['competition'] ?? '-')?></td>
              <td><?=htmlspecialchars($m['venue'] ?? '-')?></td>
              <td style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($m['notes'] ?? '')?></td>
              <td><?=htmlspecialchars($m['creator'] ?? '-')?></td>
              <td class="text-end">
                <a href="<?=htmlspecialchars(url('add_match.php') . '?id=' . $m['id'])?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>

                <form method="post" class="d-inline-block" onsubmit="return confirm('¿Eliminar este partido?');" style="margin:0">
                  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=htmlspecialchars($m['id'])?>">
                  <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>