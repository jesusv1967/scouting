<?php
// public/scouting.php — Scouting cualitativo con soporte offline
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$match_id = $_GET['match_id'] ?? null;
if (!$match_id || !is_numeric($match_id)) {
    die('Partido no especificado.');
}

$stmt = $pdo->prepare("
    SELECT m.home_team_id, m.away_team_id, 
           th.name AS home_name, ta.name AS away_name
    FROM matches m
    LEFT JOIN teams th ON m.home_team_id = th.id
    LEFT JOIN teams ta ON m.away_team_id = ta.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();
if (!$match) {
    die('Partido no encontrado.');
}

function getMatchDorsalsSorted($pdo, $match_id, $team_id) {
    $sql = "SELECT DISTINCT p.number
            FROM match_players mp
            LEFT JOIN players p ON mp.player_id = p.id
            WHERE mp.match_id = ? AND (mp.team_id = ? OR p.team_id = ?)
            AND p.number IS NOT NULL AND p.number != ''
            ORDER BY (p.number+0), p.number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$match_id, $team_id, $team_id]);
    return array_column($stmt->fetchAll(), 'number');
}

$home_dorsales = getMatchDorsalsSorted($pdo, $match_id, $match['home_team_id']);
$away_dorsales = getMatchDorsalsSorted($pdo, $match_id, $match['away_team_id']);

