<?php

namespace App\Models;

use App\Core\Database;

class Turma extends BaseModel
{
    protected static string $table = 'turmas';

    public static function allComCurso(): array
    {
        return Database::fetchAll(
            "SELECT t.*, c.nome AS curso_nome, c.turno_inicio, c.turno_fim, c.duracao_aula_minutos, c.cor AS curso_cor
             FROM turmas t
             JOIN cursos c ON c.id = t.curso_id
             WHERE t.ativo = 1
             ORDER BY c.nome, t.serie_periodo"
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
