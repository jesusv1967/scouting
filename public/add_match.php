<?php
// public/add_match.php
// Fichero completo corregido: guarda team_id en match_players, garantiza que los players referenciados
// estén presentes en players_by_team para que siempre aparezcan en los selects al editar.
// También mantiene la lista dinámica de jugadores (titulares/suplentes) y PRG.
//
// Corregido: los campos "is_starter" usan indices por slot (home_is_starter[0], ...) para mantener alineación.

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$errors = [];
$success = '';

$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Helpers
function safe_int($v) {
    $v = trim((string)$v);
    return $v === '' ? null : (int)$v;
}

// helper: comprueba si player_id está ya en el array de jugadores pasados
function player_in_team_list($players_arr, $player_id) {
    if (empty($players_arr) || !$player_id) return false;
    foreach ($players_arr as $p) {
        if ((int)$p['id'] === (int)$player_id) return true;
    }
    return false;
}

// POST -> crear/actualizar partido y jugadores asociados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido.';
    } else {
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
            // Cuartos
            $home_q = $_POST['home_q'] ?? [];
            $away_q = $_POST['away_q'] ?? [];
            $quarters = [];
            $maxQ = max(count($home_q), count($away_q));
            for ($i = 0; $i < $maxQ; $i++) {
                $h = isset($home_q[$i]) ? trim($home_q[$i]) : '';
                $a = isset($away_q[$i]) ? trim($away_q[$i]) : '';
                $quarters[] = [
                    'home' => ($h === '' ? null : (int)$h),
                    'away' => ($a === '' ? null : (int)$a),
                ];
            }
            // Totales (o override)
            $home_total = 0; $away_total = 0;
            foreach ($quarters as $q) {
                if (is_int($q['home'])) $home_total += $q['home'];
                if (is_int($q['away'])) $away_total += $q['away'];
            }
            $home_score = (isset($_POST['home_score_override']) && $_POST['home_score_override'] !== '') ? safe_int($_POST['home_score_override']) : ($home_total ?: null);
            $away_score = (isset($_POST['away_score_override']) && $_POST['away_score_override'] !== '') ? safe_int($_POST['away_score_override']) : ($away_total ?: null);

            // Arrays dinámicos de jugadores (por equipo)
            $home_player_ids = $_POST['home_player_id'] ?? [];
            $home_custom_numbers = $_POST['home_player_custom_number'] ?? [];
            $home_custom_names = $_POST['home_player_custom_name'] ?? [];
            $home_notes = $_POST['player_notes_home'] ?? [];
            // ahora home_is_starter es un array asociativo indexado por slot: home_is_starter[0] = 1/0
            $home_is_starter = $_POST['home_is_starter'] ?? [];

            $away_player_ids = $_POST['away_player_id'] ?? [];
            $away_custom_numbers = $_POST['away_player_custom_number'] ?? [];
            $away_custom_names = $_POST['away_player_custom_name'] ?? [];
            $away_notes = $_POST['player_notes_away'] ?? [];
            $away_is_starter = $_POST['away_is_starter'] ?? [];

            try {
                $pdo->beginTransaction();

                // Insert/Update match
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

                // Borrar todos los match_players de este partido y reinsertar
                $del = $pdo->prepare("DELETE FROM match_players WHERE match_id = ?");
                $del->execute([$match_id]);

                // Resolver/crear jugador helper (devuelve player_id)
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
                        // crear jugador nuevo con team_id si está disponible
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

                // Insertar jugadores home (guardando team_id). Nos aseguramos de obtener starterFlag por índice de slot.
                $countHome = max( count($home_player_ids), count($home_custom_numbers), count($home_custom_names), count($home_notes) );
                for ($i = 0; $i < $countHome; $i++) {
                    $pid = $home_player_ids[$i] ?? null;
                    $cnum = $home_custom_numbers[$i] ?? '';
                    $cname = $home_custom_names[$i] ?? '';
                    $pnotes = trim($home_notes[$i] ?? '');
                    // starterFlag ahora se lee por índice: si existe home_is_starter[$i] => 1, else 0
                    $starterFlag = isset($home_is_starter[$i]) ? (int)$home_is_starter[$i] : 0;
                    $resolved = $resolvePlayer($home, $pid, $cnum, $cname);
                    if ($resolved) {
                        $ins = $pdo->prepare("INSERT INTO match_players (match_id, player_id, team_id, is_starter, notes) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$match_id, $resolved, $home ?: null, $starterFlag ? 1 : 0, $pnotes ?: null]);
                    }
                }

                // Insertar jugadores away (guardando team_id)
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

                $pdo->commit();
                // PRG para recargar la ficha y evitar reenvío
                header('Location: ' . url('add_match.php') . '?id=' . $match_id . '&saved=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Error guardando partido: ' . $e->getMessage();
            }
        }
    }
}

