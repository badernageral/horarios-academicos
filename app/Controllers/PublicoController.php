<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\Horario;
use App\Services\GradeLayout;

/**
 * Área pública: consulta do horário vigente sem login.
 *
 * Só leitura, e só o que já é afixado no mural — disciplina, horário, professor
 * e sala. Nada de cadastro, exportação ou edição: a guarda em index.php libera
 * exclusivamente /publico para quem não tem sessão.
 */
class PublicoController extends BaseController
{
    public function index(): void
    {
        $config = require ROOT_PATH . '/config/app.php';

        // Semestre corrente pelo calendário; o 2º semestre começa em agosto.
        $anoAtual      = (int) date('Y');
        $semestreAtual = (int) date('n') <= 7 ? 1 : 2;

        // Só o semestre CORRENTE e só se liberado. Sem fallback para o último
        // publicado: mostrar horário de outro semestre confundiria quem consulta.
        $geracao = Database::fetchOne(
            "SELECT g.*, s.semestre, s.ano
             FROM geracoes g
             JOIN semestres s ON s.id = g.semestre_id
             WHERE s.ano = ? AND s.semestre = ? AND g.publico = 1
             ORDER BY g.id DESC LIMIT 1",
            [$anoAtual, $semestreAtual]
        );

        $grade       = [];
        $turmas      = [];
        $professores = [];
        $anotacoes   = [];

        // Filtros de consulta (o aluno acha a turma dele; o professor, o dele)
        $turmaFiltro = (int) $this->get('turma_id', 0);
        $profFiltro  = (int) $this->get('professor_id', 0);

        if ($geracao) {
            $todos = Horario::porGeracao((int) $geracao['id']);

            foreach ($todos as $h) {
                if ((int) $h['dia_semana'] === 0) continue;   // limbo não é público
                $turmas[(int) $h['turma_id']] = $h['curso_nome'] . ' — ' . $h['turma_nome'];
                $professores[(int) $h['professor_id']] = $h['professor_nome'];
            }
            asort($turmas, SORT_NATURAL | SORT_FLAG_CASE);
            asort($professores, SORT_NATURAL | SORT_FLAG_CASE);

            $visiveis = array_values(array_filter($todos, function ($h) use ($turmaFiltro, $profFiltro) {
                if ((int) $h['dia_semana'] === 0) return false;
                if ($turmaFiltro && (int) $h['turma_id'] !== $turmaFiltro) return false;
                if ($profFiltro && (int) $h['professor_id'] !== $profFiltro) return false;
                return true;
            }));

            $grade = GradeLayout::montar($visiveis);

            foreach (Database::fetchAll(
                "SELECT turma_id, texto FROM anotacoes_turma WHERE geracao_id = ?",
                [(int) $geracao['id']]
            ) as $a) {
                $anotacoes[(int) $a['turma_id']] = $a['texto'];
            }
        }

        $dias = $config['dias_semana'] ?? [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta'];
        $base = BASE_PATH;

        require ROOT_PATH . '/app/Views/publico/horario.php';
    }
}
