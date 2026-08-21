<?php

namespace App\Controllers;

use App\Models\{Turma, Curso, Turno};

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
        $turnos = Turno::todos();

        // Turma nova nasce liberada em tudo (comportamento anterior a esta tela).
        $gradeDisp = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys($turnos) as $chave) {
                $gradeDisp[$dia][$chave] = 1;
            }
        }

        $this->render('turmas/form', [
            'turma' => null, 'cursos' => $cursos, 'flash' => null,
            'turnos' => $turnos, 'gradeDisp' => $gradeDisp,
        ]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');
        $seriePeriodo = trim($this->post('serie_periodo'));
        $data = [
            'curso_id'     => (int)$this->post('curso_id'),
            'nome'         => $seriePeriodo,
            'serie_periodo'=> $seriePeriodo,
            'ativo'        => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Turma::update((int)$id, $data);
            $turmaId = (int)$id;
            $this->flash('success', 'Turma atualizada com sucesso!');
        } else {
            $turmaId = Turma::create($data);
            $this->flash('success', 'Turma cadastrada com sucesso!');
        }

        // Lacunas: só os turnos marcados viram linha; bloqueado é a ausência.
        $grade = $this->post('disp', []);
        $liberados = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys(Turno::todos()) as $chave) {
                if ((int)($grade[$dia][$chave] ?? 0) === 1) {
                    $liberados[] = ['dia_semana' => $dia, 'turno' => $chave];
                }
            }
        }
        Turma::salvarDisponibilidade($turmaId, $liberados);

        $this->redirect('/turmas');
    }

    public function editar(string $id): void
    {
        $turma  = Turma::find((int)$id);
        $cursos = Curso::allAtivos();
        if (!$turma) $this->redirect('/turmas');
        $turnos = Turno::todos();
        $this->render('turmas/form', [
            'turma' => $turma, 'cursos' => $cursos, 'flash' => null,
            'turnos' => $turnos,
            'gradeDisp' => Turma::gradeDisponibilidade((int)$id, $turnos),
        ]);
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
        $turnosImport = Turno::todos();

        foreach ($linhas as $serie) {
            $serie = trim($serie, " \t\r\n");
            if ($serie === '') continue;

            $novaId = Turma::create([
                'curso_id'      => $cursoId,
                'nome'          => $serie,
                'serie_periodo' => $serie,
                'ativo'         => 1,
            ]);

            // Turma importada nasce liberada em todos os turnos; as lacunas são
            // ajustadas depois, na edição.
            $liberados = [];
            foreach ([1, 2, 3, 4, 5] as $dia) {
                foreach (array_keys($turnosImport) as $chave) {
                    $liberados[] = ['dia_semana' => $dia, 'turno' => $chave];
                }
            }
            Turma::salvarDisponibilidade($novaId, $liberados);

            $criadas++;
        }

        $this->flash('success', "{$criadas} turma(s) cadastrada(s) com sucesso.");
        $this->redirect('/turmas');
    }
}
