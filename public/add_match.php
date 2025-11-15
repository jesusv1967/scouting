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
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="<?=htmlspecialchars($csrf)?>">
  <title><?= $editId ? 'Editar partido' : 'Añadir partido' ?> - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
  <style>
    .quarter-input { width:80px; }
    .team-column { border-radius:8px; background: #fff; padding:12px; }
    .slot { margin-bottom:10px; }
    .media-preview img, .media-preview video { max-width:160px; border-radius:6px; margin-right:8px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<div class="container py-4">
  <a href="<?=htmlspecialchars(url('dashboard.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver al dashboard</a>
  <a href="<?=htmlspecialchars(url('matches.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver a Partidos</a>
  <h2><?= $editId ? 'Editar partido' : 'Añadir partido' ?></h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Partido guardado correctamente.</div>
  <?php endif; ?>

  <!-- Note: enctype required for file upload -->
  <form method="post" enctype="multipart/form-data" id="match-form">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
    <?php if ($editId): ?><input type="hidden" name="id" value="<?=htmlspecialchars($editId)?>"><?php endif; ?>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Temporada</label>
        <select name="season_id" class="form-select">
          <option value="">--</option>
          <?php foreach($seasons as $s): ?>
            <option value="<?=htmlspecialchars($s['id'])?>" <?=($matchToEdit && $matchToEdit['season_id'] == $s['id']) ? 'selected' : ''?>><?=htmlspecialchars($s['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Categoría</label>
        <select name="category_id" class="form-select">
          <option value="">--</option>
          <?php foreach($categories as $c): ?>
            <option value="<?=htmlspecialchars($c['id'])?>" <?=($matchToEdit && $matchToEdit['category_id'] == $c['id']) ? 'selected' : ''?>><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha y hora</label>
        <input type="datetime-local" name="date" class="form-control" required value="<?= $matchToEdit ? date('Y-m-d\TH:i', strtotime($matchToEdit['date'])) : '' ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Local</label>
        <select name="home_team_id" id="home_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['home_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Visitante</label>
        <select name="away_team_id" id="away_team_id" class="form-select" required>
          <option value="">--</option>
          <?php foreach($teams as $t): ?>
            <option value="<?=htmlspecialchars($t['id'])?>" <?=($matchToEdit && $matchToEdit['away_team_id'] == $t['id']) ? 'selected' : ''?>><?=htmlspecialchars($t['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php
// === INICIO BLOQUE JUGADORES: pegar aquí reemplazando el comentario "scores, players, etc..." ===
?>
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="team-names-row d-flex justify-content-between align-items-center mb-2">
      <div class="team-name"><?= htmlspecialchars($homeTeamName ?: 'Local') ?></div>
      <div class="team-name text-end"><?= htmlspecialchars($awayTeamName ?: 'Visitante') ?></div>
    </div>
  </div>

  <!-- HOME -->
  <div class="col-md-6">
    <div class="team-column">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Jugadores - Local</h6>
        <div class="small-action">
          <button type="button" id="add-home-player" class="btn btn-sm btn-outline-secondary">Añadir jugador</button>
        </div>
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
        <div class="slot d-flex gap-2 align-items-start" data-index="<?= $i ?>">
          <div style="flex:1">
            <select name="home_player_id[]" class="form-select starter-select mb-1">
              <option value="">-- seleccionar jugador --</option>
              <?php
              $hid = $matchToEdit['home_team_id'] ?? $homeTeamId;
              // si el preset no está en players_by_team[hid] lo añadimos como opción seleccionada
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

            <input type="text" name="home_player_custom_number[]" class="form-control mb-1" placeholder="Nº (opcional)" value="<?=htmlspecialchars($preset_num)?>">
            <input type="text" name="home_player_custom_name[]" class="form-control mb-1" placeholder="Nombre (opcional)" value="<?=htmlspecialchars(trim(($preset_first . ' ' . $preset_last) ?: ''))?>">
            <textarea name="player_notes_home[]" class="form-control mb-1" rows="2" placeholder="Notas jugador..."><?=htmlspecialchars($preset_notes)?></textarea>
          </div>

          <div style="width:110px; display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
            <!-- indexed starter input -->
            <input type="hidden" name="home_is_starter[<?= $i ?>]" value="0">
            <label class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="home_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
              <span class="form-check-label">Titular</span>
            </label>
            <button type="button" class="btn btn-sm btn-outline-danger remove-player mt-2">Eliminar</button>
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <!-- AWAY -->
  <div class="col-md-6">
    <div class="team-column">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Jugadores - Visitante</h6>
        <div class="small-action">
          <button type="button" id="add-away-player" class="btn btn-sm btn-outline-secondary">Añadir jugador</button>
        </div>
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
        <div class="slot d-flex gap-2 align-items-start" data-index="<?= $i ?>">
          <div style="flex:1">
            <select name="away_player_id[]" class="form-select starter-select mb-1">
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

            <input type="text" name="away_player_custom_number[]" class="form-control mb-1" placeholder="Nº (opcional)" value="<?=htmlspecialchars($preset_num)?>">
            <input type="text" name="away_player_custom_name[]" class="form-control mb-1" placeholder="Nombre (opcional)" value="<?=htmlspecialchars(trim(($preset_first . ' ' . $preset_last) ?: ''))?>">
            <textarea name="player_notes_away[]" class="form-control mb-1" rows="2" placeholder="Notas jugador..."><?=htmlspecialchars($preset_notes)?></textarea>
          </div>

          <div style="width:110px; display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
            <!-- indexed starter input -->
            <input type="hidden" name="away_is_starter[<?= $i ?>]" value="0">
            <label class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="away_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
              <span class="form-check-label">Titular</span>
            </label>
            <button type="button" class="btn btn-sm btn-outline-danger remove-player mt-2">Eliminar</button>
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<script>
/* JS para añadir/eliminar slots dinámicamente y mantener índices de starter */
(function(){
  function optionsFromSelect(selectElem) {
    return Array.from(selectElem.options).map(o => `<option value="${o.value}">${o.text}</option>`).join('');
  }

  const homeList = document.getElementById('home-players-list');
  const awayList = document.getElementById('away-players-list');
  const addHomeBtn = document.getElementById('add-home-player');
  const addAwayBtn = document.getElementById('add-away-player');

  // counters init from existing slots so index names don't collide
  let homeCounter = homeList ? homeList.querySelectorAll('.slot').length : 0;
  let awayCounter = awayList ? awayList.querySelectorAll('.slot').length : 0;

  function createPlayerSlot(teamPrefix, playersHtml, index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'slot d-flex gap-2 align-items-start';
    wrapper.dataset.index = index;
    wrapper.innerHTML = `
      <div style="flex:1">
        <select name="${teamPrefix}_player_id[]" class="form-select starter-select mb-1">
          <option value="">-- seleccionar jugador --</option>
          ${playersHtml || ''}
        </select>
        <input type="text" name="${teamPrefix}_player_custom_number[]" class="form-control mb-1" placeholder="Nº (opcional)">
        <input type="text" name="${teamPrefix}_player_custom_name[]" class="form-control mb-1" placeholder="Nombre (opcional)">
        <textarea name="${teamPrefix === 'home' ? 'player_notes_home[]' : 'player_notes_away[]'}" class="form-control mb-1" rows="2" placeholder="Notas jugador..."></textarea>
      </div>
      <div style="width:110px; display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
        <input type="hidden" name="${teamPrefix}_is_starter[${index}]" value="0">
        <label class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" name="${teamPrefix}_is_starter[${index}]" value="1">
          <span class="form-check-label">Titular</span>
        </label>
        <button type="button" class="btn btn-sm btn-outline-danger remove-player mt-2">Eliminar</button>
      </div>`;
    return wrapper;
  }

  function removeSlotHandler(e) {
    const slot = e.target.closest('.slot');
    if (!slot) return;
    if (!confirm('Eliminar este jugador de la lista?')) return;
    slot.remove();
  }

  if (addHomeBtn) addHomeBtn.addEventListener('click', () => {
    const firstSelect = homeList.querySelector('select[name="home_player_id[]"]');
    const opts = firstSelect ? optionsFromSelect(firstSelect) : '';
    const node = createPlayerSlot('home', opts, homeCounter);
    homeList.appendChild(node);
    node.querySelector('.remove-player').addEventListener('click', removeSlotHandler);
    homeCounter++;
  });

  if (addAwayBtn) addAwayBtn.addEventListener('click', () => {
    const firstSelect = awayList.querySelector('select[name="away_player_id[]"]');
    const opts = firstSelect ? optionsFromSelect(firstSelect) : '';
    const node = createPlayerSlot('away', opts, awayCounter);
    awayList.appendChild(node);
    node.querySelector('.remove-player').addEventListener('click', removeSlotHandler);
    awayCounter++;
  });

  document.querySelectorAll('.remove-player').forEach(btn => btn.addEventListener('click', removeSlotHandler));
})();
</script>
<?php
// === FIN BLOQUE JUGADORES ===
?>

    <!-- Notas generales -->
    <div class="mb-3">
      <label class="form-label">Notas generales del partido</label>
      <textarea name="notes" class="form-control" rows="4"><?= $matchToEdit ? htmlspecialchars($matchToEdit['notes']) : '' ?></textarea>
    </div>

    <!-- Media upload -->
    <div class="mb-3">
      <label class="form-label">Adjuntar imágenes o vídeos (opcional)</label>
      <input type="file" name="media[]" id="media-input" class="form-control" accept="image/*,video/*" multiple>
      <div class="form-text">Imágenes hasta 5MB, vídeos hasta 50MB. Se crearán miniaturas para imágenes.</div>

      <!-- Client-side preview -->
      <div id="media-preview" class="mt-2 d-flex flex-wrap gap-2"></div>

      <?php if (!empty($existing_media_for_match)): ?>
        <div class="mt-3">
          <small class="text-muted">Archivos ya subidos:</small>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($existing_media_for_match as $mm): ?>
              <div style="max-width:160px;">
                <?php if ($mm['media_type'] === 'image'): ?>
                 <img src="<?= htmlspecialchars(url($mm['thumb_path'] ?: $mm['file_name'])) ?>" style="width:100%; border-radius:6px;" alt="<?=htmlspecialchars($mm['original_name'] ?? '')?>">
                <?php elseif ($mm['media_type'] === 'video'): ?>
                  <video src="/<?=htmlspecialchars($mm['file_name'])?>" controls style="width:100%; border-radius:6px;" <?= $mm['thumb_path'] ? 'poster="/' . htmlspecialchars($mm['thumb_path']) . '"' : '' ?>></video>
                <?php else: ?>
                  <a href="/<?=htmlspecialchars($mm['file_name'])?>" target="_blank"><?=htmlspecialchars($mm['original_name'] ?: $mm['file_name'])?></a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <div class="mt-3">
      <button class="btn btn-primary"><?= $editId ? 'Actualizar partido' : 'Crear partido' ?></button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const rosterModalEl = document.getElementById('teamRosterModal');
  if (!rosterModalEl) return;
  const rosterModal = new bootstrap.Modal(rosterModalEl);
  const rosterList = document.getElementById('roster-list');
  const rosterTeamName = document.getElementById('roster-team-name');
  const rosterTeamIdInput = document.getElementById('roster-team-id');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // botón(s) para abrir: añadimos dinámicamente dos botones junto a los selects home/away
  function ensureRosterButtons() {
    ['home_team_id','away_team_id'].forEach(id => {
      const sel = document.getElementById(id);
      if (!sel) return;
      // evita añadir botón duplicado
      if (sel.nextElementSibling && sel.nextElementSibling.classList && sel.nextElementSibling.classList.contains('btn-roster')) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-secondary btn-sm ms-2 btn-roster';
      btn.textContent = 'Plantilla';
      btn.title = 'Gestionar plantilla';
      sel.parentNode.appendChild(btn);
      btn.addEventListener('click', () => openRosterForSelect(sel));
    });
  }

  // abrir modal para el team select
  function openRosterForSelect(selectElem) {
    const teamId = selectElem.value;
    const teamName = selectElem.options[selectElem.selectedIndex] ? selectElem.options[selectElem.selectedIndex].text : 'Equipo';
    if (!teamId) {
      if (!confirm('No has seleccionado equipo. ¿Abrir igualmente para escoger equipo manualmente?')) return;
    }
    rosterTeamName.textContent = teamName || 'Equipo';
    rosterTeamIdInput.value = teamId || '';
    loadRoster(teamId);
    rosterModal.show();
  }

  // carga jugadores del equipo mediante ajax_players.php (reusar endpoint)
  async function loadRoster(teamId) {
    rosterList.innerHTML = '<div class="text-muted small">Cargando...</div>';
    if (!teamId) {
      rosterList.innerHTML = '<div class="text-muted small">Selecciona un equipo primero.</div>';
      return;
    }
    try {
      const res = await fetch('<?= htmlspecialchars(url('ajax_players.php')) ?>?team_id=' + encodeURIComponent(teamId), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Error al cargar players: ' + res.status);
      const players = await res.json();
      if (!Array.isArray(players)) {
        rosterList.innerHTML = '<div class="text-danger small">Respuesta inesperada</div>';
        return;
      }
      if (players.length === 0) {
        rosterList.innerHTML = '<div class="text-muted small">Sin jugadores para este equipo.</div>';
        return;
      }
      // mostrar lista
      rosterList.innerHTML = '<div class="list-group"></div>';
      const list = rosterList.querySelector('.list-group');
      players.forEach(p => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';
        item.innerHTML = `<div>${escapeHtml(p.label)}</div><div><button type="button" class="btn btn-sm btn-outline-primary btn-insert" data-id="${p.id}" data-label="${escapeHtml(p.label)}">Insertar</button></div>`;
        list.appendChild(item);
      });
      // attach insert handlers: insert adds option to all relevant selects and selects it in the active slot if any
      list.querySelectorAll('.btn-insert').forEach(b => {
        b.addEventListener('click', (e) => {
          const id = e.currentTarget.dataset.id;
          const label = e.currentTarget.dataset.label;
          // insert option into all selects for that team (home or away)
          insertPlayerOptionToSelects(teamId, id, label);
        });
      });
    } catch (err) {
      rosterList.innerHTML = '<div class="text-danger small">Error: ' + escapeHtml(err.message) + '</div>';
      console.error(err);
    }
  }

  // inserta opción en selects del equipo (home or away selects that match team id)
  function insertPlayerOptionToSelects(teamId, playerId, label) {
    // for each column, find the select elements for that team (the first select in each slot has options for that team)
    // We'll add option to all selects with name home_player_id[] where the referenced team equals teamId (by checking matchToEdit or current home_team_id)
    // Simpler: add to every select that currently contains options for that team (option text contains team players)
    const homeTeamSel = document.getElementById('home_team_id');
    const awayTeamSel = document.getElementById('away_team_id');

    // Determine which column to update by comparing teamId to home_team_id / away_team_id values
    let column = null;
    if (homeTeamSel && String(homeTeamSel.value) === String(teamId)) column = 'home';
    if (awayTeamSel && String(awayTeamSel.value) === String(teamId)) column = 'away';
    // If neither matches, we still update both columns (useful when opening modal without team selected)
    const selectors = [];
    if (column === 'home' || column === null) selectors.push('select[name="home_player_id[]"]');
    if (column === 'away' || column === null) selectors.push('select[name="away_player_id[]"]');

    selectors.forEach(selQuery => {
      document.querySelectorAll(selQuery).forEach(sel => {
        // avoid duplicating option
        if (sel.querySelector('option[value="' + playerId + '"]')) return;
        const opt = document.createElement('option');
        opt.value = playerId;
        opt.textContent = label;
        // append at end
        sel.appendChild(opt);
      });
    });
    // optional: close modal
    rosterModal.hide();
  }

  // ajax create player form submit
  const addForm = document.getElementById('roster-add-form');
  addForm?.addEventListener('submit', async function(e){
    e.preventDefault();
    const teamId = rosterTeamIdInput.value || document.getElementById('home_team_id')?.value || document.getElementById('away_team_id')?.value;
    if (!teamId) {
      alert('Selecciona primero el equipo (home o away).');
      return;
    }
    const number = document.getElementById('roster-number').value.trim();
    const name = document.getElementById('roster-name').value.trim();
    if (number === '' && name === '') {
      alert('Rellena número o nombre.');
      return;
    }
    try {
      const body = new URLSearchParams();
      body.append('team_id', teamId);
      body.append('number', number);
      body.append('name', name);
      body.append('csrf_token', csrfToken);

      const res = await fetch('<?= htmlspecialchars(url('ajax_create_player.php')) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error((data && data.error) ? data.error : 'Error al crear jugador');
      }
      const p = data.player;
      // add to selects
      insertPlayerOptionToSelects(teamId, p.id, p.label);
      // clear form
      document.getElementById('roster-number').value = '';
      document.getElementById('roster-name').value = '';
      // reload roster list to include the new player
      loadRoster(teamId);
    } catch (err) {
      alert('Error: ' + err.message);
      console.error(err);
    }
  });

  // small helper
  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // run on load
  ensureRosterButtons();
})();
</script>
<script>
  // Preview for media input
  document.getElementById('media-input')?.addEventListener('change', function(e){
    const preview = document.getElementById('media-preview');
    preview.innerHTML = '';
    const files = Array.from(e.target.files).slice(0, 12);
    files.forEach(f => {
      const container = document.createElement('div');
      container.style.width = '160px';
      container.style.marginRight = '8px';
      if (f.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.style.width = '160px'; img.style.height = '100px'; img.style.objectFit = 'cover';
        const reader = new FileReader();
        reader.onload = (ev) => img.src = ev.target.result;
        reader.readAsDataURL(f);
        container.appendChild(img);
      } else if (f.type.startsWith('video/')) {
        const v = document.createElement('video');
        v.style.width = '160px'; v.style.height = '100px';
        v.controls = false; v.muted = true; v.playsInline = true;
        const reader = new FileReader();
        reader.onload = (ev) => { v.src = ev.target.result; };
        reader.readAsDataURL(f);
        container.appendChild(v);
      } else {
        container.textContent = f.name;
      }
      preview.appendChild(container);
    });
  });
</script>

<!-- Modal: Gestionar plantilla de equipo -->
<div class="modal fade" id="teamRosterModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Gestionar plantilla</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <strong id="roster-team-name">Equipo</strong>
        </div>

        <div id="roster-list" class="mb-3">
          <!-- lista de jugadores cargada por JS -->
          <div class="text-muted small">Cargando...</div>
        </div>

        <hr>
        <h6>Añadir jugador</h6>
        <form id="roster-add-form">
          <input type="hidden" name="team_id" id="roster-team-id" value="">
          <div class="row g-2">
            <div class="col-4">
              <input name="number" id="roster-number" class="form-control" placeholder="Nº">
            </div>
            <div class="col-8">
              <input name="name" id="roster-name" class="form-control" placeholder="Nombre (Nombre Apellido)">
            </div>
          </div>
          <div class="mt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Crear jugador</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

</body>
</html>