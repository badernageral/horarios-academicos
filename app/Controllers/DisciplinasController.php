<?php

namespace App\Controllers;

use App\Models\{Disciplina, Turma};

class DisciplinasController extends BaseController
{
    public function index(): void
    {
        $disciplinas = Disciplina::allComRelacoes();
        $flash       = $this->getFlash();
        $this->render('disciplinas/index', compact('disciplinas', 'flash'));
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
}
