<?php

namespace App\Models;

use App\Core\Database;

class Semestre extends BaseModel
{
    protected static string $table = 'semestres';

    public static function disciplinasComAtribuicao(int $semestreId): array
    {
        $rows = Database::fetchAll(
            "SELECT d.id, d.nome, d.sigla,
                    d.qtd_encontros_semanais, d.qtd_aulas, d.semestre_oferta, d.qtd_professores,
                    c.nome AS curso_nome, c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome,
                    GROUP_CONCAT(sa.professor_id ORDER BY sa.slot SEPARATOR ',') AS professores_atribuidos,
                    MAX(CASE WHEN sa.slot = 1 THEN sa.sala_id ELSE NULL END) AS sala_atribuida
             FROM disciplinas d
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             JOIN semestres sem  ON sem.id = ?
             LEFT JOIN semestre_atribuicoes sa
                    ON sa.disciplina_id = d.id AND sa.semestre_id = ?
             WHERE d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
             GROUP BY d.id, d.nome, d.sigla,
                      d.qtd_encontros_semanais, d.qtd_aulas, d.semestre_oferta, d.qtd_professores,
                      c.nome, c.duracao_aula_minutos, t.serie_periodo
             ORDER BY c.nome, t.serie_periodo, d.nome",
            [$semestreId, $semestreId]
        );

        foreach ($rows as &$row) {
            $ids = array_filter(explode(',', $row['professores_atribuidos'] ?? ''));
            $row['professores_atribuidos'] = array_values(array_map('intval', $ids));
        }
        unset($row);
        return $rows;
    }

    public static function salvarAtribuicoes(int $semestreId, array $professores, array $salas = []): void
    {
        // Atribuição atual (antes da troca), por disciplina: slot => professor_id.
        // Usado para sincronizar horarios.professor_id já gerados sem perder dia/hora/sala.
        $atuais = [];
        foreach (Database::fetchAll(
            "SELECT disciplina_id, slot, professor_id FROM semestre_atribuicoes WHERE semestre_id = ?",
            [$semestreId]
        ) as $r) {
            $atuais[(int)$r['disciplina_id']][(int)$r['slot']] = (int)$r['professor_id'];
        }

        Database::query("DELETE FROM semestre_atribuicoes WHERE semestre_id = ?", [$semestreId]);

        // $professores: [disciplina_id => [slot => professor_id]]
        foreach ($professores as $disciplinaId => $slots) {
            $disciplinaId = (int)$disciplinaId;
            $salaId = ($salas[$disciplinaId] ?? '') !== '' ? (int)$salas[$disciplinaId] : null;
            $trocas = [];
            foreach ((array)$slots as $slot => $professorId) {
                if ((string)$professorId === '') continue;
                $slot        = (int)$slot;
                $professorId = (int)$professorId;
                Database::query(
                    "INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id, slot, sala_id)
                     VALUES (?, ?, ?, ?, ?)",
                    [$semestreId, $disciplinaId, $professorId, $slot, $slot === 1 ? $salaId : null]
                );

                $antigo = $atuais[$disciplinaId][$slot] ?? null;
                if ($antigo !== null && $antigo !== $professorId) {
                    $trocas[$antigo] = $professorId;
                }
            }
            if ($trocas) {
                self::sincronizarProfessorHorarios($semestreId, $disciplinaId, $trocas);
            }
        }
    }

    // Atualiza horarios.professor_id já gerados quando a atribuição muda, preservando
    // dia/hora/sala. Mapa antigo=>novo aplicado com CASE para suportar trocas simultâneas
    // (ex.: slot 1 vira slot 2 e vice-versa) sem um update sobrescrever o outro.
    private static function sincronizarProfessorHorarios(int $semestreId, int $disciplinaId, array $trocas): void
    {
        $cases  = [];
        $params = [];
        foreach ($trocas as $antigo => $novo) {
            $cases[]  = 'WHEN ? THEN ?';
            $params[] = $antigo;
            $params[] = $novo;
        }
        $antigos      = array_keys($trocas);
        $placeholders = implode(',', array_fill(0, count($antigos), '?'));

        Database::query(
            "UPDATE horarios h
             JOIN geracoes g ON g.id = h.geracao_id
             SET h.professor_id = CASE h.professor_id " . implode(' ', $cases) . " ELSE h.professor_id END
             WHERE g.semestre_id = ? AND h.disciplina_id = ? AND h.professor_id IN ($placeholders)",
            [...$params, $semestreId, $disciplinaId, ...$antigos]
        );
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
