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

        // O bootstrap (index.php) já aplicou o que havia; aqui só exibimos.
        $avisoMigracao = $_SESSION['sga_migracao'] ?? null;
        unset($_SESSION['sga_migracao']);

        // Página standalone (sem o layout/sidebar)
        View::renderPartial('auth/login', [
            'base'          => BASE_PATH,
            'erro'          => $this->getFlash(),
            'avisoMigracao' => $avisoMigracao,
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    // ── Primeiro acesso: cadastro do 1º usuário (só quando não há nenhum) ──
    public function setupForm(): void
    {
        if (Usuario::count() > 0) {
            $this->redirect('/login');
        }
        View::renderPartial('auth/setup', [
            'base'  => BASE_PATH,
            'erro'  => $this->getFlash(),
        ]);
    }

    public function setup(): void
    {
        // Só permitido enquanto o sistema estiver sem usuários.
        if (Usuario::count() > 0) {
            $this->redirect('/login');
        }

        $nome     = trim($this->post('nome', ''));
        $login    = trim($this->post('usuario', ''));
        $senha    = (string) $this->post('senha', '');
        $confirma = (string) $this->post('senha_confirma', '');

        if ($nome === '' || $login === '' || $senha === '') {
            $this->flash('danger', 'Preencha nome, usuário e senha.');
            $this->redirect('/setup');
        }
        if (strlen($senha) < 4) {
            $this->flash('danger', 'A senha deve ter ao menos 4 caracteres.');
            $this->redirect('/setup');
        }
        if ($senha !== $confirma) {
            $this->flash('danger', 'A confirmação de senha não confere.');
            $this->redirect('/setup');
        }

        $id = Usuario::create([
            'nome'       => $nome,
            'usuario'    => $login,
            'senha_hash' => Usuario::hash($senha),
            'ativo'      => 1,
        ]);

        Auth::login(Usuario::find($id));
        $this->flash('success', 'Usuário criado. Bem-vindo!');
        $this->redirect('/');
    }
}
