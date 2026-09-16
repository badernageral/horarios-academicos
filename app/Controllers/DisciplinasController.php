<?php

namespace App\Controllers;

use App\Models\{Disciplina, Turma, Curso, Nda};
use App\Services\FeasibilityChecker;

class DisciplinasController extends BaseController
{
    public function index(): void
    {
        // Curso e turma saíram das colunas ordenáveis: viraram os próprios grupos.
        [$sort, $dir] = $this->sortParams(['nome','sigla','nda_nome','qtd_encontros_semanais'], 'nda_nome');
        $disciplinas = Disciplina::allComRelacoes($sort, $dir);
        $flash       = $this->getFlash();

        // Filtros de visualização: curso, turma e NDA. "nda_id=sem" filtra as
        // disciplinas sem núcleo (nda_id NULL), já que "" já significa "todos".
        $cursoFiltro = (int)$this->get('curso_id', 0);
        $turmaFiltro = (int)$this->get('turma_id', 0);
        $ndaFiltro   = $this->get('nda_id', '');
        $filtroAtivo = $cursoFiltro || $turmaFiltro || $ndaFiltro !== '';

        if ($filtroAtivo) {
            $disciplinas = array_values(array_filter($disciplinas,
                function ($d) use ($cursoFiltro, $turmaFiltro, $ndaFiltro) {
                    if ($cursoFiltro && (int)$d['curso_id'] !== $cursoFiltro) return false;
                    if ($turmaFiltro && (int)$d['turma_id'] !== $turmaFiltro) return false;
                    if ($ndaFiltro === 'sem' && $d['nda_id'] !== null) return false;
                    if ($ndaFiltro !== '' && $ndaFiltro !== 'sem' && (int)$d['nda_id'] !== (int)$ndaFiltro) return false;
                    return true;
                }
            ));
        }

        $cursosFiltro = Curso::allAtivos();
        $turmasFiltro = Turma::allComCurso();
        $ndasFiltro   = Nda::allAtivos();

        // URL para "voltar" preservando os filtros/ordenação atuais: vai e volta
        // com o formulário de cadastro/edição, e com o POST de remover.
        $voltarQs = http_build_query(array_filter([
            'sort' => $sort, 'dir' => $dir,
            'curso_id' => $cursoFiltro ?: null, 'turma_id' => $turmaFiltro ?: null,
            'nda_id' => $ndaFiltro !== '' ? $ndaFiltro : null,
        ], fn($v) => $v !== null));
        $voltarUrl = $voltarQs ? '/disciplinas?' . $voltarQs : '/disciplinas';

        // Agrupa curso → turma. A ordenação escolhida no cabeçalho vale DENTRO
        // da turma (o SQL já entregou as linhas nessa ordem); os grupos ficam
        // sempre em ordem natural de nome, para a lista não dançar a cada clique.
        $grupos = [];
        foreach ($disciplinas as $d) {
            $cursoId = (int)$d['curso_id'];
            $turmaId = (int)$d['turma_id'];

            $grupos[$cursoId]['nome'] ??= $d['curso_nome'];
            $grupos[$cursoId]['turmas'][$turmaId]['id']   ??= $turmaId;
            $grupos[$cursoId]['turmas'][$turmaId]['nome'] ??= $d['turma_nome'];
            $grupos[$cursoId]['turmas'][$turmaId]['disciplinas'][] = $d;
        }
        uasort($grupos, fn($a, $b) => strnatcasecmp($a['nome'], $b['nome']));

        foreach ($grupos as &$g) {
            uasort($g['turmas'], fn($a, $b) => strnatcasecmp($a['nome'], $b['nome']));
            $g['qtd']     = 0;
            $g['minutos'] = 0;
            foreach ($g['turmas'] as &$t) {
                $t['minutos'] = 0;
                foreach ($t['disciplinas'] as $d) {
                    $t['minutos'] += (int)$d['qtd_encontros_semanais'] * (int)$d['qtd_aulas']
                                   * (int)$d['duracao_aula_minutos'];
                }
                $g['qtd']     += count($t['disciplinas']);
                $g['minutos'] += $t['minutos'];
            }
            unset($t);
        }
        unset($g);

        $this->render('disciplinas/index', compact(
            'disciplinas', 'grupos', 'flash', 'sort', 'dir',
            'cursoFiltro', 'turmaFiltro', 'ndaFiltro', 'filtroAtivo',
            'cursosFiltro', 'turmasFiltro', 'ndasFiltro', 'voltarUrl'
        ));
    }

    // Só aceita voltar para dentro de /disciplinas, para não virar um open redirect.
    private function voltarSeguro(string $voltar): string
    {
        return (str_starts_with($voltar, '/disciplinas') && !str_starts_with($voltar, '//'))
            ? $voltar : '/disciplinas';
    }

    public function nova(): void
    {
        $turmas = Turma::allComCurso();
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('disciplinas/form', [
            'disciplina' => null,
            'turmas'     => $turmas,
            'ndas'       => Nda::allAtivos(),
            'config'     => $config,
            'flash'      => null,
            'voltar'     => $this->voltarSeguro($this->get('voltar', '')),
        ]);
    }

