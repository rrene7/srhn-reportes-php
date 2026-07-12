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
        // Este módulo consulta temporalmente la base nueva de RRHH.
        // La base principal del proyecto continúa definida por DB_NAME.
        $this->model = new OpcionesMultiplesModel(Database::connect('rrhh'));
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
                    $error = 'No se encontraron resultados con la clasificación operativa ' . $tipo . '. Mantenga los demás filtros y pruebe la opción Todas.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('reportes/opciones_multiples_v2', [
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

        [$rangoInicial, $rangoFinal] = $this->ordenarLimites(
            trim((string) ($_GET['rango_inicial'] ?? '')),
            trim((string) ($_GET['rango_final'] ?? ''))
        );
        [$tsMin, $tsMax] = $this->ordenarLimites(
            trim((string) ($_GET['ts_min'] ?? '')),
            trim((string) ($_GET['ts_max'] ?? ''))
        );
        [$trMin, $trMax] = $this->ordenarLimites(
            trim((string) ($_GET['tr_min'] ?? '')),
            trim((string) ($_GET['tr_max'] ?? ''))
        );

        $tipoPolicia = strtoupper(trim((string) ($_GET['tipo_policia'] ?? 'TODOS')));
        if ($tipoPolicia === '') {
            $tipoPolicia = 'TODOS';
        }

        return [
            'reporte_por' => trim((string) ($_GET['reporte_por'] ?? 'ambos')),
            'rango_inicial' => $rangoInicial,
            'rango_final' => $rangoFinal,
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'sexo' => strtoupper(trim((string) ($_GET['sexo'] ?? 'A'))),
            'tipo_policia' => strtolower($tipoPolicia) === 'todos' ? 'todos' : $tipoPolicia,
            'estado_modo' => trim((string) ($_GET['estado_modo'] ?? 'todos')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'fecha_modo' => trim((string) ($_GET['fecha_modo'] ?? 'actual')),
            'fecha_corte' => trim((string) ($_GET['fecha_corte'] ?? '')),
            'ts_min' => $tsMin,
            'ts_max' => $tsMax,
            'tr_min' => $trMin,
            'tr_max' => $trMax,
            'ordenar_por' => trim((string) ($_GET['ordenar_por'] ?? 'rango')),
            'tipo_papel' => trim((string) ($_GET['tipo_papel'] ?? 'carta')),
            'clasificacion' => trim((string) ($_GET['clasificacion'] ?? 'ambos')),
            'sede' => trim((string) ($_GET['sede'] ?? '')),
            'sectorizacion' => trim((string) ($_GET['sectorizacion'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'campos' => $campos,
        ];
    }

    private function ordenarLimites(string $desde, string $hasta): array
    {
        if ($desde !== '' && $hasta !== '' && is_numeric($desde) && is_numeric($hasta) && (float) $desde > (float) $hasta) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function debeGenerar(): bool
    {
        return isset($_GET['generar']) || isset($_GET['exportar']) || count($_GET) > 0;
    }
}
