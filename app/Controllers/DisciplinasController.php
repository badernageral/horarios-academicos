<?php

namespace App\Controllers;

use App\Models\{Disciplina, Turma};

class DisciplinasController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome','sigla','curso_nome','turma_nome','qtd_encontros_semanais'], 'nome');
        $disciplinas = Disciplina::allComRelacoes($sort, $dir);
        $flash       = $this->getFlash();
        $this->render('disciplinas/index', compact('disciplinas', 'flash', 'sort', 'dir'));
    }

    public function nova(): void
    {
        $turmas = Turma::allComCurso();
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('disciplinas/form', [
            'disciplina' => null,
            'turmas'     => $turmas,
            'config'     => $config,
            'flash'      => null,
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
            'ativo'                  => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Disciplina::update((int)$id, $data);
            $this->flash('success', 'Disciplina atualizada!');
        } else {
            Disciplina::create($data);
            $this->flash('success', 'Disciplina cadastrada!');
        }
        $this->redirect('/disciplinas');
    }

    public function editar(string $id): void
    {
        $disciplina = Disciplina::findComRelacoes((int)$id);
        $turmas     = Turma::allComCurso();
        $config     = require ROOT_PATH . '/config/app.php';
        if (!$disciplina) $this->redirect('/disciplinas');
        $this->render('disciplinas/form', compact(
            'disciplina', 'turmas', 'config'
        ) + ['flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Disciplina::delete($id);
            $this->flash('success', 'Disciplina removida.');
        }
        $this->redirect('/disciplinas');
    }

    public function verImportar(): void
    {
        $turmas = Turma::allComCurso();
        $this->render('disciplinas/importar', ['turmas' => $turmas, 'flash' => null]);
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

            $partes   = explode('-', $linha, 2);
            $nome     = trim($partes[0], " \t");
            $aulasPart = isset($partes[1]) ? trim($partes[1], " \t") : '';

            if ($nome === '') { $puladas++; continue; }

            $qtdAulas = (ctype_digit($aulasPart) && (int)$aulasPart >= 1)
                ? (int)$aulasPart
                : 2; // padrão

            Disciplina::create([
                'nome'                   => $nome,
                'sigla'                  => (preg_match('/^.{0,50}/us', $nome, $m) ? $m[0] : substr($nome, 0, 50)),
                'curso_id'               => $cursoId,
                'turma_id'               => $turmaId,
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
