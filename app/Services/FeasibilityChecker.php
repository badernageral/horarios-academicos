<?php

namespace App\Services;

use App\Core\Database;

/**
 * Verifica inviabilidades estruturais ANTES da geração do horário:
 *  1. Encontro com mais aulas do que o dia comporta (slots do turno)
 *  2. Demanda semanal da turma maior que a capacidade do turno
 *  3. Professor atribuído sem disponibilidade cadastrada
 */
class FeasibilityChecker
{
    /** @return string[] lista de avisos (vazia = tudo viável) */
    public static function verificar(int $semestreId): array
    {
        $avisos = [];

        // Cursos ativos com seus dias
        $cursos = [];
        foreach (Database::fetchAll("SELECT * FROM cursos WHERE ativo = 1") as $c) {
            $c['dias'] = array_map('intval', json_decode($c['dias_semana'], true) ?? [1,2,3,4,5]);
            $cursos[$c['id']] = $c;
        }

        // Intervalos por curso+dia (dia NULL = todos os dias do curso)
        $breaks = [];
        foreach (Database::fetchAll("SELECT * FROM intervalos_curso") as $iv) {
            $cid  = (int)$iv['curso_id'];
            $dias = $iv['dia_semana'] !== null
                ? [(int)$iv['dia_semana']]
                : ($cursos[$cid]['dias'] ?? [1,2,3,4,5]);
            foreach ($dias as $d) {
                $breaks[$cid][$d][] = [
                    'inicio' => TimeHelper::toMinutes($iv['hora_inicio']),
                    'fim'    => TimeHelper::toMinutes($iv['hora_fim']),
                ];
            }
        }

        // Slots de aula por curso+dia (mesma malha do gerador e da grade)
        $slotsPorDia = [];
        foreach ($cursos as $cid => $c) {
            $dur = max(1, (int)$c['duracao_aula_minutos']);
            $ini = TimeHelper::toMinutes($c['turno_inicio']);
            $fim = TimeHelper::toMinutes($c['turno_fim']);
            foreach ($c['dias'] as $dia) {
                $n = 0;
                $t = $ini;
                while ($t < $fim) {
                    $b = null;
                    foreach ($breaks[$cid][$dia] ?? [] as $bk) {
                        if ($t >= $bk['inicio'] && $t < $bk['fim']) { $b = $bk; break; }
                    }
                    if ($b) { $t = $b['fim']; continue; }
                    if ($t + $dur > $fim) break;
                    $n++;
                    $t += $dur;
                }
                $slotsPorDia[$cid][$dia] = $n;
            }
        }

        // Disciplinas ofertadas neste semestre
        $discs = Database::fetchAll(
            "SELECT d.id, d.nome, d.qtd_aulas, d.qtd_encontros_semanais,
                    d.turma_id, d.curso_id,
                    t.serie_periodo, c.nome AS curso_nome
             FROM disciplinas d
             JOIN turmas t ON t.id = d.turma_id
             JOIN cursos c ON c.id = d.curso_id
             JOIN semestres sem ON sem.id = ?
             WHERE d.ativo = 1 AND (d.semestre_oferta & sem.semestre) > 0",
            [$semestreId]
        );

        // 1. Encontro maior que o dia + acumula demanda semanal por turma
        $demandaTurma = [];
        $turmaInfo    = [];
        foreach ($discs as $d) {
            $cid       = (int)$d['curso_id'];
            $slotsDias = $slotsPorDia[$cid] ?? [];
            $maxDia    = empty($slotsDias) ? 0 : max($slotsDias);
            $rotulo    = $d['curso_nome'] . ' – ' . $d['serie_periodo'];

            if ((int)$d['qtd_aulas'] > $maxDia) {
                $avisos[] = "\"{$d['nome']}\" ({$rotulo}): encontro de {$d['qtd_aulas']} aulas não cabe em nenhum dia — o turno comporta no máximo {$maxDia} aula(s)/dia.";
            }

            $tid = (int)$d['turma_id'];
            $demandaTurma[$tid] = ($demandaTurma[$tid] ?? 0)
                + (int)$d['qtd_aulas'] * (int)$d['qtd_encontros_semanais'];
            $turmaInfo[$tid] = ['rotulo' => $rotulo, 'capacidade' => array_sum($slotsDias)];
        }

        // 2. Demanda semanal da turma × capacidade
        foreach ($demandaTurma as $tid => $demanda) {
            $cap = $turmaInfo[$tid]['capacidade'];
            if ($demanda > $cap) {
                $avisos[] = "Turma {$turmaInfo[$tid]['rotulo']}: demanda de {$demanda} aulas/semana excede a capacidade de {$cap} do turno — impossível agendar tudo.";
            }
        }

        // 3. Professor atribuído sem disponibilidade cadastrada
        $profsSemDisp = Database::fetchAll(
            "SELECT DISTINCT p.nome
             FROM semestre_atribuicoes sa
             JOIN professores p ON p.id = sa.professor_id
             LEFT JOIN disponibilidade_professor dp ON dp.professor_id = p.id
             WHERE sa.semestre_id = ? AND dp.professor_id IS NULL
             ORDER BY p.nome",
            [$semestreId]
        );
        foreach ($profsSemDisp as $p) {
            $avisos[] = "Professor {$p['nome']} tem disciplinas atribuídas, mas nenhuma disponibilidade cadastrada — nada dele será agendado.";
        }

        return $avisos;
    }

