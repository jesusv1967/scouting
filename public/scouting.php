<?php
// public/scouting.php — Scouting cualitativo en vivo (observaciones de jugador)
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$match_id = $_GET['match_id'] ?? null;
if (!$match_id || !is_numeric($match_id)) {
    die('Partido no especificado.');
}

$stmt = $pdo->prepare("SELECT home_team_id, away_team_id FROM matches WHERE id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch();
if (!$match) {
    die('Partido no encontrado.');
}

// Obtener dorsales registrados
function getMatchDorsals($pdo, $match_id, $team_id) {
    $sql = "SELECT DISTINCT p.number
            FROM match_players mp
            LEFT JOIN players p ON mp.player_id = p.id
            WHERE mp.match_id = ? AND (mp.team_id = ? OR p.team_id = ?)
            AND p.number IS NOT NULL AND p.number != ''
            ORDER BY (p.number+0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$match_id, $team_id, $team_id]);
    return array_column($stmt->fetchAll(), 'number');
}

$home_dorsales = getMatchDorsals($pdo, $match_id, $match['home_team_id']);
$away_dorsales = getMatchDorsals($pdo, $match_id, $match['away_team_id']);
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
      overflow: hidden;
    }
    .header {
      text-align: center;
      font-size: 30px;
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
      font-size: 24px;
      font-weight: bold;
      background: #333;
      color: white;
      border: none;
      border-radius: 14px;
      cursor: pointer;
    }
    .team-btn.active { background: #2563eb; }
    .dorsal-buttons {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 8px;
      margin-bottom: 20px;
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
    .section {
      margin: 16px 0;
    }
    .section-title {
      font-size: 20px;
      margin-bottom: 12px;
      color: #ddd;
    }
    .note-buttons {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .note-btn {
      padding: 16px;
      font-size: 18px;
      border: none;
      border-radius: 12px;
      color: white;
      cursor: pointer;
      text-align: left;
    }
    .btn-mano      { background: #6366f1; } /* Índigo */
    .btn-ataque    { background: #10b981; } /* Verde */
    .btn-rebote    { background: #f59e0b; } /* Ámbar */
    .btn-trans     { background: #8b5cf6; } /* Púrpura */
    .btn-defensa   { background: #ef4444; } /* Rojo */
    .btn-actitud   { background: #ec4899; } /* Rosa */
    .btn-fisico    { background: #0ea5e9; } /* Azul */
    .log {
      position: absolute;
      bottom: 12px;
      left: 12px;
      right: 12px;
      background: rgba(0,0,0,0.8);
      padding: 12px;
      font-size: 16px;
      border-radius: 10px;
      max-height: 80px;
      overflow: auto;
      color: #ccc;
    }
  </style>
</head>
<body>
  <div class="header">Scouting Cualitativo</div>

  <div class="team-selector">
    <button class="team-btn active" data-team="home">LOCAL</button>
    <button class="team-btn" data-team="away">VISITANTE</button>
  </div>

  <div class="dorsal-buttons" id="dorsal-buttons">
    <!-- Se llenará con JS -->
  </div>

  <!-- Mano dominante -->
  <div class="section">
    <div class="section-title">Mano dominante</div>
    <div class="note-buttons">
      <button class="note-btn btn-mano" data-type="mano" data-value="zurdo">👁️ Zurdo</button>
      <button class="note-btn btn-mano" data-type="mano" data-value="diestro">🖐️ Derecho</button>
    </div>
  </div>

  <!-- Ataque -->
  <div class="section">
    <div class="section-title">Ataque</div>
    <div class="note-buttons">
      <button class="note-btn btn-ataque" data-type="ataque" data-value="tira_bien">🎯 Tira bien</button>
      <button class="note-btn btn-ataque" data-type="ataque" data-value="penetra">🏀 Penetra</button>
      <button class="note-btn btn-ataque" data-type="ataque" data-value="no_finaliza">🔄 No finaliza</button>
    </div>
  </div>

  <!-- Rebote -->
  <div class="section">
    <div class="section-title">Rebote</div>
    <div class="note-buttons">
      <button class="note-btn btn-rebote" data-type="rebote" data-value="fuerte">🧱 Rebotea fuerte</button>
      <button class="note-btn btn-rebote" data-type="rebote" data-value="debil">🕊️ Débil en rebote</button>
    </div>
  </div>

  <!-- Transición -->
  <div class="section">
    <div class="section-title">Contraataque</div>
    <div class="note-buttons">
      <button class="note-btn btn-trans" data-type="transicion" data-value="corre">🏃‍♂️ Corre CA</button>
      <button class="note-btn btn-trans" data-type="transicion" data-value="no_corre">🚶‍♂️ No corre</button>
    </div>
  </div>

  <!-- Defensa -->
  <div class="section">
    <div class="section-title">Defensa</div>
    <div class="note-buttons">
      <button class="note-btn btn-defensa" data-type="defensa" data-value="buena">🛡️ Buen defensor</button>
      <button class="note-btn btn-defensa" data-type="defensa" data-value="floja">🕳️ Flojo en defensa</button>
    </div>
  </div>

  <!-- Actitud -->
  <div class="section">
    <div class="section-title">Actitud</div>
    <div class="note-buttons">
      <button class="note-btn btn-actitud" data-type="actitud" data-value="comprometido">🔥 Comprometido</button>
      <button class="note-btn btn-actitud" data-type="actitud" data-value="pasivo">😐 Pasivo</button>
    </div>
  </div>

  <!-- Físico -->
  <div class="section">
    <div class="section-title">Físico</div>
    <div class="note-buttons">
      <button class="note-btn btn-fisico" data-type="fisico" data-value="fuerte">💪 Fuerte</button>
      <button class="note-btn btn-fisico" data-type="fisico" data-value="fragil">🦴 Frágil</button>
    </div>
  </div>

  <div class="log" id="event-log">Tus observaciones aparecerán aquí...</div>

  <script>
    const matchId = <?= (int)$match_id ?>;
    const homeDorsales = <?= json_encode($home_dorsales) ?>;
    const awayDorsales = <?= json_encode($away_dorsales) ?>;
    
    let currentTeam = 'home';
    let currentDorsal = '';
    const log = document.getElementById('event-log');
    const dorsalButtons = document.getElementById('dorsal-buttons');

    function renderDorsalButtons(team) {
      const dorsales = team === 'home' ? homeDorsales : awayDorsales;
      dorsalButtons.innerHTML = '';
      dorsales.forEach(num => {
        const btn = document.createElement('button');
        btn.className = 'dorsal-btn';
        btn.textContent = num;
        btn.onclick = () => {
          document.querySelectorAll('.dorsal-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          currentDorsal = num;
        };
        dorsalButtons.appendChild(btn);
      });
      if (dorsales.length === 0) {
        dorsalButtons.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#888;">Selecciona equipo con jugadores</div>';
      }
    }

    document.querySelectorAll('.team-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.team-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentTeam = btn.dataset.team;
        renderDorsalButtons(currentTeam);
        currentDorsal = '';
        document.querySelectorAll('.dorsal-btn').forEach(b => b.classList.remove('active'));
      });
    });

    document.querySelectorAll('.note-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!currentDorsal) {
          alert('Selecciona un dorsal primero');
          return;
        }
        const type = btn.dataset.type;
        const value = btn.dataset.value;
        const label = btn.textContent.trim();
        const event = `[${currentTeam === 'home' ? 'L' : 'V'}] ${currentDorsal} → ${label}`;
        log.innerHTML = event + '<br>' + log.innerHTML;

        try {
          await fetch('save_player_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              match_id: matchId,
              team: currentTeam,
              dorsal: currentDorsal,
              note_type: type,
              note_value: value,
              csrf_token: '<?= csrf_token() ?>'
            })
          });
        } catch (e) {
          log.innerHTML = '⚠️ Error de red<br>' + log.innerHTML;
        }
      });
    });

    renderDorsalButtons('home');
  </script>
</body>
</html>