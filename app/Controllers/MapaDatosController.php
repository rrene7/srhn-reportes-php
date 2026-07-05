<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MapaDatosModel;
use App\Support\Database;
use App\Support\View;

final class MapaDatosController
{
    private MapaDatosModel $model;

    public function __construct()
    {
        $this->model = new MapaDatosModel(Database::connect());
    }

    public function index(): void
    {
        View::render('reportes/mapa_datos_granular', [
            'title' => 'Mapa General de Datos',
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
}
