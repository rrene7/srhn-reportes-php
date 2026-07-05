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
        View::render('reportes/mapa_datos_explorador', [
            'title' => 'Mapa General de Datos',
        ]);
    }

    public function ejemplo(): void
    {
        View::render('reportes/mapa_datos_ejemplo', [
            'title' => 'Ejemplo Real Mapa de Datos',
        ]);
    }

    public function diagnostico(): void
    {
        View::render('reportes/mapa_datos_ejemplo', [
            'title' => 'Ejemplo Real Mapa de Datos',
        ]);
    }
}
