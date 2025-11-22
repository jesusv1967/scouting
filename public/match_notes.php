<?php
// public/match_notes.php
// Notas generales del equipo en un partido
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$match_id = $_GET['match_id'] ?? null;
if (!$match_id || !is_numeric($match_id)) {
    die('Partido no especificado.');
}

$stmt = $pdo->prepare("
    SELECT m.date, m.competition, m.notes AS match_notes,
           th.name AS home_team, ta.name AS away_team
    FROM matches m
    LEFT JOIN teams th ON m.home_team_id = th.id
    LEFT JOIN teams ta ON m.away_team_id = ta.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();
if (!$match) die('Partido no encontrado');

$csrf = csrf_token();

// Guardar notas si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    $notes = trim($_POST['match_notes'] ?? '');
    $stmt = $pdo->prepare("UPDATE matches SET notes = ? WHERE id = ?");
    $stmt->execute([$notes ?: null, $match_id]);
    header('Location: ' . url('match_notes.php?match_id=' . $match_id . '&saved=1'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notas del equipo - Scouting</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_asset('css/styles.css')) ?>">
  <style>
    body {
      padding: 16px;
      background: #f9fafb;
      font-family: -apple-system, sans-serif;
    }
    .report-header {
      background: white;
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .report-header h1 {
      font-size: 28px;
      margin: 0 0 12px;
      color: var(--primary);
    }
    .report-header p {
      font-size: 18px;
      color: var(--gray);
      margin: 4px 0;
    }
    .notes-section {
      background: white;
      border-radius: 14px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .notes-section textarea {
      width: 100%;
      min-height: 200px;
      padding: 16px;
      font-size: 18px;
      border: 1px solid var(--border);
      border-radius: 12px;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 16px;
      font-size: 18px;
      font-weight: bold;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 12px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="report-header">
    <h1>Notas del partido</h1>
    <p><strong><?= htmlspecialchars($match['home_team']) ?> vs <?= htmlspecialchars($match['away_team']) ?></strong></p>
    <p><?= htmlspecialchars($match['competition'] ?? '') ?> · <?= date('d/m/Y H:i', strtotime($match['date'])) ?></p>
  </div>

  <?php if (!empty($_GET['saved'])): ?>
    <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;">
      Notas guardadas correctamente.
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="notes-section">
      <label style="display:block; font-size:20px; margin-bottom:12px; font-weight:bold;">Observaciones generales del equipo:</label>
      <textarea name="match_notes" placeholder="Ej: Buena defensa colectiva, malos rebotes, excesivas pérdidas..."><?= htmlspecialchars($match['match_notes'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn">Guardar notas del partido</button>
    <div style="text-align:center; margin-top:20px;">
      <a href="<?= htmlspecialchars(url('scouting.php?match_id=' . $match_id)) ?>" style="color:var(--primary); text-decoration:underline;">← Volver al scouting</a>
    </div>
  </form>
</body>
</html>