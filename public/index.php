<?php

declare(strict_types=1);

use App\Controllers\EditorReportesController;
use App\Controllers\EstadisticasAccionesController;
use App\Controllers\EstadisticasPersonalController;
use App\Controllers\EstudiosGeneralesController;
use App\Controllers\HojaVidaPlacaController;
use App\Controllers\OperativosController;
use App\Controllers\OpcionesMultiplesController;
use App\Controllers\ReportesController;
use App\Controllers\VacacionesController;
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
    $opcionesMultiplesController = new OpcionesMultiplesController();
    $estudiosGeneralesController = new EstudiosGeneralesController();
    $estadisticasPersonalController = new EstadisticasPersonalController();
    $estadisticasAccionesController = new EstadisticasAccionesController();
    $hojaVidaController = new HojaVidaPlacaController();
    $operativosController = new OperativosController();
    $editorReportesController = new EditorReportesController();
    $vacacionesController = new VacacionesController();

    match ($path) {
        '/', '/reportes' => $controller->index(),
        '/reportes/por-rango' => $controller->porRango(),
        '/reportes/por-dependencia' => $controller->porDependencia(),
        '/reportes/estudios-generales' => $estudiosGeneralesController->index(),
        '/reportes/estudios-generales/exportar-csv' => $estudiosGeneralesController->exportarCsv(),
        '/reportes/estado-fuerza' => $estadisticasPersonalController->index(),
        '/reportes/estado-fuerza/exportar-csv' => $estadisticasPersonalController->exportarCsv(),
        '/reportes/estadisticas-acciones' => $estadisticasAccionesController->index(),
        '/reportes/estadisticas-acciones/exportar-csv' => $estadisticasAccionesController->exportarCsv(),
        '/reportes/editor' => $editorReportesController->index(),
        '/reportes/editor/exportar-csv' => $editorReportesController->exportarCsv(),
        '/reportes/opciones-multiples' => $opcionesMultiplesController->index(),
        '/reportes/opciones-multiples/exportar-csv' => $opcionesMultiplesController->exportarCsv(),
        '/reportes/operativos' => $operativosController->index(),
        '/reportes/operativos/exportar-csv' => $operativosController->exportarCsv(),
        '/reportes/vacaciones' => $vacacionesController->index(),
        '/reportes/vacaciones/diagnostico' => $vacacionesController->diagnostico(),
        '/reportes/vacaciones/exportar-csv' => $vacacionesController->exportarCsv(),
        '/reportes/hoja-vida' => $hojaVidaController->index(),
        '/reportes/hoja-vida/exportar-csv' => $hojaVidaController->exportarCsv(),
        '/reportes/procedencia-oficiales' => $controller->procedenciaOficiales(),
        '/reportes/procedencia-oficiales/exportar-csv' => $controller->exportarProcedenciaOficialesCsv(),
        '/reportes/acciones' => $controller->acciones(),
        '/reportes/acciones/resultado' => $controller->accionesResultado(),
        '/reportes/acciones/exportar-csv' => $controller->exportarAccionesCsv(),
        '/reportes/consulta-funcionario' => $controller->consultaFuncionario(),
        '/reportes/consulta-funcionario/resultado' => $controller->consultaResultado(),
        '/reportes/funcionario' => $controller->fichaFuncionario(),
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
