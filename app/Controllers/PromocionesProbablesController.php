<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromocionesProbablesModel;
use App\Support\Database;
use App\Support\View;
use Throwable;

final class PromocionesProbablesController
{
    private PromocionesProbablesModel $model;

    public function __construct()
    {
        $this->model = new PromocionesProbablesModel(Database::connect());
    }

    public function index(): void
    {
        $filtros = $this->filtros();
        $grupos = [];
        $detalle = [];
        $error = null;

        try {
            $grupos = $this->model->grupos(
                $filtros['fecha_base'],
                $filtros['minimo_integrantes'],
                $filtros['promocion_inicial']
            );

            if (($filtros['ver_detalle'] ?? '') !== '') {
                foreach ($grupos as $grupo) {
                    if ((string) $grupo['numero_promocion_probable'] === (string) $filtros['ver_detalle']) {
                        $detalle = [
                            'grupo' => $grupo,
                            'integrantes' => $this->model->integrantes($grupo),
                        ];
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        View::render('reportes/promociones_probables', [
            'title' => 'Promociones probables',
            'filtros' => $filtros,
            'grupos' => $grupos,
            'detalle' => $detalle,
            'error' => $error,
        ]);
    }

    private function filtros(): array
    {
        $promocionInicial = (int) ($_GET['promocion_inicial'] ?? 20);
        $minimoIntegrantes = (int) ($_GET['minimo_integrantes'] ?? 40);
        $fechaBase = trim((string) ($_GET['fecha_base'] ?? '1997-03-01'));

        if ($promocionInicial < 1) {
            $promocionInicial = 20;
        }

        if ($minimoIntegrantes < 1) {
            $minimoIntegrantes = 40;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaBase)) {
            $fechaBase = '1997-03-01';
        }

        return [
            'promocion_inicial' => $promocionInicial,
            'fecha_base' => $fechaBase,
            'minimo_integrantes' => $minimoIntegrantes,
            'ver_detalle' => trim((string) ($_GET['ver_detalle'] ?? '')),
        ];
    }
}
