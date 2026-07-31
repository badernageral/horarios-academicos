<?php

namespace App\Services;

use App\Core\Database;

/**
 * Monta a malha visual da grade (slots do turno + posicionamento dos blocos).
 *
 * Fonte única de verdade do layout: a tela (`horarios/grade.php`) e a
 * exportação em PDF (`PdfExporter`) consomem exatamente a mesma estrutura,
 * para que o papel reproduza o que se vê no navegador.
 */
class GradeLayout
{
    /**
     * @param  array $todosHorarios  Saída de Horario::porGeracao() (inclui limbo)
     * @return array  [turma_id => [curso_nome, turma_nome, duracao, slots,
     *                              num_slots, grid, skip, limbo]]
     */
    public static function montar(array $todosHorarios): array
    {
        $intervalos = self::intervalosPorCurso();

        // 1. Agrupar por turma (dia_semana = 0 → limbo: sem horário atribuído)
        $raw = [];
        foreach ($todosHorarios as $h) {
            $dia = (int)$h['dia_semana'];
            if ($dia > 5) continue;
            $tid = $h['turma_id'];
            if (!isset($raw[$tid])) {
                $raw[$tid] = [
                    'curso_id'   => (int)$h['curso_id'],
                    'curso_nome' => $h['curso_nome'],
                    'turma_nome' => $h['serie_periodo'],
                    'duracao'    => max(1, (int)$h['duracao_aula_minutos']),
                    'turno_ini'  => TimeHelper::toMinutes($h['turno_inicio']),
                    'turno_fim'  => TimeHelper::toMinutes($h['turno_fim']),
                    'por_dia'    => [1=>[], 2=>[], 3=>[], 4=>[], 5=>[]],
                    'limbo'      => [],
                ];
            }
            if ($dia < 1) {
                $raw[$tid]['limbo'][] = $h;
                continue;
            }
            $raw[$tid]['por_dia'][$dia][] = $h;
        }

        // 2. Montar grade com slots do turno completo (incluindo linhas de intervalo)
        $grade = [];
        foreach ($raw as $tid => $tData) {
            $dur      = $tData['duracao'];
            $turnoIni = $tData['turno_ini'];
            $turnoFim = $tData['turno_fim'];
            $cursoId  = $tData['curso_id'];
            $ivList   = array_values($intervalos[$cursoId] ?? []);

            // Cada slot: ['min'=>int, 'fim'=>int, 'type'=>'aula'|'intervalo']
            $slots = [];
            $t = $turnoIni;
            while ($t < $turnoFim) {
                $inIv = null;
                foreach ($ivList as $iv) {
                    if ($t >= $iv['inicio'] && $t < $iv['fim']) { $inIv = $iv; break; }
                }
                if ($inIv) {
                    $slots[] = ['min' => $t, 'fim' => $inIv['fim'], 'type' => 'intervalo'];
                    $t = $inIv['fim'];
                } else {
                    if ($t + $dur > $turnoFim) break;
                    $slots[] = ['min' => $t, 'fim' => $t + $dur, 'type' => 'aula'];
                    $t += $dur;
                }
            }

            $numSlots = count($slots);
            if ($numSlots === 0) continue;

            // slotMap: hora_inicio (minutos) → índice (apenas slots de aula)
            $slotMap = [];
            foreach ($slots as $idx => $slot) {
                if ($slot['type'] === 'aula') $slotMap[$slot['min']] = $idx;
            }

            $grid = $skip = [];
            for ($dia = 1; $dia <= 5; $dia++) {
                $grid[$dia] = array_fill(0, $numSlots, null);
                $skip[$dia] = array_fill(0, $numSlots, false);
            }

            // Um bloco POR LINHA de horário: cada slot ocupado repete disciplina,
            // o horário daquela linha e o professor. Assim nenhum bloco fica alto
            // com um vazio entre o nome e a faixa do professor — e o comportamento
            // fica igual ao de quem já era partido por intervalo.
            // Usa qtd_aulas para contar slots — hora_fim no BD pode ignorar intervalo.
            foreach ($tData['por_dia'] as $dia => $diaList) {
                foreach ($diaList as $h) {
                    $hIni     = TimeHelper::toMinutes($h['hora_inicio']);
                    $qtdAulas = max(1, (int)$h['qtd_aulas']);
                    $firstIdx = $slotMap[$hIni] ?? null;
                    if ($firstIdx === null) continue;

                    $consumed = 0;
                    for ($i = $firstIdx; $i < $numSlots && $consumed < $qtdAulas; $i++) {
                        if ($slots[$i]['type'] === 'intervalo') continue;
                        $grid[$dia][$i] = array_merge($h, [
                            'rowspan'  => 1,
                            'slot_ini' => $slots[$i]['min'],
                            'slot_fim' => $slots[$i]['fim'],
                        ]);
                        $consumed++;
                    }
                }
            }

            $grade[$tid] = [
                'curso_nome' => $tData['curso_nome'],
                'turma_nome' => $tData['turma_nome'],
                'duracao'    => $dur,
                'slots'      => $slots,
                'num_slots'  => $numSlots,
                'grid'       => $grid,
                'skip'       => $skip,
                'limbo'      => $tData['limbo'],
            ];
        }

        return $grade;
    }

    /** Intervalos deduplicados por curso_id (flat, sem duplicar por dia) */
    private static function intervalosPorCurso(): array
    {
        $intervalos = [];
        foreach (Database::fetchAll("SELECT * FROM intervalos_curso ORDER BY hora_inicio") as $iv) {
            $inicio = TimeHelper::toMinutes($iv['hora_inicio']);
            $fim    = TimeHelper::toMinutes($iv['hora_fim']);
            $key    = $iv['curso_id'] . '-' . $inicio . '-' . $fim;
            $intervalos[$iv['curso_id']][$key] = ['inicio' => $inicio, 'fim' => $fim];
        }
        return $intervalos;
    }
}
