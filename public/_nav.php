<?php
// public/_nav.php
// Cabecera simplificada: solo muestra el logo/título de la app.
// Si prefieres eliminar también el botón de salir, borra el bloque con el enlace de logout.
?>
<nav class="navbar navbar-light bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 mx-auto" href="<?=htmlspecialchars(url('dashboard.php'))?>" style="font-weight:700;">
      <img src="<?=htmlspecialchars(url_asset('img/logo.svg'))?>" class="brand-logo" alt="Scouting">
      <span>Scouting</span>
    </a>

    <!-- Pequeño botón de logout en caso de que quieras mantener la opción visible;
         si no lo quieres, elimina este <div> por completo -->
    <div class="position-absolute" style="right:16px; top:10px;">
      <?php if (is_logged_in()): ?>
        <a href="<?=htmlspecialchars(url('logout.php'))?>" class="btn btn-outline-secondary btn-sm" title="Salir">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

