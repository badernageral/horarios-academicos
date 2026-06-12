<?php

namespace App\Controllers;

use App\Core\View;

abstract class BaseController
{
    protected function render(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        View::json($data, $status);
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function flash(string $type, string $message): void
    {
        if (!isset($_SESSION)) session_start();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    protected function getFlash(): ?array
    {
        if (!isset($_SESSION)) session_start();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
