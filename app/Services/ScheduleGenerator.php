<?php

namespace App\Services;

use App\Core\Database;
use App\Models\{Curso, Sala};

/**
 * Motor de geração automática de horários acadêmicos.
 *
 * Algoritmo:
 *  1. Cria lista de "atividades" a agendar (1 por encontro de cada disciplina).
 *  2. Ordena por dificuldade (MCF - Most Constrained First).
 *  3. Para cada atividade, gera candidatos válidos (dia, hora_inicio, sala)
 *     considerando TODAS as restrições de tempo real (sem períodos fixos).
 *  4. Pontua os candidatos com as restrições soft configuráveis.
 *  5. Atribui o melhor candidato.
 *  6. Registra conflitos e inviabilidades.
 *  7. Fase de busca local: tenta realocar atividades falhas via swap.
 */
class ScheduleGenerator
{
    // ── Estado interno ─────────────────────────────────────────────
    /** @var array<int, array<int, array[]>> [professor_id][dia] = [[inicio,fim],...] */
    private array $ocupacaoProfessor = [];

    /** @var array<int, array<int, array[]>> [turma_id][dia] */
    private array $ocupacaoTurma = [];

    /** @var array<int, array<int, array[]>> [sala_id][dia] */
    private array $ocupacaoSala = [];

    /** Atividades já agendadas: [atividade, dia, inicio, fim, sala_id] */
    private array $agendados = [];

    /** Atividades que não puderam ser agendadas */
    private array $falhas = [];

    // ── Dados carregados do banco ──────────────────────────────────
    private array $cursos       = [];
    private array $intervalos   = [];  // [curso_id][dia] = [[inicio,fim],...]
    private array $professores  = [];  // [id] = dados
    private array $disponibilidade = []; // [professor_id][dia] = [[inicio,fim],...]
    private array $salas        = [];
    private array $salasPorTurma = []; // [turma_id => sala_id]

    /** Pesos das restrições soft */
    private array $pesos = [
        'janela_professor'    => 10.0,
        'janela_turma'        => 8.0,
        'agrupa_disciplina'   => 5.0,
        'distribuicao_semana' => 3.0,
        'horario_extremo'     => 0.01,
        'balancear_professor' => 4.0,
    ];

    private int $geracaoId;
    private int $semestreId;

    // ──────────────────────────────────────────────────────────────
    public function __construct(int $geracaoId, int $semestreId, array $pesos = [])
    {
        $this->geracaoId  = $geracaoId;
        $this->semestreId = $semestreId;
        $this->pesos      = array_merge($this->pesos, $pesos);
    }

    // ──────────────────────────────────────────────────────────────
    public function gerar(): array
    {
        $this->log('Iniciando carregamento de dados...');
        $this->carregarDados();

        $atividades = $this->criarAtividades();
        $total      = count($atividades);
        $this->log("Total de atividades a agendar: {$total}");

        // ── Fase 1: Ordenação MCF ──────────────────────────────────
        usort($atividades, [$this, 'compararDificuldade']);

        // ── Fase 2: Agendamento greedy ─────────────────────────────
        foreach ($atividades as $atividade) {
            $candidatos = $this->gerarCandidatos($atividade);

            if (empty($candidatos)) {
                $this->falhas[] = $atividade;
                continue;
            }

            usort($candidatos, fn($a, $b) => $a['score'] <=> $b['score']);
            $this->atribuir($atividade, $candidatos[0]);
        }

        // ── Fase 3: Busca local – tenta realocar falhas ───────────
        if (!empty($this->falhas)) {
            $this->log('Tentando realocar ' . count($this->falhas) . ' atividades falhas...');
            $this->buscaLocal();
        }

        // ── Fase 4: Persistir resultado ────────────────────────────
        return $this->salvar($total);
    }

