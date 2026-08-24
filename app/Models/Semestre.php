<?php

namespace App\Models;

use App\Core\Database;

class Semestre extends BaseModel
{
    protected static string $table = 'semestres';

    /**
     * Carga de cada professor no semestre, a partir das ATRIBUIÇÕES (não da
     * grade gerada) — serve para conferir a distribuição antes de gerar.
     *
     * Agrupado pelo NDA do PROFESSOR. Quando uma disciplina tem mais de um
     * professor, os encontros são divididos entre eles pela MESMA regra do
     * gerador (teto para os primeiros slots), senão o relatório prometeria uma
     * carga diferente da que será agendada.
     *
     * @return array [nda_id => ['nome' => string, 'professores' => [...], 'minutos' => int, 'aulas' => int]]
     */
    /**
     * Ocupação de cada sala no semestre, a partir das ATRIBUIÇÕES.
     *
     * A sala fica em `semestre_atribuicoes`, uma linha por slot de professor —
     * por isso o MAX(sala_id): disciplina com dois professores tem duas linhas,
     * mas uma sala só. Salas ativas sem nenhuma disciplina também entram, e as
     * disciplinas sem sala caem num grupo à parte.
     *
     * @return array lista de ['id','nome','disciplinas'=>[...],'aulas'=>int]
     */
    public static function ocupacaoPorSala(int $semestreId): array
    {
        $linhas = Database::fetchAll(
            "SELECT MAX(sa.sala_id) AS sala_id,
                    d.nome AS disciplina_nome,
                    d.qtd_encontros_semanais, d.qtd_aulas,
                    c.nome AS curso_nome, c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome
             FROM semestre_atribuicoes sa
             JOIN disciplinas d  ON d.id = sa.disciplina_id
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             JOIN semestres sem  ON sem.id = ?
             WHERE sa.semestre_id = ?
               AND d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
             GROUP BY d.id, d.nome, d.qtd_encontros_semanais, d.qtd_aulas,
                      c.nome, c.duracao_aula_minutos, t.serie_periodo
             ORDER BY c.nome, t.serie_periodo, d.nome",
            [$semestreId, $semestreId]
        );

        // Todas as salas ativas entram, mesmo vazias — é assim que se enxerga
        // a sala ociosa e a superlotada lado a lado.
        $salas = [];
        foreach (Database::fetchAll("SELECT id, nome FROM salas WHERE ativo = 1 ORDER BY nome") as $s) {
            $salas[(int)$s['id']] = ['id' => (int)$s['id'], 'nome' => $s['nome'], 'disciplinas' => []];
        }

        $semSala = ['id' => 0, 'nome' => 'Sem sala definida', 'disciplinas' => []];

        foreach ($linhas as $l) {
            $aulas   = max(0, (int)$l['qtd_encontros_semanais']) * max(0, (int)$l['qtd_aulas']);
            $item = [
                'nome'    => $l['disciplina_nome'],
                'turma'   => $l['curso_nome'] . ' – ' . $l['turma_nome'],
                'aulas'   => $aulas,
                'minutos' => $aulas * max(0, (int)$l['duracao_aula_minutos']),
            ];
            $sid = $l['sala_id'] !== null ? (int)$l['sala_id'] : 0;

            if ($sid > 0 && isset($salas[$sid])) {
                $salas[$sid]['disciplinas'][] = $item;
            } else {
                $semSala['disciplinas'][] = $item;   // sem sala, ou sala inativa
            }
        }

        $saida = array_values($salas);
        if ($semSala['disciplinas']) $saida[] = $semSala;

        foreach ($saida as &$s) {
            $s['aulas']   = array_sum(array_column($s['disciplinas'], 'aulas'));
            $s['minutos'] = array_sum(array_column($s['disciplinas'], 'minutos'));
        }
        unset($s);

        return $saida;
    }

