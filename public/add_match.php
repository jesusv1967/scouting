<?php
// public/add_match.php
require_once __DIR__ . '/../src/db.php';

// Manejo de POST para crear equipos si no existen y el partido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $match_date = $_POST['match_date'] ?? '';
    $home = trim($_POST['home_team'] ?? '');
    $away = trim($_POST['away_team'] ?? '');
    $location = $_POST['location'] ?? '';

    if ($match_date && $home && $away) {
        $mysqli->begin_transaction();

        // Insertar o obtener home team
        $stmt = $mysqli->prepare("SELECT id FROM teams WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $home);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) {
            $home_id = $row['id'];
        } else {
            $stmt = $mysqli->prepare("INSERT INTO teams (name) VALUES (?)");
            $stmt->bind_param('s', $home);
            $stmt->execute();
            $home_id = $stmt->insert_id;
        }

        // Insertar o obtener away team
        $stmt = $mysqli->prepare("SELECT id FROM teams WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $away);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if ($row) {
            $away_id = $row['id'];
        } else {
            $stmt = $mysqli->prepare("INSERT INTO teams (name) VALUES (?)");
            $stmt->bind_param('s', $away);
            $stmt->execute();
            $away_id = $stmt->insert_id;
        }

        // Insertar partido
        $stmt = $mysqli->prepare("INSERT INTO matches (match_date, home_team_id, away_team_id, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siis', $match_date, $home_id, $away_id, $location);
        $stmt->execute();

        $mysqli->commit();

        header("Location: index.php");
        exit;
    } else {
        $error = "Rellena fecha, equipo local y visitante.";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Crear partido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
  <h1>Crear partido</h1>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label">Fecha y hora</label>
      <input type="datetime-local" name="match_date" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Equipo local (nombre)</label>
      <input type="text" name="home_team" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Equipo visitante (nombre)</label>
      <input type="text" name="away_team" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Ubicación</label>
      <input type="text" name="location" class="form-control">
    </div>
    <button class="btn btn-primary">Crear</button>
    <a class="btn btn-secondary" href="index.php">Volver</a>
  </form>
</body>
</html>