    // ──────────────────────────────────────────────────────────────
    // CARREGAMENTO
    // ──────────────────────────────────────────────────────────────
    private function carregarDados(): void
    {
        // Cursos com turno
        foreach (Database::fetchAll("SELECT * FROM cursos WHERE ativo = 1") as $c) {
            $c['dias_semana'] = json_decode($c['dias_semana'], true);
            $this->cursos[$c['id']] = $c;
        }

        // Intervalos por curso e dia
        foreach (Database::fetchAll("SELECT * FROM intervalos_curso ORDER BY hora_inicio") as $i) {
            $inicio = TimeHelper::toMinutes($i['hora_inicio']);
            $fim    = TimeHelper::toMinutes($i['hora_fim']);
            $dias   = $i['dia_semana'] !== null
                ? [(int)$i['dia_semana']]
                : [1, 2, 3, 4, 5, 6];
            foreach ($dias as $dia) {
                $this->intervalos[$i['curso_id']][$dia][] = ['inicio' => $inicio, 'fim' => $fim];
            }
        }

        // Professores
        foreach (Database::fetchAll("SELECT * FROM professores WHERE ativo = 1") as $p) {
            $this->professores[$p['id']] = $p;
        }

        // Disponibilidade dos professores
        foreach (Database::fetchAll("SELECT * FROM disponibilidade_professor") as $d) {
            $this->disponibilidade[$d['professor_id']][$d['dia_semana']][] = [
                'inicio' => TimeHelper::toMinutes($d['hora_inicio']),
                'fim'    => TimeHelper::toMinutes($d['hora_fim']),
            ];
        }

        // Salas
        foreach (Database::fetchAll("SELECT * FROM salas WHERE ativo = 1") as $s) {
            $this->salas[$s['id']] = $s;
        }

        // Salas pré-atribuídas por disciplina neste semestre
        foreach (Database::fetchAll(
            "SELECT disciplina_id, sala_id FROM semestre_atribuicoes WHERE semestre_id = ? AND sala_id IS NOT NULL",
            [$this->semestreId]
        ) as $row) {
            $this->salasPorTurma[(int)$row['disciplina_id']] = (int)$row['sala_id'];
        }
    }

    // ──────────────────────────────────────────────────────────────
    // CRIAÇÃO DAS ATIVIDADES
    // ──────────────────────────────────────────────────────────────
    private function criarAtividades(): array
    {
        $atividades = [];
        $disciplinas = Database::fetchAll(
            "SELECT d.*,
                    c.turno_inicio, c.turno_fim, c.dias_semana, c.duracao_aula_minutos,
                    sa.professor_id, sa.sala_id AS sala_atribuida
             FROM disciplinas d
             JOIN turmas t ON t.id = d.turma_id
             JOIN cursos c ON c.id = d.curso_id
             JOIN semestre_atribuicoes sa ON sa.disciplina_id = d.id AND sa.semestre_id = ?
             WHERE d.ativo = 1",
            [$this->semestreId]
        );

        foreach ($disciplinas as $disc) {
            $disc['dias_semana'] = json_decode($disc['dias_semana'], true);
            $duracao = (int)$disc['qtd_aulas'] * (int)$disc['duracao_aula_minutos'];
            for ($n = 1; $n <= (int)$disc['qtd_encontros_semanais']; $n++) {
                $atividades[] = [
                    'disciplina_id'   => (int)$disc['id'],
                    'disciplina_nome' => $disc['nome'],
                    'disciplina_cor'  => $disc['cor'],
                    'turma_id'        => (int)$disc['turma_id'],
                    'professor_id'    => (int)$disc['professor_id'],
                    'curso_id'        => (int)$disc['curso_id'],
                    'turno_inicio'    => TimeHelper::toMinutes($disc['turno_inicio']),
                    'turno_fim'       => TimeHelper::toMinutes($disc['turno_fim']),
                    'dias_semana'     => array_map('intval', $disc['dias_semana']),
                    'duracao'         => $duracao,
                    'encontro_num'    => $n,
                ];
            }
        }

        return $atividades;
    }

