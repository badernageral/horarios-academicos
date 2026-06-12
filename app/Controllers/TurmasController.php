<?php

namespace App\Controllers;

use App\Models\{Turma, Curso};

class TurmasController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['curso_nome', 'serie_periodo', 'ativo'], 'curso_nome');
        $turmas = Turma::allComCurso($sort, $dir);
        $flash  = $this->getFlash();
        $this->render('turmas/index', compact('turmas', 'flash', 'sort', 'dir'));
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

    public function verImportar(): void
    {
        $cursos = Curso::allAtivos();
        $this->render('turmas/importar', ['cursos' => $cursos, 'flash' => null]);
    }

    public function importar(): void
    {
        $cursoId = (int)$this->post('curso_id');
        $texto   = $this->post('series', '');

        if (!$cursoId) {
            $this->flash('danger', 'Selecione um curso.');
            $this->redirect('/turmas/importar');
            return;
        }

        $linhas  = array_filter(array_map('trim', explode("\n", $texto)));
        $criadas = 0;

        foreach ($linhas as $serie) {
            $serie = trim($serie, " \t\r\n");
            if ($serie === '') continue;

            Turma::create([
                'curso_id'      => $cursoId,
                'nome'          => $serie,
                'serie_periodo' => $serie,
                'ativo'         => 1,
            ]);
            $criadas++;
        }

        $this->flash('success', "{$criadas} turma(s) cadastrada(s) com sucesso.");
        $this->redirect('/turmas');
    }
}
