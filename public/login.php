<?php
// public/login.php
require_once __DIR__ . '/../src/config.php';
$config = require __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

secure_session_start($config);

if (is_logged_in()) {
    header('Location: /dashboard.php');
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
            header('Location: dashboard.php');
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
  <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-8 col-md-6 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h4 class="card-title mb-3">Iniciar sesión</h4>
            <?php if ($error): ?>
              <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
            <?php endif; ?>
            <form method="post" class="mb-0">
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
            <small class="text-muted">App MVP — versión inicial</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>