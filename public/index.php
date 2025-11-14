<?php
// public/index.php (responsive)
require_once __DIR__ . '/../src/db.php';

// Obtener lista de partidos
$stmt = $mysqli->prepare("
    SELECT m.id, m.match_date, t1.name AS home, t2.name AS away, m.location
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    ORDER BY m.match_date DESC
");
$stmt->execute();
$res = $stmt->get_result();
$matches = $res->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- crucial para mobile/tablet -->
  <title>Scouting - Partidos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Scouting</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="add_match.php">Crear partido</a></li>
          <li class="nav-item"><a class="nav-link" href="../Readme.md">Readme</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Partidos</h1>
      <a class="btn btn-primary d-none d-sm-inline-block" href="add_match.php">Crear partido</a>
    </div>

    <?php if (empty($matches)): ?>
      <div class="alert alert-secondary">No hay partidos. Crea uno nuevo.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Fecha</th>
              <th>Local</th>
              <th>Visitante</th>
              <th>Ubicación</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($matches as $m): ?>
            <tr>
              <td><?=htmlspecialchars($m['match_date'])?></td>
              <td><?=htmlspecialchars($m['home'])?></td>
              <td><?=htmlspecialchars($m['away'])?></td>
              <td><?=htmlspecialchars($m['location'])?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="view_match.php?id=<?=$m['id']?>">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>

  <footer class="bg-light text-center py-3">
    <small class="text-muted">Scouting · Notas de partidos</small>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>