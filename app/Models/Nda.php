<?php

namespace App\Models;

use App\Core\Database;

class Nda extends BaseModel
{
    protected static string $table = 'ndas';

    public static function allAtivos(): array
    {
        return Database::fetchAll("SELECT * FROM ndas WHERE ativo = 1 ORDER BY nome");
    }
}
