<?php
// public/dashboard.php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
secure_session_start(require __DIR__ . '/../src/config.php');
require_login();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/dashboard.php">Scouting</a>
    <div class="ms-auto">
      <span class="me-3">Hola, <?=htmlspecialchars(current_user_display())?></span>
      <a href="/logout.php" class="btn btn-outline-secondary btn-sm">Salir</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <h1 class="h4 mb-3">Dashboard</h1>
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-md-3">
      <a href="/teams.php" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Equipos</h5>
          <p class="card-text">Crear y listar equipos</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="/seasons.php" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Temporadas</h5>
          <p class="card-text">Gestionar temporadas</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="/categories.php" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Categorías</h5>
          <p class="card-text">Añadir categorías (ej. Senior, U18)</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <a href="/add_match.php" class="card text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Añadir partido</h5>
          <p class="card-text">Crear un nuevo partido</p>
        </div>
      </a>
    </div>
  </div>
</main>
</body>
</html>