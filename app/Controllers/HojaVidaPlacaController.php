<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccionesPersonalModel;
use App\Models\HojaVidaComplementariaModel;
use App\Models\ReportePersonalModel;
use App\Support\Database;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class HojaVidaPlacaController
{
    private ReportePersonalModel $personalModel;
    private AccionesPersonalModel $accionesModel;
    private HojaVidaComplementariaModel $complementariaModel;

    public function __construct()
    {
        $db = Database::connect();
        $this->personalModel = new ReportePersonalModel($db);
        $this->accionesModel = new AccionesPersonalModel($db);
        $this->complementariaModel = new HojaVidaComplementariaModel($db);
    }

    public function index(): void
    {
        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        $funcionario = null;
        $acciones = [];
        $complementaria = [];
        $error = null;

        if ($buscar !== '') {
            try {
                $filtros = [
                    'rango_desde' => '',
                    'rango_hasta' => '',
                    'cuartel_desde' => '',
                    'cuartel_hasta' => '',
                    'estado' => '',
                    'buscar' => $buscar,
                ];

                $rows = $this->personalModel->buscarPersonalPaginado($filtros, 1, 0);
                $funcionario = $rows[0] ?? null;

                if ($funcionario === null) {
                    $error = 'No se encontró funcionario para la búsqueda indicada.';
                } else {
                    $identificador = $this->identificadorFuncionario($funcionario, $buscar);
                    $acciones = $this->accionesModel->buscar([
                        'buscar' => $identificador,
                        'tipo' => '',
                        'categoria' => '',
                        'fecha_desde' => '',
                        'fecha_hasta' => '',
                    ], 50);
                    $complementaria = $this->complementariaModel->obtener($identificador);
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        View::render('reportes/hoja_vida_placa', [
            'title' => 'Hoja de Vida para la placa',
            'buscar' => $buscar,
            'funcionario' => $funcionario,
            'acciones' => $acciones,
            'complementaria' => $complementaria,
            'error' => $error,
        ]);
    }

    public function exportarCsv(): void
    {
        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        if ($buscar === '') {
            Response::csv('srhn-hoja-vida-placa.csv', ['Resultado'], [['Debe indicar cédula, posición o número de empleado.']]);
        }

        $filtros = [
            'rango_desde' => '',
            'rango_hasta' => '',
            'cuartel_desde' => '',
            'cuartel_hasta' => '',
            'estado' => '',
            'buscar' => $buscar,
        ];

        $rows = $this->personalModel->buscarPersonalPaginado($filtros, 1, 0);
        $funcionario = $rows[0] ?? null;
        if ($funcionario === null) {
            Response::csv('srhn-hoja-vida-placa.csv', ['Resultado'], [['No se encontró funcionario para la búsqueda indicada.']]);
        }

        $identificador = $this->identificadorFuncionario($funcionario, $buscar);
        $acciones = $this->accionesModel->buscar([
            'buscar' => $identificador,
            'tipo' => '',
            'categoria' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ], 100);
        $complementaria = $this->complementariaModel->obtener($identificador);

        $csvRows = [];
        $campos = [
            'Nombre completo' => trim((string) (($funcionario['nombre'] ?? '') . ' ' . ($funcionario['apellido'] ?? ''))),
            'Cédula' => $funcionario['cedula'] ?? '',
            'Número de empleado' => $funcionario['nemp'] ?? '',
            'Sexo' => $funcionario['sexo'] ?? '',
            'Rango' => trim((string) (($funcionario['rango'] ?? '') . ' - ' . ($funcionario['rango_nombre'] ?? ''))),
            'Estado' => trim((string) (($funcionario['estado'] ?? '') . ' - ' . ($funcionario['estado_nombre'] ?? ''))),
            'Dependencia' => trim((string) (($funcionario['cuartel'] ?? '') . ' - ' . ($funcionario['cuartel_nombre'] ?? ''))),
            'Posición PN' => $funcionario['posicipn'] ?? '',
            'Posición MI' => $funcionario['posicimi'] ?? '',
            'Fecha ingreso' => $funcionario['fecing'] ?? '',
            'Fecha ascenso' => $funcionario['fecascen'] ?? '',
            'Fecha traslado / estado' => $funcionario['fectras'] ?? '',
            'Fecha vacaciones' => $funcionario['fecvac'] ?? '',
            'Fecha nacimiento' => $funcionario['fecnac'] ?? '',
            'Tipo policía' => $funcionario['tipopol'] ?? '',
        ];

        foreach ($campos as $campo => $valor) {
            $csvRows[] = ['Datos generales', $campo, $valor];
        }

        foreach ($acciones as $index => $accion) {
            $numero = (string) ($index + 1);
            $csvRows[] = ['Acción ' . $numero, 'Tipo', trim((string) (($accion['action_type_id'] ?? '') . ' - ' . ($accion['tipo_accion'] ?? '')) )];
            $csvRows[] = ['Acción ' . $numero, 'Fecha acción', $accion['action_date'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'Resolución', $accion['resolution_number'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'OGD', $accion['ogd_number'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'Destino', trim((string) (($accion['target_position'] ?? '') . ' / ' . ($accion['rango_destino'] ?? '') . ' / ' . ($accion['unidad_destino'] ?? '')) )];
            $csvRows[] = ['Acción ' . $numero, 'Detalle', $accion['notes'] ?? ''];
        }

        foreach ($complementaria as $seccion) {
            $titulo = (string) ($seccion['titulo'] ?? 'Sección complementaria');
            $tabla = (string) ($seccion['tabla'] ?? 'No detectada');
            $csvRows[] = [$titulo, 'Tabla detectada', $tabla];

            foreach (($seccion['rows'] ?? []) as $index => $row) {
                foreach (array_slice(($seccion['columnas'] ?? []), 0, 12) as $columna) {
                    $csvRows[] = [$titulo . ' ' . ((int) $index + 1), (string) $columna, $row[$columna] ?? ''];
                }
            }
        }

        Response::csv('srhn-hoja-vida-placa-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $identificador) . '.csv', ['Sección', 'Campo', 'Valor'], $csvRows);
    }

    private function identificadorFuncionario(array $funcionario, string $buscar): string
    {
        $nemp = trim((string) ($funcionario['nemp'] ?? ''));
        if ($nemp !== '') {
            return $nemp;
        }

        $cedula = trim((string) ($funcionario['cedula'] ?? ''));
        return $cedula !== '' ? $cedula : $buscar;
    }
}