    // ──────────────────────────────────────────────────────────────
    // ORDENAÇÃO: mais restrita primeiro
    // ──────────────────────────────────────────────────────────────
    private function compararDificuldade(array $a, array $b): int
    {
        // Menos dias disponíveis → mais restrito
        $diasA = count($this->getDiasDisponiveis($a));
        $diasB = count($this->getDiasDisponiveis($b));
        if ($diasA !== $diasB) return $diasA - $diasB;

        // Aulas mais longas → mais difíceis de encaixar
        if ($a['duracao'] !== $b['duracao']) return $b['duracao'] - $a['duracao'];

        return 0;
    }

    private function getDiasDisponiveis(array $atividade): array
    {
        $diasCurso = $atividade['dias_semana'];
        $diasProf  = array_keys($this->disponibilidade[$atividade['professor_id']] ?? []);
        return array_intersect($diasCurso, $diasProf);
    }

    // ──────────────────────────────────────────────────────────────
    // GERAÇÃO DE CANDIDATOS
    // ──────────────────────────────────────────────────────────────
    private function gerarCandidatos(array $atividade): array
    {
        $candidatos  = [];
        $duracao     = $atividade['duracao'];
        $turnoInicio = $atividade['turno_inicio'];
        $turnoFim    = $atividade['turno_fim'];
        $diasCurso   = $atividade['dias_semana'];

        // Salas compatíveis ordenadas por preferência
        $salas = $this->salasCompativeis($atividade);

        // Aula sem sala possível → candidato sem sala (aviso)
        $salaIds = empty($salas) ? [null] : array_column($salas, 'id');

        foreach ($diasCurso as $dia) {
            // Verificar disponibilidade do professor neste dia
            if (!$this->professorDisponivelDia($atividade['professor_id'], $dia)) continue;


            // Intervalos (breaks) deste curso neste dia
            $breaks = $this->intervalos[$atividade['curso_id']][$dia] ?? [];

            foreach ($salaIds as $salaId) {
                // Ocupação combinada: professor + turma + (sala se houver)
                $ocupado = $this->getOcupadoCombinado(
                    $atividade['professor_id'],
                    $atividade['turma_id'],
                    $salaId,
                    $dia
                );

                // Inícios candidatos: início do turno + logo após cada ocupação
                $inicioCandidatos = TimeHelper::possiveisInicios($ocupado, $turnoInicio);

                foreach ($inicioCandidatos as $inicio) {
                    $fim = $inicio + $duracao;

                    // 1. Dentro do turno
                    if ($fim > $turnoFim) continue;

                    // 2. Não sobrepõe breaks
                    if (TimeHelper::overlapsAny($inicio, $fim, $breaks)) continue;
                    // Também não começa durante break
                    if ($this->dentroDeBreak($inicio, $fim, $breaks)) continue;

                    // 3. Não sobrepõe ocupações existentes
                    if (TimeHelper::overlapsAny($inicio, $fim, $ocupado)) continue;

                    // 4. Dentro da disponibilidade do professor
                    if (!$this->professorDisponivelHorario($atividade['professor_id'], $dia, $inicio, $fim)) continue;

                    // 5. Calcular score (soft constraints)
                    $score = $this->calcularScore($atividade, $dia, $inicio, $fim, $salaId);

                    $candidatos[] = [
                        'dia'     => $dia,
                        'inicio'  => $inicio,
                        'fim'     => $fim,
                        'sala_id' => $salaId,
                        'score'   => $score,
                    ];
                }
            }
        }

        return $candidatos;
    }

