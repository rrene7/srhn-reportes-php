<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\VacacionesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class VacacionesController
{
    private VacacionesModel $model;

    public function __construct()
    {
        $this->model = new VacacionesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $data = [
            'resumen' => [
                'total' => 0,
                'con_fecha_vacaciones' => 0,
                'sin_fecha_vacaciones' => 0,
                'mas_de_un_anio' => 0,
                'mas_de_dos_anios' => 0,
            ],
            'porRango' => [],
            'porDependencia' => [],
            'listado' => [],
            'diagnostico' => [],
        ];
        $error = null;

        try {
            $data = [
                'resumen' => $this->model->resumen($filtros),
                'porRango' => $this->model->porRango($filtros),
                'porDependencia' => $this->model->porDependencia($filtros),
                'listado' => $this->model->listado($filtros, 300),
                'diagnostico' => $this->model->diagnostico($filtros),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/vacaciones', [
            'title' => 'Reporte de Vacaciones',
            'filtros' => $filtros,
            'catalogos' => $this->model->catalogos(),
            'data' => $data,
            'error' => $error,
        ]);
    }

    public function diagnostico(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $diagnostico = $this->model->diagnostico($filtros);

        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Diagnóstico de vacaciones</h1>';
        echo '<p><a href="' . e(url('/reportes/vacaciones')) . '">Volver</a></p>';
        echo '<h2>Totales</h2>';
        echo '<pre>' . e(json_encode([
            'total_employee_actions' => $diagnostico['total_employee_actions'] ?? 0,
            'total_action_type_4' => $diagnostico['total_action_type_4'] ?? 0,
            'total_filtrado_actual' => $diagnostico['total_filtrado_actual'] ?? 0,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        echo '<h2>Top tipos de acción</h2>';
        echo '<table border="1" cellpadding="6" cellspacing="0">';
        echo '<tr><th>Tipo</th><th>Nombre</th><th>Total</th><th>Fecha mínima</th><th>Fecha máxima</th></tr>';
        foreach (($diagnostico['top_tipos'] ?? []) as $row) {
            echo '<tr>';
            echo '<td>' . e($row['action_type_id'] ?? '') . '</td>';
            echo '<td>' . e($row['nombre'] ?? '') . '</td>';
            echo '<td>' . e($row['total'] ?? 0) . '</td>';
            echo '<td>' . e($row['fecha_min'] ?? '') . '</td>';
            echo '<td>' . e($row['fecha_max'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = $this->model->listado($filtros, 1000);

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['nemp'] ?? '',
                $row['cedula'] ?? '',
                $row['funcionario'] ?? '',
                $row['rango_codigo'] ?? '',
                $row['rango_nombre'] ?? '',
                $row['unidad_codigo'] ?? '',
                $row['unidad_nombre'] ?? '',
                $row['sexo'] ?? '',
                $row['estado_codigo'] ?? '',
                $row['estado_nombre'] ?? '',
                $row['fecha_ingreso'] ?? '',
                $row['fecha_ultimas_vacaciones'] ?? '',
                $row['dias_desde_vacaciones'] ?? '',
                $row['anios_servicio'] ?? '',
                $row['dias_teoricos_generados'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-reporte-vacaciones.csv', [
            'N. empleado',
            'Cédula',
            'Funcionario',
            'Código rango',
            'Rango',
            'Código dependencia',
            'Dependencia',
            'Sexo',
            'Código estado',
            'Estado',
            'Fecha ingreso',
            'Fecha últimas vacaciones',
            'Días desde vacaciones',
            'Años servicio',
            'Días teóricos generados',
        ], $csvRows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => strtoupper(trim((string) ($_GET['sexo'] ?? 'A'))),
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'activo')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'estado_vacaciones' => trim((string) ($_GET['estado_vacaciones'] ?? 'todos')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
        ];
    }
}
