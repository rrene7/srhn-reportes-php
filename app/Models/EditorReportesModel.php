<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class EditorReportesModel
{
    public function __construct(private PDO $db) {}

    public function fuentes(): array
    {
        return [
            'personal' => [
                'titulo' => 'Funcionarios',
                'descripcion' => 'Datos generales del personal con rango, dependencia, sexo, estatus y fechas institucionales.',
                'default' => ['nemp', 'cedula', 'funcionario', 'rango', 'dependencia', 'sexo', 'estado'],
                'columnas' => [
                    'nemp' => ['titulo' => 'N. empleado', 'sql' => "COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR))"],
                    'cedula' => ['titulo' => 'Cédula', 'sql' => 'e.document_number'],
                    'funcionario' => ['titulo' => 'Funcionario', 'sql' => "TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')))"],
                    'rango' => ['titulo' => 'Rango', 'sql' => "TRIM(CONCAT(COALESCE(r.legacy_code, ''), ' - ', COALESCE(r.name, e.legacy_rank_name, '')))"],
                    'dependencia' => ['titulo' => 'Dependencia', 'sql' => "TRIM(CONCAT(COALESCE(u.legacy_code, ''), ' - ', COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '')))"],
                    'sexo' => ['titulo' => 'Sexo', 'sql' => 'e.sex'],
                    'estado' => ['titulo' => 'Estado', 'sql' => "TRIM(CONCAT(COALESCE(s.legacy_code, e.legacy_status_code, ''), ' - ', COALESCE(s.name, e.external_user_status, e.external_agent_status, '')))"],
                    'tipo_policia' => ['titulo' => 'Tipo policía', 'sql' => 'e.external_user_type'],
                    'fecha_ingreso' => ['titulo' => 'Fecha ingreso', 'sql' => 'e.hire_date'],
                    'fecha_ascenso' => ['titulo' => 'Fecha ascenso', 'sql' => 'e.promotion_date'],
                    'fecha_estado' => ['titulo' => 'Fecha estado', 'sql' => 'e.status_date'],
                    'fecha_nacimiento' => ['titulo' => 'Fecha nacimiento', 'sql' => 'e.birth_date'],
                ],
            ],
            'acciones' => [
                'titulo' => 'Acciones de personal',
                'descripcion' => 'Historial institucional de acciones: nombramientos, ascensos, traslados, vacaciones, sanciones, incapacidades y novedades.',
                'default' => ['fecha_accion', 'tipo_accion', 'funcionario', 'cedula', 'rango_actual', 'dependencia_actual', 'resolucion', 'ogd'],
                'columnas' => [
                    'fecha_accion' => ['titulo' => 'Fecha acción', 'sql' => 'a.action_date'],
                    'tipo_accion' => ['titulo' => 'Tipo acción', 'sql' => "TRIM(CONCAT(COALESCE(a.action_type_id, ''), ' - ', COALESCE(at.name, '')))"],
                    'funcionario' => ['titulo' => 'Funcionario', 'sql' => "TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')))"],
                    'cedula' => ['titulo' => 'Cédula', 'sql' => 'e.document_number'],
                    'nemp' => ['titulo' => 'N. empleado', 'sql' => "COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR))"],
                    'rango_actual' => ['titulo' => 'Rango actual', 'sql' => "TRIM(CONCAT(COALESCE(r.legacy_code, ''), ' - ', COALESCE(r.name, e.legacy_rank_name, '')))"],
                    'dependencia_actual' => ['titulo' => 'Dependencia actual', 'sql' => "TRIM(CONCAT(COALESCE(u.legacy_code, ''), ' - ', COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '')))"],
                    'resolucion' => ['titulo' => 'Resolución', 'sql' => 'a.resolution_number'],
                    'ogd' => ['titulo' => 'OGD', 'sql' => 'a.ogd_number'],
                    'inicio' => ['titulo' => 'Fecha inicio', 'sql' => 'a.start_date'],
                    'fin' => ['titulo' => 'Fecha fin', 'sql' => 'a.end_date'],
                    'posicion_destino' => ['titulo' => 'Posición destino', 'sql' => 'a.target_position'],
                    'rango_destino' => ['titulo' => 'Rango destino', 'sql' => "TRIM(CONCAT(COALESCE(rt.legacy_code, ''), ' - ', COALESCE(rt.name, '')))"],
                    'dependencia_destino' => ['titulo' => 'Dependencia destino', 'sql' => "TRIM(CONCAT(COALESCE(ut.legacy_code, ''), ' - ', COALESCE(ut.name, '')))"],
                    'detalle' => ['titulo' => 'Detalle', 'sql' => 'a.notes'],
                ],
            ],
        ];
    }

    public function catalogos(): array
    {
        return [
            'rangos' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC')->fetchAll(),
            'unidades' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC')->fetchAll(),
            'estados' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC')->fetchAll(),
            'tiposAccion' => $this->db->query('SELECT id AS codigo, name AS nombre FROM action_types ORDER BY name ASC')->fetchAll(),
        ];
    }

    public function construir(array $filtros, int $limit = 300): array
    {
        $fuente = $this->fuenteActual($filtros);
        $columnas = $this->columnasSeleccionadas($fuente, $filtros['columnas'] ?? []);
        [$from, $where, $params, $orderBy] = $this->sqlBase($fuente, $filtros);

        $select = [];
        foreach ($columnas as $clave) {
            $select[] = $fuente['columnas'][$clave]['sql'] . ' AS ' . $this->id($clave);
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' ' . $from . ' WHERE ' . $where . ' ' . $orderBy . ' LIMIT :limit';
        $params[':limit'] = ['value' => max(1, min($limit, 1000)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return [
            'fuente' => $fuente,
            'columnas' => $columnas,
            'headers' => array_map(fn (string $clave): string => (string) $fuente['columnas'][$clave]['titulo'], $columnas),
            'rows' => $stmt->fetchAll(),
            'sql_resumen' => $where,
        ];
    }

    public function fuenteActual(array $filtros): array
    {
        $fuentes = $this->fuentes();
        $codigo = (string) ($filtros['fuente'] ?? 'personal');
        return $fuentes[$codigo] ?? $fuentes['personal'];
    }

    public function codigoFuenteActual(array $filtros): string
    {
        $codigo = (string) ($filtros['fuente'] ?? 'personal');
        return array_key_exists($codigo, $this->fuentes()) ? $codigo : 'personal';
    }

    public function columnasSeleccionadas(array $fuente, array|string $seleccionadas): array
    {
        $disponibles = array_keys($fuente['columnas']);
        $seleccionadas = is_array($seleccionadas) ? $seleccionadas : [$seleccionadas];
        $seleccionadas = array_values(array_intersect($seleccionadas, $disponibles));

        if ($seleccionadas === []) {
            $seleccionadas = array_values(array_intersect((array) ($fuente['default'] ?? []), $disponibles));
        }

        return $seleccionadas !== [] ? $seleccionadas : array_slice($disponibles, 0, 6);
    }

    private function sqlBase(array $fuente, array $filtros): array
    {
        $codigoFuente = $this->codigoFuenteActual($filtros);
        $where = ['1 = 1'];
        $params = [];

        if ($codigoFuente === 'acciones') {
            $from = 'FROM employee_actions a LEFT JOIN action_types at ON at.id = a.action_type_id LEFT JOIN employees e ON e.id = a.employee_id LEFT JOIN ranks r ON r.id = e.rank_id LEFT JOIN units u ON u.id = e.unit_id LEFT JOIN statuses s ON s.id = e.status_id LEFT JOIN ranks rt ON rt.id = a.target_rank_id LEFT JOIN units ut ON ut.id = a.target_unit_id';
            $where[] = 'a.action_date IS NOT NULL';
            $orderBy = 'ORDER BY a.action_date DESC, a.id DESC';

            $tipo = trim((string) ($filtros['tipo_accion'] ?? ''));
            if ($tipo !== '') {
                $where[] = 'a.action_type_id = :tipo_accion';
                $params[':tipo_accion'] = ['value' => (int) $tipo, 'type' => PDO::PARAM_INT];
            }

            $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
                $where[] = 'a.action_date >= :fecha_desde';
                $params[':fecha_desde'] = $fechaDesde;
            }

            $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
                $where[] = 'a.action_date <= :fecha_hasta';
                $params[':fecha_hasta'] = $fechaHasta;
            } else {
                $where[] = 'a.action_date <= CURDATE()';
            }
        } else {
            $from = 'FROM employees e LEFT JOIN ranks r ON r.id = e.rank_id LEFT JOIN units u ON u.id = e.unit_id LEFT JOIN statuses s ON s.id = e.status_id';
            $orderBy = 'ORDER BY u.legacy_code ASC, r.sort_order ASC, e.last_name ASC, e.first_name ASC';
        }

        $this->aplicarFiltrosPersonal($where, $params, $filtros);

        return [$from, implode(' AND ', $where), $params, $orderBy];
    }

    private function aplicarFiltrosPersonal(array &$where, array &$params, array $filtros): void
    {
        $rangoDesde = trim((string) ($filtros['rango_desde'] ?? ''));
        $rangoHasta = trim((string) ($filtros['rango_hasta'] ?? ''));
        if ($rangoDesde !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) >= CAST(:rango_desde AS UNSIGNED)';
            $params[':rango_desde'] = $rangoDesde;
        }
        if ($rangoHasta !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) <= CAST(:rango_hasta AS UNSIGNED)';
            $params[':rango_hasta'] = $rangoHasta;
        }

        $unidad = trim((string) ($filtros['unidad'] ?? ''));
        if ($unidad !== '') {
            $unidadBase = rtrim($unidad, '0');
            if ($unidadBase !== '' && $unidadBase !== $unidad && strlen($unidadBase) >= 4) {
                $where[] = "(COALESCE(u.legacy_code, '') = :unidad OR COALESCE(u.legacy_code, '') LIKE :unidad_prefijo)";
                $params[':unidad'] = $unidad;
                $params[':unidad_prefijo'] = $unidadBase . '%';
            } else {
                $where[] = "COALESCE(u.legacy_code, '') = :unidad";
                $params[':unidad'] = $unidad;
            }
        }

        $sexo = strtoupper(trim((string) ($filtros['sexo'] ?? 'A')));
        if (in_array($sexo, ['M', 'F'], true)) {
            $where[] = "UPPER(COALESCE(e.sex, '')) = :sexo";
            $params[':sexo'] = $sexo;
        }

        $estadoModo = trim((string) ($filtros['estado_modo'] ?? 'activo'));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estadoModo === 'activo') {
            $where[] = "(TRIM(COALESCE(s.legacy_code, e.legacy_status_code, '')) IN ('10','010') OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) LIKE 'ACTIVO%' OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) LIKE '%EN SERVICIO%')";
        } elseif ($estadoModo === 'especifico' && $estado !== '') {
            $where[] = "TRIM(COALESCE(s.legacy_code, e.legacy_status_code, '')) = :estado";
            $params[':estado'] = $estado;
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $where[] = "(e.document_number LIKE :buscar OR e.first_name LIKE :buscar OR e.last_name LIKE :buscar OR e.external_agent_number LIKE :buscar OR CAST(e.legacy_position AS CHAR) LIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $stmt->bindValue($key, $value['value'], $value['type']);
                continue;
            }

            $stmt->bindValue($key, $value);
        }
    }

    private function id(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