// GET -> cargar partido si existe
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

// Datos para selects
$seasons = $pdo->query("SELECT id, name FROM seasons ORDER BY start_date DESC")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();

// Cargar jugadores por equipo (players_by_team)
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

// Cargar match_players existentes y separarlos por equipo para pre-poblar slots
$home_existing = [];
$away_existing = [];
if ($editId) {
    // usamos resolved_team_id = COALESCE(mp.team_id, p.team_id)
    $ps = $pdo->prepare("SELECT mp.*, COALESCE(mp.team_id, p.team_id) AS resolved_team_id, p.number, p.first_name, p.last_name FROM match_players mp LEFT JOIN players p ON mp.player_id = p.id WHERE mp.match_id = ? ORDER BY mp.id ASC");
    $ps->execute([$editId]);
    $rows = $ps->fetchAll();

    // primero separamos en home/away según resolved_team_id, y recolectamos players referenciados
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
            // si no coincide con ninguno, lo añadimos a home por defecto para que no desaparezca
            $home_existing[] = $r;
        }
        // track para asegurarnos de que el jugador esté en players_by_team
        if ($pid) {
            $pid_to_resolved_team[(int)$pid] = $resolved_team;
            if ($resolved_team && !player_in_team_list($players_by_team[$resolved_team] ?? [], $pid)) {
                $missing_player_ids[] = (int)$pid;
            }
        }
    }

    // Si hay players faltantes, los recuperamos y los añadimos a players_by_team[resolved_team]
    if (!empty($missing_player_ids)) {
        $placeholders = implode(',', array_fill(0, count($missing_player_ids), '?'));
        $ps2 = $pdo->prepare("SELECT id, team_id, number, first_name, last_name FROM players WHERE id IN ($placeholders)");
        $ps2->execute($missing_player_ids);
        $fetched = $ps2->fetchAll();
        foreach ($fetched as $p) {
            $pid = (int)$p['id'];
            $resolved_team = $pid_to_resolved_team[$pid] ?? ($p['team_id'] ?? null);
            if (!$resolved_team) {
                $resolved_team = $matchToEdit['home_team_id'] ?? null;
            }
            if (!isset($players_by_team[$resolved_team])) $players_by_team[$resolved_team] = [];
            if (!player_in_team_list($players_by_team[$resolved_team], $pid)) {
                $players_by_team[$resolved_team][] = $p;
            }
        }
    }
}

