<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = dirname(__DIR__, 2) . '/views/' . $view . '.php';

        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        require dirname(__DIR__, 2) . '/views/layouts/app.php';
    }
}
