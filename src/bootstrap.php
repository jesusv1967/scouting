<?php
// src/bootstrap.php
// Inicialización ligera para construir URLs relativas, detectar ubicación de assets e iniciar sesión segura.

// Cargar configuración
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/../src/helpers.php';

// Calcular BASE_URL dinámicamente:
// dirname($_SERVER['SCRIPT_NAME']) -> por ejemplo "/scouting/public" o "/" si public es document root
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
// Normalizar: si dirname devuelve '/' dejamos cadena vacía
if ($base === '/' || $base === '\\') {
    $base = '';
}
define('BASE_URL', $base);

// Helper para construir URLs (sin doble slash)
/*
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    if ($path === '') return BASE_URL === '' ? '/' : BASE_URL . '/';
    return (BASE_URL === '' ? '/' : BASE_URL . '/') . $path;
}
*/
// Detectar ubicación de la carpeta assets en el árbol de ficheros (fallback inteligente)
// project root (un nivel arriba de src)
$projectRoot = realpath(__DIR__ . '/..');
$publicAssetsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets';
$rootAssetsPath = $projectRoot . DIRECTORY_SEPARATOR . 'assets';

// Por defecto asumimos assets accesible desde BASE_URL/assets
$assetsUrl = BASE_URL === '' ? '/assets' : BASE_URL . '/assets';

// Si public/assets existe, usar BASE_URL/assets
if (is_dir($publicAssetsPath)) {
    $assetsUrl = BASE_URL === '' ? '/assets' : BASE_URL . '/assets';
}
// Si existe assets en la raíz del proyecto (fuera de public), construir ruta un nivel arriba de BASE_URL
elseif (is_dir($rootAssetsPath)) {
    // Determinar la parte padre de BASE_URL: si BASE_URL termina en /public, subimos un nivel
    $parts = explode('/', trim(BASE_URL, '/'));
    if (end($parts) === 'public') {
        array_pop($parts);
    }
    $parentBase = '/' . implode('/', array_filter($parts, fn($p) => $p !== ''));
    if ($parentBase === '/') { $parentBase = ''; }
    $assetsUrl = ($parentBase === '' ? '/' : $parentBase . '/') . 'assets';
}
// Define constante usable en templates
define('ASSETS_URL', rtrim($assetsUrl, '/'));
/*
// Helper para construir rutas a assets (css/js/img)
function url_asset(string $path = ''): string {
    $path = ltrim($path, '/');
    // ASSETS_URL ya incluye /assets y no termina en slash
    return ASSETS_URL . '/' . $path;
}
*/
// Iniciar sesión segura según config
require_once __DIR__ . '/helpers.php';
secure_session_start($config);