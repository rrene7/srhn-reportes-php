<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MapaDatosModel;
use App\Support\Database;
use App\Support\View;
use Throwable;

final class MapaDatosController
{
    private MapaDatosModel $model;

    public function __construct()
    {
        $this->model = new MapaDatosModel(Database::connect());
    }

    public function index(): void
    {
        $errores = [];

        $data = [
            'resumen' => $this->seguro('resumenGeneral', static fn (MapaDatosModel $m): array => $m->resumenGeneral(), [], $errores),
            'zonas' => $this->seguro('zonas', static fn (MapaDatosModel $m): array => $m->zonas(), [], $errores),
            'areas' => $this->seguro('areas', static fn (MapaDatosModel $m): array => $m->areas(), [], $errores),
            'dependencias' => $this->seguro('dependencias', static fn (MapaDatosModel $m): array => $m->dependencias(), [], $errores),
            'estadosPersonal' => $this->seguro('personalPorEstado', static fn (MapaDatosModel $m): array => $m->personalPorEstado(), [], $errores),
            'rangos' => $this->seguro('personalPorRango', static fn (MapaDatosModel $m): array => $m->personalPorRango(), [], $errores),
            'sexo' => $this->seguro('personalPorSexo', static fn (MapaDatosModel $m): array => $m->personalPorSexo(), [], $errores),
            'tipoPolicia' => $this->seguro('personalPorTipoPolicia', static fn (MapaDatosModel $m): array => $m->personalPorTipoPolicia(), [], $errores),
            'accionesTipo' => $this->seguro('accionesPorTipo', static fn (MapaDatosModel $m): array => $m->accionesPorTipo(), [], $errores),
            'accionesEstadoRevision' => $this->seguro('accionesPorEstadoRevision', static fn (MapaDatosModel $m): array => $m->accionesPorEstadoRevision(), [], $errores),
            'accionesAnio' => $this->seguro('accionesPorAnio', static fn (MapaDatosModel $m): array => $m->accionesPorAnio(), [], $errores),
            'catalogoEstados' => $this->seguro('catalogoEstados', static fn (MapaDatosModel $m): array => $m->catalogoEstados(), [], $errores),
        ];

        View::render('reportes/mapa_datos', [
            'title' => 'Mapa General de Datos',
            'data' => $data,
            'error' => $errores === [] ? null : implode(' | ', $errores),
        ]);
    }

    public function diagnostico(): void
    {
        $diagnostico = $this->model->diagnostico();

        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>Diagnóstico Mapa General de Datos</h1>';
        echo '<p><a href="' . e(url('/reportes/mapa-datos')) . '">Volver al mapa</a></p>';
        echo '<h2>Base conectada</h2>';
        echo '<pre>' . e((string) ($diagnostico['database'] ?? '')) . '</pre>';
        echo '<h2>Tablas principales</h2>';
        echo '<table border="1" cellpadding="6" cellspacing="0">';
        echo '<tr><th>Tabla</th><th>Existe</th><th>Total</th><th>Error</th></tr>';
        foreach (($diagnostico['tablas'] ?? []) as $row) {
            echo '<tr>';
            echo '<td>' . e($row['tabla'] ?? '') . '</td>';
            echo '<td>' . e($row['existe'] ?? '') . '</td>';
            echo '<td>' . e($row['total'] ?? '') . '</td>';
            echo '<td>' . e($row['error'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    private function seguro(string $nombre, callable $callback, array $default, array &$errores): array
    {
        try {
            return $callback($this->model);
        } catch (Throwable $e) {
            $errores[] = $nombre . ': ' . $e->getMessage();
            return $default;
        }
    }
}
