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
</head>
<body>
<?php require_once __DIR__ . '/_nav.php'; ?>

<main class="container py-4">
  <h1 class="h4 mb-3">Dashboard</h1>
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-md-3">
      <a href="<?=htmlspecialchars(url('teams.php'))?>" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Equipos</h5>
          <p class="card-text">Crear y listar equipos</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="<?=htmlspecialchars(url('seasons.php'))?>" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Temporadas</h5>
          <p class="card-text">Gestionar temporadas</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="<?=htmlspecialchars(url('categories.php'))?>" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Categorías</h5>
          <p class="card-text">Añadir categorías (ej. Senior, U18)</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="<?=htmlspecialchars(url('add_match.php'))?>" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Añadir partido</h5>
          <p class="card-text">Crear un nuevo partido</p>
        </div>
      </a>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <a href="<?=htmlspecialchars(url('matches.php'))?>" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Partidos</h5>
          <p class="card-text">Ver, editar y eliminar partidos grabados</p>
        </div>
      </a>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>