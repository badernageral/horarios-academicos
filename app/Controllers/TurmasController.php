<?php

namespace App\Controllers;

use App\Models\{Turma, Curso};

class TurmasController extends BaseController
{
    public function index(): void
    {
        $turmas = Turma::allComCurso();
        $flash  = $this->getFlash();
        $this->render('turmas/index', compact('turmas', 'flash'));
    }

    public function nova(): void
    {
        $cursos = Curso::allAtivos();
        $this->render('turmas/form', ['turma' => null, 'cursos' => $cursos, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');
        $data = [
            'curso_id'     => (int)$this->post('curso_id'),
            'serie_periodo'=> trim($this->post('serie_periodo')),
            'ativo'        => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Turma::update((int)$id, $data);
            $this->flash('success', 'Turma atualizada com sucesso!');
        } else {
            Turma::create($data);
            $this->flash('success', 'Turma cadastrada com sucesso!');
        }
        $this->redirect('/turmas');
    }

    public function editar(string $id): void
    {
        $turma  = Turma::find((int)$id);
        $cursos = Curso::allAtivos();
        if (!$turma) $this->redirect('/turmas');
        $this->render('turmas/form', ['turma' => $turma, 'cursos' => $cursos, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Turma::delete($id);
            $this->flash('success', 'Turma removida.');
        }
        $this->redirect('/turmas');
    }
}
