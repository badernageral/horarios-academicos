<?php

namespace App\Models;

use App\Core\Database;

class Semestre extends BaseModel
{
    protected static string $table = 'semestres';

    public static function disciplinasComAtribuicao(int $semestreId): array
    {
        return Database::fetchAll(
            "SELECT d.id, d.nome, d.sigla,
                    d.qtd_encontros_semanais, d.qtd_aulas, d.semestre_oferta,
                    c.nome AS curso_nome, c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome,
                    sa.professor_id AS professor_atribuido,
                    sa.sala_id AS sala_atribuida
             FROM disciplinas d
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             JOIN semestres sem  ON sem.id = ?
             LEFT JOIN semestre_atribuicoes sa
                    ON sa.disciplina_id = d.id AND sa.semestre_id = ?
             WHERE d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
             ORDER BY c.nome, t.serie_periodo, d.nome",
            [$semestreId, $semestreId]
        );
    }

    public static function salvarAtribuicoes(int $semestreId, array $professores, array $salas = []): void
    {
        Database::query("DELETE FROM semestre_atribuicoes WHERE semestre_id = ?", [$semestreId]);
        foreach ($professores as $disciplinaId => $professorId) {
            if ($professorId !== '') {
                $salaId = ($salas[$disciplinaId] ?? '') !== '' ? (int)$salas[$disciplinaId] : null;
                Database::query(
                    "INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id, sala_id)
                     VALUES (?, ?, ?, ?)",
                    [$semestreId, (int)$disciplinaId, (int)$professorId, $salaId]
                );
            }
        }
    }

    public static function geracoes(int $semestreId): array
    {
        return Database::fetchAll(
            "SELECT * FROM geracoes WHERE semestre_id = ? ORDER BY created_at DESC",
            [$semestreId]
        );
    }

    public static function turmasComSala(int $semestreId): array
    {
        return Database::fetchAll(
            "SELECT t.id, t.serie_periodo, c.nome AS curso_nome,
                    ss.sala_id AS sala_atribuida
             FROM turmas t
             JOIN cursos c      ON c.id = t.curso_id
             LEFT JOIN semestre_salas ss
                    ON ss.turma_id = t.id AND ss.semestre_id = ?
             WHERE t.ativo = 1
             ORDER BY c.nome, t.serie_periodo",
            [$semestreId]
        );
    }

    public static function salvarSalas(int $semestreId, array $salas): void
    {
        Database::query("DELETE FROM semestre_salas WHERE semestre_id = ?", [$semestreId]);
        foreach ($salas as $turmaId => $salaId) {
            if ($salaId !== '') {
                Database::query(
                    "INSERT INTO semestre_salas (semestre_id, turma_id, sala_id) VALUES (?, ?, ?)",
                    [$semestreId, (int)$turmaId, (int)$salaId]
                );
            }
        }
    }

    public static function countAtribuicoes(int $semestreId): int
    {
        $r = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM semestre_atribuicoes WHERE semestre_id = ?",
            [$semestreId]
        );
        return (int)($r['total'] ?? 0);
    }
}
