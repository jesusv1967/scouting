<?php
// public/scouting.php — Scouting cualitativo final con ✔️ y toggle
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
      font-size: 20px;
      background: #222;
      color: white;
      border: 1px solid #444;
      border-radius: 10px;
      cursor: pointer;
    }
    .dorsal-btn.active { background: #2563eb; border-color: #1d4ed8; }

    .accordion {
      margin-bottom: 16px;
      border: 1px solid #444;
      border-radius: 12px;
      overflow: hidden;
    }
    .accordion-header {
      padding: 14px;
      background: #222;
      font-size: 20px;
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
      padding: 16px;
      font-size: 18px;
      border: 2px solid #555;
      border-radius: 12px;
      color: white;
      background: #333;
      cursor: pointer;
      text-align: left;
      position: relative;
    }
    .note-btn.active {
      border-color: #fff;
      background: #444;
    }
    .note-btn .check {
      display: none;
      position: absolute;
      top: 8px;
      right: 10px;
      font-size: 24px;
      color: #4ade80;
      font-weight: bold;
    }
    .note-btn.active .check { display: block; }

    #free-note-input {
      width: 100%;
      padding: 14px;
      font-size: 18px;
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
  background: #2563eb;
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

  </style>
</head>
<body>
<a href="<?= htmlspecialchars(url('matches.php')) ?>" class="back-to-app-btn">⬅️ Partidos</a>
  

  <div class="team-selector">
    <button class="team-btn active" data-team="home"><?= $home_name ?></button>
    <button class="team-btn" data-team="away"><?= $away_name ?></button>
  </div>

  <div class="dorsal-buttons" id="dorsal-buttons"></div>

  <div class="accordion active" data-category="mano">
    <div class="accordion-header">
      <span>👁️ Mano dominante</span>
      <span>▼</span>
    </div>
    <div class="accordion-content">
      <div class="note-buttons">
        <button class="note-btn" data-category="mano" data-value="zurdo">👁️ Zurdo<span class="check">✔</span></button>
        <button class="note-btn" data-category="mano" data-value="diestro">🖐️ Derecho<span class="check">✔</span></button>
        <button class="note-btn" data-category="mano" data-value="ambidiestro">⚖️ Ambidiestro<span class="check">✔</span></button>
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
        <button class="note-btn" data-category="ataque" data-value="tira_bien">🎯 Tira bien<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="penetra">🏀 Penetra bien<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="corre_ca">🏃‍♂️ Corre CA<span class="check">✔</span></button>
        <button class="note-btn" data-category="ataque" data-value="pasa_bien">🤲 Pasa bien<span class="check">✔</span></button>
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
        <button class="note-btn" data-category="defensa" data-value="agresiva">🛡️ Agresiva<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="ayudas">🤝 Hace ayudas<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="flojo">🕳️ Flojo<span class="check">✔</span></button>
        <button class="note-btn" data-category="defensa" data-value="muchas_faltas">⚠️ Muchas faltas<span class="check">✔</span></button>
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
    const matchId = <?= (int)$match_id ?>;
    const homeTeamId = <?= (int)$match['home_team_id'] ?>;
    const awayTeamId = <?= (int)$match['away_team_id'] ?>;
    const homeDorsales = <?= json_encode($home_dorsales) ?>;
    const awayDorsales = <?= json_encode($away_dorsales) ?>;
    const homeName = <?= json_encode($home_name) ?>;
    const awayName = <?= json_encode($away_name) ?>;

    let currentTeam = 'home';
    let currentDorsal = '';
    let currentObservations = { mano: null, ataque: [], defensa: [], nota: '' };

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
          selectPlayer(num);
        };
        dorsalButtons.appendChild(btn);
      });
    }

    // --- Seleccionar jugador y cargar observaciones ---
    async function selectPlayer(dorsal) {
      currentDorsal = dorsal;
      try {
        const res = await fetch(`get_observations.php?match_id=${matchId}&team=${currentTeam}&dorsal=${encodeURIComponent(dorsal)}`);
        const obs = await res.json();
        currentObservations = { mano: null, ataque: [], defensa: [], nota: '' };
        obs.forEach(o => {
          if (o.category === 'nota') {
            currentObservations.nota = o.value;
          } else if (o.category === 'mano') {
            currentObservations.mano = o.value;
          } else if (o.category === 'ataque' || o.category === 'defensa') {
            if (!Array.isArray(currentObservations[o.category])) {
              currentObservations[o.category] = [];
            }
            currentObservations[o.category].push(o.value);
          }
        });
        noteInput.value = currentObservations.nota;
        updateButtonStates();
      } catch (e) {
        currentObservations = { mano: null, ataque: [], defensa: [], nota: '' };
        noteInput.value = '';
        updateButtonStates();
      }
    }

    // --- Actualizar estado visual de botones ---
    function updateButtonStates() {
      document.querySelectorAll('.note-btn').forEach(btn => {
        const cat = btn.dataset.category;
        const val = btn.dataset.value;
        let isActive = false;

        if (cat === 'mano') {
          isActive = (currentObservations.mano === val);
        } else if (cat === 'ataque' || cat === 'defensa') {
          isActive = Array.isArray(currentObservations[cat]) && currentObservations[cat].includes(val);
        }

        if (isActive) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
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
        currentObservations = { mano: null, ataque: [], defensa: [], nota: '' };
        noteInput.value = '';
      });
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

        if (category === 'mano') {
          // Sobrescribe
          currentObservations.mano = value;
          logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → Mano: ${btn.textContent.replace('✔', '').trim()}`;
          await saveObservation(category, value, logText);
        } else if (category === 'ataque' || category === 'defensa') {
          // Toggle
          const idx = currentObservations[category].indexOf(value);
          if (idx === -1) {
            currentObservations[category].push(value);
            logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → + ${btn.textContent.replace('✔', '').trim()}`;
            await saveObservation(category, value, logText);
          } else {
            currentObservations[category].splice(idx, 1);
            logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → – ${btn.textContent.replace('✔', '').trim()}`;
            await deleteObservation(category, value, logText);
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
      const logText = `[${currentTeam === 'home' ? homeName : awayName}] ${currentDorsal} → Nota guardada`;
      await saveObservation('nota', note, logText);
    });

    // --- Funciones de red ---
    async function saveObservation(category, value, logText) {
      log.innerHTML = logText + '<br>' + log.innerHTML;
      try {
        await fetch('save_observation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            match_id: matchId,
            team: currentTeam,
            dorsal: currentDorsal,
            category: category,
            value: value,
            csrf_token: '<?= csrf_token() ?>'
          })
        });
      } catch (e) {
        log.innerHTML = '⚠️ Error de red<br>' + log.innerHTML;
      }
    }

    async function deleteObservation(category, value, logText) {
      log.innerHTML = logText + '<br>' + log.innerHTML;
      try {
        await fetch('delete_observation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            match_id: matchId,
            team: currentTeam,
            dorsal: currentDorsal,
            category: category,
            value: value,
            csrf_token: '<?= csrf_token() ?>'
          })
        });
      } catch (e) {
        log.innerHTML = '⚠️ Error al eliminar<br>' + log.innerHTML;
      }
    }

    // --- Inicio ---
    renderDorsalButtons('home');
  </script>
</body>
</html>