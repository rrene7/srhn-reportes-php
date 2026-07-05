<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EditorReportesModel;
use App\Models\ReportTemplateModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class EditorReportesController
{
    private EditorReportesModel $model;
    private ReportTemplateModel $templates;

    public function __construct()
    {
        $db = Database::connect();
        $this->model = new EditorReportesModel($db);
        $this->templates = new ReportTemplateModel($db);
    }

    public function index(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $resultado = null;
        $error = null;
        $mensaje = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['accion'] ?? '') === 'guardar_plantilla') {
            try {
                $nombre = trim((string) ($_POST['template_name'] ?? ''));
                if ($nombre === '') {
                    throw new \RuntimeException('Debe escribir un nombre para guardar la plantilla.');
                }

                $this->templates->guardar(
                    $nombre,
                    $this->model->codigoFuenteActual($filtros),
                    $this->model->columnasSeleccionadas($this->model->fuenteActual($filtros), $filtros['columnas'] ?? []),
                    $filtros,
                    trim((string) ($_POST['query_string'] ?? ''))
                );
                $mensaje = 'Plantilla guardada correctamente.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

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
            'plantillas' => $this->templates->listar(),
            'tablaPlantillasExiste' => $this->templates->existeTabla(),
            'mensaje' => $mensaje,
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
        $origen = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $columnas = $origen['columnas'] ?? [];
        if (!is_array($columnas)) {
            $columnasTexto = trim((string) $columnas);
            $columnas = $columnasTexto !== '' ? explode(',', $columnasTexto) : [];
        }

        return [
            'fuente' => trim((string) ($origen['fuente'] ?? 'personal')),
            'columnas' => array_values(array_filter(array_map(static fn (string $columna): string => trim($columna), array_map('strval', $columnas)))),
            'rango_desde' => trim((string) ($origen['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($origen['rango_hasta'] ?? '')),
            'unidad' => trim((string) ($origen['unidad'] ?? '')),
            'sexo' => strtoupper(trim((string) ($origen['sexo'] ?? 'A'))),
            'estado_modo' => trim((string) ($origen['estado_modo'] ?? 'activo')),
            'estado' => trim((string) ($origen['estado'] ?? '')),
            'tipo_accion' => trim((string) ($origen['tipo_accion'] ?? '')),
            'fecha_desde' => trim((string) ($origen['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($origen['fecha_hasta'] ?? '')),
            'buscar' => trim((string) ($origen['buscar'] ?? '')),
            'generar' => trim((string) ($origen['generar'] ?? '')),
        ];
    }
}
