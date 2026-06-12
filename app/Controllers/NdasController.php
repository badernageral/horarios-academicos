<?php

namespace App\Controllers;

use App\Models\Nda;

class NdasController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome', 'ativo'], 'nome');
        $ndas  = Nda::all($sort, strtoupper($dir));
        $flash = $this->getFlash();
        $this->render('ndas/index', compact('ndas', 'flash', 'sort', 'dir'));
    }

    public function novo(): void
    {
        $this->render('ndas/form', ['nda' => null, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id   = $this->post('id');
        $data = [
            'nome'  => trim($this->post('nome')),
            'ativo' => (int)$this->post('ativo', 1),
        ];

        if ($id) {
            Nda::update((int)$id, $data);
            $this->flash('success', 'NDA atualizado.');
        } else {
            Nda::create($data);
            $this->flash('success', 'NDA cadastrado.');
        }
        $this->redirect('/ndas');
    }

    public function editar(string $id): void
    {
        $nda = Nda::find((int)$id);
        if (!$nda) $this->redirect('/ndas');
        $this->render('ndas/form', ['nda' => $nda, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Nda::delete($id);
            $this->flash('success', 'NDA removido.');
        }
        $this->redirect('/ndas');
    }
}
