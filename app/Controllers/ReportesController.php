<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccionesPersonalModel;
use App\Models\HojaVidaComplementariaModel;
use App\Models\ProcedenciaOficialModel;
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
    private ProcedenciaOficialModel $procedenciaModel;

    public function __construct()
    {
        $db = Database::connect();
        $this->model = new ReportePersonalModel($db);
        $this->accionesModel = new AccionesPersonalModel($db);
        $this->complementariaModel = new HojaVidaComplementariaModel($db);
        $this->procedenciaModel = new ProcedenciaOficialModel($db);
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

    public function procedenciaOficiales(): void
    {
        $filtros = $this->filtrosProcedenciaDesdeRequest();

        try {
            $rows = $this->procedenciaModel->buscar($filtros, 500);
            $resumen = $this->procedenciaModel->resumen($rows);

            $this->renderProcedenciaOficiales($filtros, $rows, $resumen);
        } catch (Throwable $e) {
            $this->renderProcedenciaOficiales($filtros, [], ['total' => 0, 'escuela' => 0, 'tropa' => 0], $e->getMessage());
        }
    }

    public function exportarProcedenciaOficialesCsv(): void
    {
        $filtros = $this->filtrosProcedenciaDesdeRequest();
        $rows = $this->procedenciaModel->buscar($filtros, 1000);

        $headers = [
            'N. empleado',
            'Cédula',
            'Funcionario',
            'Sexo',
            'Código rango',
            'Rango actual',
            'Código dependencia',
            'Dependencia actual',
            'Procedencia',
            'Evidencia de tropa',
            'Fecha evidencia',
            'Motivo',
        ];

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['nemp'] ?? '',
                $row['cedula'] ?? '',
                $row['funcionario'] ?? '',
                $row['sexo'] ?? '',
                $row['rango_codigo'] ?? '',
                $row['rango_actual'] ?? '',
                $row['unidad_codigo'] ?? '',
                $row['unidad_actual'] ?? '',
                $row['procedencia_oficial'] ?? '',
                $row['evidencia_tropa'] ?? '',
                $row['fecha_evidencia'] ?? '',
                $row['motivo'] ?? '',
            ];
        }, $rows);

        Response::csv('srhn-procedencia-oficiales.csv', $headers, $csvRows);
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

    public function accionesAscensos(): void
    {
        $this->accionesPorCategoria('ascensos');
    }

    public function accionesTraslados(): void
    {
        $this->accionesPorCategoria('traslados');
    }

    public function accionesVacaciones(): void
    {
        $this->accionesPorCategoria('vacaciones');
    }

    public function accionesLicencias(): void
    {
        $this->accionesPorCategoria('licencias');
    }

    public function accionesSanciones(): void
    {
        $this->accionesPorCategoria('sanciones');
    }

    public function accionesIncapacidades(): void
    {
        $this->accionesPorCategoria('incapacidades');
    }

    public function exportarAccionesCsv(): void
    {
        $filtros = $this->filtrosAccionesDesdeRequest();
        $rows = $this->accionesModel->buscar($filtros, 500);
        $categoria = trim((string) ($filtros['categoria'] ?? ''));
        $filename = $categoria !== '' ? 'srhn-acciones-' . $categoria . '.csv' : 'srhn-acciones-personal.csv';

        $headers = [
            'ID acción',
            'ID funcionario',
            'N. empleado',
            'Cédula',
            'Funcionario',
            'Sexo',
            'Código rango actual',
            'Rango actual',
            'Código dependencia',
            'Dependencia actual',
            'Código tipo acción',
            'Tipo acción',
            'Fecha acción',
            'Fecha cruda',
            'Fecha inicio',
            'Fecha fin',
            'Resolución',
            'Fecha resolución',
            'OGD',
            'Causa',
            'Posición destino',
            'Rango destino',
            'Unidad destino',
            'Duración',
            'Unidad duración',
            'Número incapacidad',
            'Código doctor',
            'Instalación médica',
            'Adjunto',
            'Notas',
            'Estado migración',
        ];

        if ($rows === []) {
            Response::csv($filename, $headers, []);
        }

        $csvRows = array_map(static function (array $row): array {
            return [
                $row['accion_id'] ?? '',
                $row['employee_id'] ?? '',
                $row['nemp'] ?? '',
                $row['cedula'] ?? '',
                $row['funcionario'] ?? '',
                $row['sexo'] ?? '',
                $row['rango_codigo'] ?? '',
                $row['rango_nombre'] ?? '',
                $row['unidad_codigo'] ?? '',
                $row['unidad_nombre'] ?? '',
                $row['action_type_id'] ?? '',
                $row['tipo_accion'] ?? '',
                $row['action_date'] ?? '',
                $row['raw_action_date'] ?? '',
                $row['start_date'] ?? '',
                $row['end_date'] ?? '',
                $row['resolution_number'] ?? '',
                $row['resolution_date'] ?? '',
                $row['ogd_number'] ?? '',
                $row['cause_code'] ?? '',
                $row['target_position'] ?? '',
                $row['rango_destino'] ?? '',
                $row['unidad_destino'] ?? '',
                $row['duration_value'] ?? '',
                $row['duration_unit'] ?? '',
                $row['incapacity_number'] ?? '',
                $row['doctor_code'] ?? '',
                $row['medical_facility'] ?? '',
                $row['attachment_path'] ?? '',
                $row['notes'] ?? '',
                $row['migration_review_status'] ?? '',
            ];
        }, $rows);

        Response::csv($filename, $headers, $csvRows);
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
                    'categoria' => '',
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
        $formato = trim((string) ($_GET['formato'] ?? ''));
        if ($formato === 'ficha') {
            $this->exportarFichaCsv();
            return;
        }

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

    private function exportarFichaCsv(): void
    {
        $buscar = trim((string) ($_GET['buscar'] ?? ''));
        if ($buscar === '') {
            Response::csv('srhn-ficha-funcionario.csv', ['Resultado'], [['Debe indicar una cédula, posición o número de empleado']]);
        }

        $filtros = [
            'rango_desde' => '',
            'rango_hasta' => '',
            'cuartel_desde' => '',
            'cuartel_hasta' => '',
            'estado' => '',
            'buscar' => $buscar,
        ];

        $rows = $this->model->buscarPersonalPaginado($filtros, 1, 0);
        $funcionario = $rows[0] ?? null;

        if ($funcionario === null) {
            Response::csv('srhn-ficha-funcionario.csv', ['Resultado'], [['No se encontró el funcionario solicitado']]);
        }

        $identificador = (string) (($funcionario['nemp'] ?? '') !== '' ? $funcionario['nemp'] : ($funcionario['cedula'] ?? $buscar));
        $acciones = $this->accionesModel->buscar([
            'buscar' => $identificador,
            'tipo' => '',
            'categoria' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ], 50);
        $complementaria = $this->complementariaModel->obtener($identificador);

        $headers = ['Sección', 'Campo', 'Valor'];
        $csvRows = [];

        $camposFuncionario = [
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

        foreach ($camposFuncionario as $campo => $valor) {
            $csvRows[] = ['Datos generales', $campo, $valor];
        }

        foreach ($acciones as $index => $accion) {
            $numero = (string) ($index + 1);
            $csvRows[] = ['Acción ' . $numero, 'Tipo', trim((string) (($accion['action_type_id'] ?? '') . ' - ' . ($accion['tipo_accion'] ?? '')))];
            $csvRows[] = ['Acción ' . $numero, 'Fecha acción', $accion['action_date'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'Resolución', $accion['resolution_number'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'OGD', $accion['ogd_number'] ?? ''];
            $csvRows[] = ['Acción ' . $numero, 'Destino', trim((string) (($accion['target_position'] ?? '') . ' / ' . ($accion['rango_destino'] ?? '') . ' / ' . ($accion['unidad_destino'] ?? '')))];
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

        Response::csv('srhn-ficha-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $identificador) . '.csv', $headers, $csvRows);
    }

    private function accionesPorCategoria(string $categoria): void
    {
        $filtros = $this->filtrosAccionesDesdeRequest();
        $filtros['categoria'] = $categoria;

        try {
            $rows = $this->accionesModel->buscar($filtros, 100);
            $this->renderAcciones($filtros, $rows);
        } catch (Throwable $e) {
            $this->renderAcciones($filtros, [], $e->getMessage());
        }
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

    private function renderProcedenciaOficiales(array $filtros, array $rows, array $resumen, ?string $error = null): void
    {
        $modulos = $this->modulosDisponibles();

        View::render('reportes/procedencia_oficiales', [
            'title' => 'Procedencia de oficiales',
            'filtros' => $filtros,
            'rows' => $rows,
            'resumen' => $resumen,
            'error' => $error,
            'modulo' => 'procedencia_oficiales',
            'modulos' => $modulos,
            'moduloActual' => $modulos['procedencia_oficiales'],
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
            'procedencia_oficiales' => [
                'titulo' => 'Procedencia de oficiales',
                'descripcion' => 'Clasifica oficiales como escuela o tropa usando evidencia del historial de acciones.',
                'ruta' => '/reportes/procedencia-oficiales',
                'estado' => 'Nuevo reporte',
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
            'categoria' => trim((string) ($_GET['categoria'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];
    }

    private function filtrosProcedenciaDesdeRequest(): array
    {
        return [
            'buscar' => trim((string) ($_GET['buscar'] ?? '')),
            'procedencia' => trim((string) ($_GET['procedencia'] ?? '')),
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
