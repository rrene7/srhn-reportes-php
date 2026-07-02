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

final class ReportesController
{
    private ReportePersonalModel $model;
    private AccionesPersonalModel $accionesModel;
    private HojaVidaComplementariaModel $complementariaModel;

    public function __construct()
    {
        $db = Database::connect();
        $this->model = new ReportePersonalModel($db);
        $this->accionesModel = new AccionesPersonalModel($db);
        $this->complementariaModel = new HojaVidaComplementariaModel($db);
    }

    public function index(): void
    {
        $this->renderFormulario('general');
    }

    public function porRango(): void
    {
        $this->renderFormulario('rango');
    }

    public function porDependencia(): void
    {
        $this->renderFormulario('dependencia');
    }

    public function acciones(): void
    {
        $this->renderAcciones();
    }

    public function accionesResultado(): void
    {
        $filtros = $this->filtrosAccionesDesdeRequest();

        try {
            $rows = $this->accionesModel->buscar($filtros, 100);
            $this->renderAcciones($filtros, $rows);
        } catch (Throwable $e) {
            $this->renderAcciones($filtros, [], $e->getMessage());
        }
    }

    public function consultaFuncionario(): void
    {
        $this->renderConsulta();
    }

    public function consultaResultado(): void
    {
        $buscar = trim((string) ($_GET['buscar'] ?? ''));

        if ($buscar === '') {
            $this->renderConsulta('', [], null, 'Debe escribir una cédula, posición, nombre o apellido para consultar.');
            return;
        }

        $filtros = [
            'rango_desde' => '',
            'rango_hasta' => '',
            'cuartel_desde' => '',
            'cuartel_hasta' => '',
            'estado' => '',
            'buscar' => $buscar,
        ];

        try {
            $total = $this->model->contarPersonal($filtros);
            $rows = $this->model->buscarPersonalPaginado($filtros, 25, 0);

            $this->renderConsulta($buscar, $rows, $total);
        } catch (Throwable $e) {
            $this->renderConsulta($buscar, [], null, $e->getMessage());
        }
    }

    public function fichaFuncionario(): void
    {
        $buscar = trim((string) ($_GET['buscar'] ?? ''));

        if ($buscar === '') {
            $this->renderFicha(null, 'Debe indicar una cédula, posición o número de empleado.');
            return;
        }

        $filtros = [
            'rango_desde' => '',
            'rango_hasta' => '',
            'cuartel_desde' => '',
            'cuartel_hasta' => '',
            'estado' => '',
            'buscar' => $buscar,
        ];

        try {
            $rows = $this->model->buscarPersonalPaginado($filtros, 1, 0);
            $funcionario = $rows[0] ?? null;
            $acciones = [];
            $complementaria = [];

            if ($funcionario !== null) {
                $identificador = (string) (($funcionario['nemp'] ?? '') !== '' ? $funcionario['nemp'] : ($funcionario['cedula'] ?? $buscar));
                $acciones = $this->accionesModel->buscar([
                    'buscar' => $identificador,
                    'tipo' => '',
                    'fecha_desde' => '',
                    'fecha_hasta' => '',
                ], 10);
                $complementaria = $this->complementariaModel->obtener($identificador);
            }

            $this->renderFicha($funcionario, null, $buscar, $acciones, $complementaria);
        } catch (Throwable $e) {
            $this->renderFicha(null, $e->getMessage(), $buscar);
        }
    }

