<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstadisticasPersonalModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class EstadisticasPersonalController
{
    private EstadisticasPersonalModel $model;

    public function __construct()
    {
        $this->model = new EstadisticasPersonalModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $catalogos = $this->model->catalogos();
        $data = [
            'total' => 0,
            'porRango' => [],
            'porDependencia' => [],
            'porSexo' => [],
            'porEstatus' => [],
        ];
        $error = null;

        try {
            $data = [
                'total' => $this->model->total($filtros),
                'porRango' => $this->model->porRango($filtros),
                'porDependencia' => $this->model->porDependencia($filtros),
                'porSexo' => $this->model->porSexo($filtros),
                'porEstatus' => $this->model->porEstatus($filtros),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/estado_fuerza', [
            'title' => 'Estado de Fuerza',
            'filtros' => $filtros,
            'catalogos' => $catalogos,
            'data' => $data,
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = [];

        foreach ($this->model->porRango($filtros) as $row) {
            $rows[] = ['Por rango', $row['codigo'] ?? '', $row['nombre'] ?? '', $row['total'] ?? ''];
        }
        foreach ($this->model->porDependencia($filtros) as $row) {
            $rows[] = ['Por dependencia', $row['codigo'] ?? '', $row['nombre'] ?? '', $row['total'] ?? ''];
        }
        foreach ($this->model->porSexo($filtros) as $row) {
            $rows[] = ['Por sexo', $row['codigo'] ?? '', $row['nombre'] ?? '', $row['total'] ?? ''];
        }
        foreach ($this->model->porEstatus($filtros) as $row) {
            $rows[] = ['Por estatus', $row['codigo'] ?? '', $row['nombre'] ?? '', $row['total'] ?? ''];
        }

        Response::csv('srhn-estado-fuerza.csv', ['Grupo', 'Código', 'Descripción', 'Total'], $rows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => trim((string) ($_GET['sexo'] ?? 'A')),
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'activo')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];
    }
}