    public function salvar(): void
    {
        $id      = $this->post('id');
        $turmaId = (int)$this->post('turma_id');
        $turma   = Turma::find($turmaId);

        $s1 = $this->post('semestre_1') ? 1 : 0;
        $s2 = $this->post('semestre_2') ? 2 : 0;

        $data = [
            'nome'                   => trim($this->post('nome')),
            'sigla'                  => trim($this->post('sigla', '')),
            'curso_id'               => (int)($turma['curso_id'] ?? 0),
            'turma_id'               => $turmaId,
            'qtd_encontros_semanais' => (int)$this->post('qtd_encontros_semanais', 1),
            'qtd_aulas'              => (int)$this->post('qtd_aulas', 2),
            'qtd_aulas_ead'          => max(0, (int)$this->post('qtd_aulas_ead', 0)),
            'qtd_professores'        => max(1, (int)$this->post('qtd_professores', 1)),
            'semestre_oferta'        => ($s1 | $s2) ?: 3,
            // Vazio = "Qualquer NDA": a disciplina não é de um núcleo específico.
            'nda_id'                 => ($nda = $this->post('nda_id')) !== '' && $nda !== null
                                          ? (int)$nda : null,
            'ativo'                  => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            // Turma anterior, para saber se a troca invalida grades já geradas.
            $turmaAnterior = (int)(Disciplina::find((int)$id)['turma_id'] ?? 0);

            Disciplina::update((int)$id, $data);

            if ($turmaAnterior !== 0 && $turmaAnterior !== $turmaId) {
                // Diagnóstico apenas: a grade NÃO é alterada nem regerada aqui.
                // Regerar é decisão do usuário (o botão fica em /horarios).
                $impacto = FeasibilityChecker::impactoTrocaDeTurma((int)$id);
                if ($impacto) {
                    $this->flash('warning',
                        'Disciplina atualizada. ' . implode(' ', $impacto));
                } else {
                    $this->flash('success', 'Disciplina atualizada!');
                }
            } else {
                $this->flash('success', 'Disciplina atualizada!');
            }
        } else {
            Disciplina::create($data);
            $this->flash('success', 'Disciplina cadastrada!');
        }
        $this->redirect($this->voltarSeguro($this->post('voltar', '')));
    }

    public function editar(string $id): void
    {
        $disciplina = Disciplina::findComRelacoes((int)$id);
        $turmas     = Turma::allComCurso();
        $config     = require ROOT_PATH . '/config/app.php';
        if (!$disciplina) $this->redirect('/disciplinas');
        $ndas   = Nda::allAtivos();
        $voltar = $this->voltarSeguro($this->get('voltar', ''));
        $this->render('disciplinas/form', compact(
            'disciplina', 'turmas', 'ndas', 'config', 'voltar'
        ) + ['flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Disciplina::delete($id);
            $this->flash('success', 'Disciplina removida.');
        }
        $this->redirect($this->voltarSeguro($this->post('voltar', '')));
    }

    public function verImportar(): void
    {
        $turmas = Turma::allComCurso();
        $this->render('disciplinas/importar', ['turmas' => $turmas, 'ndas' => Nda::allAtivos(), 'flash' => null]);
    }

    public function importar(): void
    {
        $turmaId = (int)$this->post('turma_id');
        $turma   = Turma::find($turmaId);

        if (!$turma) {
            $this->flash('danger', 'Turma não encontrada.');
            $this->redirect('/disciplinas/importar');
            return;
        }

        $cursoId        = (int)$turma['curso_id'];
        // Vazio = "Qualquer NDA": a disciplina não é de um núcleo específico.
        $ndaId          = ($nda = $this->post('nda_id')) !== '' && $nda !== null ? (int)$nda : null;
        $semestreOferta = in_array((int)$this->post('semestre_oferta'), [1, 2, 3])
            ? (int)$this->post('semestre_oferta')
            : 3;
        $texto   = $this->post('linhas', '');
        $linhas  = array_filter(array_map('trim', explode("\n", $texto)));

        $criadas = 0;
        $puladas = 0;

        foreach ($linhas as $linha) {
            $linha = trim($linha, " \r\n");
            if ($linha === '') continue;

            // Separa no ÚLTIMO " - " (com espaços) para suportar hífen no nome,
            // como em "Físico-química do Solo - 3" (mesma regra do import de atribuições).
            $pos = strrpos($linha, ' - ');
            $aulasPart = $pos !== false ? trim(substr($linha, $pos + 3), " \t") : '';

            if ($pos !== false && ctype_digit($aulasPart) && (int)$aulasPart >= 1) {
                $nome     = trim(substr($linha, 0, $pos), " \t");
                $qtdAulas = (int)$aulasPart;
            } else {
                $nome     = trim($linha, " \t");
                $qtdAulas = 2; // padrão
            }

            if ($nome === '') { $puladas++; continue; }

            Disciplina::create([
                'nome'                   => $nome,
                'sigla'                  => (preg_match('/^.{0,20}/us', $nome, $m) ? $m[0] : substr($nome, 0, 20)),
                'curso_id'               => $cursoId,
                'turma_id'               => $turmaId,
                'nda_id'                 => $ndaId,
                'qtd_encontros_semanais' => 1,
                'qtd_aulas'              => $qtdAulas,
                'qtd_professores'        => 1,
                'semestre_oferta'        => $semestreOferta,
                'ativo'                  => 1,
            ]);
            $criadas++;
        }

        $msg = "{$criadas} disciplina(s) cadastrada(s).";
        if ($puladas) $msg .= " {$puladas} linha(s) pulada(s) por nome vazio.";
        $this->flash($criadas > 0 ? 'success' : 'warning', $msg);
        $this->redirect('/disciplinas');
    }
}