    public static function cargaPorProfessor(int $semestreId): array
    {
        $linhas = Database::fetchAll(
            "SELECT sa.slot, sa.professor_id,
                    p.nome AS professor_nome, p.nda_id AS nda_id, n.nome AS nda_nome,
                    d.id AS disciplina_id, d.nome AS disciplina_nome,
                    d.qtd_encontros_semanais, d.qtd_aulas, d.qtd_professores, d.qtd_aulas_ead,
                    c.nome AS curso_nome, c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome
             FROM semestre_atribuicoes sa
             JOIN professores p  ON p.id = sa.professor_id
             LEFT JOIN ndas n    ON n.id = p.nda_id
             JOIN disciplinas d  ON d.id = sa.disciplina_id
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             JOIN semestres sem  ON sem.id = ?
             WHERE sa.semestre_id = ?
               AND d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
             ORDER BY n.nome IS NULL, n.nome, p.nome, c.nome, t.serie_periodo, d.nome",
            [$semestreId, $semestreId]
        );

        $grupos = [];
        foreach ($linhas as $l) {
            $ndaId = $l['nda_id'] !== null ? (int)$l['nda_id'] : 0;
            $pid   = (int)$l['professor_id'];

            // Mesma divisão de encontros que ScheduleGenerator::criarAtividades()
            $totalEnc = max(0, (int)$l['qtd_encontros_semanais']);
            $qtdProfs = max(1, (int)$l['qtd_professores']);
            $slot     = max(1, (int)$l['slot']);
            $encontros = intdiv($totalEnc, $qtdProfs) + ($slot <= $totalEnc % $qtdProfs ? 1 : 0);

            $aulas   = $encontros * max(0, (int)$l['qtd_aulas']);
            $minutos = $aulas * max(0, (int)$l['duracao_aula_minutos']);

            // As aulas EaD contam na carga SEMANAL do professor (não na carga
            // relógio: não ocupam slot na grade). Numa disciplina dividida elas
            // seguem a MESMA regra dos encontros — teto nos primeiros slots —
            // senão cada professor levaria o total e o somatório do NDA dobraria.
            $totalEad = max(0, (int)$l['qtd_aulas_ead']);
            $ead      = intdiv($totalEad, $qtdProfs) + ($slot <= $totalEad % $qtdProfs ? 1 : 0);

            $grupos[$ndaId]['nome'] ??= $l['nda_nome'] ?? 'Sem NDA';
            $grupos[$ndaId]['professores'][$pid]['nome'] ??= $l['professor_nome'];
            $grupos[$ndaId]['professores'][$pid]['disciplinas'][] = [
                'nome'      => $l['disciplina_nome'],
                'turma'     => $l['curso_nome'] . ' – ' . $l['turma_nome'],
                'encontros' => $encontros,
                'aulas'     => $aulas,
                'minutos'   => $minutos,
                'ead'       => $ead,
                'dividida'  => $qtdProfs > 1,
            ];
        }

        // Professores ATIVOS sem nenhuma atribuição também entram, com zero —
        // é justamente quem se quer enxergar ao conferir a distribuição.
        foreach (Database::fetchAll(
            "SELECT p.id, p.nome, p.nda_id, n.nome AS nda_nome
             FROM professores p
             LEFT JOIN ndas n ON n.id = p.nda_id
             WHERE p.ativo = 1"
        ) as $p) {
            $ndaId = $p['nda_id'] !== null ? (int)$p['nda_id'] : 0;
            $pid   = (int)$p['id'];
            $grupos[$ndaId]['nome'] ??= $p['nda_nome'] ?? 'Sem NDA';
            if (!isset($grupos[$ndaId]['professores'][$pid])) {
                $grupos[$ndaId]['professores'][$pid] = ['nome' => $p['nome'], 'disciplinas' => []];
            }
        }

        // Ordena: NDAs por nome ("Sem NDA" no fim) e professores por nome.
        uksort($grupos, function ($a, $b) use ($grupos) {
            if ($a === 0) return 1;
            if ($b === 0) return -1;
            return strcasecmp($grupos[$a]['nome'], $grupos[$b]['nome']);
        });
        foreach ($grupos as &$g) {
            uasort($g['professores'], fn($x, $y) => strcasecmp($x['nome'], $y['nome']));
        }
        unset($g);

        // Totais por professor e por NDA. `aulas` = presencial, `ead` = a
        // distância e `aulas_total` = carga semanal de aulas (soma das duas);
        // `minutos` fica só com o presencial, que é o que ocupa a grade.
        foreach ($grupos as &$g) {
            $g['minutos'] = 0; $g['aulas'] = 0; $g['ead'] = 0;
            foreach ($g['professores'] as &$p) {
                $p['minutos']     = array_sum(array_column($p['disciplinas'], 'minutos'));
                $p['aulas']       = array_sum(array_column($p['disciplinas'], 'aulas'));
                $p['ead']         = array_sum(array_column($p['disciplinas'], 'ead'));
                $p['aulas_total'] = $p['aulas'] + $p['ead'];
                $g['minutos'] += $p['minutos'];
                $g['aulas']   += $p['aulas'];
                $g['ead']     += $p['ead'];
            }
            unset($p);
            $g['aulas_total'] = $g['aulas'] + $g['ead'];
        }
        unset($g);

        return $grupos;
    }

