<?php

namespace App\Models;

use App\Core\Database;

class Disciplina extends BaseModel
{
    protected static string $table = 'disciplinas';

    public static function allComRelacoes(): array
    {
        return Database::fetchAll(
            "SELECT d.*,
                    c.nome AS curso_nome,
                    c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome
             FROM disciplinas d
             JOIN cursos c ON c.id = d.curso_id
             JOIN turmas t ON t.id = d.turma_id
             WHERE d.ativo = 1
             ORDER BY c.nome, t.serie_periodo, d.nome"
        );
    }

    public static function findComRelacoes(int $id): array|false
    {
        return Database::fetchOne(
            "SELECT d.*,
                    c.nome AS curso_nome,
                    c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome,
                    c.turno_inicio, c.turno_fim
             FROM disciplinas d
             JOIN cursos c ON c.id = d.curso_id
             JOIN turmas t ON t.id = d.turma_id
             WHERE d.id = ?",
            [$id]
        );
    }

    public static function porTurma(int $turmaId): array
    {
        return Database::fetchAll(
            "SELECT d.*, p.nome AS professor_nome
             FROM disciplinas d
             LEFT JOIN professores p ON p.id = d.professor_id
             WHERE d.turma_id = ? AND d.ativo = 1
             ORDER BY d.nome",
            [$turmaId]
        );
    }


    public static function duracaoEncontroMinutos(array $disciplina): int
    {
        return (int)$disciplina['qtd_aulas'] * (int)$disciplina['duracao_aula_minutos'];
    }

    public static function totalMinutosSemanal(array $disciplina): int
    {
        return (int)$disciplina['qtd_encontros_semanais'] * self::duracaoEncontroMinutos($disciplina);
    }
}
