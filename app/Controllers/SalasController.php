<?php

namespace App\Controllers;

use App\Models\Sala;

class SalasController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome', 'ativo'], 'nome');
        $salas = Sala::all($sort, strtoupper($dir));
        $flash = $this->getFlash();
        $this->render('salas/index', compact('salas', 'flash', 'sort', 'dir'));
    }

    public function nova(): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        $this->render('salas/form', ['sala' => null, 'config' => $config, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');
        $data = [
            'nome'  => trim($this->post('nome')),
            'ativo' => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Sala::update((int)$id, $data);
            $this->flash('success', 'Sala atualizada!');
        } else {
            Sala::create($data);
            $this->flash('success', 'Sala cadastrada!');
        }
        $this->redirect('/salas');
    }

    public function editar(string $id): void
    {
        $sala   = Sala::find((int)$id);
        $config = require ROOT_PATH . '/config/app.php';
        if (!$sala) $this->redirect('/salas');
        $this->render('salas/form', ['sala' => $sala, 'config' => $config, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Sala::delete($id);
            $this->flash('success', 'Sala removida.');
        }
        $this->redirect('/salas');
    }
}
