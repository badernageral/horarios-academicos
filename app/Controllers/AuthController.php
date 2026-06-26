<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Usuario;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        // Página standalone (sem o layout/sidebar)
        View::renderPartial('auth/login', [
            'base'  => BASE_PATH,
            'erro'  => $this->getFlash(),
        ]);
    }

    public function login(): void
    {
        $usuario = trim($this->post('usuario', ''));
        $senha   = (string) $this->post('senha', '');

        $u = $usuario !== '' ? Usuario::porUsuario($usuario) : false;

        if (!$u || !(int) $u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
            $this->flash('danger', 'Usuário ou senha inválidos.');
            $this->redirect('/login');
        }

        Auth::login($u);
        $this->redirect('/');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
