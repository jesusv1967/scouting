<?php
// public/index.php
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
<html>
<head>
  <meta charset="utf-8">
  <title>Scouting - Partidos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Partidos</h1>
  <p><a class="btn btn-primary" href="add_match.php">Crear partido</a> <a class="btn btn-secondary" href="../README.md">Ver README</a></p>

  <?php if (empty($matches)): ?>
    <p>No hay partidos. Crea uno nuevo.</p>
  <?php else: ?>
    <table class="table table-striped">
      <thead><tr><th>Fecha</th><th>Local</th><th>Visitante</th><th>Ubicación</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($matches as $m): ?>
        <tr>
          <td><?=htmlspecialchars($m['match_date'])?></td>
          <td><?=htmlspecialchars($m['home'])?></td>
          <td><?=htmlspecialchars($m['away'])?></td>
          <td><?=htmlspecialchars($m['location'])?></td>
          <td>
            <a class="btn btn-sm btn-outline-primary" href="view_match.php?id=<?=$m['id']?>">Ver</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>