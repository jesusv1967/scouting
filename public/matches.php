<?php
// public/matches.php
// Lista de partidos con soporte para mostrar media (imágenes y vídeos) en el modal.
// Incluye JS robusto para abrir modal al pulsar la fila (delegación) y rutas de media resueltas.

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$errors = [];
$success = '';

// Manejo de borrado por POST (si llega)
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
$sql = "SELECT m.id, m.date, m.competition, m.venue, m.notes, m.home_score, m.away_score,
               m.home_team_id, m.away_team_id,
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
        LIMIT 200";

$stmt = $pdo->query($sql);
$matches = $stmt->fetchAll();

// Recoger roster (titulares + suplentes) para los partidos listados
$match_rosters = []; // match_id => team_id => [ { player_id, label, is_starter, notes } ]
$matchIds = [];
if (count($matches) > 0) {
    $matchIds = array_map(fn($m) => (int)$m['id'], $matches);
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));

    $ps = $pdo->prepare("
      SELECT mp.match_id, mp.team_id, mp.player_id, mp.is_starter, mp.notes,
             p.number, p.first_name, p.last_name
      FROM match_players mp
      LEFT JOIN players p ON mp.player_id = p.id
      WHERE mp.match_id IN ($placeholders)
      ORDER BY mp.match_id, mp.team_id, mp.is_starter DESC, mp.id ASC
    ");
    $ps->execute($matchIds);
    $rows = $ps->fetchAll();

    foreach ($rows as $r) {
        $mid = (int)$r['match_id'];
        $tid = (int)($r['team_id'] ?? 0);
        $label = trim(((string)($r['number'] ?? '') !== '' ? ($r['number'] . ' - ') : '') . trim(($r['last_name'] ?? '') . ' ' . ($r['first_name'] ?? '')));
        if ($label === '') $label = 'Jugador ' . ($r['player_id'] ?? '');
        if (!isset($match_rosters[$mid])) $match_rosters[$mid] = [];
        if (!isset($match_rosters[$mid][$tid])) $match_rosters[$mid][$tid] = [];
        $match_rosters[$mid][$tid][] = [
            'player_id' => (int)$r['player_id'],
            'label' => $label,
            'is_starter' => (int)$r['is_starter'],
            'notes' => $r['notes'] ?? '',
        ];
    }
}

// Recoger media asociado a los partidos listados
$match_media_map = []; // match_id => [ {id,file_name,original_name,mime_type,size,media_type,thumb_path,created_at} ]
if (!empty($matchIds)) {
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $psm = $pdo->prepare("SELECT * FROM match_media WHERE match_id IN ($placeholders) ORDER BY created_at ASC");
    $psm->execute($matchIds);
    $rowsm = $psm->fetchAll();
    foreach ($rowsm as $mm) {
        $mid = (int)$mm['match_id'];
        if (!isset($match_media_map[$mid])) $match_media_map[$mid] = [];
        $match_media_map[$mid][] = $mm;
    }
}

// Resolver rutas a URLs públicas usando helper url()
if (!empty($match_media_map)) {
    foreach ($match_media_map as $mid => &$list) {
        foreach ($list as &$mm) {
            // file_name and thumb_path are stored relative to public/ (e.g. uploads/...)
            $mm['url'] = url($mm['file_name']);
            $mm['thumb_url'] = $mm['thumb_path'] ? url($mm['thumb_path']) : null;
        }
    }
    unset($mm, $list);
}

