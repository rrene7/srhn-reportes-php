<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OpcionesMultiplesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class OpcionesMultiplesController
{
    private OpcionesMultiplesModel $model;

    public function __construct()
    {
        $this->model = new OpcionesMultiplesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = [];
        $total = null;
        $resumen = ['total' => 0, 'masculino' => 0, 'femenino' => 0, 'activos' => 0, 'otros_estados' => 0];
        $resumenEstados = [];
        $error = null;

        if ($this->debeGenerar()) {
            try {
                $rows = $this->model->buscar($filtros, 500);
                $total = $this->model->contar($filtros);
                $resumen = $this->model->resumen($rows);
                $resumenEstados = $this->model->resumenPorEstatus($filtros);

                if ((int) $total === 0 && strtolower((string) ($filtros['tipo_policia'] ?? 'todos')) !== 'todos') {
                    $tipo = strtoupper((string) ($filtros['tipo_policia'] ?? ''));
                    $error = 'Sin resultados con la clasificación ' . $tipo . '. Mantenga los demás filtros y pruebe la clasificación Todos para confirmar si el resto de filtros sí trae datos.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('reportes/opciones_multiples', [
            'title' => 'Reporte Opciones Múltiples',
            'catalogos' => $this->model->catalogos(),
            'filtros' => $filtros,
            'rows' => $rows,
            'total' => $total,
            'resumen' => $resumen,
            'resumenEstados' => $resumenEstados,
            'columnas' => $this->model->columnas($filtros['campos'] ?? []),
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = $this->model->buscar($filtros, 2000);
        $columnas = $this->model->columnas($filtros['campos'] ?? []);

        $headers = array_values($columnas);
        $csvRows = [];

        foreach ($rows as $row) {
            $csvRow = [];
            foreach (array_keys($columnas) as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            $csvRows[] = $csvRow;
        }

        Response::csv('srhn-reporte-opciones-multiples.csv', $headers, $csvRows);
    }

    private function filtrosDesdeRequest(): array
    {
        $campos = $_GET['campos'] ?? [];
        if (!is_array($campos)) {
            $campos = [];
        }

        $campos = array_values(array_filter(array_map('strval', $campos)));
        $campos = array_slice($campos, 0, 4);

        return [
            'reporte_por' => trim((string) ($_GET['reporte_por'] ?? 'ambos')),
            'rango_inicial' => trim((string) ($_GET['rango_inicial'] ?? '')),
            'rango_final' => trim((string) ($_GET['rango_final'] ?? '')),
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => trim((string) ($_GET['sexo'] ?? 'A')),
            'tipo_policia' => trim((string) ($_GET['tipo_policia'] ?? 'todos')),
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'todos')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'fecha_modo' => trim((string) ($_GET['fecha_modo'] ?? 'actual')),
            'fecha_corte' => trim((string) ($_GET['fecha_corte'] ?? '')),
            'ts_min' => trim((string) ($_GET['ts_min'] ?? '')),
            'ts_max' => trim((string) ($_GET['ts_max'] ?? '')),
            'tr_min' => trim((string) ($_GET['tr_min'] ?? '')),
            'tr_max' => trim((string) ($_GET['tr_max'] ?? '')),
            'ordenar_por' => trim((string) ($_GET['ordenar_por'] ?? 'rango')),
            'tipo_papel' => trim((string) ($_GET['tipo_papel'] ?? 'carta')),
            'clasificacion' => trim((string) ($_GET['clasificacion'] ?? 'ambos')),
            'sede' => trim((string) ($_GET['sede'] ?? '')),
            'sectorizacion' => trim((string) ($_GET['sectorizacion'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'campos' => $campos,
        ];
    }

    private function debeGenerar(): bool
    {
        return isset($_GET['generar']) || isset($_GET['exportar']) || count($_GET) > 0;
    }
}
