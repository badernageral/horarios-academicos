<?php

namespace App\Core;

class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $config = require ROOT_PATH . '/config/app.php';

        ob_start();
        require VIEW_PATH . '/' . $template . '.php';
        $content = ob_get_clean();

        require VIEW_PATH . '/layouts/header.php';
        echo $content;
        require VIEW_PATH . '/layouts/footer.php';
    }

    public static function renderPartial(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require VIEW_PATH . '/' . $template . '.php';
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
