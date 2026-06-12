<?php

namespace App\Models;

use App\Core\Database;

class Curso extends BaseModel
{
    protected static string $table = 'cursos';

    public static function allAtivos(): array
    {
        return Database::fetchAll("SELECT * FROM cursos WHERE ativo = 1 ORDER BY nome");
    }

    public static function intervalos(int $cursoId): array
    {
        return Database::fetchAll(
            "SELECT * FROM intervalos_curso
             WHERE curso_id = ?
             ORDER BY dia_semana IS NULL DESC, dia_semana, hora_inicio",
            [$cursoId]
        );
    }

    public static function salvarIntervalos(int $cursoId, array $intervalos): void
    {
        Database::query("DELETE FROM intervalos_curso WHERE curso_id = ?", [$cursoId]);

        foreach ($intervalos as $i) {
            if (!empty($i['hora_inicio']) && !empty($i['hora_fim'])) {
                Database::query(
                    "INSERT INTO intervalos_curso (curso_id, dia_semana, hora_inicio, hora_fim, descricao)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $cursoId,
                        isset($i['dia_semana']) && $i['dia_semana'] !== '' ? (int)$i['dia_semana'] : null,
                        $i['hora_inicio'],
                        $i['hora_fim'],
                        $i['descricao'] ?? 'Intervalo',
                    ]
                );
            }
        }
    }

    public static function findComIntervalos(int $cursoId): array|false
    {
        $curso = static::find($cursoId);
        if (!$curso) return false;

        $curso['intervalos'] = static::intervalos($cursoId);
        $curso['dias_semana'] = json_decode($curso['dias_semana'], true);
        return $curso;
    }
}
