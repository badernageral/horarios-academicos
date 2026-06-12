<?php

namespace App\Models;

use App\Core\Database;

class Sala extends BaseModel
{
    protected static string $table = 'salas';

    public static function allAtivas(): array
    {
        return Database::fetchAll("SELECT * FROM salas WHERE ativo = 1 ORDER BY nome");
    }
}