// nombres de equipos (para mostrar encima de columnas)
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
  <title><?= $editId ? 'Editar partido' : 'Añadir partido' ?> - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
  <style>
    .starter-select, .starter-custom { min-height:44px; }
    .quarter-input { width:80px; }
    .team-column { border-radius:8px; background: #fff; padding:12px; box-shadow: 0 6px 18px rgba(11,37,64,0.04); }
    .slot { margin-bottom:10px; }
    .team-names-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; }
    .team-name { font-weight:700; font-size:0.98rem; color:var(--text); }
    .small-action { font-size:0.9rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<div class="container py-4">
  <a href="<?=htmlspecialchars(url('matches.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver a Partidos</a>
  <h2><?= $editId ? 'Editar partido' : 'Añadir partido' ?></h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Partido guardado correctamente.</div>
  <?php endif; ?>

  <form method="post" id="match-form">
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

      <div class="col-md-6">
        <label class="form-label">Competición</label>
        <input name="competition" class="form-control" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['competition']) : '' ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Sede</label>
        <input name="venue" class="form-control" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['venue']) : '' ?>">
      </div>
    </div>

    <!-- Scores -->
    <div class="mb-4">
      <h5>Puntuaciones por cuartos</h5>
      <div id="quarters-area" class="mb-2">
        <?php
        $displayQuarters = max(4, count($quarters_existing));
        for ($i = 0; $i < $displayQuarters; $i++):
            $h = $quarters_existing[$i]['home'] ?? '';
            $a = $quarters_existing[$i]['away'] ?? '';
        ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <div class="me-2" style="min-width:120px;"><strong>Q<?=($i+1)?></strong></div>
            <input type="number" name="home_q[]" class="form-control quarter-input" placeholder="Local" value="<?=htmlspecialchars($h)?>">
            <div class="px-2">—</div>
            <input type="number" name="away_q[]" class="form-control quarter-input" placeholder="Visitante" value="<?=htmlspecialchars($a)?>">
          </div>
        <?php endfor; ?>
      </div>
      <div class="d-flex gap-2">
        <button type="button" id="add-ot" class="btn btn-outline-secondary btn-sm">Añadir OT</button>
        <button type="button" id="reset-quarters" class="btn btn-outline-secondary btn-sm">Reset Qs</button>
      </div>

      <div class="mt-3">
        <label class="form-label">Resultado final (si deseas sustituir el calculado)</label>
        <div class="d-flex gap-2 align-items-center">
          <input type="number" name="home_score_override" class="form-control quarter-input" placeholder="Local" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['home_score']) : '' ?>">
          <div class="px-2">—</div>
          <input type="number" name="away_score_override" class="form-control quarter-input" placeholder="Visitante" value="<?= $matchToEdit ? htmlspecialchars($matchToEdit['away_score']) : '' ?>">
          <div class="form-text text-muted-sm ms-2">Si dejas vacío se sumarán los cuartos</div>
        </div>
      </div>
    </div>

    <!-- Jugadores -->
    <div class="row g-3 mb-3">
      <div class="col-12">
        <div class="team-names-row">
          <div class="team-name"><?= htmlspecialchars($homeTeamName ?: 'Local') ?></div>
          <div class="team-name text-end"><?= htmlspecialchars($awayTeamName ?: 'Visitante') ?></div>
        </div>
      </div>

      <!-- HOME column -->
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
                <!-- changed: indexed starter input -->
                <input type="hidden" name="home_is_starter[<?= $i ?>]" value="0">
                <label class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="home_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
                  <span class="form-check-label">Titular</span>
                </label>
                <button type="button" class="btn btn-sm btn-outline-danger remove-player">Eliminar</button>
              </div>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <!-- AWAY column -->
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
                <!-- changed: indexed starter input -->
                <input type="hidden" name="away_is_starter[<?= $i ?>]" value="0">
                <label class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="away_is_starter[<?= $i ?>]" value="1" <?= $preset_is_starter ? 'checked' : '' ?>>
                  <span class="form-check-label">Titular</span>
                </label>
                <button type="button" class="btn btn-sm btn-outline-danger remove-player">Eliminar</button>
              </div>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Notas generales -->
    <div class="mb-3">
      <label class="form-label">Notas generales del partido</label>
      <textarea name="notes" class="form-control" rows="4"><?= $matchToEdit ? htmlspecialchars($matchToEdit['notes']) : '' ?></textarea>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary"><?= $editId ? 'Actualizar partido' : 'Crear partido' ?></button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // Quarters helpers
  const quartersArea = document.getElementById('quarters-area');
  const addOtBtn = document.getElementById('add-ot');
  if (addOtBtn) {
    addOtBtn.addEventListener('click', () => {
      const existing = quartersArea.querySelectorAll('div.d-flex').length;
      const idx = existing;
      const wrapper = document.createElement('div');
      wrapper.className = 'd-flex align-items-center gap-2 mb-2';
      wrapper.innerHTML = `<div class="me-2" style="min-width:120px;"><strong>OT${idx - 3}</strong></div>
        <input type="number" name="home_q[]" class="form-control quarter-input" placeholder="Local">
        <div class="px-2">—</div>
        <input type="number" name="away_q[]" class="form-control quarter-input" placeholder="Visitante">`;
      quartersArea.appendChild(wrapper);
    });
  }

  const resetQsBtn = document.getElementById('reset-quarters');
  if (resetQsBtn) {
    resetQsBtn.addEventListener('click', () => {
      if (!confirm('Resetear valores de cuartos a vacío?')) return;
      const inputs = quartersArea.querySelectorAll('input[type="number"]');
      inputs.forEach(i => i.value = '');
    });
  }

  // Templates for adding player slots (home/away) with indexed starter names
  function createPlayerSlot(teamPrefix, playersHtml, index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'slot d-flex gap-2 align-items-start';
    wrapper.dataset.index = String(index);
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
        <label class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="${teamPrefix}_is_starter[${index}]" value="1">
          <span class="form-check-label">Titular</span>
        </label>
        <button type="button" class="btn btn-sm btn-outline-danger remove-player">Eliminar</button>
      </div>`;
    return wrapper;
  }

  function optionsFromSelect(selectElem) {
    return Array.from(selectElem.options).map(o => `<option value="${o.value}">${o.text}</option>`).join('');
  }

  const addHomeBtn = document.getElementById('add-home-player');
  const addAwayBtn = document.getElementById('add-away-player');
  const homeList = document.getElementById('home-players-list');
  const awayList = document.getElementById('away-players-list');

  // counters start from existing number of slots to keep indices aligned
  let homeCounter = homeList ? homeList.querySelectorAll('.slot').length : 0;
  let awayCounter = awayList ? awayList.querySelectorAll('.slot').length : 0;

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

  function removeSlotHandler(e) {
    const slot = e.target.closest('.slot');
    if (!slot) return;
    if (!confirm('Eliminar este jugador de la lista?')) return;
    slot.remove();
  }
  document.querySelectorAll('.remove-player').forEach(btn => btn.addEventListener('click', removeSlotHandler));

  // Note: we still use AJAX to refresh players when team changes (keeps inputs)
  // Keep existing AJAX handlers you added for team change; they are compatible.

})();
</script>
</body>
</html>