    public static function disciplinasComAtribuicao(int $semestreId): array
    {
        $rows = Database::fetchAll(
            "SELECT d.id, d.nome, d.sigla,
                    d.qtd_encontros_semanais, d.qtd_aulas, d.semestre_oferta, d.qtd_professores,
                    c.nome AS curso_nome, c.duracao_aula_minutos,
                    t.serie_periodo AS turma_nome,
                    d.nda_id, n.nome AS nda_nome,
                    (SELECT GROUP_CONCAT(x.professor_id, ',')
                       FROM (SELECT professor_id FROM semestre_atribuicoes
                              WHERE disciplina_id = d.id AND semestre_id = ?
                              ORDER BY slot) x) AS professores_atribuidos,
                    MAX(CASE WHEN sa.slot = 1 THEN sa.sala_id ELSE NULL END) AS sala_atribuida
             FROM disciplinas d
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             LEFT JOIN ndas n    ON n.id = d.nda_id
             JOIN semestres sem  ON sem.id = ?
             LEFT JOIN semestre_atribuicoes sa
                    ON sa.disciplina_id = d.id AND sa.semestre_id = ?
             WHERE d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
             GROUP BY d.id, d.nome, d.sigla,
                      d.qtd_encontros_semanais, d.qtd_aulas, d.semestre_oferta, d.qtd_professores,
                      c.nome, c.duracao_aula_minutos, t.serie_periodo,
                      d.nda_id, n.nome
             ORDER BY c.nome, t.serie_periodo, d.nome",
            [$semestreId, $semestreId, $semestreId]
        );

        foreach ($rows as &$row) {
            $ids = array_filter(explode(',', $row['professores_atribuidos'] ?? ''));
            $row['professores_atribuidos'] = array_values(array_map('intval', $ids));
        }
        unset($row);
        return $rows;
    }

    /**
     * Salva as atribuições do semestre e sincroniza a grade já gerada.
     * Retorna os nomes das disciplinas cujos encontros foram enviados ao limbo
     * por gerarem conflito de professor após a troca (lista vazia se nenhum).
     *
     * @return string[]
     */
    public static function salvarAtribuicoes(int $semestreId, array $professores, array $salas = []): array
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

        // Geração ativa do semestre (para limbo e sincronização).
        $geracao = Database::fetchOne(
            "SELECT id FROM geracoes WHERE semestre_id = ? ORDER BY created_at DESC LIMIT 1",
            [$semestreId]
        );
        $geracaoId = $geracao ? (int)$geracao['id'] : null;

        // Disciplinas que já têm pelo menos um horario nessa geração (para detectar novas).
        $comHorario = [];
        if ($geracaoId) {
            foreach (Database::fetchAll(
                "SELECT DISTINCT disciplina_id FROM horarios WHERE geracao_id = ?",
                [$geracaoId]
            ) as $r) {
                $comHorario[(int)$r['disciplina_id']] = true;
            }
        }

        // Disciplinas cujo professor mudou (introduzem possíveis conflitos na grade).
        $disciplinasAlteradas = [];

