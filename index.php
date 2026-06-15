<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('VIEW_PATH', ROOT_PATH . '/app/Views');
define('BASE_PATH', '/horarios-academicos');
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

$router->dispatch($method, $uri);
