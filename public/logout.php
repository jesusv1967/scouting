<?php
// public/logout.php
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

secure_session_start(require __DIR__ . '/../src/config.php');
logout_user();
header('Location: /login.php');
exit;