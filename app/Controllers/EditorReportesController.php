<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EditorReportesModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class EditorReportesController
{
    private EditorReportesModel $model;

    public function __construct()
    {
        $this->model = new EditorReportesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $resultado = null;
        $error = null;

        if (($filtros['generar'] ?? '') === '1') {
            try {
                $resultado = $this->model->construir($filtros, 300);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('reportes/editor_reportes', [
            'title' => 'Editor de reportes',
            'filtros' => $filtros,
            'fuentes' => $this->model->fuentes(),
            'fuenteActualCodigo' => $this->model->codigoFuenteActual($filtros),
            'fuenteActual' => $this->model->fuenteActual($filtros),
            'columnasSeleccionadas' => $this->model->columnasSeleccionadas($this->model->fuenteActual($filtros), $filtros['columnas'] ?? []),
            'catalogos' => $this->model->catalogos(),
            'resultado' => $resultado,
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $resultado = $this->model->construir($filtros, 1000);
        $rows = [];

        foreach (($resultado['rows'] ?? []) as $row) {
            $csvRow = [];
            foreach (($resultado['columnas'] ?? []) as $columna) {
                $csvRow[] = $row[$columna] ?? '';
            }
            $rows[] = $csvRow;
        }

        Response::csv('srhn-editor-reportes.csv', $resultado['headers'] ?? [], $rows);
    }

    private function filtrosDesdeRequest(): array
    {
        $columnas = $_GET['columnas'] ?? [];
        if (!is_array($columnas)) {
            $columnas = [(string) $columnas];
        }

        return [
            'fuente' => trim((string) ($_GET['fuente'] ?? 'personal')),
            'columnas' => array_values(array_filter(array_map('strval', $columnas))),
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => strtoupper(trim((string) ($_GET['sexo'] ?? 'A'))),
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'activo')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'tipo_accion' => trim((string) ($_GET['tipo_accion'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'generar' => trim((string) ($_GET['generar'] ?? '')),
        ];
    }
}
