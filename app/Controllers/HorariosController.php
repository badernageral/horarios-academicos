<?php

namespace App\Controllers;

use App\Models\{Horario, Turma, Professor, Sala, Semestre};
use App\Core\Database;
use App\Services\{ScheduleGenerator, Exporter};

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

    // ── Atribuição: tela ──────────────────────────────────────────
    public function verAtribuir(string $id): void
    {
        $semestreId  = (int)$id;
        $semestre    = Database::fetchOne("SELECT * FROM semestres WHERE id = ?", [$semestreId]);
        if (!$semestre) $this->redirect('/horarios');

        $disciplinas = Semestre::disciplinasComAtribuicao($semestreId);
        $professores = Professor::allAtivos();
        $salas       = Sala::allAtivas();
        $semAtribuir = count(array_filter($disciplinas, fn($d) => !$d['professor_atribuido']));
        $flash       = $this->getFlash();

        $this->render('horarios/atribuir', compact(
            'semestre', 'semestreId', 'disciplinas', 'professores', 'salas', 'semAtribuir', 'flash'
        ));
    }

    // ── Atribuição: salvar ────────────────────────────────────────
    public function atribuir(string $id): void
    {
        $semestreId = (int)$id;
        Semestre::salvarAtribuicoes(
            $semestreId,
            $this->post('atribuicao', []),
            $this->post('sala', [])
        );
        $this->flash('success', 'Atribuições salvas.');
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
            "INSERT INTO geracoes (semestre_id, descricao, status, configuracao) VALUES (?, ?, 'processando', ?)",
            [$semestreId, $descricao, json_encode($pesos)]
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
                "UPDATE geracoes SET status='erro', log=?, finished_at=NOW() WHERE id=?",
                [$e->getMessage(), $geracaoId]
            );
            $this->flash('danger', 'Erro na geração: ' . $e->getMessage());
            $this->redirect('/horarios');
            return;
        }

        $this->redirect('/horarios/geracao/' . $geracaoId . '/grade');
    }

    // ── Visualizações ─────────────────────────────────────────────
    public function verTurma(string $geracaoId): void
    {
        $geracaoId = (int)$geracaoId;
        $turmas    = Turma::allComCurso();
        $turmaId   = (int)($this->get('turma_id') ?: ($turmas[0]['id'] ?? 0));
        $horarios  = $turmaId ? Horario::porTurma($turmaId, $geracaoId) : [];
        $geracao   = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        $turma     = $turmaId ? Turma::findComCurso($turmaId) : null;
        $config    = require ROOT_PATH . '/config/app.php';
        $semestreId = $geracao['semestre_id'] ?? null;
        $this->render('horarios/turma', compact(
            'horarios', 'turmas', 'turmaId', 'geracao', 'turma', 'config', 'geracaoId', 'semestreId'
        ));
    }

    public function verProfessor(string $geracaoId): void
    {
        $geracaoId   = (int)$geracaoId;
        $professores = Professor::allAtivos();
        $profId      = (int)($this->get('professor_id') ?: ($professores[0]['id'] ?? 0));
        $horarios    = $profId ? Horario::porProfessor($profId, $geracaoId) : [];
        $geracao     = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        $professor   = $profId ? Professor::find($profId) : null;
        $config      = require ROOT_PATH . '/config/app.php';
        $semestreId  = $geracao['semestre_id'] ?? null;
        $this->render('horarios/professor', compact(
            'horarios', 'professores', 'profId', 'geracao', 'professor', 'config', 'geracaoId', 'semestreId'
        ));
    }

    public function verSala(string $geracaoId): void
    {
        $geracaoId = (int)$geracaoId;
        $salas     = Sala::allAtivas();
        $salaId    = (int)($this->get('sala_id') ?: ($salas[0]['id'] ?? 0));
        $horarios  = $salaId ? Horario::porSala($salaId, $geracaoId) : [];
        $geracao   = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        $sala      = $salaId ? Sala::find($salaId) : null;
        $config    = require ROOT_PATH . '/config/app.php';
        $semestreId = $geracao['semestre_id'] ?? null;
        $this->render('horarios/sala', compact(
            'horarios', 'salas', 'salaId', 'geracao', 'sala', 'config', 'geracaoId', 'semestreId'
        ));
    }

    // ── Grade completa (todas as turmas × dias × slots) ──────────
    public function verGrade(string $geracaoId): void
    {
        $geracaoId     = (int)$geracaoId;
        $geracao       = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [$geracaoId]);
        if (!$geracao) $this->redirect('/horarios');

        $semestreId    = $geracao['semestre_id'] ?? null;
        $todosHorarios = Horario::porGeracao($geracaoId);

        // Intervalos dedupicados por curso_id (flat, sem duplicar por dia)
        $intervalos = [];
        foreach (Database::fetchAll("SELECT * FROM intervalos_curso ORDER BY hora_inicio") as $iv) {
            $inicio = \App\Services\TimeHelper::toMinutes($iv['hora_inicio']);
            $fim    = \App\Services\TimeHelper::toMinutes($iv['hora_fim']);
            $key    = $iv['curso_id'] . '-' . $inicio . '-' . $fim;
            $intervalos[$iv['curso_id']][$key] = ['inicio' => $inicio, 'fim' => $fim];
        }

        // 1. Agrupar por turma
        $raw = [];
        foreach ($todosHorarios as $h) {
            $dia = (int)$h['dia_semana'];
            if ($dia < 1 || $dia > 5) continue;
            $tid = $h['turma_id'];
            if (!isset($raw[$tid])) {
                $raw[$tid] = [
                    'curso_id'   => (int)$h['curso_id'],
                    'curso_nome' => $h['curso_nome'],
                    'turma_nome' => $h['serie_periodo'],
                    'duracao'    => max(1, (int)$h['duracao_aula_minutos']),
                    'turno_ini'  => \App\Services\TimeHelper::toMinutes($h['turno_inicio']),
                    'turno_fim'  => \App\Services\TimeHelper::toMinutes($h['turno_fim']),
                    'por_dia'    => [1=>[], 2=>[], 3=>[], 4=>[], 5=>[]],
                ];
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

            // Gerar slots incluindo entradas de intervalo
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

            // Inicializar grid e skip
            $grid = $skip = [];
            for ($dia = 1; $dia <= 5; $dia++) {
                $grid[$dia] = array_fill(0, $numSlots, null);
                $skip[$dia] = array_fill(0, $numSlots, false);
            }

            // Colocar disciplinas, dividindo em grupos quando cruzam intervalo
            // Usa qtd_aulas para contar slots — hora_fim no BD pode ignorar intervalo
            foreach ($tData['por_dia'] as $dia => $diaList) {
                foreach ($diaList as $h) {
                    $hIni     = \App\Services\TimeHelper::toMinutes($h['hora_inicio']);
                    $qtdAulas = max(1, (int)$h['qtd_aulas']);
                    $firstIdx = $slotMap[$hIni] ?? null;
                    if ($firstIdx === null) continue;

                    // Consumir exatamente qtd_aulas slots de aula, agrupando por contiguidade
                    $groups   = [];
                    $cur      = null;
                    $consumed = 0;
                    for ($i = $firstIdx; $i < $numSlots && $consumed < $qtdAulas; $i++) {
                        $s = $slots[$i];
                        if ($s['type'] === 'intervalo') {
                            if ($cur !== null) { $groups[] = $cur; $cur = null; }
                            continue;
                        }
                        if ($cur === null) $cur = ['start' => $i, 'end' => $i, 'count' => 1];
                        else { $cur['end'] = $i; $cur['count']++; }
                        $consumed++;
                    }
                    if ($cur !== null) $groups[] = $cur;

                    foreach ($groups as $g) {
                        $slotIni = $slots[$g['start']]['min'];
                        $slotFim = $slots[$g['end']]['fim'];
                        $grid[$dia][$g['start']] = array_merge($h, [
                            'rowspan'  => $g['count'],
                            'slot_ini' => $slotIni,
                            'slot_fim' => $slotFim,
                        ]);
                        for ($i = $g['start'] + 1; $i <= $g['end']; $i++) {
                            $skip[$dia][$i] = true;
                        }
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
            ];
        }

        $this->render('horarios/grade', compact('grade', 'geracao', 'geracaoId', 'semestreId'));
    }

    // ── API: mover horário (drag & drop) ─────────────────────────
    public function moverHorario(): void
    {
        header('Content-Type: application/json');
        $raw            = json_decode(file_get_contents('php://input'), true) ?? [];
        $horarioId      = (int)($raw['horario_id'] ?? 0);
        $novoDia        = (int)($raw['novo_dia'] ?? 0);
        $novaHoraInicio = trim($raw['nova_hora_inicio'] ?? '');

        if (!$horarioId || $novoDia < 1 || $novoDia > 5) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
            return;
        }

        $h = Database::fetchOne("SELECT * FROM horarios WHERE id=?", [$horarioId]);
        if (!$h) {
            echo json_encode(['ok' => false, 'erro' => 'Horário não encontrado']);
            return;
        }

        // Calcular nova hora_fim consumindo qtd_aulas slots e pulando intervalos
        $disc = Database::fetchOne(
            "SELECT d.qtd_aulas, c.duracao_aula_minutos, c.id AS curso_id
             FROM disciplinas d JOIN cursos c ON c.id = d.curso_id
             WHERE d.id = (SELECT disciplina_id FROM horarios WHERE id = ?)",
            [$horarioId]
        );
        $qtdAulas   = max(1, (int)($disc['qtd_aulas'] ?? 1));
        $aulaMin    = max(1, (int)($disc['duracao_aula_minutos'] ?? 50));
        $ivList     = [];
        foreach (Database::fetchAll("SELECT hora_inicio, hora_fim FROM intervalos_curso WHERE curso_id = ?", [$disc['curso_id'] ?? 0]) as $iv) {
            $ivList[] = [
                'inicio' => \App\Services\TimeHelper::toMinutes($iv['hora_inicio']),
                'fim'    => \App\Services\TimeHelper::toMinutes($iv['hora_fim']),
            ];
        }

        if ($novaHoraInicio !== '') {
            $t        = \App\Services\TimeHelper::toMinutes($novaHoraInicio);
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
            $novaHoraFim = \App\Services\TimeHelper::fromMinutes($t);
        } else {
            $novaHoraInicio = $h['hora_inicio'];
            $novaHoraFim    = $h['hora_fim'];
        }

        // Sem mudança
        if ((int)$h['dia_semana'] === $novoDia
            && $h['hora_inicio'] === $novaHoraInicio . ':00'
        ) {
            echo json_encode(['ok' => true]);
            return;
        }

        // Verifica conflito de turma
        $conflito = Database::fetchOne(
            "SELECT 1 FROM horarios WHERE geracao_id=? AND turma_id=? AND dia_semana=? AND id!=?
             AND hora_inicio < ? AND hora_fim > ? LIMIT 1",
            [$h['geracao_id'], $h['turma_id'], $novoDia, $horarioId, $novaHoraFim, $novaHoraInicio]
        );
        if ($conflito) { echo json_encode(['ok' => false, 'erro' => 'Conflito de turma neste horário.']); return; }

        // Verifica conflito de professor
        if ($h['professor_id']) {
            $conflito = Database::fetchOne(
                "SELECT 1 FROM horarios WHERE geracao_id=? AND professor_id=? AND dia_semana=? AND id!=?
                 AND hora_inicio < ? AND hora_fim > ? LIMIT 1",
                [$h['geracao_id'], $h['professor_id'], $novoDia, $horarioId, $novaHoraFim, $novaHoraInicio]
            );
            if ($conflito) { echo json_encode(['ok' => false, 'erro' => 'Conflito de professor neste horário.']); return; }
        }

        // Verifica conflito de sala
        if ($h['sala_id']) {
            $conflito = Database::fetchOne(
                "SELECT 1 FROM horarios WHERE geracao_id=? AND sala_id=? AND dia_semana=? AND id!=?
                 AND hora_inicio < ? AND hora_fim > ? LIMIT 1",
                [$h['geracao_id'], $h['sala_id'], $novoDia, $horarioId, $novaHoraFim, $novaHoraInicio]
            );
            if ($conflito) { echo json_encode(['ok' => false, 'erro' => 'Conflito de sala neste horário.']); return; }
        }

        Database::query(
            "UPDATE horarios SET dia_semana=?, hora_inicio=?, hora_fim=? WHERE id=?",
            [$novoDia, $novaHoraInicio, $novaHoraFim, $horarioId]
        );
        echo json_encode(['ok' => true]);
    }

    // ── Exportações ───────────────────────────────────────────────
    public function exportarCSV(string $geracaoId): void
    {
        $horarios = Horario::porGeracao((int)$geracaoId);
        (new Exporter())->exportarCSV($horarios, "Horario_Geracao_{$geracaoId}");
    }

    public function exportarExcel(string $geracaoId): void
    {
        $horarios = Horario::porGeracao((int)$geracaoId);
        (new Exporter())->exportarExcel($horarios, "Horario_Geracao_{$geracaoId}");
    }

    public function exportarPDF(string $geracaoId): void
    {
        $horarios = Horario::porGeracao((int)$geracaoId);
        $html     = (new Exporter())->gerarHTML($horarios, "Horário Acadêmico – Geração #{$geracaoId}", 'turma');
        header('Content-Type: text/html; charset=UTF-8');
        $html = str_replace('</body>', '<script>window.onload=function(){window.print();}</script></body>', $html);
        echo $html;
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
