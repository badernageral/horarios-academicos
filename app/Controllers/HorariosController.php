<?php

namespace App\Controllers;

use App\Models\{Horario, Turma, Professor, Sala, Semestre, Curso};
use App\Core\Database;
use App\Services\{ScheduleGenerator, Exporter, FeasibilityChecker, MoodleExporter, TimeHelper, GradeLayout, PdfExporter};

class HorariosController extends BaseController
{
    // ── Semestres ─────────────────────────────────────────────────
    public function index(): void
    {
        $semestres = Database::fetchAll("SELECT * FROM semestres ORDER BY created_at DESC");
        foreach ($semestres as &$s) {
            $s['qtd_atribuicoes'] = Semestre::countAtribuicoes($s['id']);
            $s['ultima_geracao']  = Database::fetchOne(
                "SELECT id, status, created_at FROM geracoes WHERE semestre_id = ? ORDER BY created_at DESC LIMIT 1",
                [$s['id']]
            );
            $s['avisos_viabilidade'] = FeasibilityChecker::verificar((int)$s['id']);
        }
        unset($s);
        $flash = $this->getFlash();
        $this->render('horarios/index', compact('semestres', 'flash'));
    }

    public function novo(): void
    {
        $this->render('horarios/form', ['semestre' => null, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id       = $this->post('id');
        $semestre = (int)$this->post('semestre', 1);
        $ano      = (int)$this->post('ano', (int)date('Y'));
        if ($id) {
            Database::query(
                "UPDATE semestres SET semestre = ?, ano = ? WHERE id = ?",
                [$semestre, $ano, (int)$id]
            );
            $this->flash('success', 'Semestre atualizado.');
            $this->redirect('/horarios');
        } else {
            Database::query(
                "INSERT INTO semestres (semestre, ano) VALUES (?, ?)",
                [$semestre, $ano]
            );
            $semestreId = (int)Database::lastInsertId();
            $this->flash('success', 'Semestre criado. Agora atribua os professores.');
            $this->redirect('/horarios');
        }
    }

    public function editar(string $id): void
    {
        $semestre = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [(int)$id]);
        if (!$semestre) $this->redirect('/horarios');
        $this->render('horarios/form', compact('semestre') + ['flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Database::query("DELETE FROM semestres WHERE id = ?", [$id]);
            $this->flash('success', 'Semestre removido.');
        }
        $this->redirect('/horarios');
    }

    // ── Detalhe (obsoleto — redireciona para índice) ──────────────
    public function detalhe(string $id): void
    {
        $this->redirect('/horarios');
    }

    // ── Atribuição em massa: tela ─────────────────────────────────
    public function verImportarAtribuicao(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');
        $flash = $this->getFlash();
        $this->render('horarios/atribuir_importar', compact('semestre', 'semestreId', 'flash'));
    }

    // ── Atribuição em massa: processar ────────────────────────────
    public function importarAtribuicao(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $texto  = $this->post('linhas', '');
        $linhas = array_filter(array_map('trim', explode("\n", $texto)));

        // Cache de professores: nome minúsculo → id
        $profMap = [];
        foreach (Database::fetchAll("SELECT id, nome FROM professores WHERE ativo = 1") as $p) {
            $profMap[strtolower(trim($p['nome']))] = (int)$p['id'];
        }

        // Cache de disciplinas do semestre: nome minúsculo → [ids]
        $discMap = [];
        foreach (Database::fetchAll(
            "SELECT d.id, d.nome FROM disciplinas d
             JOIN semestres sem ON sem.id = ?
             WHERE d.ativo = 1 AND (d.semestre_oferta & sem.semestre) > 0",
            [$semestreId]
        ) as $d) {
            $discMap[strtolower(trim($d['nome']))][] = (int)$d['id'];
        }

        $atribuidas = 0;
        $puladas    = 0;
        $erros      = [];
        $slotCount  = []; // [disciplina_id] => próximo slot

        foreach ($linhas as $linha) {
            $linha = trim($linha, " \r\n");
            if ($linha === '') continue;

            // Separa no ÚLTIMO " - " para suportar hífens no nome da disciplina
            $pos = strrpos($linha, ' - ');
            if ($pos === false) {
                $puladas++;
                $erros[] = 'Formato inválido: "' . $linha . '"';
                continue;
            }

            $discNome = trim(substr($linha, 0, $pos));
            $profNome = trim(substr($linha, $pos + 3));

            $profId = $profMap[strtolower($profNome)] ?? null;
            if (!$profId) {
                $puladas++;
                $erros[] = 'Professor não encontrado: "' . $profNome . '"';
                continue;
            }

            $discIds = $discMap[strtolower($discNome)] ?? [];
            if (empty($discIds)) {
                $puladas++;
                $erros[] = 'Disciplina não encontrada: "' . $discNome . '"';
                continue;
            }

            foreach ($discIds as $discId) {
                $slot = ($slotCount[$discId] ?? 0) + 1;
                $slotCount[$discId] = $slot;
                Database::query(
                    "INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id, slot)
                     VALUES (?, ?, ?, ?)
                     ON CONFLICT(semestre_id, disciplina_id, slot)
                     DO UPDATE SET professor_id = excluded.professor_id",
                    [$semestreId, $discId, $profId, $slot]
                );
                $atribuidas++;
            }
        }

        $msg = "{$atribuidas} atribuição(ões) salva(s).";
        if ($puladas) $msg .= " {$puladas} linha(s) com problema.";
        if (!empty($erros)) {
            $this->flash('warning', $msg . ' Erros: ' . implode('; ', array_slice($erros, 0, 5)));
        } else {
            $this->flash('success', $msg);
        }
        $this->redirect('/horarios/' . $semestreId . '/atribuir');
    }

    // ── Atribuição: tela ──────────────────────────────────────────
    public function verAtribuir(string $id): void
    {
        $semestreId  = (int)$id;
        $semestre    = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $disciplinas = Semestre::disciplinasComAtribuicao($semestreId);
        $professores = Professor::allAtivos();
        $salas       = Sala::allAtivas();
        $semAtribuir = 0;
        $semSala     = 0;
        foreach ($disciplinas as $d) {
            $atribuidos  = count($d['professores_atribuidos'] ?? []);
            $necessarios = max(1, (int)($d['qtd_professores'] ?? 1));
            $semAtribuir += max(0, $necessarios - $atribuidos);
            if (empty($d['sala_atribuida'])) $semSala++;
        }
        $flash  = $this->getFlash();
        $avisos = FeasibilityChecker::verificar($semestreId);

        $this->render('horarios/atribuir', compact(
            'semestre', 'semestreId', 'disciplinas', 'professores', 'salas', 'semAtribuir', 'semSala', 'flash', 'avisos'
        ));
    }

    // ── Atribuição: salvar ────────────────────────────────────────
    public function atribuir(string $id): void
    {
        $semestreId = (int)$id;
        $aoLimbo = Semestre::salvarAtribuicoes(
            $semestreId,
            $this->post('atribuicao', []),
            $this->post('sala', [])
        );
        if ($aoLimbo) {
            $this->flash(
                'warning',
                'Atribuições salvas. Por conflito de horário do professor, foram enviadas ao limbo: '
                . implode(', ', $aoLimbo) . '. Reposicione na grade ou regere o horário.'
            );
        } else {
            $this->flash('success', 'Atribuições salvas.');
        }
        $this->redirect('/horarios');
    }

    // ── Ensalamento: tela ─────────────────────────────────────────
    public function verEnsalamento(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $turmas = Semestre::turmasComSala($semestreId);
        $salas  = Sala::allAtivas();
        $flash  = $this->getFlash();

        $this->render('horarios/ensalamento', compact(
            'semestre', 'semestreId', 'turmas', 'salas', 'flash'
        ));
    }

    // ── Ensalamento: salvar ───────────────────────────────────────
    public function ensalar(string $id): void
    {
        $semestreId = (int)$id;
        Semestre::salvarSalas($semestreId, $this->post('ensalamento', []));
        $this->flash('success', 'Salas atribuídas.');
        $this->redirect('/horarios');
    }

    // ── Geração: tela (obsoleta) ──────────────────────────────────
    public function verGerar(string $id): void
    {
        $this->redirect('/horarios');
    }

    // ── Geração ───────────────────────────────────────────────────
    public function gerar(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $pesosPost = $this->post('pesos', []);
        $pesos = [];
        foreach ($pesosPost as $chave => $valor) {
            $pesos[$chave] = (float)$valor;
            Database::query("UPDATE configuracoes_soft SET valor = ? WHERE chave = ?", [(float)$valor, $chave]);
        }

        // Remove geração anterior do semestre (cascade apaga os horários)
        Database::query("DELETE FROM geracoes WHERE semestre_id = ?", [$semestreId]);

        $descricao = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
        Database::query(
            "INSERT INTO geracoes (semestre_id, descricao, status, configuracao, created_at)
             VALUES (?, ?, 'processando', ?, ?)",
            [$semestreId, $descricao, json_encode($pesos), date('Y-m-d H:i:s')]
        );
        $geracaoId = (int)Database::lastInsertId();

        try {
            $gerador   = new ScheduleGenerator($geracaoId, $semestreId, $pesos);
            $resultado = $gerador->gerar();
            $this->flash(
                $resultado['status'] === 'concluido' ? 'success' : 'warning',
                "Geração concluída: {$resultado['atividades_agendadas']} agendadas, {$resultado['atividades_falhas']} falhas."
            );
        } catch (\Throwable $e) {
            Database::query(
                "UPDATE geracoes SET status='erro', log=?, finished_at=? WHERE id=?",
                [$e->getMessage(), date('Y-m-d H:i:s'), $geracaoId]
            );
            $this->flash('danger', 'Erro na geração: ' . $e->getMessage());
            $this->redirect('/horarios');
            return;
        }

        $this->redirect('/horarios/geracao/' . $geracaoId . '/grade');
    }

    // ── Grade completa (todas as turmas × dias × slots) ──────────
    public function verGrade(string $geracaoId): void
    {
        $geracaoId     = (int)$geracaoId;
        $geracao       = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        if (!$geracao) $this->redirect('/horarios');

        $semestreId    = $geracao['semestre_id'] ?? null;
        $todosHorarios = Horario::porGeracao($geracaoId);

        // Métricas de qualidade (sempre sobre o conjunto completo, sem filtro)
        $qualidade = $this->metricasQualidade($todosHorarios);

        // Filtros de visualização (grade fica somente leitura quando ativos)
        $cursoFiltro = (int)$this->get('curso_id', 0);
        $turmaFiltro = (int)$this->get('turma_id', 0);
        $profFiltro  = (int)$this->get('professor_id', 0);
        $salaFiltro  = (int)$this->get('sala_id', 0);
        $filtroAtivo = $cursoFiltro || $turmaFiltro || $profFiltro || $salaFiltro;

        if ($filtroAtivo) {
            $todosHorarios = array_values(array_filter(
                $todosHorarios,
                function ($h) use ($cursoFiltro, $turmaFiltro, $profFiltro, $salaFiltro) {
                    if ($cursoFiltro && (int)$h['curso_id'] !== $cursoFiltro) return false;
                    if ($turmaFiltro && (int)$h['turma_id'] !== $turmaFiltro) return false;
                    if ($profFiltro && (int)$h['professor_id'] !== $profFiltro) return false;
                    if ($salaFiltro && (int)($h['sala_id'] ?? 0) !== $salaFiltro) return false;
                    return true;
                }
            ));
        }

        // Listas para os selects de filtro
        $cursosFiltro      = Curso::allAtivos();
        $turmasFiltro      = Turma::allComCurso();
        $professoresFiltro = Professor::allAtivos();
        $salasFiltro       = Sala::allAtivas();

        // Malha visual da grade (mesma estrutura usada pela exportação em PDF)
        $grade = \App\Services\GradeLayout::montar($todosHorarios);

        // Disciplinas que mudaram de turma DEPOIS desta geração: a grade abaixo
        // está desatualizada. Só avisamos — regerar é ação explícita do usuário.
        $desatualizadas = FeasibilityChecker::gradesDesatualizadas($geracaoId);

        $this->render('horarios/grade', compact(
            'grade', 'geracao', 'geracaoId', 'semestreId',
            'cursoFiltro', 'turmaFiltro', 'profFiltro', 'salaFiltro', 'filtroAtivo',
            'cursosFiltro', 'turmasFiltro', 'professoresFiltro', 'salasFiltro',
            'qualidade', 'desatualizadas'
        ));
    }

    // ── API: mover horário (drag & drop) ─────────────────────────
    public function moverHorario(): void
    {
        header('Content-Type: application/json');
        $raw            = json_decode(file_get_contents('php://input'), true) ?? [];
        $horarioId      = (int)($raw['horario_id'] ?? 0);
        $novoDia        = (int)($raw['novo_dia'] ?? 0);
        $novaHoraInicio = trim($raw['nova_hora_inicio'] ?? '');

        if (!$horarioId || $novoDia < 0 || $novoDia > 5) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
            return;
        }

        $h = Database::fetchOne("SELECT * FROM horarios WHERE id=?", [$horarioId]);
        if (!$h) {
            echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado']);
            return;
        }

        // Estado anterior (para o "Desfazer" no front)
        $anterior = ['dia' => (int)$h['dia_semana'], 'hora_inicio' => substr($h['hora_inicio'], 0, 5)];

        // Mover para o limbo (dia_semana = 0: sem horário atribuído)
        if ($novoDia === 0) {
            Database::query("UPDATE horarios SET dia_semana=0 WHERE id=?", [$horarioId]);
            echo json_encode(['ok' => true, 'anterior' => $anterior]);
            return;
        }

        if ($novaHoraInicio !== '') {
            $novaHoraInicio = TimeHelper::toHms($novaHoraInicio);
            $janela = $this->calcularJanelaAula($horarioId, $novaHoraInicio);
            if ($janela === null) {
                echo json_encode(['ok' => false, 'erro' => 'A disciplina não cabe neste horário: ultrapassaria o fim do turno.']);
                return;
            }
            $novaHoraFim = $janela['fim'];
        } else {
            $novaHoraInicio = TimeHelper::toHms($h['hora_inicio']);
            $novaHoraFim    = TimeHelper::toHms($h['hora_fim']);
        }

        // Sem mudança
        if ((int)$h['dia_semana'] === $novoDia
            && TimeHelper::toHms($h['hora_inicio']) === $novaHoraInicio
        ) {
            echo json_encode(['ok' => true, 'anterior' => $anterior]);
            return;
        }

        $erro = $this->conflitosNaPosicao($h, $novoDia, $novaHoraInicio, $novaHoraFim, [$horarioId]);
        if ($erro !== null) {
            echo json_encode(['ok' => false, 'erro' => $erro]);
            return;
        }

        Database::query(
            "UPDATE horarios SET dia_semana=?, hora_inicio=?, hora_fim=? WHERE id=?",
            [$novoDia, $novaHoraInicio, $novaHoraFim, $horarioId]
        );
        echo json_encode(['ok' => true, 'anterior' => $anterior]);
    }

    // ── API: trocar dois horários de lugar (swap) ─────────────────
    public function trocarHorarios(): void
    {
        header('Content-Type: application/json');
        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $idA = (int)($raw['horario_a'] ?? 0);
        $idB = (int)($raw['horario_b'] ?? 0);

        if (!$idA || !$idB || $idA === $idB) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
            return;
        }

        $a = Database::fetchOne("SELECT * FROM horarios WHERE id=?", [$idA]);
        $b = Database::fetchOne("SELECT * FROM horarios WHERE id=?", [$idB]);
        if (!$a || !$b) {
            echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado']);
            return;
        }
        if ($a['geracao_id'] !== $b['geracao_id'] || $a['turma_id'] !== $b['turma_id']) {
            echo json_encode(['ok' => false, 'erro' => 'Só é possível trocar disciplinas da mesma turma.']);
            return;
        }
        if ((int)$a['dia_semana'] < 1 || (int)$b['dia_semana'] < 1) {
            echo json_encode(['ok' => false, 'erro' => 'Não é possível trocar com item do limbo.']);
            return;
        }

        // Cada um assume a posição do outro (durações podem diferir)
        $janelaA = $this->calcularJanelaAula($idA, substr($b['hora_inicio'], 0, 5));
        $janelaB = $this->calcularJanelaAula($idB, substr($a['hora_inicio'], 0, 5));
        if ($janelaA === null || $janelaB === null) {
            echo json_encode(['ok' => false, 'erro' => 'A troca não cabe: uma das disciplinas ultrapassaria o fim do turno.']);
            return;
        }
        $diaA = (int)$b['dia_semana'];
        $diaB = (int)$a['dia_semana'];

        // Se caírem no mesmo dia com durações diferentes, não podem se sobrepor
        if ($diaA === $diaB
            && $janelaA['inicio'] < $janelaB['fim'] && $janelaB['inicio'] < $janelaA['fim']
        ) {
            echo json_encode(['ok' => false, 'erro' => 'A troca não cabe: as disciplinas se sobreporiam (durações diferentes).']);
            return;
        }

        $excluir = [$idA, $idB];
        $erro = $this->conflitosNaPosicao($a, $diaA, $janelaA['inicio'], $janelaA['fim'], $excluir)
             ?? $this->conflitosNaPosicao($b, $diaB, $janelaB['inicio'], $janelaB['fim'], $excluir);
        if ($erro !== null) {
            echo json_encode(['ok' => false, 'erro' => $erro]);
            return;
        }

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE horarios SET dia_semana=?, hora_inicio=?, hora_fim=? WHERE id=?",
                [$diaA, $janelaA['inicio'], $janelaA['fim'], $idA]
            );
            Database::query(
                "UPDATE horarios SET dia_semana=?, hora_inicio=?, hora_fim=? WHERE id=?",
                [$diaB, $janelaB['inicio'], $janelaB['fim'], $idB]
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            echo json_encode(['ok' => false, 'erro' => 'Erro ao trocar: ' . $e->getMessage()]);
            return;
        }

