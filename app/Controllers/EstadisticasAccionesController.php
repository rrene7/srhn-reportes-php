<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstadisticasAccionesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class EstadisticasAccionesController
{
    private EstadisticasAccionesModel $model;

    public function __construct()
    {
        $this->model = new EstadisticasAccionesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $data = ['total' => 0, 'porMes' => [], 'porTipo' => []];
        $error = null;

        try {
            $data = [
                'total' => $this->model->total($filtros),
                'porMes' => $this->model->porMes($filtros),
                'porTipo' => $this->model->porTipo($filtros),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/estadisticas_acciones', [
            'title' => 'Estadísticas de acciones por mes',
            'filtros' => $filtros,
            'tiposAccion' => $this->model->tiposAccion(),
            'data' => $data,
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = [];

        foreach ($this->model->porMes($filtros) as $row) {
            $rows[] = [
                $row['anio'] ?? '',
                $row['mes_numero'] ?? '',
                $row['tipo_accion'] ?? '',
                $row['total'] ?? '',
            ];
        }

        Response::csv('srhn-estadisticas-acciones-mes.csv', ['Año', 'Mes', 'Tipo de acción', 'Total'], $rows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];
    }
}
