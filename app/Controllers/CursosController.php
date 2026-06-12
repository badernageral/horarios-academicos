<?php

namespace App\Controllers;

use App\Models\Curso;

class CursosController extends BaseController
{
    public function index(): void
    {
        $cursos = Curso::all('nome');
        $flash  = $this->getFlash();
        $this->render('cursos/index', compact('cursos', 'flash'));
    }

    public function novo(): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('cursos/form', ['curso' => null, 'config' => $config, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');
        $diasSemana = $this->post('dias_semana', ['1','2','3','4','5']);
        $data = [
            'nome'                => trim($this->post('nome')),
            'turno_inicio'        => $this->post('turno_inicio', '07:00'),
            'turno_fim'           => $this->post('turno_fim', '12:00'),
            'duracao_aula_minutos'=> (int)$this->post('duracao_aula_minutos', 50),
            'dias_semana'         => json_encode(array_values($diasSemana)),
            'cor'          => $this->post('cor', '#3b82f6'),
            'ativo'        => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Curso::update((int)$id, $data);
            $cursoId = (int)$id;
            $this->flash('success', 'Curso atualizado com sucesso!');
        } else {
            $cursoId = Curso::create($data);
            $this->flash('success', 'Curso cadastrado com sucesso!');
        }

        // Intervalos
        $intervalos = [];
        $iDias  = $this->post('int_dia', []);
        $iInic  = $this->post('int_inicio', []);
        $iFins  = $this->post('int_fim', []);
        $iDesc  = $this->post('int_desc', []);
        foreach ($iDias as $k => $dia) {
            if (!empty($iInic[$k]) && !empty($iFins[$k])) {
                $intervalos[] = [
                    'dia_semana'  => ($dia !== '' ? (int)$dia : ''),
                    'hora_inicio' => $iInic[$k],
                    'hora_fim'    => $iFins[$k],
                    'descricao'   => $iDesc[$k] ?? 'Intervalo',
                ];
            }
        }
        Curso::salvarIntervalos($cursoId, $intervalos);

        $this->redirect('/cursos');
    }

    public function editar(string $id): void
    {
        $curso = Curso::findComIntervalos((int)$id);
        if (!$curso) {
            $this->redirect('/cursos');
        }
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('cursos/form', ['curso' => $curso, 'config' => $config, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Curso::delete($id);
            $this->flash('success', 'Curso removido.');
        }
        $this->redirect('/cursos');
    }
}
