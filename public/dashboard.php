<?php
// public/dashboard.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
  <style>
    /* Opcional: un pequeño ajuste para que las tarjetas tengan el mismo aspecto */
    .dashboard-card .card-body { display:flex; flex-direction:column; justify-content:space-between; height:100%; }
    .dashboard-card .card-title { margin-bottom:.25rem; }
    .dashboard-card .card-text { color: #6c757d; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<main class="container py-4">
  <h1 class="h4 mb-3">Dashboard</h1>

  <!--
    Usamos row-cols para controlar cuántas columnas aparecen según el breakpoint:
      - xs: 1 columna
      - sm: 2 columnas
      - md: 3 columnas (=> en portátil verás 3 + 2 si hay 5 tarjetas)
      - lg+: 5 columnas (=> en pantallas grandes se muestran las 5 en una fila)
    Ajusta row-cols-md / row-cols-lg si prefieres otro comportamiento.
  -->
  <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
    <div class="col">
      <a href="<?=htmlspecialchars(url('teams.php'))?>" class="card text-decoration-none text-dark h-100 dashboard-card">
        <div class="card-body">
          <div>
            <h5 class="card-title">Equipos</h5>
            <p class="card-text">Crear y listar equipos</p>
          </div>
          <div class="mt-2"><i class="bi bi-people" aria-hidden="true"></i></div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href="<?=htmlspecialchars(url('seasons.php'))?>" class="card text-decoration-none text-dark h-100 dashboard-card">
        <div class="card-body">
          <div>
            <h5 class="card-title">Temporadas</h5>
            <p class="card-text">Gestionar temporadas</p>
          </div>
          <div class="mt-2"><i class="bi bi-calendar3" aria-hidden="true"></i></div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href="<?=htmlspecialchars(url('categories.php'))?>" class="card text-decoration-none text-dark h-100 dashboard-card">
        <div class="card-body">
          <div>
            <h5 class="card-title">Categorías</h5>
            <p class="card-text">Añadir categorías (ej. Senior, U18)</p>
          </div>
          <div class="mt-2"><i class="bi bi-tags" aria-hidden="true"></i></div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href="<?=htmlspecialchars(url('add_match.php'))?>" class="card text-decoration-none text-dark h-100 dashboard-card">
        <div class="card-body">
          <div>
            <h5 class="card-title">Añadir partido</h5>
            <p class="card-text">Crear un nuevo partido</p>
          </div>
          <div class="mt-2"><i class="bi bi-plus-circle" aria-hidden="true"></i></div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href="<?=htmlspecialchars(url('matches.php'))?>" class="card text-decoration-none text-dark h-100 dashboard-card">
        <div class="card-body">
          <div>
            <h5 class="card-title">Partidos</h5>
            <p class="card-text">Ver, editar y eliminar partidos grabados</p>
          </div>
          <div class="mt-2"><i class="bi bi-journal-text" aria-hidden="true"></i></div>
        </div>
      </a>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>