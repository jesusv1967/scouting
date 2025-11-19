<?php
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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Login - Scouting</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_asset('css/styles.css')) ?>">
  <style>
    /* Full height layout, adaptable */
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
    }
    body {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100dvh; /* Soporta barras de sistema en iOS/Android */
      padding: 16px;
      background-color: var(--light);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .login-card {
      width: 100%;
      max-width: 500px;
      padding: 28px 24px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      box-sizing: border-box;
    }
    @media (max-width: 480px) {
      .login-card {
        padding: 24px 20px;
      }
      h2 {
        font-size: 26px;
        margin-bottom: 28px;
      }
      input, button {
        font-size: 20px;
        padding: 16px;
      }
    }
    @media (min-width: 481px) and (max-width: 768px) {
      /* Tablets verticales */
      .login-card {
        padding: 32px 28px;
      }
      h2 {
        font-size: 28px;
      }
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="text-center mb-4">
      <img src="<?= htmlspecialchars(url_asset('img/logo.svg')) ?>" alt="Logo" style="height: 80px; margin: 0 auto 20px;">
    </div>

    <h2 class="text-center">Iniciar sesión</h2>

    <?php if ($error): ?>
      <div class="alert alert-error" style="font-size: 18px; padding: 14px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(url('login.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <label for="username" style="display: block; font-size: 18px; margin: 16px 0 8px; font-weight: 600;">Usuario</label>
      <input id="username" name="username" type="text" required autofocus style="width: 100%; font-size: 20px; padding: 16px; border: 1px solid var(--border); border-radius: 12px;">

      <label for="password" style="display: block; font-size: 18px; margin: 16px 0 8px; font-weight: 600;">Contraseña</label>
      <input id="password" name="password" type="password" required style="width: 100%; font-size: 20px; padding: 16px; border: 1px solid var(--border); border-radius: 12px;">

      <button type="submit" class="primary" style="width: 100%; font-size: 22px; padding: 18px; margin-top: 24px; border-radius: 12px;">
        Entrar
      </button>
    </form>

    <div style="text-align: center; margin-top: 28px; color: var(--gray); font-size: 14px;">
      App MVP — versión inicial
    </div>
  </div>
</body>
</html>