$home_name = htmlspecialchars($match['home_name'] ?: 'Local');
$away_name = htmlspecialchars($match['away_name'] ?: 'Visitante');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Scouting Cualitativo</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_asset('css/styles.css')) ?>">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      background: #000;
      color: white;
      font-family: -apple-system, sans-serif;
      padding: 12px;
      height: 100vh;
      overflow: auto;
    }
    .header {
      text-align: center;
      font-size: 28px;
      font-weight: bold;
      padding: 16px 0;
      background: #111;
      border-radius: 16px;
      margin-bottom: 16px;
    }
    .offline-badge {
      position: fixed;
      top: 12px;
      right: 12px;
      background: #dc2626;
      color: white;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 14px;
      z-index: 200;
      display: none;
    }
    .team-selector {
      display: flex;
      gap: 10px;
      margin-bottom: 16px;
    }
    .team-btn {
      flex: 1;
      padding: 18px;
      font-size: 22px;
      font-weight: bold;
      background: #333;
      color: white;
      border: none;
      border-radius: 14px;
      cursor: pointer;
      text-align: center;
    }
    .team-btn.active { background: #2563eb; }
    .dorsal-buttons {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 8px;
      margin-bottom: 16px;
    }
    .dorsal-btn {
      padding: 14px 0;
      font-size: 28px;
      background: #222;
      color: white;
      border: 1px solid #444;
      border-radius: 10px;
      cursor: pointer;
    }
    .dorsal-btn.active { background: #2563eb; border-color: #1d4ed8; }

    .dorsal-input-area {
      margin-bottom: 16px;
    }
    .dorsal-input {
      width: 100%;
      padding: 16px;
      font-size: 28px;
      text-align: center;
      border: 2px solid #444;
      border-radius: 14px;
      background: #222;
      color: white;
      margin-bottom: 10px;
    }
    .add-player-btn {
      width: 100%;
      padding: 14px;
      font-size: 28px;
      font-weight: bold;
      background: #6b7280;
      color: white;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      margin-bottom: 8px;
    }
    .delete-player-btn {
      width: 100%;
      padding: 14px;
      font-size: 28px;
      font-weight: bold;
      background: #dc2626;
      color: white;
      border: none;
      border-radius: 12px;
      cursor: pointer;
    }

    .accordion {
      margin-bottom: 16px;
      border: 1px solid #444;
      border-radius: 12px;
      overflow: hidden;
    }
    .accordion-header {
      padding: 14px;
      background: #222;
      font-size: 30px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .accordion-content {
      display: none;
      padding: 16px;
      background: #1a1a1a;
    }
    .accordion.active .accordion-content { display: block; }
    .accordion.active .accordion-header { background: #2563eb; }

    .note-buttons {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
    .note-btn {
      padding: 24px 20px;
      font-size: 30px;
      line-height: 1.3;
      border: 2px solid #555;
      border-radius: 14px;
      color: white;
      background: #333;
      cursor: pointer;
      text-align: left;
      position: relative;
      min-height: 90px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .note-btn.active {
      border-color: #fff;
      background: #444;
    }
    .note-btn .check {
      display: none;
      position: absolute;
      top: 12px;
      right: 14px;
      font-size: 28px;
      color: #4ade80;
      font-weight: bold;
    }
    .note-btn.active .check { display: block; }

    #free-note-input {
      width: 100%;
      padding: 14px;
      font-size: 26px;
      border-radius: 12px;
      border: 1px solid #444;
      background: #222;
      color: white;
      margin-top: 8px;
      min-height: 60px;
    }
    .log {
      margin-top: 20px;
      background: rgba(0,0,0,0.7);
      padding: 12px;
      font-size: 16px;
      border-radius: 10px;
      max-height: 90px;
      overflow: auto;
      color: #ccc;
    }
    
    .back-to-app-btn {
      position: fixed;
      top: 12px;
      left: 12px;
      background: #f59e0b;
      color: white;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 12px;
      font-weight: bold;
      font-size: 18px;
      z-index: 100;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .back-to-app-btn:hover {
      background: #1d4ed8;
    }
    
    .team-notes-btn {
      position: fixed;
      top: 12px;
      right: 12px;
      background: #f59e0b;
      color: white;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 12px;
      font-weight: bold;
      font-size: 16px;
      z-index: 100;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
  </style>
</head>
<body>
<div class="offline-badge" id="offline-badge">💾 Datos pendientes</div>
<a href="<?= htmlspecialchars(url('matches.php')) ?>" class="back-to-app-btn">⬅️ Partidos</a>
<a href="<?= htmlspecialchars(url('match_notes.php?match_id=' . $match_id)) ?>" class="team-notes-btn">📝 Equipo</a>

  <div class="team-selector">
    <button class="team-btn active" data-team="home"><?= $home_name ?></button>
    <button class="team-btn" data-team="away"><?= $away_name ?></button>
  </div>

  <div class="dorsal-buttons" id="dorsal-buttons"></div>

  <div class="dorsal-input-area">
    <input type="text" id="dorsal-new" class="dorsal-input" placeholder="Nº nuevo (ej. 25)" inputmode="numeric" autocomplete="off">
    <button id="add-player-btn" class="add-player-btn">➕ Añadir jugador</button>
    <button id="delete-player-btn" class="delete-player-btn">🗑️ Eliminar jugador</button>
  </div>


  <!-- Botón para titular -->
  <div style="text-align: center; margin: 20px 0;">
    <button id="toggle-starter-btn" style="padding: 14px 24px; font-size: 22px; font-weight: bold; border: none; border-radius: 12px; background: #6b7280; color: white; cursor: pointer;">
      👕 Marcar como titular
    </button>
  </div>

  <div class="accordion active" data-category="mano">
    <div class="accordion-header">
      <span>👁️ Mano dominante</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="mano" data-value="zurdo">👁️ Zurdo<span class="check">✔</span></button>
        <button class="note-btn" data-category="mano" data-value="diestro">🖐️ Diestro<span class="check">✔</span></button>
        <button class="note-btn" data-category="mano" data-value="ambidiestro">🤲 Ambidiestro<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="habilidad">
    <div class="accordion-header">
      <span>🧩 Habilidades</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="habilidad" data-value="no_bota">🤝 No bota<span class="check">✔</span></button>
        <button class="note-btn" data-category="habilidad" data-value="juega_sin">🚶‍♂️ Juega sin balón<span class="check">✔</span></button>
        <button class="note-btn" data-category="habilidad" data-value="bloquea">🧍‍♂️ Bloquea<span class="check">✔</span></button>
        <button class="note-btn" data-category="habilidad" data-value="finta">🏃‍♂️ Finta tiro<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="ataque">
    <div class="accordion-header">
      <span>🏀 Ataque</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="ataque" data-value="bienTL">🏀 Bien TL<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="tira_bien2">🎯 Tira de 2<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="pasa_bien3">🏀 Tira de 3<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="penetra">🏀 Penetra bien<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="corre_ca">🏃‍♂️ Corre CA<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="pasa_bien">🤲 Pasa bien<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="interior">
    <div class="accordion-header">
      <span>🧑‍🤝‍🧑 Juego interior</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="interior" data-value="buenos_mov">🧱 Buenos mov. abajo<span class="check">✔</span></button>
        <button class="note-btn" data-category="interior" data-value="no_juega">⛔ No juega abajo<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="defensa">
    <div class="accordion-header">
      <span>🛡️ Defensa</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="defensa" data-value="agresiva">🛡️ Defensa agresiva<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="ayudas">🤝 Hace ayudas<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="flojo">🕳️ Flojo en defensa<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="muchas_faltas">⚠️ Comete muchas faltas<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="rebote">
    <div class="accordion-header">
      <span>🏀 Rebote</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="rebote" data-value="ofensivo">🏀 Rebote ofensivo<span class="check">✔</span></button>
        <button class="note-btn" data-category="rebote" data-value="defensivo">🛡️ Rebote defensivo<span class="check">✔</span></button>
      </div>
    </div>
  </div>

  <div class="accordion" data-category="nota">
    <div class="accordion-header">
      <span>📝 Notas libres</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <textarea id="free-note-input" placeholder="Escribe tu observación..."></textarea>
      <button id="save-note-btn" style="width:100%; padding:14px; font-size:18px; margin-top:12px; background:#10b981; color:white; border:none; border-radius:12px;">✅ Guardar nota</button>
    </div>
  </div>


  <div class="log" id="event-log">Tus observaciones aparecerán aquí...</div>

  <script>
    // === OFFLINE SUPPORT ===
    const offlineBadge = document.getElementById('offline-badge');

    function showOfflineBadge() {
      offlineBadge.style.display = 'block';
    }

    function hideOfflineBadge() {
      offlineBadge.style.display = 'none';
    }

    function getPendingObservations() {
      return JSON.parse(localStorage.getItem('pending_observations') || '[]');
    }

    function savePendingObservations(pending) {
      localStorage.setItem('pending_observations', JSON.stringify(pending));
      if (pending.length > 0) {
        showOfflineBadge();
      } else {
        hideOfflineBadge();
      }
    }

    async function syncPendingObservations() {
      const pending = getPendingObservations();
      if (pending.length === 0) return;

      for (let obs of pending) {
        try {
          const response = await fetch('<?= htmlspecialchars(url('save_observation.php')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(obs)
          });
          if (!response.ok) throw new Error('HTTP ' + response.status);
        } catch (e) {
          // Si falla uno, paramos y guardamos el resto
          savePendingObservations(pending);
          return;
        }
      }

      // Todo enviado
      savePendingObservations([]);
    }

    // Detectar conexión y sincronizar
    window.addEventListener('online', syncPendingObservations);
    window.addEventListener('load', () => {
      if (navigator.onLine) {
        syncPendingObservations();
      } else {
        showOfflineBadge();
      }
    });

    // === RESTO DEL CÓDIGO (igual que antes) ===
    const matchId = <?= (int)$match_id ?>;
    const homeTeamId = <?= (int)$match['home_team_id'] ?>;
    const awayTeamId = <?= (int)$match['away_team_id'] ?>;
    const homeDorsales = <?= json_encode($home_dorsales) ?>;
    const awayDorsales = <?= json_encode($away_dorsales) ?>;
    const homeName = <?= json_encode($home_name) ?>;
    const awayName = <?= json_encode($away_name) ?>;

    let currentTeam = 'home';
    let currentDorsal = '';
    let currentIsStarter = false;
    let currentObservations = { 
      mano: null, 
      interior: null,
      ataque: [], 
      defensa: [], 
      rebote: [], 
      habilidad: [], 
      nota: '' 
    };

    const log = document.getElementById('event-log');
    const dorsalButtons = document.getElementById('dorsal-buttons');
    const noteInput = document.getElementById('free-note-input');

    // --- Acordeones ---
    document.querySelectorAll('.accordion-header').forEach(header => {
      header.addEventListener('click', () => {
        const accordion = header.parentElement;
        document.querySelectorAll('.accordion').forEach(acc => acc.classList.remove('active'));
        accordion.classList.add('active');
      });
    });

    // --- Cargar dorsales ---
    function renderDorsalButtons(team) {
      const dorsales = team === 'home' ? homeDorsales : awayDorsales;
      dorsalButtons.innerHTML = '';
      if (dorsales.length === 0) {
        dorsalButtons.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#888;">Sin jugadores</div>';
        return;
      }
      dorsales.forEach(num => {
        const btn = document.createElement('button');
        btn.className = 'dorsal-btn';
        btn.textContent = num;
        btn.onclick = () => {
          document.querySelectorAll('.dorsal-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          currentDorsal = num;
          loadObservations(); // Cargar al seleccionar
        };
        dorsalButtons.appendChild(btn);
      });
    }

    // --- Cambio de equipo ---
    document.querySelectorAll('.team-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.team-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentTeam = btn.dataset.team;
        renderDorsalButtons(currentTeam);
        currentDorsal = '';
        document.querySelectorAll('.dorsal-btn').forEach(b => b.classList.remove('active'));
        currentObservations = { 
          mano: null, 
          interior: null,
          ataque: [], 
          defensa: [], 
          rebote: [], 
          habilidad: [], 
          nota: '' 
        };
        currentIsStarter = false;
        noteInput.value = '';
        updateStarterButton();
      });
    });

    // --- Añadir jugador ---
    document.getElementById('add-player-btn').addEventListener('click', async () => {
      const dorsal = document.getElementById('dorsal-new').value.trim();
      if (!dorsal) {
        alert('Introduce un número de dorsal.');
        return;
      }
      const teamId = currentTeam === 'home' ? homeTeamId : awayTeamId;
      
      try {
        const res = await fetch('<?= htmlspecialchars(url('ajax_create_player.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            team_id: teamId,
            number: dorsal,
            name: '',
            match_id: matchId,
            csrf_token: '<?= csrf_token() ?>'
          })
        });
        const data = await res.json();
        if (data.success) {
          if (currentTeam === 'home') {
            if (!homeDorsales.includes(dorsal)) homeDorsales.push(dorsal);
            homeDorsales.sort((a, b) => (parseInt(a) || 0) - (parseInt(b) || 0));
          } else {
            if (!awayDorsales.includes(dorsal)) awayDorsales.push(dorsal);
            awayDorsales.sort((a, b) => (parseInt(a) || 0) - (parseInt(b) || 0));
          }
          renderDorsalButtons(currentTeam);
          document.getElementById('dorsal-new').value = '';
          currentDorsal = dorsal;
          log.innerHTML = `✅ Jugador ${dorsal} añadido.<br>` + log.innerHTML;
        } else {
          alert('Error: ' + (data.error || ''));
        }
      } catch (e) {
        alert('Error de red');
      }
    });

    // --- Eliminar jugador ---
    document.getElementById('delete-player-btn').addEventListener('click', async () => {
      if (!currentDorsal) {
        alert('Selecciona un jugador primero.');
        return;
      }
      if (!confirm(`¿Eliminar al jugador ${currentDorsal} del equipo ${currentTeam === 'home' ? homeName : awayName}?`)) {
        return;
      }

      try {
        const res = await fetch('<?= htmlspecialchars(url('ajax_delete_player_from_match.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            match_id: matchId,
            dorsal: currentDorsal,
            team: currentTeam,
            csrf_token: '<?= csrf_token() ?>'
          })
        });
        const data = await res.json();
        if (data.success) {
          if (currentTeam === 'home') {
            const idx = homeDorsales.indexOf(currentDorsal);
            if (idx !== -1) homeDorsales.splice(idx, 1);
          } else {
            const idx = awayDorsales.indexOf(currentDorsal);
            if (idx !== -1) awayDorsales.splice(idx, 1);
          }
          renderDorsalButtons(currentTeam);
          const dorsalToDelete = currentDorsal;
          currentDorsal = '';
          log.innerHTML = `🗑️ Jugador ${dorsalToDelete} eliminado.<br>` + log.innerHTML;
        } else {
          alert('Error: ' + (data.error || ''));
        }
      } catch (e) {
        alert('Error de red');
      }
    });

    // --- Cargar observaciones y titular ---
    async function loadObservations() {
      if (!currentDorsal) return;
      try {
        // Cargar observaciones
        const resObs = await fetch(`<?= htmlspecialchars(url('get_observations.php')) ?>?match_id=${matchId}&team=${currentTeam}&dorsal=${encodeURIComponent(currentDorsal)}`);
        const obs = await resObs.json();
        currentObservations = { 
          mano: null, 
          interior: null,
          ataque: [], 
          defensa: [], 
          rebote: [], 
          habilidad: [], 
          nota: '' 
        };
        obs.forEach(o => {
          if (o.category === 'nota') {
            currentObservations.nota = o.value;
          } else if (o.category === 'mano' || o.category === 'interior') {
            currentObservations[o.category] = o.value;
          } else if (['ataque','defensa','rebote','habilidad'].includes(o.category)) {
            if (!Array.isArray(currentObservations[o.category])) {
              currentObservations[o.category] = [];
            }
            currentObservations[o.category].push(o.value);
          }
        });
        noteInput.value = currentObservations.nota;
        updateButtonStates();

        // Cargar titular
        const resStarter = await fetch(`<?= htmlspecialchars(url('get_starter_status.php')) ?>?match_id=${matchId}&team=${currentTeam}&dorsal=${encodeURIComponent(currentDorsal)}`);
        const starterData = await resStarter.json();
        currentIsStarter = starterData.is_starter || false;
        updateStarterButton();
      } catch (e) {
        currentObservations = { 
          mano: null, 
          interior: null,
          ataque: [], 
          defensa: [], 
          rebote: [], 
          habilidad: [], 
          nota: '' 
        };
        noteInput.value = '';
        currentIsStarter = false;
        updateButtonStates();
        updateStarterButton();
      }
    }

    // --- Estado visual de botones ---
    function updateButtonStates() {
      document.querySelectorAll('.note-btn').forEach(btn => {
        const cat = btn.dataset.category;
        const val = btn.dataset.value;
        let isActive = false;

        if (cat === 'mano' || cat === 'interior') {
          isActive = (currentObservations[cat] === val);
        } else if (['ataque','defensa','rebote','habilidad'].includes(cat)) {
          isActive = Array.isArray(currentObservations[cat]) && currentObservations[cat].includes(val);
        }

        if (isActive) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
    }

    // --- Botón de titular ---
    function updateStarterButton() {
      const btn = document.getElementById('toggle-starter-btn');
      if (!btn) return;
      if (currentIsStarter) {
        btn.textContent = '✅ Es titular';
        btn.style.background = '#10b981';
      } else {
        btn.textContent = '👕 Marcar como titular';
        btn.style.background = '#6b7280';
      }
    }

    document.getElementById('toggle-starter-btn')?.addEventListener('click', async () => {
      if (!currentDorsal) {
        alert('Selecciona un jugador primero.');
        return;
      }
      const newStatus = !currentIsStarter;
      const obs = {
        match_id: matchId,
        team: currentTeam,
        dorsal: currentDorsal,
        is_starter: newStatus,
        csrf_token: '<?= csrf_token() ?>'
      };

      if (navigator.onLine) {
        try {
          await fetch('<?= htmlspecialchars(url('toggle_starter.php')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(obs)
          });
          currentIsStarter = newStatus;
          updateStarterButton();
          log.innerHTML = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → ${newStatus ? 'Marcado como titular' : 'Quitado de titulares'}<br>` + log.innerHTML;
        } catch (e) {
          log.innerHTML = '⚠️ Error al actualizar titular<br>' + log.innerHTML;
        }
      } else {
        // Guardar offline
        const pending = getPendingObservations();
        pending.push({ type: 'starter', ...obs });
        savePendingObservations(pending);
        currentIsStarter = newStatus;
        updateStarterButton();
        log.innerHTML = '💾 Titular guardado offline<br>' + log.innerHTML;
      }
    });

    // --- Manejo de clics en botones ---
    document.querySelectorAll('.note-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!currentDorsal) {
          alert('Selecciona un jugador primero.');
          return;
        }
        const category = btn.dataset.category;
        const value = btn.dataset.value;
        let logText = '';

        const obsData = {
          match_id: matchId,
          team: currentTeam,
          dorsal: currentDorsal,
          category: category,
          value: value,
          csrf_token: '<?= csrf_token() ?>'
        };

        if (category === 'mano' || category === 'interior') {
          const oldValue = currentObservations[category];
          currentObservations[category] = value;
          logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → ${category}: ${btn.textContent.replace('✔', '').trim()}`;
          
          if (navigator.onLine) {
            await saveObservationToServer(obsData, logText);
            if (oldValue !== null && oldValue !== value) {
              await deleteObservationToServer({
                match_id: matchId,
                team: currentTeam,
                dorsal: currentDorsal,
                category: category,
                value: oldValue,
                csrf_token: '<?= csrf_token() ?>'
              }, '');
            }
          } else {
            // Guardar offline
            const pending = getPendingObservations();
            pending.push({ type: 'save', ...obsData });
            if (oldValue !== null && oldValue !== value) {
              pending.push({ type: 'delete', match_id: matchId, team: currentTeam, dorsal: currentDorsal, category: category, value: oldValue, csrf_token: '<?= csrf_token() ?>' });
            }
            savePendingObservations(pending);
            log.innerHTML = '💾 Guardado offline (se enviará al recuperar conexión)<br>' + log.innerHTML;
          }
        } else if (['ataque','defensa','rebote','habilidad'].includes(category)) {
          const idx = currentObservations[category].indexOf(value);
          if (idx === -1) {
            currentObservations[category].push(value);
            logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → + ${btn.textContent.replace('✔', '').trim()}`;
            if (navigator.onLine) {
              await saveObservationToServer(obsData, logText);
            } else {
              const pending = getPendingObservations();
              pending.push({ type: 'save', ...obsData });
              savePendingObservations(pending);
              log.innerHTML = '💾 Guardado offline<br>' + log.innerHTML;
            }
          } else {
            currentObservations[category].splice(idx, 1);
            logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → – ${btn.textContent.replace('✔', '').trim()}`;
            if (navigator.onLine) {
              await deleteObservationToServer(obsData, logText);
            } else {
              const pending = getPendingObservations();
              pending.push({ type: 'delete', ...obsData });
              savePendingObservations(pending);
              log.innerHTML = '💾 Eliminado offline<br>' + log.innerHTML;
            }
          }
        }

        updateButtonStates();
      });
    });

    // --- Notas libres ---
    document.getElementById('save-note-btn').addEventListener('click', async () => {
      const note = noteInput.value.trim();
      if (!note) return;
      currentObservations.nota = note;
      const obsData = {
        match_id: matchId,
        team: currentTeam,
        dorsal: currentDorsal,
        category: 'nota',
        value: note,
        csrf_token: '<?= csrf_token() ?>'
      };

      if (navigator.onLine) {
        await saveObservationToServer(obsData, 'Nota guardada');
      } else {
        const pending = getPendingObservations();
        pending.push({ type: 'save', ...obsData });
        savePendingObservations(pending);
        log.innerHTML = '💾 Nota guardada offline<br>' + log.innerHTML;
      }
    });

    // --- Funciones de red ---
    async function saveObservationToServer(data, logText) {
      log.innerHTML = logText + '<br>' + log.innerHTML;
      try {
        await fetch('<?= htmlspecialchars(url('save_observation.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
      } catch (e) {
        log.innerHTML = '⚠️ Error de red<br>' + log.innerHTML;
        // Guardar offline si falla
        const pending = getPendingObservations();
        pending.push({ type: 'save', ...data });
        savePendingObservations(pending);
      }
    }

    async function deleteObservationToServer(data, logText) {
      if (logText) log.innerHTML = logText + '<br>' + log.innerHTML;
      try {
        await fetch('<?= htmlspecialchars(url('delete_observation.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
      } catch (e) {
        log.innerHTML = '⚠️ Error al eliminar<br>' + log.innerHTML;
        const pending = getPendingObservations();
        pending.push({ type: 'delete', ...data });
        savePendingObservations(pending);
      }
    }

    // --- Inicio ---
    renderDorsalButtons('home');

    // Sincronizar al cargar
    if (navigator.onLine) {
      syncPendingObservations();
    }
  </script>
</body>
</html>