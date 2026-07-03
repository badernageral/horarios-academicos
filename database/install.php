#!/usr/bin/env php
<?php
/**
 * Cria o banco a partir do database/schema.sql, conforme o driver de
 * config/database.php (sqlite por padrão; mysql se DB_DRIVER=mysql).
 * Uso: php database/install.php
 */

define('ROOT_PATH', dirname(__DIR__));
$cfg    = require ROOT_PATH . '/config/database.php';
$driver = $cfg['driver'] ?? 'sqlite';
$schema = file_get_contents(ROOT_PATH . '/database/schema.sql');

try {
    if ($driver === 'sqlite') {
        $path = $cfg['path'];
        if (is_file($path)) {
            echo "ℹ Banco já existe em {$path} — nada a fazer (use migrations para atualizar).\n";
            exit(0);
        }
        @mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec($schema);                 // pdo_sqlite executa vários comandos
        echo "✔ Banco SQLite criado em {$path}\n";
    } else {
        // MySQL (legado)
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $cfg['options']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$cfg['dbname']}`");
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $schema)))) as $stmt) {
            $pdo->exec($stmt);
        }
        echo "✔ Banco MySQL '{$cfg['dbname']}' criado.\n";
    }

    echo "→ Para atualizações futuras de schema: php database/migrate.php\n";
} catch (PDOException $e) {
    echo "✘ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
