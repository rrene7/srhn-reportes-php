<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstudiosGeneralesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class EstudiosGeneralesController
{
    private EstudiosGeneralesModel $model;

    public function __construct()
    {
        $this->model = new EstudiosGeneralesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = [];
        $total = null;
        $resumen = ['total' => 0, 'por_nivel' => [], 'por_estado' => []];
        $metadata = $this->model->metadata();
        $error = null;

        if ($this->debeGenerar()) {
            try {
                $rows = $this->model->buscar($filtros, 500);
                $total = $this->model->contar($filtros);
                $resumen = $this->model->resumen($rows);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('reportes/estudios_generales', [
            'title' => 'Estudios generales',
            'filtros' => $filtros,
            'rows' => $rows,
            'total' => $total,
            'resumen' => $resumen,
            'metadata' => $metadata,
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = $this->model->buscar($filtros, 2000);

        $headers = [
            'N. empleado',
            'Cédula',
            'Funcionario',
            'Rango actual',
            'Dependencia actual',
            'Estudio',
            'Nivel',
            'Institución',
            'Fecha estudio',
            'Estado estudio',
            'Observación',
        ];

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['nemp'] ?? '',
                $row['cedula'] ?? '',
                $row['funcionario'] ?? '',
                $row['rango_actual'] ?? '',
                $row['dependencia_actual'] ?? '',
                $row['estudio'] ?? '',
                $row['nivel'] ?? '',
                $row['institucion'] ?? '',
                $row['fecha_estudio'] ?? '',
                $row['estado_estudio'] ?? '',
                $row['observacion'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-estudios-generales.csv', $headers, $csvRows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'estudio' => trim((string) ($_GET['estudio'] ?? '')),
            'institucion' => trim((string) ($_GET['institucion'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];
    }

    private function debeGenerar(): bool
    {
        return isset($_GET['generar']) || count($_GET) > 0;
    }
}
