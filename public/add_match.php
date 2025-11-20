<?php
// public/add_match.php
// Versión de add_match.php integrada con subida de imágenes/videos para el partido.
// Reemplaza tu public/add_match.php por este archivo (haz backup antes).
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/media.php'; // helper media
require_login();

$errors = [];
$success = '';

$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Helpers
function safe_int($v) {
    $v = trim((string)$v);
    return $v === '' ? null : (int)$v;
}

function player_in_team_list($players_arr, $player_id) {
    if (empty($players_arr) || !$player_id) return false;
    foreach ($players_arr as $p) {
        if ((int)$p['id'] === (int)$player_id) return true;
    }
    return false;
}

// POST handling: create/update match + players + media
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        // collect fields
        $season_id = $_POST['season_id'] ?: null;
        $category_id = $_POST['category_id'] ?: null;
        $home = $_POST['home_team_id'] ?? null;
        $away = $_POST['away_team_id'] ?? null;
        $date = $_POST['date'] ?? null;
        $competition = trim($_POST['competition'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (!$home || !$away || !$date) {
            $errors[] = 'Local, visitante y fecha son obligatorios.';
        } elseif ($home == $away) {
            $errors[] = 'El equipo local y visitante no pueden ser el mismo.';
        } else {
            // quarters
            $home_q = $_POST['home_q'] ?? [];
            $away_q = $_POST['away_q'] ?? [];
            $quarters = [];
            $maxQ = max(count($home_q), count($away_q));
            for ($i = 0; $i < $maxQ; $i++) {
                $h = isset($home_q[$i]) ? trim($home_q[$i]) : '';
                $a = isset($away_q[$i]) ? trim($away_q[$i]) : '';
                $quarters[] = ['home' => ($h === '' ? null : (int)$h), 'away' => ($a === '' ? null : (int)$a)];
            }

            // scores override
            $home_total = 0; $away_total = 0;
            foreach ($quarters as $q) {
                if (is_int($q['home'])) $home_total += $q['home'];
                if (is_int($q['away'])) $away_total += $q['away'];
            }
            $home_score = (isset($_POST['home_score_override']) && $_POST['home_score_override'] !== '') ? safe_int($_POST['home_score_override']) : ($home_total ?: null);
            $away_score = (isset($_POST['away_score_override']) && $_POST['away_score_override'] !== '') ? safe_int($_POST['away_score_override']) : ($away_total ?: null);

            // dynamic players arrays
            $home_player_ids = $_POST['home_player_id'] ?? [];
            $home_custom_numbers = $_POST['home_player_custom_number'] ?? [];
            $home_custom_names = $_POST['home_player_custom_name'] ?? [];
            $home_notes = $_POST['player_notes_home'] ?? [];
            $home_is_starter = $_POST['home_is_starter'] ?? [];

            $away_player_ids = $_POST['away_player_id'] ?? [];
            $away_custom_numbers = $_POST['away_player_custom_number'] ?? [];
            $away_custom_names = $_POST['away_player_custom_name'] ?? [];
            $away_notes = $_POST['player_notes_away'] ?? [];
            $away_is_starter = $_POST['away_is_starter'] ?? [];

            try {
                $pdo->beginTransaction();

                // insert or update match
                if (!empty($_POST['id'])) {
                    $match_id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE matches SET season_id = ?, category_id = ?, home_team_id = ?, away_team_id = ?, date = ?, competition = ?, venue = ?, notes = ?, quarter_scores = ?, home_score = ?, away_score = ? WHERE id = ?");
                    $stmt->execute([
                        $season_id ?: null, $category_id ?: null, $home, $away, $date, $competition ?: null, $venue ?: null, $notes,
                        json_encode($quarters, JSON_UNESCAPED_UNICODE),
                        $home_score, $away_score,
                        $match_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO matches (season_id, category_id, home_team_id, away_team_id, date, competition, venue, created_by, notes, quarter_scores, home_score, away_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $season_id ?: null, $category_id ?: null, $home, $away, $date, $competition ?: null, $venue ?: null, $_SESSION['user_id'] ?? null, $notes,
                        json_encode($quarters, JSON_UNESCAPED_UNICODE),
                        $home_score, $away_score
                    ]);
                    $match_id = (int)$pdo->lastInsertId();
                }

                // delete and reinsert match_players
                $del = $pdo->prepare("DELETE FROM match_players WHERE match_id = ?");
                $del->execute([$match_id]);

                // resolver/crear player helper
                $resolvePlayer = function($team_id, $player_id_val, $custom_number, $custom_name) use ($pdo) {
                    $team_id = $team_id ? (int)$team_id : null;
                    if ($player_id_val) {
                        $player_id_val = (int)$player_id_val;
                        $s = $pdo->prepare("SELECT id FROM players WHERE id = ?");
                        $s->execute([$player_id_val]);
                        if ($s->fetch()) return $player_id_val;
                    }
                    if ($custom_number !== '' || $custom_name !== '') {
                        if ($custom_number !== '') {
                            $q = $pdo->prepare("SELECT id FROM players WHERE team_id = ? AND number = ? LIMIT 1");
                            $q->execute([$team_id, $custom_number]);
                            $r = $q->fetch();
                            if ($r) return (int)$r['id'];
                        }
                        $ins = $pdo->prepare("INSERT INTO players (team_id, number, first_name, last_name) VALUES (?, ?, ?, ?)");
                        $first = null; $last = null;
                        if ($custom_name !== '') {
                            $parts = preg_split('/\s+/', trim($custom_name), 2);
                            $first = $parts[0] ?? null;
                            $last = $parts[1] ?? null;
                        }
                        $ins->execute([$team_id ?: null, $custom_number ?: null, $first ?: null, $last ?: null]);
                        return (int)$pdo->lastInsertId();
                    }
                    return null;
                };

                // insert home players
                $countHome = max( count($home_player_ids), count($home_custom_numbers), count($home_custom_names), count($home_notes) );
                for ($i = 0; $i < $countHome; $i++) {
                    $pid = $home_player_ids[$i] ?? null;
                    $cnum = $home_custom_numbers[$i] ?? '';
                    $cname = $home_custom_names[$i] ?? '';
                    $pnotes = trim($home_notes[$i] ?? '');
                    $starterFlag = isset($home_is_starter[$i]) ? (int)$home_is_starter[$i] : 0;
                    $resolved = $resolvePlayer($home, $pid, $cnum, $cname);
                    if ($resolved) {
                        $ins = $pdo->prepare("INSERT INTO match_players (match_id, player_id, team_id, is_starter, notes) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$match_id, $resolved, $home ?: null, $starterFlag ? 1 : 0, $pnotes ?: null]);
                    }
                }

                // insert away players
                $countAway = max( count($away_player_ids), count($away_custom_numbers), count($away_custom_names), count($away_notes) );
                for ($i = 0; $i < $countAway; $i++) {
                    $pid = $away_player_ids[$i] ?? null;
                    $cnum = $away_custom_numbers[$i] ?? '';
                    $cname = $away_custom_names[$i] ?? '';
                    $pnotes = trim($away_notes[$i] ?? '');
                    $starterFlag = isset($away_is_starter[$i]) ? (int)$away_is_starter[$i] : 0;
                    $resolved = $resolvePlayer($away, $pid, $cnum, $cname);
                    if ($resolved) {
                        $ins = $pdo->prepare("INSERT INTO match_players (match_id, player_id, team_id, is_starter, notes) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$match_id, $resolved, $away ?: null, $starterFlag ? 1 : 0, $pnotes ?: null]);
                    }
                }

                // Process uploaded media (if any)
                if (!empty($_FILES['media']) && isset($_FILES['media']['tmp_name']) && is_array($_FILES['media']['tmp_name'])) {
                    $resMedia = save_match_media($_FILES['media'], $match_id, $pdo);
                    if (!empty($resMedia['errors'])) {
                        foreach ($resMedia['errors'] as $err) $errors[] = $err;
                    }
                    // saved entries are in $resMedia['saved'] if needed
                }

                $pdo->commit();

                // PRG redirect
                header('Location: ' . url('add_match.php') . '?id=' . $match_id . '&saved=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Error guardando partido: ' . $e->getMessage();
            }
        }
    }
}

// GET: load match for edit
$matchToEdit = null;
$quarters_existing = [];
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$editId]);
    $matchToEdit = $stmt->fetch();
    if ($matchToEdit) {
        $quarters_existing = json_decode($matchToEdit['quarter_scores'] ?? '[]', true) ?: [];
    } else {
        $errors[] = 'Partido no encontrado';
        $editId = null;
    }
}

// Data for selects
$seasons = $pdo->query("SELECT id, name FROM seasons ORDER BY start_date DESC")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();

// Load players by team
$players_by_team = [];
$teamIds = array_map(fn($t) => $t['id'], $teams);
if (count($teamIds) > 0) {
    $in = implode(',', array_fill(0, count($teamIds), '?'));
    $stmt = $pdo->prepare("SELECT id, team_id, number, first_name, last_name FROM players WHERE team_id IN ($in) ORDER BY (number+0) ASC, number ASC, last_name ASC");
    $stmt->execute($teamIds);
    $allPlayers = $stmt->fetchAll();
    foreach ($allPlayers as $p) {
        $players_by_team[$p['team_id']][] = $p;
    }
}

// Load existing match_players for edit (home_existing / away_existing)
$home_existing = [];
$away_existing = [];
if ($editId) {
    $ps = $pdo->prepare("SELECT mp.*, COALESCE(mp.team_id, p.team_id) AS resolved_team_id, p.number, p.first_name, p.last_name FROM match_players mp LEFT JOIN players p ON mp.player_id = p.id WHERE mp.match_id = ? ORDER BY mp.id ASC");
    $ps->execute([$editId]);
    $rows = $ps->fetchAll();
    // separate and ensure players_by_team contains referenced players
    $missing_player_ids = [];
    $pid_to_resolved_team = [];
    foreach ($rows as $r) {
        $resolved_team = $r['resolved_team_id'] ?? null;
        $pid = $r['player_id'] ?? null;
        if ($pid && $resolved_team && (int)$resolved_team === (int)($matchToEdit['home_team_id'] ?? 0)) {
            $home_existing[] = $r;
        } elseif ($pid && $resolved_team && (int)$resolved_team === (int)($matchToEdit['away_team_id'] ?? 0)) {
            $away_existing[] = $r;
        } else {
            $home_existing[] = $r;
        }
        if ($pid) {
            $pid_to_resolved_team[(int)$pid] = $resolved_team;
            if ($resolved_team && !player_in_team_list($players_by_team[$resolved_team] ?? [], $pid)) {
                $missing_player_ids[] = (int)$pid;
            }
        }
    }
    if (!empty($missing_player_ids)) {
        $placeholders = implode(',', array_fill(0, count($missing_player_ids), '?'));
        $ps2 = $pdo->prepare("SELECT id, team_id, number, first_name, last_name FROM players WHERE id IN ($placeholders)");
        $ps2->execute($missing_player_ids);
        $fetched = $ps2->fetchAll();
        foreach ($fetched as $p) {
            $pid = (int)$p['id'];
            $resolved_team = $pid_to_resolved_team[$pid] ?? ($p['team_id'] ?? null);
            if (!$resolved_team) $resolved_team = $matchToEdit['home_team_id'] ?? null;
            if (!isset($players_by_team[$resolved_team])) $players_by_team[$resolved_team] = [];
            if (!player_in_team_list($players_by_team[$resolved_team], $pid)) {
                $players_by_team[$resolved_team][] = $p;
            }
        }
    }
}

// Load media for this match if editing (optional: used in form preview)
$existing_media_for_match = [];
if ($editId) {
    $pm = $pdo->prepare("SELECT * FROM match_media WHERE match_id = ? ORDER BY created_at ASC");
    $pm->execute([$editId]);
    $existing_media_for_match = $pm->fetchAll();
}

$homeTeamName = '';
$awayTeamName = '';
$homeTeamId = $matchToEdit['home_team_id'] ?? ($_GET['home_team_id'] ?? null);
$awayTeamId = $matchToEdit['away_team_id'] ?? ($_GET['away_team_id'] ?? null);
if ($homeTeamId) {
    $t = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
    $t->execute([$homeTeamId]); $r = $t->fetch();
    if ($r) $homeTeamName = $r['name'];
}
if ($awayTeamId) {
    $t = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
    $t->execute([$awayTeamId]); $r = $t->fetch();
    if ($r) $awayTeamName = $r['name'];
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <title><?= $editId ? 'Editar partido' : 'Añadir partido' ?> - Scouting</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_asset('css/styles.css')) ?>">
  <style>
    body {
      padding: 16px;
      background-color: var(--light);
    }
    .card {
      background: white;
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      border: 1px solid var(--border);
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      font-size: 18px;
      color: var(--dark);
    }
    input, select, textarea {
      width: 100%;
      padding: 16px;
      font-size: 18px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: white;
    }
    .btn {
      display: block;
      width: 100%;
      padding: 16px;
      font-size: 18px;
      font-weight: bold;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      margin: 8px 0;
      text-align: center;
    }
    .btn-primary { background: var(--primary); color: white; }
    .btn-secondary { background: #f3f4f6; color: var(--dark); }
    .btn-danger { background: var(--secondary); color: white; }
    .team-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--border);
    }
    .quarter-group {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 20px;
    }
    .quarter-item {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .quarter-label {
      font-size: 16px;
      font-weight: bold;
      text-align: center;
    }
    .slot {
      background: #f9fafb;
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 16px;
      border: 1px solid var(--border);
    }
    .slot-controls {
      display: flex;
      justify-content: space-between;
      margin-top: 12px;
    }
    .toggle-starter {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 16px;
    }
    .media-preview img, .media-preview video {
      width: 100%;
      max-width: 200px;
      border-radius: 8px;
      margin-top: 10px;
    }
    .back-links {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 20px;
    }
    .back-links a {
      flex: 1;
      min-width: 180px;
      padding: 12px;
      text-align: center;
      background: #e5e7eb;
      color: var(--dark);
      text-decoration: none;
      border-radius: 10px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="back-links">
    <a href="<?= htmlspecialchars(url('dashboard.php')) ?>">&larr; Dashboard</a>
    <a href="<?= htmlspecialchars(url('matches.php')) ?>">&larr; Partidos</a>
  </div>

  <div class="card">
    <h2 style="font-size: 26px; margin-bottom: 20px; text-align: center; color: var(--primary);">
      <?= $editId ? 'Editar partido' : 'Añadir partido' ?>
    </h2>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['saved'])): ?>
      <div class="alert alert-success">Partido guardado correctamente.</div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="match-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if ($editId): ?><input type="hidden" name="id" value="<?= htmlspecialchars($editId) ?>"><?php endif; ?>

      <!-- Sección: Temporada, Categoría, Fecha -->
      <div class="form-group">
        <label>Temporada</label>
        <select name="season_id">
          <option value="">--</option>
          <?php foreach($seasons as $s): ?>
            <option value="<?=htmlspecialchars($s['id'])?>" <?=($matchToEdit && $matchToEdit['season_id'] == $s['id']) ? 'selected' : ''?>><?=htmlspecialchars($s['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Categoría</label>
        <select name="category_id">
          <option value="">--</option>
          <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c['id'])?>" <?=($matchToEdit && $matchToEdit['category_id'] == $c['id']) ? 'selected' : ''?>><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Fecha y hora</label>
        <input type="datetime-local" name="date" required value="<?= $matchToEdit ? date('Y-m-d\TH:i', strtotime($matchToEdit['date'])) : '' ?>">
      </div>

      <!-- Equipos -->
      <div class="form-group">
        <label>Equipo local</label>
        <select name="home_team_id" id="home_team_id" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['home_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Equipo visitante</label>
        <select name="away_team_id" id="away_team_id" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['away_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- JUGADORES - LOCAL -->
      <div class="card">
        <div class="team-header">
          <h3 style="font-size: 22px; margin: 0;"><?= htmlspecialchars($homeTeamName ?: 'Jugadores - Local') ?></h3>
          <button type="button" id="add-home-player" class="btn btn-secondary" style="padding:8px 12px; width:auto;">➕ Añadir</button>
        </div>
        <div id="home-players-list">
          <?php
          $home_slots = max(5, count($home_existing));
          for ($i = 0; $i < $home_slots; $i++):
            $preset = $home_existing[$i] ?? null;
            $preset_player_id = $preset['player_id'] ?? '';
            $preset_num = $preset['number'] ?? '';
            $preset_first = $preset['first_name'] ?? '';
            $preset_last = $preset['last_name'] ?? '';
            $preset_notes = $preset['notes'] ?? '';
            $preset_is_starter = isset($preset['is_starter']) ? (int)$preset['is_starter'] : 0;
          ?>
          <div class="slot" data-index="<?= $i ?>">
            <select name="home_player_id[]" class="mb-2">
              <option value="">-- seleccionar jugador --</option>
              <?php
              $hid = $matchToEdit['home_team_id'] ?? $homeTeamId;
              if ($preset_player_id && !player_in_team_list($players_by_team[$hid] ?? [], $preset_player_id)) {
                  $label = trim(($preset_num ? $preset_num . ' - ' : '') . ($preset_last ? $preset_last . ', ' : '') . ($preset_first ?? ''));
                  echo '<option value="' . htmlspecialchars($preset_player_id) . '" selected>' . htmlspecialchars($label) . '</option>';
              }
              if ($hid && isset($players_by_team[$hid])):
                  foreach ($players_by_team[$hid] as $p):
                      $label = trim(($p['number'] ? $p['number'] . ' - ' : '') . ($p['last_name'] ? $p['last_name'] . ', ' : '') . ($p['first_name'] ?? ''));
              ?>
                <option value="<?=htmlspecialchars($p['id'])?>" <?=($preset_player_id && (int)$preset_player_id === (int)$p['id']) ? 'selected' : ''?>><?=htmlspecialchars($label)?></option>
              <?php endforeach; endif; ?>
            </select>
            <input type="text" name="home_player_custom_number[]" placeholder="Nº (opcional)" value="<?=htmlspecialchars($preset_num)?>" class="mb-2">
            <input type="text" name="home_player_custom_name[]" placeholder="Nombre (opcional)" value="<?=htmlspecialchars(trim(($preset_first . ' ' . $preset_last) ?: ''))?>" class="mb-2">
            <textarea name="player_notes_home[]" rows="2" placeholder="Notas jugador..."><?=htmlspecialchars($preset_notes)?></textarea>

            <div class="slot-controls">
              <label class="toggle-starter">
                <input type="checkbox" name="home_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
                Titular
              </label>
              <button type="button" class="btn btn-danger remove-player" style="padding:6px 12px; width:auto;">🗑️ Eliminar</button>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- JUGADORES - VISITANTE -->
      <div class="card">
        <div class="team-header">
          <h3 style="font-size: 22px; margin: 0;"><?= htmlspecialchars($awayTeamName ?: 'Jugadores - Visitante') ?></h3>
          <button type="button" id="add-away-player" class="btn btn-secondary" style="padding:8px 12px; width:auto;">➕ Añadir</button>
        </div>
        <div id="away-players-list">
          <?php
          $away_slots = max(5, count($away_existing));
          for ($i = 0; $i < $away_slots; $i++):
            $preset = $away_existing[$i] ?? null;
            $preset_player_id = $preset['player_id'] ?? '';
            $preset_num = $preset['number'] ?? '';
            $preset_first = $preset['first_name'] ?? '';
            $preset_last = $preset['last_name'] ?? '';
            $preset_notes = $preset['notes'] ?? '';
            $preset_is_starter = isset($preset['is_starter']) ? (int)$preset['is_starter'] : 0;
          ?>
          <div class="slot" data-index="<?= $i ?>">
            <select name="away_player_id[]" class="mb-2">
              <option value="">-- seleccionar jugador --</option>
              <?php
              $aid = $matchToEdit['away_team_id'] ?? $awayTeamId;
              if ($preset_player_id && !player_in_team_list($players_by_team[$aid] ?? [], $preset_player_id)) {
                  $label = trim(($preset_num ? $preset_num . ' - ' : '') . ($preset_last ? $preset_last . ', ' : '') . ($preset_first ?? ''));
                  echo '<option value="' . htmlspecialchars($preset_player_id) . '" selected>' . htmlspecialchars($label) . '</option>';
              }
              if ($aid && isset($players_by_team[$aid])):
                  foreach ($players_by_team[$aid] as $p):
                      $label = trim(($p['number'] ? $p['number'] . ' - ' : '') . ($p['last_name'] ? $p['last_name'] . ', ' : '') . ($p['first_name'] ?? ''));
              ?>
                <option value="<?=htmlspecialchars($p['id'])?>" <?=($preset_player_id && (int)$preset_player_id === (int)$p['id']) ? 'selected' : ''?>><?=htmlspecialchars($label)?></option>
              <?php endforeach; endif; ?>
            </select>
            <input type="text" name="away_player_custom_number[]" placeholder="Nº (opcional)" value="<?=htmlspecialchars($preset_num)?>" class="mb-2">
            <input type="text" name="away_player_custom_name[]" placeholder="Nombre (opcional)" value="<?=htmlspecialchars(trim(($preset_first . ' ' . $preset_last) ?: ''))?>" class="mb-2">
            <textarea name="player_notes_away[]" rows="2" placeholder="Notas jugador..."><?=htmlspecialchars($preset_notes)?></textarea>

            <div class="slot-controls">
              <label class="toggle-starter">
                <input type="checkbox" name="away_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
                Titular
              </label>
              <button type="button" class="btn btn-danger remove-player" style="padding:6px 12px; width:auto;">🗑️ Eliminar</button>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Cuartos -->
      <div class="card">
        <h3 style="font-size: 22px; margin-bottom: 16px;">Puntuación por cuartos</h3>
        <div class="quarter-group">
          <?php for ($i = 0; $i < 4; $i++):
            $h = isset($quarters_existing[$i]['home']) ? $quarters_existing[$i]['home'] : '';
            $a = isset($quarters_existing[$i]['away']) ? $quarters_existing[$i]['away'] : '';
          ?>
            <div class="quarter-item">
              <div class="quarter-label">Q<?= $i+1 ?></div>
              <input type="number" min="0" name="home_q[]" placeholder="Local" value="<?= htmlspecialchars($h) ?>">
              <input type="number" min="0" name="away_q[]" placeholder="Visitante" value="<?= htmlspecialchars($a) ?>">
            </div>
          <?php endfor; ?>
        </div>

        <div style="margin-top: 16px;">
          <label>Puntuación total (opcional, si difiere)</label>
          <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <input type="number" name="home_score_override" placeholder="Total local" value="<?= htmlspecialchars($matchToEdit['home_score'] ?? '') ?>" style="flex:1; min-width:140px;">
            <input type="number" name="away_score_override" placeholder="Total visitante" value="<?= htmlspecialchars($matchToEdit['away_score'] ?? '') ?>" style="flex:1; min-width:140px;">
          </div>
        </div>
      </div>

      <!-- Notas generales -->
      <div class="form-group">
        <label>Notas generales del partido</label>
        <textarea name="notes" rows="4"><?= $matchToEdit ? htmlspecialchars($matchToEdit['notes']) : '' ?></textarea>
      </div>

      <!-- Medios -->
      <div class="form-group">
        <label>Adjuntar imágenes o vídeos (opcional)</label>
        <input type="file" name="media[]" id="media-input" accept="image/*,video/*" multiple style="padding:8px;">
        <div class="form-text" style="font-size:14px; color:var(--gray); margin-top:6px;">
          Imágenes hasta 5MB, vídeos hasta 50MB.
        </div>
        <div id="media-preview" class="media-preview"></div>

        <?php if (!empty($existing_media_for_match)): ?>
          <div style="margin-top: 16px;">
            <strong>Archivos ya subidos:</strong>
            <?php foreach ($existing_media_for_match as $mm): ?>
              <?php if ($mm['media_type'] === 'image'): ?>
                <img src="<?= htmlspecialchars(url($mm['thumb_path'] ?: $mm['file_name'])) ?>" alt="">
              <?php elseif ($mm['media_type'] === 'video'): ?>
                <video controls poster="<?= $mm['thumb_path'] ? '/' . htmlspecialchars($mm['thumb_path']) : '' ?>" style="width:100%; max-width:300px; border-radius:8px; margin-top:10px;"></video>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Botón final -->
      <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
        <?= $editId ? 'Actualizar partido' : 'Crear partido' ?>
      </button>
    </form>
  </div>

  <!-- JS LIGERO (sin Bootstrap) -->
  <script>
    function removeSlotHandler(e) {
      const slot = e.target.closest('.slot');
      if (slot && confirm('¿Eliminar este jugador?')) slot.remove();
    }
    document.querySelectorAll('.remove-player').forEach(btn => btn.addEventListener('click', removeSlotHandler));
  </script>

  <!-- Preview de medios -->
  <script>
    document.getElementById('media-input')?.addEventListener('change', function(e){
      const preview = document.getElementById('media-preview');
      preview.innerHTML = '';
      const files = Array.from(e.target.files);
      files.forEach(f => {
        const container = document.createElement('div');
        if (f.type.startsWith('image/')) {
          const img = document.createElement('img');
          const reader = new FileReader();
          reader.onload = ev => img.src = ev.target.result;
          reader.readAsDataURL(f);
          container.appendChild(img);
        }
        preview.appendChild(container);
      });
    });
  </script>
  <!-- Modal: Añadir jugador rápido -->
<div id="add-player-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; padding:20px;">
  <div style="background:white; border-radius:16px; padding:24px; max-width:500px; margin:40px auto;">
    <h3 style="margin-top:0;">Añadir jugador</h3>
    <p><strong>Equipo:</strong> <span id="modal-team-name">--</span></p>
    <input type="hidden" id="modal-team-id">
    <input type="text" id="modal-number" placeholder="Nº dorsal (obligatorio)" style="width:100%; padding:14px; font-size:18px; margin:12px 0; border:1px solid #ccc; border-radius:8px;">
    <input type="text" id="modal-name" placeholder="Nombre (opcional)" style="width:100%; padding:14px; font-size:18px; margin:12px 0; border:1px solid #ccc; border-radius:8px;">
    <div style="display:flex; gap:12px; margin-top:16px;">
      <button id="modal-save" class="btn btn-primary" style="flex:1;">Añadir jugador</button>
      <button id="modal-cancel" class="btn btn-secondary" style="flex:1;">Cancelar</button>
    </div>
  </div>
</div>

<script>
// Variables globales
let currentTeamForModal = null;

// Abrir modal desde botón "Añadir jugador"
document.getElementById('add-home-player').addEventListener('click', () => {
  const teamSelect = document.getElementById('home_team_id');
  const teamId = teamSelect.value;
  const teamName = teamSelect.options[teamSelect.selectedIndex]?.text || 'Local';
  if (!teamId) {
    alert('Selecciona primero el equipo local.');
    return;
  }
  openAddPlayerModal(teamId, teamName);
});

document.getElementById('add-away-player').addEventListener('click', () => {
  const teamSelect = document.getElementById('away_team_id');
  const teamId = teamSelect.value;
  const teamName = teamSelect.options[teamSelect.selectedIndex]?.text || 'Visitante';
  if (!teamId) {
    alert('Selecciona primero el equipo visitante.');
    return;
  }
  openAddPlayerModal(teamId, teamName);
});

function openAddPlayerModal(teamId, teamName) {
  currentTeamForModal = teamId;
  document.getElementById('modal-team-id').value = teamId;
  document.getElementById('modal-team-name').textContent = teamName;
  document.getElementById('modal-number').value = '';
  document.getElementById('modal-name').value = '';
  document.getElementById('add-player-modal').style.display = 'block';
}

document.getElementById('modal-cancel').addEventListener('click', () => {
  document.getElementById('add-player-modal').style.display = 'none';
});

document.getElementById('modal-save').addEventListener('click', async () => {
  const number = document.getElementById('modal-number').value.trim();
  const name = document.getElementById('modal-name').value.trim();
  const teamId = document.getElementById('modal-team-id').value;

  if (!number) {
    alert('El número de dorsal es obligatorio.');
    return;
  }

  try {
    const res = await fetch('<?= htmlspecialchars(url('ajax_create_player.php')) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        team_id: teamId,
        number: number,
        name: name,
        csrf_token: '<?= htmlspecialchars($csrf) ?>'
      })
    });

    const data = await res.json();
    if (data.success) {
      // Añadir opción al select del equipo correspondiente
      const option = document.createElement('option');
      option.value = data.player.id;
      option.textContent = data.player.label;
      
      const isHome = (document.getElementById('home_team_id').value == teamId);
      const selects = isHome
        ? document.querySelectorAll('select[name="home_player_id[]"]')
        : document.querySelectorAll('select[name="away_player_id[]"]');
      
      selects.forEach(sel => sel.appendChild(option.cloneNode(true)));

      document.getElementById('add-player-modal').style.display = 'none';
      alert('Jugador añadido correctamente.');
    } else {
      alert('Error: ' + (data.error || 'No se pudo crear el jugador.'));
    }
  } catch (e) {
    alert('Error de conexión.');
  }
});
</script>
  
  
</body>
</html>