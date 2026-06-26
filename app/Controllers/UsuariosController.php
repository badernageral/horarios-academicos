<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Usuario;

class UsuariosController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome', 'usuario', 'ativo'], 'nome');
        $usuarios = Usuario::all($sort, strtoupper($dir));
        $flash    = $this->getFlash();
        $this->render('usuarios/index', compact('usuarios', 'flash', 'sort', 'dir'));
    }

    public function novo(): void
    {
        $this->render('usuarios/form', ['usuario' => null, 'flash' => null]);
    }

    public function editar(string $id): void
    {
        $usuario = Usuario::find((int) $id);
        if (!$usuario) $this->redirect('/usuarios');
        $this->render('usuarios/form', ['usuario' => $usuario, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id      = $this->post('id');
        $nome    = trim($this->post('nome', ''));
        $login   = trim($this->post('usuario', ''));
        $senha   = (string) $this->post('senha', '');
        $ativo   = (int) $this->post('ativo', 1);

        if ($nome === '' || $login === '') {
            $this->flash('danger', 'Nome e usuário são obrigatórios.');
            $this->redirect($id ? "/usuarios/{$id}/editar" : '/usuarios/novo');
        }

        // Usuário (login) único
        $existente = Usuario::porUsuario($login);
        if ($existente && (int) $existente['id'] !== (int) $id) {
            $this->flash('danger', "O usuário \"{$login}\" já está em uso.");
            $this->redirect($id ? "/usuarios/{$id}/editar" : '/usuarios/novo');
        }

        $data = [
            'nome'    => $nome,
            'usuario' => $login,
            'ativo'   => $ativo ? 1 : 0,
        ];

        if ($id) {
            // Não permitir desativar a si próprio
            if ((int) $id === Auth::id() && !$ativo) {
                $this->flash('danger', 'Você não pode desativar o próprio usuário.');
                $this->redirect("/usuarios/{$id}/editar");
            }
            if ($senha !== '') {
                $data['senha_hash'] = Usuario::hash($senha);
            }
            Usuario::update((int) $id, $data);
            $this->flash('success', 'Usuário atualizado.');
        } else {
            if ($senha === '') {
                $this->flash('danger', 'A senha é obrigatória para novos usuários.');
                $this->redirect('/usuarios/novo');
            }
            $data['senha_hash'] = Usuario::hash($senha);
            Usuario::create($data);
            $this->flash('success', 'Usuário cadastrado.');
        }

        $this->redirect('/usuarios');
    }

    public function deletar(): void
    {
        $id = (int) $this->post('id');

        if ($id === Auth::id()) {
            $this->flash('danger', 'Você não pode excluir o próprio usuário.');
            $this->redirect('/usuarios');
        }
        if (Usuario::count() <= 1) {
            $this->flash('danger', 'Deve existir ao menos um usuário no sistema.');
            $this->redirect('/usuarios');
        }
        if ($id) {
            Usuario::delete($id);
            $this->flash('success', 'Usuário removido.');
        }
        $this->redirect('/usuarios');
    }
}
