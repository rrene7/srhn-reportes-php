<?php

declare(strict_types=1);

use App\Controllers\ReportesController;
use App\Support\Env;

session_start();

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');

$app = require BASE_PATH . '/config/app.php';

function url(string $path = ''): string
{
    $app = require BASE_PATH . '/config/app.php';
    $base = $app['base_path'] ?? '';

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = (string) ($app['base_path'] ?? '');

if ($basePath !== '' && str_starts_with($uriPath, $basePath)) {
    $uriPath = substr($uriPath, strlen($basePath));
}

$path = '/' . trim($uriPath, '/');
$path = $path === '/' ? '/' : $path;

try {
    $controller = new ReportesController();

    match ($path) {
        '/', '/reportes' => $controller->index(),
        '/reportes/por-rango' => $controller->porRango(),
        '/reportes/por-dependencia' => $controller->porDependencia(),
        '/reportes/acciones' => $controller->acciones(),
        '/reportes/consulta-funcionario' => $controller->consultaFuncionario(),
        '/reportes/resultado' => $controller->resultado(),
        '/reportes/exportar-csv' => $controller->exportarCsv(),
        default => notFound(),
    };
} catch (Throwable $e) {
    http_response_code(500);
    $debug = filter_var($app['debug'] ?? false, FILTER_VALIDATE_BOOL);

    echo '<h1>Error interno</h1>';

    if ($debug) {
        echo '<pre>' . e($e->getMessage()) . '</pre>';
    }
}

function notFound(): void
{
    http_response_code(404);
    echo '<h1>404</h1>';
    echo '<p>Ruta no encontrada.</p>';
    echo '<p><a href="' . e(url('/reportes')) . '">Volver a reportes</a></p>';
}
