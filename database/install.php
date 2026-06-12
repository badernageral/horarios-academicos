#!/usr/bin/env php
<?php
/**
 * Script de instalação do banco de dados
 * Uso: php database/install.php [--seed]
 */

define('ROOT_PATH', dirname(__DIR__));
$cfg = require ROOT_PATH . '/config/database.php';

try {
    // Conectar sem selecionar banco para poder criá-lo
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $cfg['options']);
    echo "✔ Conexão com MySQL estabelecida.\n";

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✔ Banco '{$cfg['dbname']}' verificado.\n";

    $pdo->exec("USE `{$cfg['dbname']}`");

    // Ler e executar o schema em statements individuais, respeitando ordem
    $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');

    // Remove comentários de linha
    $schema = preg_replace('/--[^\n]*/', '', $schema);
    // Remove comentários de bloco
    $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);

    // Separar statements por ; ignorando ; dentro de strings
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        fn($s) => $s !== ''
    );

    $errors = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            echo "  ⚠ " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    if ($errors > 0) {
        echo "  → {$errors} aviso(s) durante criação do schema.\n";
    } else {
        echo "✔ Schema criado sem erros.\n";
    }

    // Verificar tabelas criadas
    $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✔ Tabelas criadas: " . implode(', ', $tabelas) . "\n";

    if (empty($tabelas)) {
        echo "✘ Nenhuma tabela criada! Verifique os erros acima.\n";
        exit(1);
    }

    // Dados de exemplo
    if (in_array('--seed', $argv ?? [])) {
        $seed = file_get_contents(ROOT_PATH . '/database/seed.sql');
        $seed = preg_replace('/--[^\n]*/', '', $seed);
        $seedStmts = array_filter(
            array_map('trim', explode(';', $seed)),
            fn($s) => $s !== ''
        );

        $seedErrors = 0;
        foreach ($seedStmts as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                echo "  ⚠ Seed: " . $e->getMessage() . "\n";
                $seedErrors++;
            }
        }
        $total = $pdo->query("SELECT COUNT(*) FROM disciplinas")->fetchColumn();
        echo "✔ Dados de exemplo inseridos. ($total disciplinas)\n";
    }

    echo "\n🎉 Instalação concluída! Acesse: http://localhost:8080\n";

} catch (PDOException $e) {
    echo "✘ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