        // $professores: [disciplina_id => [slot => professor_id]]
        foreach ($professores as $disciplinaId => $slots) {
            $disciplinaId = (int)$disciplinaId;
            $salaId = ($salas[$disciplinaId] ?? '') !== '' ? (int)$salas[$disciplinaId] : null;
            $trocas     = [];
            $novosSlots = []; // slot => professor_id efetivamente gravado
            $eraVazia = !isset($atuais[$disciplinaId][1]);
            foreach ((array)$slots as $slot => $professorId) {
                if ((string)$professorId === '') continue;
                $slot        = (int)$slot;
                $professorId = (int)$professorId;
                Database::query(
                    "INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id, slot, sala_id)
                     VALUES (?, ?, ?, ?, ?)",
                    [$semestreId, $disciplinaId, $professorId, $slot, $slot === 1 ? $salaId : null]
                );
                $novosSlots[$slot] = $professorId;

                $antigo = $atuais[$disciplinaId][$slot] ?? null;
                if ($antigo !== null && $antigo !== $professorId) {
                    $trocas[$antigo] = $professorId;
                }

                // Disciplina nova (sem horarios na geração): cria registros de limbo para o slot 1.
                if ($slot === 1 && $eraVazia && $geracaoId && !($comHorario[$disciplinaId] ?? false)) {
                    self::criarLimbo($geracaoId, $disciplinaId, $professorId, $salaId);
                    $comHorario[$disciplinaId] = true; // evita duplicar se o loop rodar novamente
                }
            }

            $slotsAntigos = array_keys($atuais[$disciplinaId] ?? []);
            $slotsAtuais  = array_keys($novosSlots);
            sort($slotsAntigos);
            sort($slotsAtuais);

            if ($geracaoId && $novosSlots && $slotsAntigos !== $slotsAtuais
                && ($comHorario[$disciplinaId] ?? false)
            ) {
                // Professor adicionado ou removido (estrutura de slots mudou): não há
                // "troca" 1-para-1 — redistribui os encontros existentes entre os
                // professores atuais, na mesma proporção que o gerador usaria.
                self::redistribuirEncontros($geracaoId, $disciplinaId, $novosSlots);
                $disciplinasAlteradas[] = $disciplinaId;
            } elseif ($trocas) {
                self::sincronizarProfessorHorarios($semestreId, $disciplinaId, $trocas);
                $disciplinasAlteradas[] = $disciplinaId;
            }
        }

