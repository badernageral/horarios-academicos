<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $cfg    = require ROOT_PATH . '/config/database.php';
            $driver = $cfg['driver'] ?? 'mysql';

            try {
                if ($driver === 'sqlite') {
                    $dir = dirname($cfg['path']);
                    if (!is_dir($dir)) @mkdir($dir, 0775, true);
                    self::$instance = new PDO('sqlite:' . $cfg['path'], null, null, $cfg['options']);
                    // Integridade referencial precisa ser habilitada por conexão.
                    self::$instance->exec('PRAGMA foreign_keys = ON');
                    // Melhor concorrência de leitura/escrita para um único usuário.
                    self::$instance->exec('PRAGMA journal_mode = WAL');
                } else {
                    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
                    self::$instance = new PDO($dsn, $cfg['user'], $cfg['password'], $cfg['options']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }

        return self::$instance;
    }

    /** Execute a prepared statement and return PDOStatement */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Fetch single row */
    public static function fetchOne(string $sql, array $params = []): array|false
    {
        return self::query($sql, $params)->fetch();
    }

    /** Fetch single column value */
    public static function fetchValue(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }
}
