<?php
// src/db.php
$config = require __DIR__ . '/config.php';

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port'] ?? 3306
);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Error de conexión a la base de datos: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    exit;
}

$mysqli->set_charset('utf8mb4');