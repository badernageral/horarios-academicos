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

// ── Atualização do schema ─────────────────────────────────────────
// Em TODA requisição, como o app desktop faz a cada abertura. Prender isso à
// tela de login deixaria quem já está com sessão aberta rodando código novo
// contra schema velho depois de um deploy. O custo normal é um filemtime().
$migracao = \App\Core\Migrator::verificarNoBoot();

if ($migracao['erro']) {
    // Schema desalinhado quebraria em SQL no meio de qualquer tela; melhor
    // parar aqui com uma mensagem que diz o que fazer.
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8">'
       . '<div style="font-family:system-ui;max-width:640px;margin:3rem auto;line-height:1.5">'
       . '<h2>Falha ao atualizar o banco</h2>'
       . '<p>' . htmlspecialchars($migracao['erro']) . '</p>'
       . '<p style="color:#64748b">Corrija e recarregue a página. Nenhuma tela é servida '
       . 'enquanto o schema estiver desatualizado, para não gerar erro de SQL no meio do uso.</p>'
       . '</div>';
    exit;
}

// Mensagens para a tela de login (única que as exibe).
if ($migracao['aplicadas']) {
    $n = count($migracao['aplicadas']);
    $_SESSION['sga_migracao'] = ['tipo' => 'success', 'texto' =>
        $n === 1 ? 'Banco atualizado (1 migration aplicada).'
                 : "Banco atualizado ({$n} migrations aplicadas)."];
} elseif ($migracao['precisaBaseline']) {
    // O comando certo depende do estado do schema: banco já no formato atual
    // precisa de `baseline` (marcar sem executar); banco antigo precisa de
    // `up` (executar de fato). Sugerir o errado deixaria o schema desalinhado.
    $_SESSION['sga_migracao'] = ['tipo' => 'warning', 'texto' =>
        'O banco ainda não está sob controle de migrations. Rode uma vez: '
        . 'php database/migrate.php' . ($migracao['schemaAtual'] ? ' baseline' : '')];
}

// ── Roteamento ────────────────────────────────────────────────────
$router = new \App\Core\Router();
require ROOT_PATH . '/routes.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

// ── Guarda de autenticação ────────────────────────────────────────
// Sem usuário logado: se o sistema ainda não tem nenhum usuário, manda para
// o cadastro do primeiro (/setup). Fora isso, só a ÁREA PÚBLICA (/publico) e
// o /login respondem — é por ali que aluno e professor consultam o horário
// sem credencial. Qualquer outra rota exige sessão.
if (!\App\Core\Auth::check()) {
    try {
        $semUsuarios = (int) \App\Core\Database::fetchValue("SELECT COUNT(*) FROM usuarios") === 0;
    } catch (\Throwable $e) {
        $semUsuarios = true; // banco recém-criado / sem tabela → tratar como 1º acesso
    }

    $ehPublica = REQUEST_PATH === '/publico' || str_starts_with(REQUEST_PATH, '/publico/');

    if ($semUsuarios) {
        $alvo = '/setup';
    } elseif ($ehPublica || REQUEST_PATH === '/login') {
        $alvo = REQUEST_PATH;               // deixa passar (inclusive o POST do login)
    } elseif (REQUEST_PATH === '/' || REQUEST_PATH === '/dashboard') {
        $alvo = '/publico';                 // abrir o sistema cai na consulta pública
    } else {
        $alvo = '/login';                   // rota interna: exige sessão
    }

    if (REQUEST_PATH !== $alvo) {
        header('Location: ' . BASE_PATH . $alvo);
        exit;
    }
}

$router->dispatch($method, $uri);
