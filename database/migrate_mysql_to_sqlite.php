<?php

declare(strict_types=1);

/**
 * Migração ÚNICA dos dados: MySQL (produção) → SQLite.
 *
 * Uso: php database/migrate_mysql_to_sqlite.php [caminho/destino.sqlite]
 *
 * - NÃO altera o MySQL (só lê).
 * - Cria o arquivo SQLite do zero a partir de database/schema.sql e copia todas
 *   as linhas de todas as tabelas, preservando os IDs.
 * - Confere as contagens tabela a tabela e roda um integrity_check no final.
 */

define('ROOT_PATH', dirname(__DIR__));

// ── Origem: MySQL (somente leitura) ───────────────────────────────
$mysqlCfg = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_NAME') ?: 'horarios_academicos',
    'user' => getenv('DB_USER') ?: 'sga',
    'pass' => getenv('DB_PASS') ?: 'Sga@12345',
];
$mysql = new PDO(
    "mysql:host={$mysqlCfg['host']};port={$mysqlCfg['port']};dbname={$mysqlCfg['name']};charset=utf8mb4",
    $mysqlCfg['user'], $mysqlCfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ── Destino: SQLite (recriado do zero) ────────────────────────────
$destino = $argv[1] ?? (ROOT_PATH . '/database/sga.sqlite');
foreach ([$destino, $destino . '-wal', $destino . '-shm'] as $f) {
    if (is_file($f)) unlink($f);
}
$sqlite = new PDO('sqlite:' . $destino, null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

echo "Origem : MySQL {$mysqlCfg['name']}\n";
echo "Destino: {$destino}\n\n";

// ── Cria a estrutura a partir do schema.sql ───────────────────────
$schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
$sqlite->exec($schema);

$tabelasSqlite = $sqlite->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
)->fetchAll(PDO::FETCH_COLUMN);
echo "Tabelas criadas no SQLite: " . count($tabelasSqlite) . "\n\n";

// ── Copia os dados (FK desligada durante a carga em massa) ────────
$sqlite->exec('PRAGMA foreign_keys = OFF');

$tabelas = $mysql->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$erros = [];

foreach ($tabelas as $tab) {
    if (!in_array($tab, $tabelasSqlite, true)) {
        $erros[] = "Tabela {$tab} existe no MySQL mas não no SQLite — pulada.";
        continue;
    }
    $sqlite->exec("DELETE FROM \"{$tab}\"");                 // limpa seeds do schema
    $rows = $mysql->query("SELECT * FROM `{$tab}`")->fetchAll();

    if ($rows) {
        $cols = array_keys($rows[0]);
        $colList = '"' . implode('","', $cols) . '"';
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $ins = $sqlite->prepare("INSERT INTO \"{$tab}\" ({$colList}) VALUES ({$ph})");

        $sqlite->beginTransaction();
        foreach ($rows as $r) {
            $ins->execute(array_values($r));
        }
        $sqlite->commit();
    }

    $srcCount = (int)$mysql->query("SELECT COUNT(*) FROM `{$tab}`")->fetchColumn();
    $dstCount = (int)$sqlite->query("SELECT COUNT(*) FROM \"{$tab}\"")->fetchColumn();
    $ok = $srcCount === $dstCount ? 'OK ' : '!! ';
    if ($srcCount !== $dstCount) $erros[] = "{$tab}: MySQL={$srcCount} SQLite={$dstCount}";
    printf("  %s %-28s MySQL %4d  →  SQLite %4d\n", $ok, $tab, $srcCount, $dstCount);
}

$sqlite->exec('PRAGMA foreign_keys = ON');

// ── Verificação de integridade referencial ────────────────────────
echo "\nintegrity_check: ";
$ic = $sqlite->query("PRAGMA integrity_check")->fetchColumn();
echo $ic . "\n";
$fk = $sqlite->query("PRAGMA foreign_key_check")->fetchAll();
echo "foreign_key_check: " . (count($fk) === 0 ? "OK (sem violações)" : count($fk) . " violação(ões)") . "\n";

echo "\n" . (empty($erros) && $ic === 'ok' && count($fk) === 0
    ? ">>> MIGRAÇÃO OK — todos os dados conferem.\n"
    : ">>> ATENÇÃO:\n   - " . implode("\n   - ", $erros ?: ['(ver integrity/fk acima)']) . "\n");
