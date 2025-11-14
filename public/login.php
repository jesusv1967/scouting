<?php
// public/login.php
// Login sin barra de navegación (solo el formulario centrado).
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/auth.php';

if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_check($token)) {
        $error = 'Token CSRF inválido';
    } else {
        if ($username === '' || $password === '') {
            $error = 'Rellena usuario y contraseña';
        } elseif (login_user($username, $password)) {
            header('Location: ' . url('dashboard.php'));
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login - Scouting</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?=htmlspecialchars(url_asset('css/styles.css'))?>">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-8 col-md-6 col-lg-5">
        <div class="card shadow-sm login-card">
          <div class="card-body">
            <div class="login-illustration mb-3 text-center">
              <img src="<?=htmlspecialchars(url_asset('img/logo.svg'))?>" alt="Logo" style="height:72px">
            </div>

            <h4 class="card-title mb-3 text-center">Iniciar sesión</h4>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
            <?php endif; ?>

            <form method="post" class="mb-0" action="<?=htmlspecialchars(url('login.php'))?>">
              <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
              <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input name="username" class="form-control" required autofocus>
              </div>
              <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input name="password" type="password" class="form-control" required>
              </div>
              <div class="d-grid">
                <button class="btn btn-primary">Entrar</button>
              </div>
            </form>

            <hr>
            <div class="text-center text-muted-sm">App MVP — versión inicial</div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>