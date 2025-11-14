<?php
// src/create_admin.php
// Script CLI para crear un usuario admin desde línea de comandos.
// Uso: php src/create_admin.php usuario contraseña "Nombre visible"

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    echo "Este script está pensado para ejecutarse desde la línea de comandos.\n";
    exit;
}

if ($argc < 3) {
    echo "Uso: php src/create_admin.php <username> <password> [display_name]\n";
    exit;
}

$username = $argv[1];
$password = $argv[2];
$display = $argv[3] ?? null;

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash, display_name, role) VALUES (?, ?, ?, 'admin')");
try {
    $stmt->execute([$username, $hash, $display]);
    echo "Admin creado: $username\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}