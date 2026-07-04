<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstadisticasAccionesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use DateTimeImmutable;
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
        $estadisticas = ['total' => 0, 'porMes' => [], 'porTipo' => []];
        $error = null;

        try {
            $estadisticas = [
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
            'estadisticas' => $estadisticas,
            'error' => $error,
        ]);
    }

    public function anios(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $estadisticas = ['total' => 0, 'porAnio' => [], 'porTipo' => []];
        $error = null;

        try {
            $estadisticas = [
                'total' => $this->model->total($filtros),
                'porAnio' => $this->model->porAnio($filtros),
                'porTipo' => $this->model->porTipo($filtros),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/estadisticas_acciones_anios', [
            'title' => 'Estadísticas de acciones por año',
            'filtros' => $filtros,
            'tiposAccion' => $this->model->tiposAccion(),
            'estadisticas' => $estadisticas,
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

    public function exportarAniosCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = [];

        foreach ($this->model->porAnio($filtros) as $row) {
            $rows[] = [
                $row['anio'] ?? '',
                $row['tipo_accion'] ?? '',
                $row['total'] ?? '',
            ];
        }

        Response::csv('srhn-estadisticas-acciones-anios.csv', ['Año', 'Tipo de acción', 'Total'], $rows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'fecha_desde' => $this->normalizarFecha(trim((string) ($_GET['fecha_desde'] ?? ''))),
            'fecha_hasta' => $this->normalizarFecha(trim((string) ($_GET['fecha_hasta'] ?? ''))),
        ];
    }

    private function normalizarFecha(string $fecha): string
    {
        if ($fecha === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            $date = DateTimeImmutable::createFromFormat('d/m/Y', $fecha);
            return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : '';
        }

        return '';
    }
}
