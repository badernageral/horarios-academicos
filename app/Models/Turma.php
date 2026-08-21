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

    /**
     * Turnos liberados da turma: [dia][turno] = 0 (bloqueado) | 1 (pode).
     * A ausência de linha no banco é o "bloqueado" — mesma convenção da
     * disponibilidade do professor.
     */
    public static function gradeDisponibilidade(int $turmaId, array $turnos): array
    {
        $liberado = [];
        foreach (Database::fetchAll(
            "SELECT dia_semana, turno FROM disponibilidade_turma WHERE turma_id = ?",
            [$turmaId]
        ) as $l) {
            $liberado[(int)$l['dia_semana']][$l['turno']] = 1;
        }

        $grade = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys($turnos) as $chave) {
                $grade[$dia][$chave] = $liberado[$dia][$chave] ?? 0;
            }
        }
        return $grade;
    }

    /**
     * Substitui os turnos liberados da turma.
     * @param array $liberados lista de ['dia_semana' => int, 'turno' => string]
     */
    public static function salvarDisponibilidade(int $turmaId, array $liberados): void
    {
        Database::query("DELETE FROM disponibilidade_turma WHERE turma_id = ?", [$turmaId]);

        foreach ($liberados as $l) {
            $turno = trim((string)($l['turno'] ?? ''));
            if ($turno === '') continue;
            Database::query(
                "INSERT OR IGNORE INTO disponibilidade_turma (turma_id, dia_semana, turno)
                 VALUES (?, ?, ?)",
                [$turmaId, (int)$l['dia_semana'], $turno]
            );
        }
    }

    public static function porcurso(int $cursoId): array
    {
        return Database::fetchAll(
            "SELECT * FROM turmas WHERE curso_id = ? AND ativo = 1 ORDER BY serie_periodo, nome",
            [$cursoId]
        );
    }
}
