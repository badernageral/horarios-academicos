<?php
/**
 * Router do servidor embutido do PHP (php -S) para o modo DESKTTOP.
 * Emula o rewrite do .htaccess: arquivos existentes (assets) são servidos
 * direto; qualquer outra rota cai no front controller (index.php).
 *
 * Uso: php -S 127.0.0.1:<porta> -t <raiz-da-app> desktop/router.php
 * (DOCUMENT_ROOT aponta para a raiz onde está o index.php do SGA)
 */
$root = $_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__);
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Bloqueia database/ (schema, migrations e o .sqlite com os dados) e lib/ (FPDF).
if (preg_match('#^/(database|lib)(/|$)#', $uri) || preg_match('#\.sqlite(-wal|-shm)?$#', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Arquivo estático existente (css/js/img) → deixa o servidor embutido servir.
if ($uri !== '/' && is_file($root . $uri)) {
    return false;
}

// Demais rotas → front controller.
require $root . '/index.php';
