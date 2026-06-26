<?php

namespace App\Core;

/**
 * Autenticação por sessão. Perfil único com acesso total ao sistema.
 * A sessão já é iniciada em index.php (session_start()).
 */
class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function login(array $u): void
    {
        // Regenera o id da sessão para evitar fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        $_SESSION['user']    = [
            'id'      => (int) $u['id'],
            'nome'    => $u['nome'],
            'usuario' => $u['usuario'],
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user']);
    }
}
