<?php

declare(strict_types=1);

ini_set('pcre.jit', '0');

define('ROOT_PATH', __DIR__);
define('VIEW_PATH', ROOT_PATH . '/app/Views');
// Padrão: '/horarios-academicos' (Apache). O modo desktop (php -S na raiz)
// define SGA_BASE_PATH='' para servir a app na raiz. Sem a env var, nada muda.
define('BASE_PATH', getenv('SGA_BASE_PATH') !== false ? getenv('SGA_BASE_PATH') : '/horarios-academicos');
define('REQUEST_PATH', '/' . ltrim(substr(strtok($_SERVER['REQUEST_URI'], '?'), strlen(BASE_PATH)), '/'));

date_default_timezone_set('America/Sao_Paulo');
session_start();

// ── Autoloader PSR-4 simples ──────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\Core\\'        => ROOT_PATH . '/app/Core/',
        'App\\Models\\'      => ROOT_PATH . '/app/Models/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Services\\'    => ROOT_PATH . '/app/Services/',
    ];
    foreach ($map as $ns => $dir) {
        if (str_starts_with($class, $ns)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// ── Error handling ────────────────────────────────────────────────
$appConfig = require ROOT_PATH . '/config/app.php';
if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ── Roteamento ────────────────────────────────────────────────────
$router = new \App\Core\Router();
require ROOT_PATH . '/routes.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

// ── Guarda de autenticação ────────────────────────────────────────
// Tudo exige login, exceto a própria tela de login.
$rotasPublicas = ['/login'];
if (!\App\Core\Auth::check() && !in_array(REQUEST_PATH, $rotasPublicas, true)) {
    header('Location: ' . BASE_PATH . '/login');
    exit;
}

$router->dispatch($method, $uri);