        // Após sincronizar os professores na grade, uma troca pode colocar o mesmo
        // professor em duas disciplinas no mesmo horário. Os encontros em conflito
        // (das disciplinas alteradas) vão para o limbo para reposicionamento manual.
        if ($geracaoId && $disciplinasAlteradas) {
            return self::resolverConflitosProfessor($geracaoId, $disciplinasAlteradas);
        }
        return [];
    }

    // Detecta conflitos de professor (mesmo professor, mesmo dia, horários sobrepostos)
    // na geração ativa e envia ao limbo (dia_semana=0) os encontros das disciplinas
    // recém-alteradas que colidem. Retorna os nomes das disciplinas afetadas.
    private static function resolverConflitosProfessor(int $geracaoId, array $disciplinasAlteradas): array
    {
        $rows = Database::fetchAll(
            "SELECT id, disciplina_id, professor_id, dia_semana, hora_inicio, hora_fim
             FROM horarios
             WHERE geracao_id = ? AND dia_semana > 0
             ORDER BY professor_id, dia_semana, hora_inicio",
            [$geracaoId]
        );

        $alteradas = array_flip(array_map('intval', $disciplinasAlteradas));
        $mover     = []; // ids de horarios a enviar ao limbo
        $n         = count($rows);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $rows[$i];
                $b = $rows[$j];
                if ((int)$a['professor_id'] !== (int)$b['professor_id']) continue;
                if ((int)$a['dia_semana']   !== (int)$b['dia_semana'])   continue;
                // Sobreposição de intervalos (strings TIME HH:MM:SS comparam lexicograficamente).
                if ($a['hora_inicio'] < $b['hora_fim'] && $b['hora_inicio'] < $a['hora_fim']) {
                    // Move apenas o(s) lado(s) cuja disciplina foi alterada nesta operação;
                    // o encontro pré-existente permanece no lugar.
                    if (isset($alteradas[(int)$a['disciplina_id']])) $mover[(int)$a['id']] = true;
                    if (isset($alteradas[(int)$b['disciplina_id']])) $mover[(int)$b['id']] = true;
                }
            }
        }

        if (!$mover) return [];

        $ids = array_keys($mover);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        Database::query("UPDATE horarios SET dia_semana = 0 WHERE id IN ($ph)", $ids);

        // Nomes distintos das disciplinas afetadas (para feedback ao usuário).
        return array_column(
            Database::fetchAll(
                "SELECT DISTINCT d.nome
                 FROM horarios h JOIN disciplinas d ON d.id = h.disciplina_id
                 WHERE h.id IN ($ph) ORDER BY d.nome",
                $ids
            ),
            'nome'
        );
    }

    // Insere registros de limbo (dia_semana=0) para uma disciplina recém-atribuída,
    // um registro por encontro semanal, usando o turno_inicio do curso como hora base.
    private static function criarLimbo(int $geracaoId, int $disciplinaId, int $professorId, ?int $salaId): void
    {
        $disc = Database::fetchOne(
            "SELECT d.turma_id, d.qtd_encontros_semanais, d.qtd_aulas, c.turno_inicio, c.duracao_aula_minutos
             FROM disciplinas d
             JOIN turmas t ON t.id = d.turma_id
             JOIN cursos c ON c.id = t.curso_id
             WHERE d.id = ?",
            [$disciplinaId]
        );
        if (!$disc) return;

        $durMin    = (int)$disc['qtd_aulas'] * (int)$disc['duracao_aula_minutos'];
        $inicioStr = \App\Services\TimeHelper::toHms($disc['turno_inicio']);
        [$h, $m]   = array_map('intval', explode(':', $inicioStr));
        $inicioMin = $h * 60 + $m;
        $fimStr    = sprintf('%02d:%02d:00', intdiv($inicioMin + $durMin, 60), ($inicioMin + $durMin) % 60);

        $stmt = Database::getInstance()->prepare(
            "INSERT INTO horarios (geracao_id, disciplina_id, turma_id, professor_id, sala_id, dia_semana, hora_inicio, hora_fim)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)"
        );
        for ($i = 0; $i < (int)$disc['qtd_encontros_semanais']; $i++) {
            $stmt->execute([$geracaoId, $disciplinaId, (int)$disc['turma_id'], $professorId, $salaId, $inicioStr, $fimStr]);
        }
    }

    // Redistribui os encontros já gerados de uma disciplina entre os professores
    // atuais quando a estrutura de slots muda (professor adicionado/removido),
    // preservando dia/hora/sala. Mesma regra do gerador: primeiros slots recebem
    // o teto (intdiv + resto), na ordem dia/hora.
    private static function redistribuirEncontros(int $geracaoId, int $disciplinaId, array $slotsProfessores): void
    {
        ksort($slotsProfessores);
        $horarios = Database::fetchAll(
            "SELECT id FROM horarios WHERE geracao_id = ? AND disciplina_id = ?
             ORDER BY dia_semana, hora_inicio, id",
            [$geracaoId, $disciplinaId]
        );
        $total = count($horarios);
        if ($total === 0) return;

        $profs = array_values($slotsProfessores);
        $qtd   = count($profs);
        $base  = intdiv($total, $qtd);
        $extra = $total % $qtd;

        $i = 0;
        foreach ($profs as $k => $professorId) {
            $n = $base + ($k < $extra ? 1 : 0);
            for ($j = 0; $j < $n && $i < $total; $j++, $i++) {
                Database::query(
                    "UPDATE horarios SET professor_id = ? WHERE id = ?",
                    [$professorId, $horarios[$i]['id']]
                );
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
            "UPDATE horarios
             SET professor_id = CASE professor_id " . implode(' ', $cases) . " ELSE professor_id END
             WHERE geracao_id IN (SELECT id FROM geracoes WHERE semestre_id = ?)
               AND disciplina_id = ? AND professor_id IN ($placeholders)",
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
