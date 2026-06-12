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
        // Janelas do professor e balanceamento diário zerados por decisão do
        // usuário: não importa o horário dentro do dia, só a quantidade de dias
        'janela_professor'    => 0.0,
        'janela_turma'        => 8.0,
        'agrupa_disciplina'   => 5.0,
        'distribuicao_semana' => 3.0,
        'horario_extremo'     => 0.01,
        'balancear_professor' => 0.0,
        'compactar_professor' => 2500.0,
        'preferencia_dia2'    => 2000.0,
    ];

    /** Quantidade de atividades por professor (para ordenação MCF) */
    private array $cargaAtividadesProfessor = [];

    /** Cache de slots de aula: [curso_id][dia] = [['inicio','fim'],...] */
    private array $slotsCache = [];

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

        // Carga de atividades por professor (professores mais carregados primeiro)
        foreach ($atividades as $a) {
            $this->cargaAtividadesProfessor[$a['professor_id']] =
                ($this->cargaAtividadesProfessor[$a['professor_id']] ?? 0) + 1;
        }

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

        // ── Fase 4: Otimização – move atividades para melhorar score ──
        $this->otimizar();

        // ── Fase 5: Mínimo de 2 dias de aula por professor ─────────
        $this->garantirDoisDias();

        // ── Fase 6: Persistir resultado ────────────────────────────
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
                    sa.professor_id, sa.slot AS professor_slot, sa.sala_id AS sala_atribuida
             FROM disciplinas d
             JOIN turmas t ON t.id = d.turma_id
             JOIN cursos c ON c.id = d.curso_id
             JOIN semestre_atribuicoes sa ON sa.disciplina_id = d.id AND sa.semestre_id = ?
             WHERE d.ativo = 1",
            [$this->semestreId]
        );

        foreach ($disciplinas as $disc) {
            $disc['dias_semana'] = json_decode($disc['dias_semana'], true);
            $duracao      = (int)$disc['qtd_aulas'] * (int)$disc['duracao_aula_minutos'];
            $qtdProfs     = max(1, (int)($disc['qtd_professores'] ?? 1));
            $slot         = max(1, (int)($disc['professor_slot'] ?? 1));
            $totalEnc     = (int)$disc['qtd_encontros_semanais'];
            // Divide encontros entre os slots: os primeiros slots recebem o teto, os restantes o piso
            $base         = intdiv($totalEnc, $qtdProfs);
            $extra        = $totalEnc % $qtdProfs;
            $encontrosSlot = $base + ($slot <= $extra ? 1 : 0);

            for ($n = 1; $n <= $encontrosSlot; $n++) {
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
                    'qtd_aulas'       => (int)$disc['qtd_aulas'],
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

        // Professores mais carregados → agendar primeiro (grade ainda vazia)
        $cargaA = $this->cargaAtividadesProfessor[$a['professor_id']] ?? 0;
        $cargaB = $this->cargaAtividadesProfessor[$b['professor_id']] ?? 0;
        return $cargaB - $cargaA;
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
        $candidatos = [];
        $qtdAulas   = max(1, (int)($atividade['qtd_aulas'] ?? 1));
        $diasCurso  = $atividade['dias_semana'];

        // Salas compatíveis ordenadas por preferência
        $salas = $this->salasCompativeis($atividade);

        // Aula sem sala possível → candidato sem sala (aviso)
        $salaIds = empty($salas) ? [null] : array_column($salas, 'id');

        foreach ($diasCurso as $dia) {
            // Verificar disponibilidade do professor neste dia
            if (!$this->professorDisponivelDia($atividade['professor_id'], $dia)) continue;

            // Slots de aula do curso neste dia (mesma malha da grade visual).
            // Slots consecutivos podem atravessar um intervalo: nesse caso o
            // encontro engloba o intervalo (ex.: 07:00–11:50 com break dentro).
            $slots = $this->slotsAula($atividade['curso_id'], $dia);
            $n     = count($slots);
            if ($n < $qtdAulas) continue;

            foreach ($salaIds as $salaId) {
                // Ocupação combinada: professor + turma + (sala se houver)
                $ocupado = $this->getOcupadoCombinado(
                    $atividade['professor_id'],
                    $atividade['turma_id'],
                    $salaId,
                    $dia
                );

                for ($i = 0; $i + $qtdAulas <= $n; $i++) {
                    $inicio = $slots[$i]['inicio'];
                    $fim    = $slots[$i + $qtdAulas - 1]['fim'];

                    // 1. Não sobrepõe ocupações existentes
                    if (TimeHelper::overlapsAny($inicio, $fim, $ocupado)) continue;

                    // 2. Dentro da disponibilidade do professor
                    if (!$this->professorDisponivelHorario($atividade['professor_id'], $dia, $inicio, $fim)) continue;

                    // 3. Calcular score (soft constraints)
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

    /**
     * Slots de aula do curso no dia (sem os intervalos), alinhados à mesma
     * malha usada pela grade visual em HorariosController::verGrade().
     */
    private function slotsAula(int $cursoId, int $dia): array
    {
        if (isset($this->slotsCache[$cursoId][$dia])) {
            return $this->slotsCache[$cursoId][$dia];
        }

        $curso = $this->cursos[$cursoId] ?? null;
        if (!$curso) return $this->slotsCache[$cursoId][$dia] = [];

        $dur      = max(1, (int)$curso['duracao_aula_minutos']);
        $turnoIni = TimeHelper::toMinutes($curso['turno_inicio']);
        $turnoFim = TimeHelper::toMinutes($curso['turno_fim']);
        $breaks   = $this->intervalos[$cursoId][$dia] ?? [];

        $slots = [];
        $t = $turnoIni;
        while ($t < $turnoFim) {
            $break = null;
            foreach ($breaks as $b) {
                if ($t >= $b['inicio'] && $t < $b['fim']) { $break = $b; break; }
            }
            if ($break) { $t = $break['fim']; continue; }
            if ($t + $dur > $turnoFim) break;
            $slots[] = ['inicio' => $t, 'fim' => $t + $dur];
            $t += $dur;
        }

        return $this->slotsCache[$cursoId][$dia] = $slots;
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
        $aindaFalhas = [];

        foreach ($this->falhas as $falha) {
            if (!$this->tentarRealocar($falha)) {
                $aindaFalhas[] = $falha;
            }
        }

        $this->falhas = $aindaFalhas;
    }

    /**
     * Tenta agendar a falha removendo temporariamente uma atividade já
     * agendada que compartilhe professor ou turma (recursos em conflito).
     */
    private function tentarRealocar(array $falha): bool
    {
        // Snapshot por valor: a lista real muda durante as tentativas
        foreach (array_values($this->agendados) as $agendado) {
            $mesmoProf  = $agendado['atividade']['professor_id'] === $falha['professor_id'];
            $mesmaTurma = $agendado['atividade']['turma_id'] === $falha['turma_id'];
            if (!$mesmoProf && !$mesmaTurma) continue;

            if (!$this->removerAgendado($agendado)) continue;

            $candidatos = $this->gerarCandidatos($falha);
            if (!empty($candidatos)) {
                usort($candidatos, fn($a, $b) => $a['score'] <=> $b['score']);
                $this->atribuir($falha, $candidatos[0]);

                // Tenta re-agendar a removida em outro lugar
                $candidatosRem = $this->gerarCandidatos($agendado['atividade']);
                if (!empty($candidatosRem)) {
                    usort($candidatosRem, fn($a, $b) => $a['score'] <=> $b['score']);
                    $this->atribuir($agendado['atividade'], $candidatosRem[0]);
                    return true;
                }

                // Reverter: remove a falha recém-agendada (inclusive ocupações)
                $this->removerAtribuicao(count($this->agendados) - 1);
            }

            $this->restaurarAtribuicao($agendado);
        }

        return false;
    }

    /**
     * Otimização local (hill climbing): tenta mover cada atividade agendada
     * para uma posição de score melhor no estado atual da grade. Melhora
     * janelas e a compactação da semana dos professores.
     */
    private function otimizar(int $maxPasses = 3): void
    {
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $mudou = false;

            foreach (array_values($this->agendados) as $ag) {
                if (!$this->removerAgendado($ag)) continue;

                $candidatos = $this->gerarCandidatos($ag['atividade']);
                if (empty($candidatos)) {
                    $this->restaurarAtribuicao($ag);
                    continue;
                }

                usort($candidatos, fn($a, $b) => $a['score'] <=> $b['score']);
                $melhor = $candidatos[0];

                // Score da posição atual recalculado no mesmo estado (sem ela)
                $scoreAtual = $this->calcularScore(
                    $ag['atividade'], $ag['dia'], $ag['inicio'], $ag['fim'], $ag['sala_id']
                );

                if ($melhor['score'] < $scoreAtual - 0.001) {
                    $this->atribuir($ag['atividade'], $melhor);
                    $mudou = true;
                } else {
                    $this->restaurarAtribuicao($ag);
                }
            }

            if (!$mudou) break;
        }
    }

    /**
     * Garante o mínimo de 2 dias de aula para professores com 2+ atividades:
     * se tudo caiu num único dia, move uma atividade para outro dia.
     */
    private function garantirDoisDias(): void
    {
        $porProf = [];
        foreach ($this->agendados as $ag) {
            $porProf[$ag['atividade']['professor_id']][] = $ag;
        }

        foreach ($porProf as $ags) {
            if (count($ags) < 2) continue; // com 1 atividade não há o que dividir
            $dias = array_unique(array_column($ags, 'dia'));
            if (count($dias) >= 2) continue;
            $diaUnico = reset($dias);

            // Move a primeira atividade que tiver candidato válido em outro dia
            foreach ($ags as $ag) {
                if (!$this->removerAgendado($ag)) continue;

                $candidatos = array_values(array_filter(
                    $this->gerarCandidatos($ag['atividade']),
                    fn($c) => $c['dia'] !== $diaUnico
                ));
                if (!empty($candidatos)) {
                    usort($candidatos, fn($a, $b) => $a['score'] <=> $b['score']);
                    $this->atribuir($ag['atividade'], $candidatos[0]);
                    break; // professor agora tem 2 dias
                }

                $this->restaurarAtribuicao($ag);
            }
        }
    }

    /** Localiza um agendamento pelo valor e o remove (lista muda de ordem). */
    private function removerAgendado(array $ag): bool
    {
        foreach ($this->agendados as $i => $cand) {
            if ($cand == $ag) {
                $this->removerAtribuicao($i);
                return true;
            }
        }
        return false;
    }

    private function removerAtribuicao(int $agendadoIdx): void
    {
        $ag = $this->agendados[$agendadoIdx];
        $entry = ['inicio' => $ag['inicio'], 'fim' => $ag['fim']];

        $profId  = $ag['atividade']['professor_id'];
        $turmaId = $ag['atividade']['turma_id'];
        $dia     = $ag['dia'];

        $this->ocupacaoProfessor[$profId][$dia]  ??= [];
        $this->ocupacaoTurma[$turmaId][$dia]     ??= [];
        $this->removerEntrada($this->ocupacaoProfessor[$profId][$dia], $entry);
        $this->removerEntrada($this->ocupacaoTurma[$turmaId][$dia], $entry);
        if ($ag['sala_id']) {
            $this->ocupacaoSala[$ag['sala_id']][$dia] ??= [];
            $this->removerEntrada($this->ocupacaoSala[$ag['sala_id']][$dia], $entry);
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

        // S7: Compactar a semana do professor em torno de 2 dias:
        //  - abrir o 2º dia é desejável (mínimo de 2 dias por professor);
        //  - do 3º dia em diante, penalidade forte proporcional à distância.
        $diasProf = [];
        foreach ($this->ocupacaoProfessor[$atividade['professor_id']] ?? [] as $d => $ints) {
            if (!empty($ints)) $diasProf[] = $d;
        }
        $nDias   = count($diasProf);
        $diaNovo = !in_array($dia, $diasProf, true);

        if ($nDias === 1) {
            if ($diaNovo) {
                // Preferência por abrir o 2º dia perto do 1º (evita seg+sex)
                $distMin = min(array_map(fn($d) => abs($d - $dia), $diasProf));
                $score += $this->pesos['preferencia_dia2'] * $distMin;
            } else {
                // Empilhar tudo num único dia: empurra para abrir o 2º dia
                $score += $this->pesos['compactar_professor'] * 0.4;
            }
        } elseif ($nDias >= 2 && $diaNovo) {
            $distMin = min(array_map(fn($d) => abs($d - $dia), $diasProf));
            $score += $this->pesos['compactar_professor'] * $distMin;
        }

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
            $logLines[] = "NÃO AGENDADO: {$f['disciplina_nome']} (Turma ID {$f['turma_id']}, Encontro {$f['encontro_num']}) — enviado ao limbo";
        }

        Database::query(
            "UPDATE geracoes SET status=?, total_atividades=?, atividades_agendadas=?,
             atividades_falhas=?, log=?, finished_at=NOW()
             WHERE id=?",
            [$status, $total, $agendados, $falhas, implode("\n", $logLines), $this->geracaoId]
        );

        if ($agendados > 0 || $falhas > 0) {
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

                // Falhas vão para o limbo (dia_semana = 0) para ajuste manual
                foreach ($this->falhas as $f) {
                    $stmt->execute([
                        $this->geracaoId,
                        $f['disciplina_id'],
                        $f['turma_id'],
                        $f['professor_id'],
                        $this->salasPorTurma[$f['disciplina_id']] ?? null,
                        0,
                        TimeHelper::fromMinutes($f['turno_inicio']),
                        TimeHelper::fromMinutes($f['turno_inicio'] + $f['duracao']),
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
