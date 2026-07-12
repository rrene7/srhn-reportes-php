<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class OperativosModel
{
    private const TIPOS_OPERATIVIDAD = [
        ['codigo' => 'OO', 'nombre' => 'Operativo'],
        ['codigo' => 'OA', 'nombre' => 'Operativo administrativo'],
        ['codigo' => 'NO', 'nombre' => 'No operativo'],
        ['codigo' => 'SIN DEFINIR', 'nombre' => 'Sin definir'],
    ];

    private ?array $employeeColumns = null;

    public function __construct(private PDO $db) {}

    public function catalogos(): array
    {
        return [
            'rangos' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC')->fetchAll(),
            'unidades' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC')->fetchAll(),
            'estados' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC')->fetchAll(),
            'tiposOperatividad' => self::TIPOS_OPERATIVIDAD,
            'fuenteOperatividad' => $this->fuenteOperatividad(),
            'camposOperatividadNuevos' => $this->hasEmployeeColumn('police_operativity_type'),
        ];
    }

    public function total(array $filtros): int
    {
        [$where, $params] = $this->where($filtros);
        $sql = "SELECT COUNT(*) FROM employees e LEFT JOIN ranks r ON r.id = e.rank_id LEFT JOIN units u ON u.id = e.unit_id LEFT JOIN statuses s ON s.id = e.status_id WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function resumenOperatividad(array $filtros): array
    {
        $filtrosSinTipo = $filtros;
        $filtrosSinTipo['operatividad'] = '';
        $tipoExpr = $this->operatividadTipoSql();

        return $this->agrupar(
            $filtrosSinTipo,
            $tipoExpr,
            "CASE {$tipoExpr} WHEN 'OO' THEN 'Operativo' WHEN 'OA' THEN 'Operativo administrativo' WHEN 'NO' THEN 'No operativo' ELSE 'Sin definir' END",
            "CASE codigo WHEN 'OO' THEN 1 WHEN 'OA' THEN 2 WHEN 'NO' THEN 3 ELSE 4 END"
        );
    }

    public function porRango(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(r.legacy_code, '')", "COALESCE(r.name, e.legacy_rank_name, 'Sin rango')", 'CAST(codigo AS UNSIGNED) ASC, nombre ASC');
    }

    public function porDependencia(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(u.legacy_code, '')", "COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia')", 'codigo ASC, nombre ASC');
    }

    public function porSexo(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(NULLIF(e.sex, ''), 'SIN SEXO')", "COALESCE(NULLIF(e.sex, ''), 'SIN SEXO')", 'codigo ASC');
    }

    public function porEstatus(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(s.legacy_code, e.legacy_status_code, '')", "COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado')", 'codigo ASC, nombre ASC');
    }

    public function listado(array $filtros, int $limit = 300): array
    {
        [$where, $params] = $this->where($filtros);

        $tipoExpr = $this->operatividadTipoSql();
        $motivoExpr = $this->employeeTextSql('police_operativity_reason');
        $referenciaExpr = $this->employeeTextSql('police_operativity_reference');
        $fechaExpr = $this->employeeDateSql('police_operativity_effective_date');
        $notasExpr = $this->employeeTextSql('police_operativity_notes');

        $sql = "
            SELECT
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.document_number AS cedula,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                COALESCE(r.legacy_code, '') AS rango_codigo,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_nombre,
                COALESCE(u.legacy_code, '') AS unidad_codigo,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS unidad_nombre,
                e.sex AS sexo,
                COALESCE(s.legacy_code, e.legacy_status_code, '') AS estado_codigo,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_nombre,
                {$tipoExpr} AS operatividad_tipo,
                {$motivoExpr} AS operatividad_motivo,
                {$referenciaExpr} AS operatividad_referencia,
                {$fechaExpr} AS operatividad_fecha_efectiva,
                {$notasExpr} AS operatividad_notas
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            ORDER BY
                CASE {$tipoExpr}
                    WHEN 'OO' THEN 1
                    WHEN 'OA' THEN 2
                    WHEN 'NO' THEN 3
                    ELSE 4
                END,
                u.legacy_code ASC,
                r.sort_order ASC,
                e.last_name ASC,
                e.first_name ASC
            LIMIT :limit
        ";
        $params[':limit'] = ['value' => max(1, min($limit, 1000)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function agrupar(array $filtros, string $codigoSql, string $nombreSql, string $orderBy): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "SELECT {$codigoSql} AS codigo, {$nombreSql} AS nombre, COUNT(*) AS total FROM employees e LEFT JOIN ranks r ON r.id = e.rank_id LEFT JOIN units u ON u.id = e.unit_id LEFT JOIN statuses s ON s.id = e.status_id WHERE {$where} GROUP BY codigo, nombre ORDER BY {$orderBy}";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function where(array $filtros): array
    {
        $where = ['1 = 1'];
        $params = [];

        [$rangoDesde, $rangoHasta] = $this->limitesNumericos(
            trim((string) ($filtros['rango_desde'] ?? '')),
            trim((string) ($filtros['rango_hasta'] ?? ''))
        );
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

        $operatividad = strtoupper(trim((string) ($filtros['operatividad'] ?? '')));
        $tipoExpr = $this->operatividadTipoSql();
        if (in_array($operatividad, ['OO', 'OA', 'NO'], true)) {
            $where[] = "{$tipoExpr} = :operatividad";
            $params[':operatividad'] = $operatividad;
        } elseif ($operatividad === 'SIN DEFINIR') {
            $where[] = "{$tipoExpr} = 'SIN DEFINIR'";
        }

        $motivo = trim((string) ($filtros['motivo'] ?? ''));
        if ($motivo !== '' && $this->hasEmployeeColumn('police_operativity_reason')) {
            $where[] = "COALESCE(e.police_operativity_reason, '') LIKE :motivo";
            $params[':motivo'] = '%' . $motivo . '%';
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
            $searchClauses = [
                'e.document_number LIKE :buscar_documento',
                'e.first_name LIKE :buscar_nombre',
                'e.last_name LIKE :buscar_apellido',
                'e.external_agent_number LIKE :buscar_agente',
                'CAST(e.legacy_position AS CHAR) LIKE :buscar_posicion',
            ];
            $like = '%' . $buscar . '%';
            $params[':buscar_documento'] = $like;
            $params[':buscar_nombre'] = $like;
            $params[':buscar_apellido'] = $like;
            $params[':buscar_agente'] = $like;
            $params[':buscar_posicion'] = $like;

            $camposBusqueda = [
                'police_operativity_reason' => 'motivo',
                'police_operativity_reference' => 'referencia',
                'police_operativity_notes' => 'notas',
            ];
            foreach ($camposBusqueda as $column => $suffix) {
                if (!$this->hasEmployeeColumn($column)) {
                    continue;
                }
                $placeholder = ':buscar_' . $suffix;
                $searchClauses[] = "COALESCE(e.{$column}, '') LIKE {$placeholder}";
                $params[$placeholder] = $like;
            }

            $where[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    private function limitesNumericos(string $desde, string $hasta): array
    {
        if ($desde !== '' && $hasta !== '' && is_numeric($desde) && is_numeric($hasta) && (float) $desde > (float) $hasta) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function fuenteOperatividad(): string
    {
        if ($this->hasEmployeeColumn('police_operativity_type')) {
            return 'employees.police_operativity_type';
        }

        if ($this->hasEmployeeColumn('external_user_type')) {
            return 'employees.external_user_type (compatibilidad temporal)';
        }

        return 'sin campo de operatividad disponible';
    }

    private function operatividadTipoSql(string $alias = 'e'): string
    {
        $column = null;
        if ($this->hasEmployeeColumn('police_operativity_type')) {
            $column = 'police_operativity_type';
        } elseif ($this->hasEmployeeColumn('external_user_type')) {
            $column = 'external_user_type';
        }

        if ($column === null) {
            return "'SIN DEFINIR'";
        }

        return "CASE
            WHEN TRIM(UPPER(COALESCE({$alias}.{$column}, ''))) IN ('OO', 'OA', 'NO')
                THEN TRIM(UPPER({$alias}.{$column}))
            ELSE 'SIN DEFINIR'
        END";
    }

    private function employeeTextSql(string $column, string $alias = 'e'): string
    {
        if (!$this->hasEmployeeColumn($column)) {
            return "''";
        }

        return "COALESCE({$alias}.{$column}, '')";
    }

    private function employeeDateSql(string $column, string $alias = 'e'): string
    {
        if (!$this->hasEmployeeColumn($column)) {
            return 'NULL';
        }

        return "{$alias}.{$column}";
    }

    private function hasEmployeeColumn(string $column): bool
    {
        return isset($this->employeeColumns()[strtolower($column)]);
    }

    private function employeeColumns(): array
    {
        if ($this->employeeColumns !== null) {
            return $this->employeeColumns;
        }

        $this->employeeColumns = [];

        try {
            $rows = $this->db->query('SHOW COLUMNS FROM employees')->fetchAll();
            foreach ($rows as $row) {
                $name = strtolower(trim((string) ($row['Field'] ?? '')));
                if ($name !== '') {
                    $this->employeeColumns[$name] = true;
                }
            }
        } catch (\Throwable) {
            // La consulta principal mostrará el error real si employees no existe.
        }

        return $this->employeeColumns;
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
}