    // ──────────────────────────────────────────────────────────────
    // ATRIBUIÇÃO
    // ──────────────────────────────────────────────────────────────
    private function atribuir(array $atividade, array $slot): void
    {
        $entry = ['inicio' => $slot['inicio'], 'fim' => $slot['fim']];

        $this->ocupacaoProfessor[$atividade['professor_id']][$slot['dia']][] = $entry;
        $this->ocupacaoTurma[$atividade['turma_id']][$slot['dia']][]         = $entry;
        if ($slot['sala_id'] !== null) {
            $this->ocupacaoSala[$slot['sala_id']][$slot['dia']][] = $entry;
        }

        $this->agendados[] = [
            'atividade' => $atividade,
            'dia'       => $slot['dia'],
            'inicio'    => $slot['inicio'],
            'fim'       => $slot['fim'],
            'sala_id'   => $slot['sala_id'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // BUSCA LOCAL: tenta liberar espaço para atividades falhas
    // ──────────────────────────────────────────────────────────────
    private function buscaLocal(): void
    {
        $resolvidosIdx = [];

        foreach ($this->falhas as $idx => $falha) {
            // Tenta mover uma atividade agendada do mesmo professor/turma para liberar espaço
            foreach ($this->agendados as $i => $agendado) {
                if ($agendado['atividade']['professor_id'] !== $falha['professor_id']) continue;
                if ($agendado['atividade']['turma_id'] !== $falha['turma_id']) continue;

                // Remove temporariamente
                $this->removerAtribuicao($i);

                // Tenta agendar a falha
                $candidatos = $this->gerarCandidatos($falha);
                if (!empty($candidatos)) {
                    usort($candidatos, fn($a, $b) => $a['score'] <=> $b['score']);
                    $this->atribuir($falha, $candidatos[0]);

                    // Tenta re-agendar a removida
                    $candidatosRem = $this->gerarCandidatos($agendado['atividade']);
                    if (!empty($candidatosRem)) {
                        usort($candidatosRem, fn($a, $b) => $a['score'] <=> $b['score']);
                        $this->atribuir($agendado['atividade'], $candidatosRem[0]);
                        $resolvidosIdx[] = $idx;
                        break;
                    } else {
                        // Reverter: a falha continua falha, restaura removida
                        array_pop($this->agendados); // remove falha recém inserida
                    }
                }

                // Restaura se não resolveu
                $this->restaurarAtribuicao($agendado);
            }
        }

        // Remove as falhas resolvidas
        foreach (array_reverse($resolvidosIdx) as $idx) {
            unset($this->falhas[$idx]);
        }
        $this->falhas = array_values($this->falhas);
    }

    private function removerAtribuicao(int $agendadoIdx): void
    {
        $ag = $this->agendados[$agendadoIdx];
        $entry = ['inicio' => $ag['inicio'], 'fim' => $ag['fim']];

        $this->removerEntrada($this->ocupacaoProfessor[$ag['atividade']['professor_id']][$ag['dia']] ?? [], $entry);
        $this->removerEntrada($this->ocupacaoTurma[$ag['atividade']['turma_id']][$ag['dia']] ?? [], $entry);
        if ($ag['sala_id']) {
            $this->removerEntrada($this->ocupacaoSala[$ag['sala_id']][$ag['dia']] ?? [], $entry);
        }
        unset($this->agendados[$agendadoIdx]);
        $this->agendados = array_values($this->agendados);
    }

    private function restaurarAtribuicao(array $ag): void
    {
        $entry = ['inicio' => $ag['inicio'], 'fim' => $ag['fim']];
        $this->ocupacaoProfessor[$ag['atividade']['professor_id']][$ag['dia']][] = $entry;
        $this->ocupacaoTurma[$ag['atividade']['turma_id']][$ag['dia']][]         = $entry;
        if ($ag['sala_id']) {
            $this->ocupacaoSala[$ag['sala_id']][$ag['dia']][] = $entry;
        }
        $this->agendados[] = $ag;
    }

    private function removerEntrada(array &$lista, array $entry): void
    {
        foreach ($lista as $k => $item) {
            if ($item['inicio'] === $entry['inicio'] && $item['fim'] === $entry['fim']) {
                unset($lista[$k]);
                $lista = array_values($lista);
                return;
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // PONTUAÇÃO SOFT
    // ──────────────────────────────────────────────────────────────
    private function calcularScore(array $atividade, int $dia, int $inicio, int $fim, ?int $salaId): float
    {
        $score = 0.0;

        // S1: Minimizar janelas do professor
        $intervsProfDia = $this->ocupacaoProfessor[$atividade['professor_id']][$dia] ?? [];
        $newInterval    = ['inicio' => $inicio, 'fim' => $fim];
        $allIntervals   = array_merge($intervsProfDia, [$newInterval]);
        $janelaAtual    = TimeHelper::calcularJanelas($allIntervals);
        $janelaAntes    = TimeHelper::calcularJanelas($intervsProfDia);
        $score += ($janelaAtual - $janelaAntes) * $this->pesos['janela_professor'];

        // S2: Minimizar janelas da turma
        $intervsTurmaDia = $this->ocupacaoTurma[$atividade['turma_id']][$dia] ?? [];
        $allTurma        = array_merge($intervsTurmaDia, [$newInterval]);
        $janelaTurmaNova = TimeHelper::calcularJanelas($allTurma);
        $janelaTurmaAntes= TimeHelper::calcularJanelas($intervsTurmaDia);
        $score += ($janelaTurmaNova - $janelaTurmaAntes) * $this->pesos['janela_turma'];

        // S3: Evitar mesma disciplina no mesmo dia (distribuir na semana)
        $mesmaDisciplinaDia = $this->disciplinaNesteDia(
            $atividade['disciplina_id'], $atividade['turma_id'], $dia
        );
        if ($mesmaDisciplinaDia) {
            $score += $this->pesos['agrupa_disciplina'] * 1000;
        }

        // S4: Distribuição semanal da turma (evitar sobrecarregar um dia)
        $cargaTurmaDia = TimeHelper::totalMinutosDia($intervsTurmaDia);
        $score += $cargaTurmaDia * $this->pesos['distribuicao_semana'];

        // S5: Evitar horários extremos (penalizar início muito cedo ou muito tarde)
        $turnoMeio = ($atividade['turno_inicio'] + $atividade['turno_fim']) / 2;
        $distCentro = abs($inicio - $turnoMeio);
        $score += $distCentro * $this->pesos['horario_extremo'];

        // S6: Balancear carga diária do professor
        $cargaProfDia = TimeHelper::totalMinutosDia($intervsProfDia);
        $score += $cargaProfDia * $this->pesos['balancear_professor'];

        return $score;
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS DE RESTRIÇÕES
    // ──────────────────────────────────────────────────────────────
    private function salasCompativeis(array $atividade): array
    {
        $disciplinaId = $atividade['disciplina_id'] ?? 0;
        if (isset($this->salasPorTurma[$disciplinaId])) {
            $salaId = $this->salasPorTurma[$disciplinaId];
            return isset($this->salas[$salaId]) ? [$this->salas[$salaId]] : [];
        }
        return array_values(array_filter($this->salas, fn($s) => (bool)$s['ativo']));
    }

    private function getOcupadoCombinado(int $profId, int $turmaId, ?int $salaId, int $dia): array
    {
        $ocp = array_merge(
            $this->ocupacaoProfessor[$profId][$dia] ?? [],
            $this->ocupacaoTurma[$turmaId][$dia] ?? []
        );
        if ($salaId !== null) {
            $ocp = array_merge($ocp, $this->ocupacaoSala[$salaId][$dia] ?? []);
        }
        return $ocp;
    }

    private function professorDisponivelDia(int $profId, int $dia): bool
    {
        return isset($this->disponibilidade[$profId][$dia])
            && !empty($this->disponibilidade[$profId][$dia]);
    }

    private function professorDisponivelHorario(int $profId, int $dia, int $inicio, int $fim): bool
    {
        $slots = $this->disponibilidade[$profId][$dia] ?? [];
        if (empty($slots)) return false;

        foreach ($slots as $s) {
            if ($inicio >= $s['inicio'] && $fim <= $s['fim']) return true;
        }
        return false;
    }

    private function cargaDiariaProfessor(int $profId, int $dia): int
    {
        return TimeHelper::totalMinutosDia($this->ocupacaoProfessor[$profId][$dia] ?? []);
    }

    private function cargaSemanalProfessor(int $profId): int
    {
        $total = 0;
        foreach ($this->ocupacaoProfessor[$profId] ?? [] as $diaIntervals) {
            $total += TimeHelper::totalMinutosDia($diaIntervals);
        }
        return $total;
    }

    private function dentroDeBreak(int $inicio, int $fim, array $breaks): bool
    {
        // Verifica se a aula começa dentro de um intervalo (break)
        foreach ($breaks as $b) {
            if ($inicio >= $b['inicio'] && $inicio < $b['fim']) return true;
        }
        return false;
    }

    private function disciplinaNesteDia(int $disciplinaId, int $turmaId, int $dia): bool
    {
        foreach ($this->agendados as $ag) {
            if ($ag['atividade']['disciplina_id'] === $disciplinaId
                && $ag['atividade']['turma_id'] === $turmaId
                && $ag['dia'] === $dia
            ) {
                return true;
            }
        }
        return false;
    }

    // ──────────────────────────────────────────────────────────────
    // PERSISTÊNCIA
    // ──────────────────────────────────────────────────────────────
    private function salvar(int $total): array
    {
        $agendados = count($this->agendados);
        $falhas    = count($this->falhas);
        $status    = $falhas === 0 ? 'concluido' : ($agendados > 0 ? 'parcial' : 'erro');

        // Monta log de conflitos
        $logLines = [];
        foreach ($this->falhas as $f) {
            $logLines[] = "NÃO AGENDADO: {$f['disciplina_nome']} (Turma ID {$f['turma_id']}, Encontro {$f['encontro_num']})";
        }

        Database::query(
            "UPDATE geracoes SET status=?, total_atividades=?, atividades_agendadas=?,
             atividades_falhas=?, log=?, finished_at=NOW()
             WHERE id=?",
            [$status, $total, $agendados, $falhas, implode("\n", $logLines), $this->geracaoId]
        );

        if ($agendados > 0) {
            Database::beginTransaction();
            try {
                Database::query("DELETE FROM horarios WHERE geracao_id = ?", [$this->geracaoId]);

                $stmt = Database::getInstance()->prepare(
                    "INSERT INTO horarios
                     (geracao_id, disciplina_id, turma_id, professor_id, sala_id, dia_semana, hora_inicio, hora_fim)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                foreach ($this->agendados as $ag) {
                    $stmt->execute([
                        $this->geracaoId,
                        $ag['atividade']['disciplina_id'],
                        $ag['atividade']['turma_id'],
                        $ag['atividade']['professor_id'],
                        $ag['sala_id'],
                        $ag['dia'],
                        TimeHelper::fromMinutes($ag['inicio']),
                        TimeHelper::fromMinutes($ag['fim']),
                    ]);
                }

                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                throw $e;
            }
        }

        return [
            'geracao_id'          => $this->geracaoId,
            'status'              => $status,
            'total_atividades'    => $total,
            'atividades_agendadas'=> $agendados,
            'atividades_falhas'   => $falhas,
            'conflitos'           => $this->formatarConflitos(),
        ];
    }

    private function formatarConflitos(): array
    {
        return array_map(fn($f) => [
            'disciplina' => $f['disciplina_nome'],
            'turma_id'   => $f['turma_id'],
            'encontro'   => $f['encontro_num'],
            'motivo'     => 'Nenhum horário válido encontrado para este encontro.',
        ], $this->falhas);
    }

    private function log(string $msg): void
    {
        // Poderia persistir em arquivo/banco; por ora apenas registra internamente
    }
}
