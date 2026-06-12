<?php

namespace App\Models;

use App\Core\Database;

abstract class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    public static function all(string $orderBy = 'id', string $dir = 'ASC'): array
    {
        return Database::fetchAll(
            "SELECT * FROM " . static::$table . " ORDER BY {$orderBy} {$dir}"
        );
    }

    public static function find(int|string $id): array|false
    {
        return Database::fetchOne(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        );
    }

    public static function where(array $conditions, string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $cols = array_keys($conditions);
        $where = implode(' AND ', array_map(fn($c) => "{$c} = ?", $cols));
        return Database::fetchAll(
            "SELECT * FROM " . static::$table . " WHERE {$where} ORDER BY {$orderBy} {$dir}",
            array_values($conditions)
        );
    }

    public static function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int) Database::fetchValue("SELECT COUNT(*) FROM " . static::$table);
        }
        $cols = array_keys($conditions);
        $where = implode(' AND ', array_map(fn($c) => "{$c} = ?", $cols));
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM " . static::$table . " WHERE {$where}",
            array_values($conditions)
        );
    }

    public static function create(array $data): int
    {
        $cols = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList = implode(', ', $cols);
        Database::query(
            "INSERT INTO " . static::$table . " ({$colList}) VALUES ({$placeholders})",
            array_values($data)
        );
        return (int) Database::lastInsertId();
    }

    public static function update(int|string $id, array $data): bool
    {
        $cols = array_keys($data);
        $set = implode(', ', array_map(fn($c) => "{$c} = ?", $cols));
        $values = array_values($data);
        $values[] = $id;
        $affected = Database::query(
            "UPDATE " . static::$table . " SET {$set} WHERE " . static::$primaryKey . " = ?",
            $values
        )->rowCount();
        return $affected > 0;
    }

    public static function delete(int|string $id): bool
    {
        return Database::query(
            "DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        )->rowCount() > 0;
    }
}
