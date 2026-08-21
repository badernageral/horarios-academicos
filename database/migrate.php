<?php

declare(strict_types=1);

/**
 * Runner de migrations do SGA (sem framework).
 *
 * Uso:
 *   php database/migrate.php            → aplica as migrations pendentes
 *   php database/migrate.php up         → idem
 *   php database/migrate.php baseline   → marca TODAS as migrations atuais como
 *                                         aplicadas SEM executá-las. Use num banco
 *                                         que já está no estado do schema.sql (ex.:
 *                                         logo após criar um banco novo a partir do
 *                                         schema.sql, ou no banco de produção atual).
 *   php database/migrate.php status     → lista aplicadas x pendentes
 *
 * As migrations ficam em database/migrations/ como arquivos ordenados por nome
 * (ex.: 001_descricao.sql). Devem ser INCREMENTAIS e não destrutivas
 * (ALTER TABLE, CREATE TABLE IF NOT EXISTS, INSERT ...). Cada arquivo pode conter
 * vários comandos separados por ";".
 *
 * Conexão: usa config/database.php (que lê as variáveis DB_* do ambiente), então
 * no desktop o main.js injeta as credenciais do MariaDB local.
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Core/Database.php';
require ROOT_PATH . '/app/Core/Migrator.php';

use App\Core\Database;
use App\Core\Migrator;

$mode = $argv[1] ?? 'up';
$pdo  = Database::getInstance();

// Tabela de controle.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        migration  VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     )"
);

$dir   = ROOT_PATH . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING); // ordem determinística por nome (001_, 002_, ...)

$aplicadas = $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(\PDO::FETCH_COLUMN);
$aplicadas = array_flip($aplicadas);

$registrar = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");

// ── status ────────────────────────────────────────────────────────
if ($mode === 'status') {
    echo "Migrations:\n";
    if (!$files) echo "  (nenhuma)\n";
    foreach ($files as $f) {
        $nome = basename($f);
        echo (isset($aplicadas[$nome]) ? "  [x] " : "  [ ] ") . $nome . "\n";
    }
    exit(0);
}

$pendentes = 0;
foreach ($files as $file) {
    $nome = basename($file);
    if (isset($aplicadas[$nome])) continue;
    $pendentes++;

    if ($mode === 'baseline') {
        $registrar->execute([$nome]);
        echo "baseline (marcada): {$nome}\n";
        continue;
    }


    // mode = up → delega ao Migrator, o MESMO caminho usado pela tela de
    // login; assim CLI e aplicação nunca divergem no que consideram pendente.
    $r = Migrator::aplicar($pdo);
    foreach ($r['aplicadas'] as $aplicada) {
        echo "aplicada: {$aplicada}\n";
    }
    if ($r['erro']) {
        fwrite(STDERR, "ERRO ao aplicar: " . $r['erro'] . "\n");
        exit(1);
    }
    break;   // o Migrator já percorreu todas as pendentes
}

echo $pendentes === 0
    ? "Banco já está atualizado (nenhuma migration pendente).\n"
    : ($mode === 'baseline' ? "Baseline concluído.\n" : "Migrations aplicadas com sucesso.\n");