// Helper para acortar etiqueta (para badges)
function short_label(string $full): string {
    $full = trim($full);
    if ($full === '') return '';
    if (preg_match('/^\s*(\S+)\s*-\s*(.+)$/', $full, $m)) {
        $num = $m[1];
        $rest = $m[2];
        if (preg_match('/^\d+$/', $num)) return $num;
        $first = explode(' ', trim($rest))[0];
        return $first ? $first : $num;
    }
    $first = explode(' ', $full)[0];
    return $first;
}

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
  <style>
    /* Touch-friendly / responsive tweaks */
    .score-badge {
      font-weight:700;
      font-size:1rem;
      padding:8px 12px;
      border-radius:999px;
      display:inline-block;
    }
    .starter-pill {
      display:inline-block;
      margin:0 6px 6px 0;
      padding:6px 10px;
      border-radius:999px;
      font-size:0.88rem;
      color:var(--text);
      border:1px solid rgba(11,37,64,0.06);
    }
    .match-row .notes { max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .match-card { margin-bottom:12px; }
    .match-card .card-body { padding:12px; }
    .match-card .title { font-size:1rem; font-weight:700; }
    .match-card .meta { font-size:0.86rem; color:#666; }
    .touch-btn { min-height:44px; padding:8px 12px; font-size:0.95rem; }
    .modal-media img, .modal-media video { max-width:100%; display:block; margin-bottom:8px; border-radius:6px; }
    .modal-media .media-item { width:100%; max-width:320px; margin-right:8px; }
    @media (max-width: 767px) {
      .starter-pill { font-size:0.82rem; padding:6px 8px; }
      .score-badge { font-size:1.02rem; padding:10px 14px; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<main class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?=htmlspecialchars(url('dashboard.php'))?>" class="btn btn-outline-secondary mb-3">&larr; Volver al dashboard</a>
	<h2 class="h5 mb-0">Partidos</h2>
    <div>
	
      <a href="<?=htmlspecialchars(url('add_match.php'))?>" class="btn btn-primary touch-btn">Nuevo partido</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
  <?php endif; ?>

  <?php if (count($matches) === 0): ?>
    <div class="alert alert-info">No hay partidos grabados todavía.</div>
  <?php else: ?>

    <!-- TABLE: visible en md+ -->
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm align-middle match-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Temporada / Categoría</th>
            <th>Local - Visitante</th>
            <th>Resultado</th>
            <th>Competición / Sede</th>
            <th>Notas</th>
            <th>Creado por</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matches as $m): ?>
            <?php
              $mid = (int)$m['id'];
              $homeScore = $m['home_score'] !== null ? (int)$m['home_score'] : null;
              $awayScore = $m['away_score'] !== null ? (int)$m['away_score'] : null;
              $scoreText = ($homeScore !== null && $awayScore !== null) ? ($homeScore . ' – ' . $awayScore) : '-';
              $winner = null;
              if ($homeScore !== null && $awayScore !== null) {
                if ($homeScore > $awayScore) $winner = 'home';
                elseif ($awayScore > $homeScore) $winner = 'away';
                else $winner = 'tie';
              }
              $home_starters = [];
              $away_starters = [];
              $home_team_id = (int)($m['home_team_id'] ?? 0);
              $away_team_id = (int)($m['away_team_id'] ?? 0);
              if (isset($match_rosters[$mid][$home_team_id])) {
                  foreach ($match_rosters[$mid][$home_team_id] as $pl) {
                      if ($pl['is_starter']) $home_starters[] = $pl['label'];
                  }
              }
              if (isset($match_rosters[$mid][$away_team_id])) {
                  foreach ($match_rosters[$mid][$away_team_id] as $pl) {
                      if ($pl['is_starter']) $away_starters[] = $pl['label'];
                  }
              }
              $home_short = array_map(fn($s) => short_label($s), $home_starters);
              $away_short = array_map(fn($s) => short_label($s), $away_starters);
              $home_title = htmlspecialchars(implode(', ', $home_starters));
              $away_title = htmlspecialchars(implode(', ', $away_starters));
            ?>
            <tr class="match-row" data-match-id="<?= $mid ?>">
              <td style="white-space:nowrap;min-width:110px;">
                <?=htmlspecialchars(date('Y-m-d H:i', strtotime($m['date'])))?>
                <div><small class="text-muted"><?=htmlspecialchars($m['season_name'] ?? '')?></small></div>
              </td>

              <td>
                <div><?=htmlspecialchars($m['season_name'] ?? '-')?></div>
                <div><small class="text-muted"><?=htmlspecialchars($m['category_name'] ?? '-')?></small></div>
              </td>

              <td style="min-width:190px;">
                <div>
                  <strong><?=htmlspecialchars($m['home_name'] ?? '—')?></strong>
                  <span class="text-muted"> vs </span>
                  <strong><?=htmlspecialchars($m['away_name'] ?? '—')?></strong>
                </div>

                <div class="mt-2">
                  <div>
                    <small class="text-muted">Titulares:</small>
                    <?php if (count($home_starters) === 0): ?>
                      <span class="text-muted small">—</span>
                    <?php else: ?>
                      <span title="<?= $home_title ?>">
                        <?php foreach (array_slice($home_short, 0, 4) as $lab): ?>
                          <span class="starter-pill"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($lab) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($home_short) > 4): ?>
                          <span class="starter-pill">+<?= count($home_short) - 4 ?></span>
                        <?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="mt-1">
                    <small class="text-muted">Titulares (V):</small>
                    <?php if (count($away_starters) === 0): ?>
                      <span class="text-muted small">—</span>
                    <?php else: ?>
                      <span title="<?= $away_title ?>">
                        <?php foreach (array_slice($away_short, 0, 4) as $lab): ?>
                          <span class="starter-pill"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($lab) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($away_short) > 4): ?>
                          <span class="starter-pill">+<?= count($away_short) - 4 ?></span>
                        <?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td style="min-width:100px;">
                <?php
                  $badgeClass = 'bg-secondary text-white';
                  if ($winner === 'home') $badgeClass = 'bg-success text-white';
                  elseif ($winner === 'away') $badgeClass = 'bg-danger text-white';
                  elseif ($winner === 'tie') $badgeClass = 'bg-info text-white';
                ?>
                <div class="score-badge <?= $badgeClass ?>"><?=htmlspecialchars($scoreText)?></div>
              </td>

              <td>
                <div><?=htmlspecialchars($m['competition'] ?: '-')?></div>
                <div><small class="text-muted"><?=htmlspecialchars($m['venue'] ?: '-')?></small></div>
              </td>

              <td class="notes"><?=htmlspecialchars($m['notes'] ?? '')?></td>

              <td><?=htmlspecialchars($m['creator'] ?? '-')?></td>

              <td class="text-end">
                <a href="<?=htmlspecialchars(url('add_match.php') . '?id=' . $m['id'])?>" class="btn btn-sm btn-outline-primary me-1" title="Editar"><i class="bi bi-pencil"></i></a>

                <form method="post" class="d-inline-block ms-1" onsubmit="return confirm('¿Eliminar este partido?');" style="margin:0">
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

    <!-- CARDS: visible en sm (movil/tablet) -->
    <div class="d-block d-md-none">
      <?php foreach ($matches as $m): ?>
        <?php
          $mid = (int)$m['id'];
          $homeScore = $m['home_score'] !== null ? (int)$m['home_score'] : null;
          $awayScore = $m['away_score'] !== null ? (int)$m['away_score'] : null;
          $scoreText = ($homeScore !== null && $awayScore !== null) ? ($homeScore . ' – ' . $awayScore) : '-';
          $winner = null;
          if ($homeScore !== null && $awayScore !== null) {
            if ($homeScore > $awayScore) $winner = 'home';
            elseif ($awayScore > $homeScore) $winner = 'away';
            else $winner = 'tie';
          }
          $home_team_id = (int)($m['home_team_id'] ?? 0);
          $away_team_id = (int)($m['away_team_id'] ?? 0);
          $home_starters = isset($match_rosters[$mid][$home_team_id]) ? array_filter($match_rosters[$mid][$home_team_id], fn($p)=>$p['is_starter']) : [];
          $away_starters = isset($match_rosters[$mid][$away_team_id]) ? array_filter($match_rosters[$mid][$away_team_id], fn($p)=>$p['is_starter']) : [];
        ?>
        <div class="card match-card" data-match-id="<?= $mid ?>">
          <div class="card-body d-flex align-items-start">
            <div style="flex:1">
              <div class="title"><?=htmlspecialchars($m['home_name'] ?? '—')?> <span class="text-muted">vs</span> <?=htmlspecialchars($m['away_name'] ?? '—')?></div>
              <div class="meta"><?=htmlspecialchars(date('Y-m-d H:i', strtotime($m['date'])))?> · <?=htmlspecialchars($m['competition'] ?: '')?></div>

              <div class="mt-2">
                <small class="text-muted">Titulares:</small>
                <div class="mt-1">
                  <?php if (count($home_starters) === 0): ?>
                    <div class="text-muted small">—</div>
                  <?php else: ?>
                    <?php foreach (array_slice($home_starters, 0, 3) as $p): ?>
                      <span class="starter-pill"><?=htmlspecialchars(short_label($p['label']))?></span>
                    <?php endforeach; ?>
                    <?php if (count($home_starters) > 3): ?>
                      <span class="starter-pill">+<?= count($home_starters)-3 ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="text-end ms-3">
              <?php
                $badgeClass = 'bg-secondary text-white';
                if ($winner === 'home') $badgeClass = 'bg-success text-white';
                elseif ($winner === 'away') $badgeClass = 'bg-danger text-white';
                elseif ($winner === 'tie') $badgeClass = 'bg-info text-white';
              ?>
              <div class="score-badge <?= $badgeClass ?>"><?=htmlspecialchars($scoreText)?></div>
              <div class="mt-2">
                <a class="btn btn-sm btn-outline-primary touch-btn d-inline-block" href="<?=htmlspecialchars(url('add_match.php') . '?id=' . $m['id'])?>">Editar</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- Modal detalle de partido: fullscreen en md y menores (mejor para tablet/móvil) -->
<div class="modal fade" id="matchDetailModal" tabindex="-1" aria-labelledby="matchDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-md-down modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="matchDetailModalLabel">Detalle partido</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="modal-match-info" class="mb-3"></div>

        <div class="row">
          <div class="col-12 col-md-6">
            <h6 id="modal-home-name"></h6>
            <div id="modal-home-starters" class="mb-2"></div>
            <h6 class="mt-2">Suplentes</h6>
            <div id="modal-home-subs"></div>
          </div>
          <div class="col-12 col-md-6">
            <h6 id="modal-away-name"></h6>
            <div id="modal-away-starters" class="mb-2"></div>
            <h6 class="mt-2">Suplentes</h6>
            <div id="modal-away-subs"></div>
          </div>
        </div>

        <hr>
        <div>
          <h6>Notas del partido</h6>
          <div id="modal-match-notes" class="small text-muted"></div>
        </div>

        <hr>
        <div>
          <h6>Archivos adjuntos</h6>
          <div id="modal-media-list" class="d-flex flex-wrap modal-media gap-2"></div>
        </div>

      </div>
      <div class="modal-footer">
        <a id="modal-edit-link" class="btn btn-primary">Editar partido</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- load bootstrap JS BEFORE the inline script that uses it -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Transfer PHP roster and media data to JS
  const MATCH_ROSTERS = <?= json_encode($match_rosters, JSON_UNESCAPED_UNICODE) ?>;
  const MATCH_MEDIA = <?= json_encode($match_media_map, JSON_UNESCAPED_UNICODE) ?>;
  const MATCH_META = <?= json_encode(array_map(function($m){
      return [
        'id' => (int)$m['id'],
        'home_team_id' => (int)($m['home_team_id'] ?? 0),
        'away_team_id' => (int)($m['away_team_id'] ?? 0),
        'home_name' => $m['home_name'] ?? '',
        'away_name' => $m['away_name'] ?? '',
        'date' => $m['date'] ?? '',
        'home_score' => $m['home_score'] !== null ? (int)$m['home_score'] : null,
        'away_score' => $m['away_score'] !== null ? (int)$m['away_score'] : null,
        'notes' => $m['notes'] ?? '',
      ];
  }, $matches), JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
/*
  JS robusto: delegación para abrir modal al pulsar fila/tarjeta, renderizado defensivo y media urls.
*/
(function(){
  // Esperar al DOM ready (si tu script ya está al final del body no es estrictamente necesario)
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function(){
    if (typeof bootstrap === 'undefined') {
      console.error('Bootstrap JS no está cargado. Asegúrate de incluir bootstrap.bundle.min.js antes de este script.');
      return;
    }
    const modalEl = document.getElementById('matchDetailModal');
    if (!modalEl) {
      console.error('Elemento modal #matchDetailModal no encontrado en el DOM.');
      return;
    }

    // Lazy init
    let bsModal = null;
    function getModal() {
      if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
      return bsModal;
    }

    function escapeHtml(s){
      if (!s) return '';
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function createPlayerHtml(p){
      const notesHtml = p.notes ? `<div class="text-muted small">${escapeHtml(p.notes)}</div>` : '';
      const starBadge = p.is_starter ? '<span class="badge bg-primary me-1">Titular</span>' : '';
      return `<div class="mb-2"><div>${starBadge}<strong>${escapeHtml(p.label)}</strong></div>${notesHtml}</div>`;
    }

    function renderMatchIntoModal(matchId){
      const meta = (typeof MATCH_META !== 'undefined') ? MATCH_META.find(m => m.id === matchId) : null;
      const rosters = (typeof MATCH_ROSTERS !== 'undefined') ? (MATCH_ROSTERS[matchId] || {}) : {};
      const media = (typeof MATCH_MEDIA !== 'undefined') ? (MATCH_MEDIA[matchId] || []) : [];
      const homeId = meta ? meta.home_team_id : null;
      const awayId = meta ? meta.away_team_id : null;

      document.getElementById('modal-match-info').innerHTML = `<div class="small text-muted">${meta ? escapeHtml(meta.date) : ''}</div>`;
      document.getElementById('modal-home-name').textContent = meta ? meta.home_name : 'Local';
      document.getElementById('modal-away-name').textContent = meta ? meta.away_name : 'Visitante';
      document.getElementById('modal-match-notes').textContent = meta ? meta.notes : '';

      const homeRoster = rosters[homeId] || [];
      const awayRoster = rosters[awayId] || [];

      const hStar = homeRoster.filter(p => p.is_starter);
      const hSub  = homeRoster.filter(p => !p.is_starter);
      const aStar = awayRoster.filter(p => p.is_starter);
      const aSub  = awayRoster.filter(p => !p.is_starter);

      document.getElementById('modal-home-starters').innerHTML = hStar.length ? hStar.map(createPlayerHtml).join('') : '<div class="text-muted small">Sin titulares</div>';
      document.getElementById('modal-home-subs').innerHTML     = hSub.length  ? hSub.map(createPlayerHtml).join('')  : '<div class="text-muted small">Sin suplentes</div>';
      document.getElementById('modal-away-starters').innerHTML = aStar.length ? aStar.map(createPlayerHtml).join('') : '<div class="text-muted small">Sin titulares</div>';
      document.getElementById('modal-away-subs').innerHTML     = aSub.length  ? aSub.map(createPlayerHtml).join('')  : '<div class="text-muted small">Sin suplentes</div>';

      const mediaContainer = document.getElementById('modal-media-list');
      if (mediaContainer) {
        mediaContainer.innerHTML = '';
        media.forEach(m => {
          const wrapper = document.createElement('div');
          wrapper.className = 'media-item';
          if (m.media_type === 'image') {
            const img = document.createElement('img');
            img.src = m.thumb_url ? m.thumb_url : (m.thumb_path ? (m.thumb_path[0] === '/' ? m.thumb_path : ('/' + m.thumb_path)) : (m.url ? m.url : ('/' + m.file_name)));
            img.alt = m.original_name || '';
            wrapper.appendChild(img);
          } else if (m.media_type === 'video') {
            const v = document.createElement('video');
            v.controls = true;
            v.src = m.url ? m.url : ('/' + m.file_name);
            if (m.thumb_url) v.poster = m.thumb_url;
            wrapper.appendChild(v);
          } else {
            const a = document.createElement('a');
            a.href = m.url ? m.url : ('/' + m.file_name);
            a.textContent = m.original_name || m.file_name;
            a.target = '_blank';
            wrapper.appendChild(a);
          }
          mediaContainer.appendChild(wrapper);
        });
      }

      const editLink = document.getElementById('modal-edit-link');
      if (editLink && meta) editLink.href = '<?= htmlspecialchars(url('add_match.php')) ?>?id=' + matchId;
    }

    // Delegated click handler
    document.body.addEventListener('click', function(e){
      // don't open modal if clicking on interactive elements
      if (e.target.closest('a,button,form,input,select,textarea')) return;
      const row = e.target.closest('.match-row, .match-card');
      if (!row) return;
      const matchId = row.dataset.matchId ? parseInt(row.dataset.matchId, 10) : null;
      if (!matchId) return;
      try {
        renderMatchIntoModal(matchId);
        getModal().show();
      } catch (err) {
        console.error('Error al abrir modal de partido:', err);
      }
    });

    // debug helpers
    window._debugMatches = {
      countRows: function(){ return document.querySelectorAll('.match-row').length; },
      hasModal: function(){ return !!document.getElementById('matchDetailModal'); },
      isBootstrap: function(){ return typeof bootstrap !== 'undefined'; }
    };

    console.log('Match modal handler inicializado. Filas detectadas:', document.querySelectorAll('.match-row, .match-card').length);
  });
})();
</script>
</body>
</html>