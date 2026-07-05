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
        $error = null;
        $data = [];

        try {
            $data = [
                'resumen' => $this->model->resumenGeneral(),
                'zonas' => $this->model->zonas(),
                'areas' => $this->model->areas(),
                'dependencias' => $this->model->dependencias(),
                'estadosPersonal' => $this->model->personalPorEstado(),
                'rangos' => $this->model->personalPorRango(),
                'sexo' => $this->model->personalPorSexo(),
                'tipoPolicia' => $this->model->personalPorTipoPolicia(),
                'accionesTipo' => $this->model->accionesPorTipo(),
                'accionesEstadoRevision' => $this->model->accionesPorEstadoRevision(),
                'accionesAnio' => $this->model->accionesPorAnio(),
                'catalogoEstados' => $this->model->catalogoEstados(),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/mapa_datos', [
            'title' => 'Mapa General de Datos',
            'data' => $data,
            'error' => $error,
        ]);
    }
}
