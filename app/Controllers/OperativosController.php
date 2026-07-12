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
        // Vista temporal de consulta sobre la base nueva de RRHH.
        // El resto del sistema conserva la conexión principal DB_NAME.
        $this->model = new OperativosModel(Database::connect('rrhh'));
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $data = [
            'total' => 0,
            'resumenOperatividad' => [],
            'porRango' => [],
            'porDependencia' => [],
            'porSexo' => [],
            'porEstatus' => [],
            'listado' => [],
        ];
        $error = null;

        try {
            $data = [
                'total' => $this->model->total($filtros),
                'resumenOperatividad' => $this->model->resumenOperatividad($filtros),
                'porRango' => $this->model->porRango($filtros),
                'porDependencia' => $this->model->porDependencia($filtros),
                'porSexo' => $this->model->porSexo($filtros),
                'porEstatus' => $this->model->porEstatus($filtros),
                'listado' => $this->model->listado($filtros, 300),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/operativos', [
            'title' => 'Operatividad policial',
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
                $row['operatividad_tipo'] ?? '',
                $row['operatividad_motivo'] ?? '',
                $row['operatividad_referencia'] ?? '',
                $row['operatividad_fecha_efectiva'] ?? '',
                $row['operatividad_notas'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-operatividad-policial.csv', [
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
            'Operatividad',
            'Motivo',
            'Referencia',
            'Fecha efectiva',
            'Notas',
        ], $csvRows);
    }

    private function filtrosDesdeRequest(): array
    {
        $operatividad = strtoupper(trim((string) ($_GET['operatividad'] ?? $_GET['tipo_policia'] ?? '')));

        return [
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => strtoupper(trim((string) ($_GET['sexo'] ?? 'A'))),
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'activo')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'operatividad' => $operatividad,
            'motivo' => trim((string) ($_GET['motivo'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
        ];
    }
}
