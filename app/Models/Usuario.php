<?php

namespace App\Models;

use App\Core\Database;

class Usuario extends BaseModel
{
    protected static string $table = 'usuarios';

    public static function porUsuario(string $usuario): array|false
    {
        return Database::fetchOne(
            "SELECT * FROM usuarios WHERE usuario = ? LIMIT 1",
            [$usuario]
        );
    }

    /** Hash de senha no padrão do PHP (bcrypt). */
    public static function hash(string $senha): string
    {
        return password_hash($senha, PASSWORD_DEFAULT);
    }
}
