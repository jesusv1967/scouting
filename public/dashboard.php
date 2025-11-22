<?php
// public/dashboard.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  
    <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  
  
  
  
  <title>Dashboard - Scouting</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_asset('css/styles.css')) ?>">
  
  
  
  
  
  
  <style>
    body {
      padding: 0 12px 24px;
      background-color: var(--light);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .dashboard-grid {
      display: grid;
      gap: 24px;
      max-width: 1200px;
      margin: 0 auto;
    }
    .dashboard-grid { grid-template-columns: 1fr; }
    @media (min-width: 768px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .dashboard-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (min-width: 1400px) { .dashboard-grid { grid-template-columns: repeat(5, 1fr); } }

    .dashboard-card {
      display: block;
      text-decoration: none;
      color: var(--dark);
      background: white;
      border-radius: 20px;
      padding: 36px 24px;
      text-align: center;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      border: 1px solid var(--border);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dashboard-card:hover, .dashboard-card:focus {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }
    .dashboard-card .icon {
      font-size: 48px;
      margin-bottom: 18px;
      color: var(--primary);
    }
    .dashboard-card h3 {
      font-size: 38px;
      margin: 0 0 14px;
      font-weight: 700;
      line-height: 1.3;
      color: var(--primary);
    }
    .dashboard-card p {
      font-size: 20px;
      line-height: 1.5;
      color: var(--gray);
      margin: 0;
      font-weight: 500;
    }
    main {
      max-width: 1200px;
      margin: 0 auto;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

  <main>
    <h1 style="text-align: center; font-size: 34px; margin: 20px 0 32px; color: var(--primary); font-weight: 700;">
      Dashboard
    </h1>
<div class="dashboard-grid">
      <a href="<?= htmlspecialchars(url('matches.php')) ?>" class="dashboard-card">
        <div class="icon">📋</div>
        <h3>Partidos</h3>
        <p>Ver, editar y eliminar partidos</p>
      </a>
      <a href="<?= htmlspecialchars(url('add_match.php')) ?>" class="dashboard-card">
        <div class="icon">➕</div>
        <h3>Añadir partido</h3>
        <p>Crear un nuevo partido</p>
      </a>
	  
	  
    
      <a href="<?= htmlspecialchars(url('teams.php')) ?>" class="dashboard-card">
        <div class="icon">👥</div>
        <h3>Equipos</h3>
        <p>Crear y listar equipos</p>
      </a>

      <a href="<?= htmlspecialchars(url('seasons.php')) ?>" class="dashboard-card">
        <div class="icon">📅</div>
        <h3>Temporadas</h3>
        <p>Gestionar temporadas</p>
      </a>

      <a href="<?= htmlspecialchars(url('categories.php')) ?>" class="dashboard-card">
        <div class="icon">🏷️</div>
        <h3>Categorías</h3>
        <p>Añadir categorías (ej. Senior, U18)</p>
      </a>


    </div>
  </main>
</body>
</html>