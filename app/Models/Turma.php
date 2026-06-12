<?php

namespace App\Models;

use App\Core\Database;

class Turma extends BaseModel
{
    protected static string $table = 'turmas';

    public static function allComCurso(string $sort = 'curso_nome', string $dir = 'asc'): array
    {
        $map = ['curso_nome' => 'c.nome', 'serie_periodo' => 't.serie_periodo', 'ativo' => 't.ativo'];
        $col = $map[$sort] ?? 'c.nome';
        $dir = $dir === 'desc' ? 'DESC' : 'ASC';
        return Database::fetchAll(
            "SELECT t.*, c.nome AS curso_nome, c.turno_inicio, c.turno_fim, c.duracao_aula_minutos, c.cor AS curso_cor
             FROM turmas t
             JOIN cursos c ON c.id = t.curso_id
             WHERE t.ativo = 1
             ORDER BY {$col} {$dir}"
        );
    }

    public static function findComCurso(int $id): array|false
    {
        return Database::fetchOne(
            "SELECT t.*, c.nome AS curso_nome, c.turno_inicio, c.turno_fim, c.duracao_aula_minutos, c.dias_semana, c.cor AS curso_cor
             FROM turmas t
             JOIN cursos c ON c.id = t.curso_id
             WHERE t.id = ?",
            [$id]
        );
    }

    public static function porcurso(int $cursoId): array
    {
        return Database::fetchAll(
            "SELECT * FROM turmas WHERE curso_id = ? AND ativo = 1 ORDER BY serie_periodo, nome",
            [$cursoId]
        );
    }
}
