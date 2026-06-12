<?php

namespace App\Controllers;

use App\Models\Professor;
use App\Core\Database;

class ProfessoresController extends BaseController
{
    public function index(): void
    {
        $professores = Professor::all('nome');
        $flash = $this->getFlash();
        $this->render('professores/index', compact('professores', 'flash'));
    }

    public function novo(): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('professores/form', ['professor' => null, 'config' => $config, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');

        // Gerar matrícula automática se não existir
        $matricula = $id
            ? (Professor::find((int)$id)['matricula'] ?? null)
            : null;
        if (!$matricula) {
            $matricula = 'P' . str_pad((int)Database::fetchValue("SELECT COALESCE(MAX(id),0)+1 FROM professores") , 4, '0', STR_PAD_LEFT);
        }

        $cor = trim($this->post('cor', '#3b82f6'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#3b82f6';

        $data = [
            'nome'     => trim($this->post('nome')),
            'matricula'=> $matricula,
            'ativo'    => $this->post('ativo', 1),
            'cor'      => $cor,
        ];

        if ($id) {
            Professor::update((int)$id, $data);
            $profId = (int)$id;
            $this->flash('success', 'Professor atualizado com sucesso!');
        } else {
            $profId = Professor::create($data);
            $this->flash('success', 'Professor cadastrado com sucesso!');
        }

        // Disponibilidade
        $disponibilidades = [];
        $dias = $this->post('disp_dia', []);
        $inic = $this->post('disp_inicio', []);
        $fins = $this->post('disp_fim', []);
        foreach ($dias as $k => $dia) {
            if (!empty($dia) && !empty($inic[$k]) && !empty($fins[$k])) {
                $disponibilidades[] = [
                    'dia_semana'  => (int)$dia,
                    'hora_inicio' => $inic[$k],
                    'hora_fim'    => $fins[$k],
                ];
            }
        }
        Professor::salvarDisponibilidade($profId, $disponibilidades);

        $this->redirect('/professores');
    }

    public function editar(string $id): void
    {
        $professor = Professor::find((int)$id);
        if (!$professor) {
            $this->redirect('/professores');
        }
        $professor['disponibilidade'] = Professor::disponibilidade((int)$id);
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('professores/form', ['professor' => $professor, 'config' => $config, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Professor::delete($id);
            $this->flash('success', 'Professor removido.');
        }
        $this->redirect('/professores');
    }
}
