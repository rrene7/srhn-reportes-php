<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReportePersonalModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class ReportesController
{
    private ReportePersonalModel $model;

    public function __construct()
    {
        $this->model = new ReportePersonalModel(Database::connect());
    }

    public function index(): void
    {
        View::render('reportes/index', [
            'title' => 'Reportes generales',
            'rangos' => $this->model->listarRangos(),
            'cuarteles' => $this->model->listarCuarteles(),
            'estados' => $this->model->listarEstados(),
            'filtros' => [],
            'error' => null,
        ]);
    }

    public function resultado(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $pagina = $this->paginaDesdeRequest();
        $porPagina = 100;

        try {
            $rowsCompletas = $this->model->buscarPersonal($filtros);
            $total = count($rowsCompletas);
            $totalPaginas = max(1, (int) ceil($total / $porPagina));
            $pagina = min($pagina, $totalPaginas);
            $offset = ($pagina - 1) * $porPagina;
            $rows = array_slice($rowsCompletas, $offset, $porPagina);

            View::render('reportes/resultado', [
                'title' => 'Resultado del reporte',
                'filtros' => $filtros,
                'rows' => $rows,
                'total' => $total,
                'totalesRango' => $this->model->totalesPorCampo($rowsCompletas, 'rango'),
                'totalesCuartel' => $this->model->totalesPorCampo($rowsCompletas, 'cuartel'),
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'totalPaginas' => $totalPaginas,
                'offset' => $offset,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            View::render('reportes/index', [
                'title' => 'Reportes generales',
                'rangos' => $this->model->listarRangos(),
                'cuarteles' => $this->model->listarCuarteles(),
                'estados' => $this->model->listarEstados(),
                'filtros' => $filtros,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = $this->model->buscarPersonal($filtros);

        $headers = [
            'Rango',
            'Rango nombre',
            'N. empleado',
            'Nombre',
            'Apellido',
            'Cuartel',
            'Dependencia',
            'Cédula',
            'Sexo',
            'Posición PN',
            'Posición MI',
            'Fecha ingreso',
            'Fecha ascenso',
            'Fecha traslado',
            'Fecha vacaciones',
            'Estado',
            'Estado nombre',
            'Fecha nacimiento',
            'Tipo policía',
        ];

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['rango'] ?? '',
                $row['rango_nombre'] ?? '',
                $row['nemp'] ?? '',
                $row['nombre'] ?? '',
                $row['apellido'] ?? '',
                $row['cuartel'] ?? '',
                $row['cuartel_nombre'] ?? '',
                $row['cedula'] ?? '',
                $row['sexo'] ?? '',
                $row['posicipn'] ?? '',
                $row['posicimi'] ?? '',
                $row['fecing'] ?? '',
                $row['fecascen'] ?? '',
                $row['fectras'] ?? '',
                $row['fecvac'] ?? '',
                $row['estado'] ?? '',
                $row['estado_nombre'] ?? '',
                $row['fecnac'] ?? '',
                $row['tipopol'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-reporte-personal.csv', $headers, $csvRows);
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'cuartel_desde' => trim((string) ($_GET['cuartel_desde'] ?? '')),
            'cuartel_hasta' => trim((string) ($_GET['cuartel_hasta'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
        ];
    }

    private function paginaDesdeRequest(): int
    {
        $pagina = (int) ($_GET['page'] ?? 1);

        return max(1, $pagina);
    }
}
