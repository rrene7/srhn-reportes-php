<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OperativosModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class OperativosController
{
    private OperativosModel $model;

    public function __construct()
    {
        $this->model = new OperativosModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $data = [
            'total' => 0,
            'porRango' => [],
            'porDependencia' => [],
            'porSexo' => [],
            'porEstatus' => [],
            'porTipoPolicia' => [],
            'listado' => [],
        ];
        $error = null;

        try {
            $data = [
                'total' => $this->model->total($filtros),
                'porRango' => $this->model->porRango($filtros),
                'porDependencia' => $this->model->porDependencia($filtros),
                'porSexo' => $this->model->porSexo($filtros),
                'porEstatus' => $this->model->porEstatus($filtros),
                'porTipoPolicia' => $this->model->porTipoPolicia($filtros),
                'listado' => $this->model->listado($filtros, 300),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/operativos', [
            'title' => 'Operativos',
            'filtros' => $filtros,
            'catalogos' => $this->model->catalogos(),
            'data' => $data,
            'error' => $error,
        ]);
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
                $row['tipo_policia'] ?? '',
                $row['fecha_ingreso'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-operativos.csv', [
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
            'Tipo policía',
            'Fecha ingreso',
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
            'tipo_policia' => trim((string) ($_GET['tipo_policia'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
        ];
    }
}
