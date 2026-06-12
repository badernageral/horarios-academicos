<?php

namespace App\Models;

use App\Core\Database;

class Horario extends BaseModel
{
    protected static string $table = 'horarios';

    public static function porGeracao(int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla, d.qtd_aulas,
                    t.serie_periodo AS turma_nome, t.serie_periodo,
                    p.nome AS professor_nome, p.cor AS professor_cor,
                    s.nome AS sala_nome,
                    c.id AS curso_id, c.nome AS curso_nome,
                    c.duracao_aula_minutos, c.turno_inicio, c.turno_fim
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             JOIN professores p  ON p.id = h.professor_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             JOIN cursos c       ON c.id = d.curso_id
             WHERE h.geracao_id = ?
             ORDER BY h.turma_id, h.dia_semana, h.hora_inicio",
            [$geracaoId]
        );
    }

    public static function porTurma(int $turmaId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    p.nome AS professor_nome, p.cor AS professor_cor,
                    s.nome AS sala_nome
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN professores p  ON p.id = h.professor_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             WHERE h.turma_id = ? AND h.geracao_id = ?
             ORDER BY h.dia_semana, h.hora_inicio",
            [$turmaId, $geracaoId]
        );
    }

    public static function porProfessor(int $professorId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    t.serie_periodo AS turma_nome,
                    s.nome AS sala_nome,
                    p.cor AS professor_cor
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             JOIN professores p  ON p.id = h.professor_id
             WHERE h.professor_id = ? AND h.geracao_id = ?
             ORDER BY h.dia_semana, h.hora_inicio",
            [$professorId, $geracaoId]
        );
    }

    public static function porSala(int $salaId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    t.serie_periodo AS turma_nome,
                    p.nome AS professor_nome, p.cor AS professor_cor
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             JOIN professores p  ON p.id = h.professor_id
             WHERE h.sala_id = ? AND h.geracao_id = ?
             ORDER BY h.dia_semana, h.hora_inicio",
            [$salaId, $geracaoId]
        );
    }

    public static function ultimaGeracao(): array|false
    {
        return Database::fetchOne(
            "SELECT * FROM geracoes WHERE status IN ('concluido','parcial') ORDER BY id DESC LIMIT 1"
        );
    }

    public static function todasGeracoes(): array
    {
        return Database::fetchAll(
            "SELECT * FROM geracoes ORDER BY created_at DESC"
        );
    }
}