    public function resultado(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $pagina = $this->paginaDesdeRequest();
        $porPagina = $this->porPaginaDesdeRequest();

        try {
            $total = $this->model->contarPersonal($filtros);
            $totalPaginas = max(1, (int) ceil($total / $porPagina));
            $pagina = min($pagina, $totalPaginas);
            $offset = ($pagina - 1) * $porPagina;
            $rows = $this->model->buscarPersonalPaginado($filtros, $porPagina, $offset);

            View::render('reportes/resultado', [
                'title' => 'Resultado del reporte',
                'filtros' => $filtros,
                'rows' => $rows,
                'total' => $total,
                'totalesRango' => $this->model->totalesPorCampoConsulta($filtros, 'rango'),
                'totalesCuartel' => $this->model->totalesPorCampoConsulta($filtros, 'cuartel'),
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'porPaginaOpciones' => [50, 100, 200, 500],
                'totalPaginas' => $totalPaginas,
                'offset' => $offset,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->renderFormulario('general', $filtros, $e->getMessage());
        }
    }

    public function exportarCsv(): void
    {
        $filtros = $this->filtrosDesdeRequest();
        $rows = $this->model->buscarPersonal($filtros);

        $headers = [
            'Rango',
            'Rango nombre',
            'N. empleado',
            'Nombre',
            'Apellido',
            'Cuartel',
            'Dependencia',
            'Cédula',
            'Sexo',
            'Posición PN',
            'Posición MI',
            'Fecha ingreso',
            'Fecha ascenso',
            'Fecha traslado',
            'Fecha vacaciones',
            'Estado',
            'Estado nombre',
            'Fecha nacimiento',
            'Tipo policía',
        ];

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['rango'] ?? '',
                $row['rango_nombre'] ?? '',
                $row['nemp'] ?? '',
                $row['nombre'] ?? '',
                $row['apellido'] ?? '',
                $row['cuartel'] ?? '',
                $row['cuartel_nombre'] ?? '',
                $row['cedula'] ?? '',
                $row['sexo'] ?? '',
                $row['posicipn'] ?? '',
                $row['posicimi'] ?? '',
                $row['fecing'] ?? '',
                $row['fecascen'] ?? '',
                $row['fectras'] ?? '',
                $row['fecvac'] ?? '',
                $row['estado'] ?? '',
                $row['estado_nombre'] ?? '',
                $row['fecnac'] ?? '',
                $row['tipopol'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-reporte-personal.csv', $headers, $csvRows);
    }

    private function renderFormulario(string $modulo, array $filtros = [], ?string $error = null): void
    {
        $modulos = $this->modulosDisponibles();
        $actual = $modulos[$modulo] ?? $modulos['general'];

        View::render('reportes/index', [
            'title' => $actual['titulo'],
            'rangos' => $this->model->listarRangos(),
            'cuarteles' => $this->model->listarCuarteles(),
            'estados' => $this->model->listarEstados(),
            'filtros' => $filtros,
            'error' => $error,
            'modulo' => $modulo,
            'modulos' => $modulos,
            'moduloActual' => $actual,
        ]);
    }

    private function renderAcciones(array $filtros = [], ?array $rows = null, ?string $error = null): void
    {
        $modulos = $this->modulosDisponibles();
        $metadata = $this->accionesModel->metadata();

        View::render('reportes/acciones', [
            'title' => 'Acciones de personal',
            'filtros' => $filtros,
            'rows' => $rows,
            'metadata' => $metadata,
            'error' => $error,
            'modulo' => 'acciones',
            'modulos' => $modulos,
            'moduloActual' => $modulos['acciones'],
        ]);
    }

    private function renderConsulta(string $buscar = '', array $rows = [], ?int $total = null, ?string $error = null): void
    {
        $modulos = $this->modulosDisponibles();

        View::render('reportes/consulta', [
            'title' => 'Consulta de funcionario',
            'buscar' => $buscar,
            'rows' => $rows,
            'total' => $total,
            'error' => $error,
            'modulo' => 'consulta',
            'modulos' => $modulos,
            'moduloActual' => $modulos['consulta'],
        ]);
    }

    private function renderFicha(?array $funcionario, ?string $error = null, string $buscar = '', array $acciones = [], array $complementaria = []): void
    {
        $modulos = $this->modulosDisponibles();

        View::render('reportes/funcionario', [
            'title' => 'Ficha de funcionario',
            'funcionario' => $funcionario,
            'acciones' => $acciones,
            'complementaria' => $complementaria,
            'buscar' => $buscar,
            'error' => $error,
            'modulo' => 'consulta',
            'modulos' => $modulos,
            'moduloActual' => $modulos['consulta'],
        ]);
    }

    private function modulosDisponibles(): array
    {
        return [
            'general' => [
                'titulo' => 'Reporte general de personal',
                'descripcion' => 'Listado general equivalente al reporte principal del módulo legado.',
                'ruta' => '/reportes',
                'estado' => 'Disponible',
            ],
            'rango' => [
                'titulo' => 'Reporte por rango',
                'descripcion' => 'Equivalente base a LISTARAN: filtra y resume el personal por jerarquía/rango.',
                'ruta' => '/reportes/por-rango',
                'estado' => 'Disponible',
            ],
            'dependencia' => [
                'titulo' => 'Reporte por dependencia',
                'descripcion' => 'Equivalente base a LISTAUBI: filtra y resume el personal por ubicación o dependencia.',
                'ruta' => '/reportes/por-dependencia',
                'estado' => 'Disponible',
            ],
            'acciones' => [
                'titulo' => 'Acciones de personal',
                'descripcion' => 'Base para reconstruir listados de acciones, traslados, ascensos, vacaciones, licencias, sanciones y novedades.',
                'ruta' => '/reportes/acciones',
                'estado' => 'Base lista',
            ],
            'consulta' => [
                'titulo' => 'Consulta de funcionario',
                'descripcion' => 'Equivalente base a CONSULTA: búsqueda individual por cédula, posición, nombre o apellido.',
                'ruta' => '/reportes/consulta-funcionario',
                'estado' => 'Disponible',
            ],
        ];
    }

    private function filtrosDesdeRequest(): array
    {
        return [
            'rango_desde' => trim((string) ($_GET['rango_desde'] ?? '')),
            'rango_hasta' => trim((string) ($_GET['rango_hasta'] ?? '')),
            'cuartel_desde' => trim((string) ($_GET['cuartel_desde'] ?? '')),
            'cuartel_hasta' => trim((string) ($_GET['cuartel_hasta'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
        ];
    }

    private function filtrosAccionesDesdeRequest(): array
    {
        return [
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];
    }

    private function paginaDesdeRequest(): int
    {
        $pagina = (int) ($_GET['page'] ?? 1);

        return max(1, $pagina);
    }

    private function porPaginaDesdeRequest(): int
    {
        $opciones = [50, 100, 200, 500];
        $porPagina = (int) ($_GET['per_page'] ?? 100);

        return in_array($porPagina, $opciones, true) ? $porPagina : 100;
    }
}