        echo json_encode(['ok' => true]);
    }

    /**
     * Métricas de qualidade da geração por professor:
     * dias de aula, buracos de dias na semana, janelas (min) e carga (min).
     */
    private function metricasQualidade(array $horarios): array
    {
        $porProf    = [];
        $eadPorProf = []; // nome => [disciplina_id => qtd_aulas_ead] (contado uma vez por disciplina)
        $totalAulas = 0;
        $noLimbo    = 0;
        foreach ($horarios as $h) {
            if ((int)$h['dia_semana'] < 1) {
                $noLimbo++;
                continue;
            }
            $totalAulas++;
            $nome = $h['professor_nome'];
            $porProf[$nome][(int)$h['dia_semana']][] = [
                'inicio'   => \App\Services\TimeHelper::toMinutes($h['hora_inicio']),
                'fim'      => \App\Services\TimeHelper::toMinutes($h['hora_fim']),
                'periodos' => max(1, (int)($h['qtd_aulas'] ?? 1)),
            ];
            $ead = (int)($h['qtd_aulas_ead'] ?? 0);
            if ($ead > 0) {
                $eadPorProf[$nome][(int)$h['disciplina_id']] = $ead;
            }
        }
        ksort($porProf, SORT_NATURAL | SORT_FLAG_CASE);

        $profs     = [];
        $comBuraco = 0;
        $totDias   = 0;
        foreach ($porProf as $nome => $dias) {
            $ds = array_keys($dias);
            sort($ds);
            $minutos  = 0;
            $periodos = 0; // nº de aulas (períodos), independente da duração-relógio
            foreach ($dias as $ints) {
                $minutos  += \App\Services\TimeHelper::totalMinutosDia($ints);
                $periodos += array_sum(array_column($ints, 'periodos'));
            }
            $buracos = (max($ds) - min($ds) + 1) - count($ds); // dias vazios entre o 1º e o último
            if ($buracos > 0) $comBuraco++;
            $totDias += count($ds);
            $aulas = array_sum(array_map('count', $dias));

            $ead       = array_sum($eadPorProf[$nome] ?? []); // aulas EaD (uma vez por disciplina)
            $periodos += $ead;

            $profs[] = [
                'nome'     => $nome,
                'dias'     => $ds,
                'buracos'  => $buracos,
                'minutos'  => $minutos,
                'aulas'    => $aulas,
                'periodos' => $periodos,
                'ead'      => $ead,
            ];
        }

        return [
            'professores' => $profs,
            'com_buraco'  => $comBuraco,
            'max_dias'    => empty($profs) ? 0 : max(array_map(fn($p) => count($p['dias']), $profs)),
            'media_dias'  => empty($profs) ? 0 : round($totDias / count($profs), 1),
            'total_aulas' => $totalAulas,
            'no_limbo'    => $noLimbo,
        ];
    }

    /**
     * Calcula início/fim de um horário começando em $horaInicio (HH:MM),
     * consumindo qtd_aulas slots e pulando os intervalos do curso.
     * Retorna null se ultrapassar o fim do turno.
     */
    private function calcularJanelaAula(int $horarioId, string $horaInicio): ?array
    {
        $disc = Database::fetchOne(
            "SELECT d.qtd_aulas, c.duracao_aula_minutos, c.id AS curso_id, c.turno_fim
             FROM disciplinas d JOIN cursos c ON c.id = d.curso_id
             WHERE d.id = (SELECT disciplina_id FROM horarios WHERE id = ?)",
            [$horarioId]
        );
        if (!$disc) return null;

        $qtdAulas = max(1, (int)$disc['qtd_aulas']);
        $aulaMin  = max(1, (int)$disc['duracao_aula_minutos']);
        $ivList   = [];
        foreach (Database::fetchAll("SELECT hora_inicio, hora_fim FROM intervalos_curso WHERE curso_id = ?", [$disc['curso_id']]) as $iv) {
            $ivList[] = [
                'inicio' => \App\Services\TimeHelper::toMinutes($iv['hora_inicio']),
                'fim'    => \App\Services\TimeHelper::toMinutes($iv['hora_fim']),
            ];
        }

        $t        = \App\Services\TimeHelper::toMinutes($horaInicio);
        $consumed = 0;
        while ($consumed < $qtdAulas) {
            $inIv = null;
            foreach ($ivList as $iv) {
                if ($t >= $iv['inicio'] && $t < $iv['fim']) { $inIv = $iv; break; }
            }
            if ($inIv) { $t = $inIv['fim']; continue; }
            $t += $aulaMin;
            $consumed++;
        }

        if ($t > \App\Services\TimeHelper::toMinutes($disc['turno_fim'])) return null;

        return [
            'inicio' => TimeHelper::toHms($horaInicio),
            'fim'    => TimeHelper::toHms(TimeHelper::fromMinutes($t)),
        ];
    }

    /**
     * Verifica conflitos de turma/professor/sala para o horário $h na posição
     * indicada, ignorando os ids em $excluir. Retorna mensagem de erro ou null.
     */
    private function conflitosNaPosicao(array $h, int $dia, string $ini, string $fim, array $excluir): ?string
    {
        // Comparação lexicográfica exige formato uniforme HH:MM:SS dos dois lados
        $ini = TimeHelper::toHms($ini);
        $fim = TimeHelper::toHms($fim);
        $checks = [
            ['turma_id',     (int)$h['turma_id'],          'Conflito de turma neste horário.'],
            ['professor_id', (int)($h['professor_id'] ?? 0), 'Conflito de professor neste horário.'],
            ['sala_id',      (int)($h['sala_id'] ?? 0),    'Conflito de sala neste horário.'],
        ];
        $place = implode(',', array_fill(0, count($excluir), '?'));

        foreach ($checks as [$campo, $valor, $msg]) {
            if (!$valor) continue;
            $conflito = Database::fetchOne(
                "SELECT 1 FROM horarios
                 WHERE geracao_id=? AND {$campo}=? AND dia_semana=? AND id NOT IN ({$place})
                 AND hora_inicio < ? AND hora_fim > ? LIMIT 1",
                array_merge([(int)$h['geracao_id'], $valor, $dia], $excluir, [$fim, $ini])
            );
            if ($conflito) return $msg;
        }
        return null;
    }

    // ── Clonar atribuições + grade de outro semestre ──────────────
    public function clonarAtribuicoes(string $id): void
    {
        $destinoId = (int)$id;
        $origemId  = (int)$this->post('origem_id', 0);

        $destino = Database::fetchOne("SELECT * FROM semestres WHERE id=?", [$destinoId]);
        $origem  = Database::fetchOne("SELECT * FROM semestres WHERE id=?", [$origemId]);
        if (!$destino || !$origem || $destinoId === $origemId) {
            $this->flash('danger', 'Semestre de origem inválido.');
            $this->redirect('/horarios');
            return;
        }

        // Disciplinas ativas e ofertadas no semestre de destino
        $oferta = (int)$destino['semestre'];

        Database::beginTransaction();
        try {
            Database::query("DELETE FROM semestre_atribuicoes WHERE semestre_id=?", [$destinoId]);
            Database::query(
                "INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id, slot, sala_id)
                 SELECT ?, sa.disciplina_id, sa.professor_id, sa.slot, sa.sala_id
                 FROM semestre_atribuicoes sa
                 JOIN disciplinas d ON d.id = sa.disciplina_id
                 WHERE sa.semestre_id = ? AND d.ativo = 1
                   AND (d.semestre_oferta & ?) > 0",
                [$destinoId, $origemId, $oferta]
            );
            $qtd = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM semestre_atribuicoes WHERE semestre_id=?", [$destinoId]
            );

            // A grade do destino sempre é substituída: manter a antiga deixaria
            // horários apontando para atribuições que acabaram de ser trocadas.
            Database::query("DELETE FROM geracoes WHERE semestre_id=?", [$destinoId]);

            $gerOrigem = Database::fetchOne(
                "SELECT * FROM geracoes WHERE semestre_id=? ORDER BY id DESC LIMIT 1", [$origemId]
            );
            $novaGeracaoId = null;
            if ($gerOrigem) {
                $agora = date('Y-m-d H:i:s');
                Database::query(
                    "INSERT INTO geracoes (semestre_id, descricao, status, configuracao, log, created_at, finished_at)
                     VALUES (?, ?, 'concluido', ?, ?, ?, ?)",
                    [
                        $destinoId,
                        $destino['semestre'] . 'º Semestre / ' . $destino['ano'],
                        $gerOrigem['configuracao'],
                        "Grade copiada do {$origem['semestre']}º Semestre/{$origem['ano']}.",
                        $agora,
                        $agora,
                    ]
                );
                $novaGeracaoId = (int) Database::lastInsertId();

                Database::query(
                    "INSERT INTO horarios
                     (geracao_id, disciplina_id, turma_id, professor_id, sala_id, dia_semana, hora_inicio, hora_fim)
                     SELECT ?, h.disciplina_id, h.turma_id, h.professor_id, h.sala_id,
                            h.dia_semana, h.hora_inicio, h.hora_fim
                     FROM horarios h
                     JOIN disciplinas d ON d.id = h.disciplina_id
                     WHERE h.geracao_id = ? AND d.ativo = 1
                       AND (d.semestre_oferta & ?) > 0",
                    [$novaGeracaoId, (int)$gerOrigem['id'], $oferta]
                );

                // Recontagem: o filtro de oferta pode descartar parte da origem
                $agendadas = (int) Database::fetchValue(
                    "SELECT COUNT(*) FROM horarios WHERE geracao_id=? AND dia_semana >= 1", [$novaGeracaoId]
                );
                $noLimbo = (int) Database::fetchValue(
                    "SELECT COUNT(*) FROM horarios WHERE geracao_id=? AND dia_semana = 0", [$novaGeracaoId]
                );
                Database::query(
                    "UPDATE geracoes SET total_atividades=?, atividades_agendadas=?, atividades_falhas=?, status=?
                     WHERE id=?",
                    [$agendadas + $noLimbo, $agendadas, $noLimbo, $noLimbo > 0 ? 'parcial' : 'concluido', $novaGeracaoId]
                );
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            $this->flash('danger', 'Erro ao copiar semestre: ' . $e->getMessage());
            $this->redirect('/horarios');
            return;
        }

        $msg = "Copiado de {$origem['semestre']}º Semestre/{$origem['ano']}: {$qtd} atribuição(ões)";
        if ($novaGeracaoId) {
            $msg .= " e a grade ({$agendadas} aula(s)"
                  . ($noLimbo > 0 ? ", {$noLimbo} no limbo" : '') . '). Revise antes de usar.';
        } else {
            $msg .= '. A origem não tinha grade gerada — gere a deste semestre.';
        }

        $this->flash('success', $msg);
        $this->redirect('/horarios');
    }

    // ── Exportação Moodle (CSVs de importação em massa) ──────────
    public function verMoodle(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $diag = MoodleExporter::diagnostico($semestreId);

        // Turmas com disciplinas ofertadas no semestre (para os campos de categoria).
        $turmas = [];
        foreach ($diag['rows'] as $r) {
            $turmas[(int)$r['turma_id']] ??= [
                'turma_id'      => (int)$r['turma_id'],
                'serie_periodo' => $r['serie_periodo'],
                'curso_nome'    => $r['curso_nome'],
                'qtd'           => 0,
            ];
            $turmas[(int)$r['turma_id']]['qtd']++;
        }

        $this->render('horarios/moodle', [
            'semestre' => $semestre,
            'turmas'   => array_values($turmas),
            'diag'     => $diag,
            'flash'    => null,
        ]);
    }

    public function exportarMoodleDisciplinas(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $categorias = $this->post('categoria', []);
        $categorias = is_array($categorias) ? array_map('strval', $categorias) : [];

        $startdate = $this->formatarDataMoodle($this->post('startdate', ''));
        $enddate   = $this->formatarDataMoodle($this->post('enddate', ''));

        $arquivo = "moodle_disciplinas_{$semestre['ano']}_{$semestre['semestre']}";
        (new MoodleExporter())->disciplinasCSV($semestreId, $categorias, $startdate, $enddate, $arquivo);
        exit;
    }

    public function exportarMoodleProfessores(string $id): void
    {
        $semestreId = (int)$id;
        $semestre   = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $arquivo = "moodle_professores_{$semestre['ano']}_{$semestre['semestre']}";
        (new MoodleExporter())->professoresCSV($semestreId, $arquivo);
        exit;
    }

    // Converte AAAA-MM-DD (input date) para AA-MM-DD (formato do template Moodle do campus).
    private function formatarDataMoodle(string $data): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($data), $m)) return '';
        return substr($m[1], 2) . '-' . $m[2] . '-' . $m[3];
    }

    // ── Impressão: todos os horários agrupados por professor ─────
    public function imprimirProfessores(string $geracaoId): void
    {
        $geracaoId = (int)$geracaoId;
        $geracao   = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        if (!$geracao) $this->redirect('/horarios');

        $horarios = $this->semLimbo(Horario::porGeracao($geracaoId));

        // Agrupar por professor → dia, ordenado por nome e hora
        $porProfessor = [];
        $temSabado    = false;
        foreach ($horarios as $h) {
            $nome = $h['professor_nome'];
            $porProfessor[$nome]['cor']     = $h['professor_cor'] ?: '#94a3b8';
            $porProfessor[$nome]['cor_sec'] = $h['professor_cor_secundaria'] ?: $h['professor_cor'];
            $porProfessor[$nome]['dias'][(int)$h['dia_semana']][] = $h;
            if ((int)$h['dia_semana'] === 6) $temSabado = true;
        }
        ksort($porProfessor, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($porProfessor as &$p) {
            foreach ($p['dias'] as &$lista) {
                usort($lista, fn($a, $b) => strcmp($a['hora_inicio'], $b['hora_inicio']));
            }
            unset($lista);
        }
        unset($p);

        $dias = $temSabado
            ? [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta', 6=>'Sábado']
            : [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta'];

        // Página standalone (sem layout da aplicação)
        $base = BASE_PATH;
        require ROOT_PATH . '/app/Views/horarios/imprimir_professores.php';
    }

    // ── Verificar conflitos entre disciplinas (aluno multi-período) ──
    public function verificarConflitos(string $geracaoId): void
    {
        header('Content-Type: application/json');
        $geracaoId = (int)$geracaoId;
        $raw       = json_decode(file_get_contents('php://input'), true) ?? [];
        $texto     = trim($raw['disciplinas'] ?? '');

        $nomes = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $texto)))));
        if (count($nomes) < 2) {
            echo json_encode(['ok' => false, 'erro' => 'Informe ao menos duas disciplinas, uma por linha.']);
            return;
        }

        $diasNomes = [1=>'Segunda', 2=>'Terça', 3=>'Quarta', 4=>'Quinta', 5=>'Sexta', 6=>'Sábado'];

        $naoEncontradas = [];
        $semHorario     = [];
        $itens          = [];

        foreach ($nomes as $nome) {
            $discs = Database::fetchAll(
                "SELECT d.id, d.nome, t.serie_periodo, c.nome AS curso_nome
                 FROM disciplinas d
                 JOIN turmas t ON t.id = d.turma_id
                 JOIN cursos c ON c.id = d.curso_id
                 WHERE d.ativo = 1 AND LOWER(d.nome) = LOWER(?)",
                [$nome]
            );
            if (empty($discs)) {
                $naoEncontradas[] = $nome;
                continue;
            }
            foreach ($discs as $d) {
                $rotulo = $d['nome'] . ' (' . $d['curso_nome'] . ' – ' . $d['serie_periodo'] . ')';
                $hs = Database::fetchAll(
                    "SELECT dia_semana, hora_inicio, hora_fim FROM horarios
                     WHERE geracao_id = ? AND disciplina_id = ? AND dia_semana >= 1",
                    [$geracaoId, $d['id']]
                );
                if (empty($hs)) {
                    $semHorario[] = $rotulo;
                }
                $itens[] = ['rotulo' => $rotulo, 'nome' => $d['nome'], 'horarios' => $hs];
            }
        }

        // Conflito = sobreposição de horário no mesmo dia entre disciplinas diferentes
        $conflitos = [];
        $n = count($itens);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                // Mesmo nome em turmas diferentes = alternativas, não conflito
                if ($itens[$i]['nome'] === $itens[$j]['nome']) continue;

                foreach ($itens[$i]['horarios'] as $ha) {
                    foreach ($itens[$j]['horarios'] as $hb) {
                        if ((int)$ha['dia_semana'] !== (int)$hb['dia_semana']) continue;
                        if ($ha['hora_inicio'] < $hb['hora_fim'] && $hb['hora_inicio'] < $ha['hora_fim']) {
                            $conflitos[] = [
                                'a'         => $itens[$i]['rotulo'],
                                'b'         => $itens[$j]['rotulo'],
                                'dia'       => $diasNomes[(int)$ha['dia_semana']] ?? $ha['dia_semana'],
                                'horario_a' => substr($ha['hora_inicio'], 0, 5) . '–' . substr($ha['hora_fim'], 0, 5),
                                'horario_b' => substr($hb['hora_inicio'], 0, 5) . '–' . substr($hb['hora_fim'], 0, 5),
                            ];
                        }
                    }
                }
            }
        }

        echo json_encode([
            'ok'              => true,
            'possivel'        => empty($conflitos),
            'conflitos'       => $conflitos,
            'nao_encontradas' => $naoEncontradas,
            'sem_horario'     => $semHorario,
        ]);
    }

    // ── Exportações ───────────────────────────────────────────────

    /** Remove itens do limbo (dia_semana = 0) das exportações */
    private function semLimbo(array $horarios): array
    {
        return array_values(array_filter($horarios, fn($h) => (int)$h['dia_semana'] >= 1));
    }

    public function exportarCSV(string $geracaoId): void
    {
        $horarios = $this->semLimbo(Horario::porGeracao((int)$geracaoId));
        (new Exporter())->exportarCSV($horarios, "Horario_Geracao_{$geracaoId}");
    }

    public function exportarExcel(string $geracaoId): void
    {
        $horarios = $this->semLimbo(Horario::porGeracao((int)$geracaoId));
        (new Exporter())->exportarExcel($horarios, "Horario_Geracao_{$geracaoId}");
    }

    /**
     * Baixa a grade em PDF (uma turma por página), com as mesmas opções da
     * impressão: ?turmas=1,2,3 (vazio = todas) e ?orientacao=landscape|portrait.
     */
    public function exportarPDF(string $geracaoId): void
    {
        $geracaoId = (int)$geracaoId;
        $grade     = GradeLayout::montar(Horario::porGeracao($geracaoId));

        $turmas = array_filter(array_map('intval', explode(',', (string)$this->get('turmas', ''))));
        if ($turmas) {
            $grade = array_intersect_key($grade, array_flip($turmas));
        }

        $orientacao = $this->get('orientacao') === 'portrait' ? 'portrait' : 'landscape';
        (new PdfExporter($orientacao))->gerar($grade, 'grade_horarios_' . date('Y-m-d'));
    }

    public function deletarGeracao(): void
    {
        $id         = (int)$this->post('id');
        $semestreId = (int)$this->post('semestre_id');
        if ($id) {
            Database::query("DELETE FROM geracoes WHERE id=?", [$id]);
            $this->flash('success', 'Geração removida.');
        }
        $this->redirect($semestreId ? '/horarios' : '/horarios');
    }
}