    /**
     * Impacto de trocar a turma de uma disciplina sobre grades JÁ GERADAS.
     *
     * `horarios` guarda `turma_id` desnormalizado (a turma no momento da
     * geração), então trocar a turma da disciplina não mexe na grade: ela fica
     * desatualizada até ser regerada. Este método só DIAGNOSTICA — nada é
     * alterado, e regerar é decisão do usuário.
     *
     * Deve ser chamado DEPOIS do UPDATE: compara os horários gravados (que
     * ainda trazem a turma antiga) com a turma nova da disciplina.
     *
     * @return string[]
     */
    public static function impactoTrocaDeTurma(int $disciplinaId): array
    {
        $avisos = [];

        $horarios = Database::fetchAll(
            "SELECT h.id, h.geracao_id, h.dia_semana, h.hora_inicio, h.hora_fim,
                    d.nome AS disciplina_nome, d.turma_id AS turma_nova
             FROM horarios h
             JOIN disciplinas d ON d.id = h.disciplina_id
             WHERE h.disciplina_id = ? AND h.dia_semana <> 0
             ORDER BY h.dia_semana, h.hora_inicio",
            [$disciplinaId]
        );
        if (!$horarios) return $avisos;

        $dias = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta'];

        // Turno do curso da turma nova: o horário atual pode nem caber nele.
        $turmaNova = (int)$horarios[0]['turma_nova'];
        $destino = Database::fetchOne(
            "SELECT t.nome AS turma_nome, c.nome AS curso_nome, c.turno_inicio, c.turno_fim
             FROM turmas t JOIN cursos c ON c.id = t.curso_id WHERE t.id = ?",
            [$turmaNova]
        );

        $foraDoTurno = 0;
        $choques     = [];

        foreach ($horarios as $h) {
            $ini = TimeHelper::toHms($h['hora_inicio']);
            $fim = TimeHelper::toHms($h['hora_fim']);

            if ($destino
                && ($ini < TimeHelper::toHms($destino['turno_inicio'])
                    || $fim > TimeHelper::toHms($destino['turno_fim']))) {
                $foraDoTurno++;
            }

            // Choque com aulas que a turma NOVA já tem no mesmo dia/horário.
            $conflitos = Database::fetchAll(
                "SELECT DISTINCT d2.nome
                 FROM horarios h2
                 JOIN disciplinas d2 ON d2.id = h2.disciplina_id
                 WHERE h2.geracao_id = ? AND h2.turma_id = ? AND h2.dia_semana = ?
                   AND h2.id <> ? AND h2.hora_inicio < ? AND h2.hora_fim > ?",
                [$h['geracao_id'], $turmaNova, $h['dia_semana'], $h['id'], $fim, $ini]
            );
            foreach ($conflitos as $c) {
                $choques[] = sprintf(
                    '%s (%s, %s–%s)',
                    $c['nome'], $dias[$h['dia_semana']] ?? '?',
                    substr($ini, 0, 5), substr($fim, 0, 5)
                );
            }
        }

        $nome = $horarios[0]['disciplina_nome'];
        $qtd  = count($horarios);
        $avisos[] = sprintf(
            'A grade já gerada tem %d aula(s) de "%s" na turma anterior. Ela NÃO foi alterada — regere para aplicar a troca.',
            $qtd, $nome
        );

        if ($foraDoTurno > 0 && $destino) {
            $avisos[] = sprintf(
                '%d dessas aulas ficam fora do turno de %s (%s–%s) e não caberiam onde estão hoje.',
                $foraDoTurno, $destino['curso_nome'],
                substr($destino['turno_inicio'], 0, 5), substr($destino['turno_fim'], 0, 5)
            );
        }

        if ($choques) {
            $avisos[] = 'Nos mesmos horários a turma nova já tem: ' . implode('; ', array_unique($choques)) . '.';
        }

        return $avisos;
    }

    /**
     * Disciplinas cuja grade gerada aponta para uma turma diferente da atual —
     * ou seja, a turma foi trocada depois da geração. Serve de aviso persistente
     * (o flash do salvamento some; a inconsistência não).
     *
     * @return array<int, array{disciplina:string, antiga:string, nova:string, aulas:int}>
     */
    public static function gradesDesatualizadas(?int $geracaoId = null): array
    {
        $sql = "SELECT d.nome AS disciplina,
                       COALESCE(ta.nome, '?') AS antiga,
                       COALESCE(tn.nome, '?') AS nova,
                       COUNT(*) AS aulas
                FROM horarios h
                JOIN disciplinas d ON d.id = h.disciplina_id
                LEFT JOIN turmas ta ON ta.id = h.turma_id
                LEFT JOIN turmas tn ON tn.id = d.turma_id
                WHERE h.turma_id <> d.turma_id";
        $params = [];
        if ($geracaoId !== null) {
            $sql .= " AND h.geracao_id = ?";
            $params[] = $geracaoId;
        }
        $sql .= " GROUP BY d.id, ta.nome, tn.nome ORDER BY d.nome";

        return array_map(
            fn($r) => [
                'disciplina' => $r['disciplina'],
                'antiga'     => $r['antiga'],
                'nova'       => $r['nova'],
                'aulas'      => (int)$r['aulas'],
            ],
            Database::fetchAll($sql, $params)
        );
    }
}
