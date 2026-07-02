<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class AccionesPersonalModel
{
    private const POSIBLES_TABLAS = [
        'acciones',
        'actions',
        'personal_actions',
        'employee_actions',
        'accion_personal',
        'acciones_personal',
        'ACCIONES',
    ];

    public function __construct(
        private PDO $db
    ) {}

    public function tablaDetectada(): ?string
    {
        try {
            $placeholders = implode(',', array_fill(0, count(self::POSIBLES_TABLAS), '?'));
            $sql = "
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND LOWER(TABLE_NAME) IN ({$placeholders})
                ORDER BY FIELD(LOWER(TABLE_NAME), {$placeholders})
                LIMIT 1
            ";

            $values = array_map('strtolower', self::POSIBLES_TABLAS);
            $stmt = $this->db->prepare($sql);
            $stmt->execute([...$values, ...$values]);

            $table = $stmt->fetchColumn();

            return is_string($table) && $table !== '' ? $table : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function columnas(): array
    {
        $tabla = $this->tablaDetectada();

        if ($tabla === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM ' . $this->id($tabla));
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function tiposAccion(): array
    {
        if ($this->tablaDetectada() === 'employee_actions' && $this->tablaExiste('action_types')) {
            try {
                $stmt = $this->db->query('SELECT id, name FROM action_types ORDER BY name ASC');
                return array_map(static function (array $row): array {
                    return [
                        'codigo' => (string) ($row['id'] ?? ''),
                        'nombre' => (string) ($row['name'] ?? $row['id'] ?? ''),
                    ];
                }, $stmt->fetchAll());
            } catch (Throwable) {
                return [];
            }
        }

        $tabla = $this->tablaDetectada();
        $columnas = $this->columnas();

        if ($tabla === null || $columnas === []) {
            return [];
        }

        $tipoColumna = $this->primeraColumnaExistente($columnas, [
            'tipo_accion',
            'action_type',
            'tipo',
            'accion',
            'codigo_accion',
            'codaccion',
            'cod_accion',
            'action_code',
            'action_type_id',
        ]);

        if ($tipoColumna === null) {
            return [];
        }

        try {
            $sql = 'SELECT DISTINCT ' . $this->id($tipoColumna) . ' AS tipo FROM ' . $this->id($tabla) . ' WHERE ' . $this->id($tipoColumna) . ' IS NOT NULL ORDER BY ' . $this->id($tipoColumna) . ' ASC LIMIT 200';
            $stmt = $this->db->query($sql);

            return array_values(array_filter(array_map(static function (array $row): array {
                $tipo = trim((string) ($row['tipo'] ?? ''));
                return ['codigo' => $tipo, 'nombre' => $tipo];
            }, $stmt->fetchAll()), static fn (array $row): bool => $row['codigo'] !== ''));
        } catch (Throwable) {
            return [];
        }
    }

    public function buscar(array $filtros, int $limit = 100): array
    {
        if (!$this->tieneFiltros($filtros)) {
            return [];
        }

        if ($this->tablaDetectada() === 'employee_actions') {
            return $this->buscarEmployeeActions($filtros, $limit);
        }

        return $this->buscarGenerico($filtros, $limit);
    }

    public function metadata(): array
    {
        $tabla = $this->tablaDetectada();
        $columnas = $this->columnas();

        return [
            'tabla' => $tabla,
            'columnas' => array_map(static fn (array $col): string => (string) ($col['Field'] ?? ''), $columnas),
            'tipos' => $this->tiposAccion(),
            'modo' => $tabla === 'employee_actions' ? 'employee_actions' : 'generico',
        ];
    }

    private function buscarEmployeeActions(array $filtros, int $limit): array
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $employeeIds = $buscar !== '' ? $this->resolverEmployeeIds($buscar) : [];

        $where = ['a.deleted_at IS NULL'];
        $params = [];

        if ($buscar !== '') {
            if ($employeeIds !== []) {
                $placeholders = [];
                foreach ($employeeIds as $index => $employeeId) {
                    $param = ':employee_id_' . $index;
                    $placeholders[] = $param;
                    $params[$param] = ['value' => $employeeId, 'type' => PDO::PARAM_INT];
                }

                $where[] = 'a.employee_id IN (' . implode(',', $placeholders) . ')';
            } elseif (ctype_digit($buscar)) {
                $where[] = '(a.resolution_number = :buscar_texto OR a.ogd_number = :buscar_texto)';
                $params[':buscar_texto'] = $buscar;
            } else {
                $where[] = '(a.resolution_number LIKE :buscar_prefijo OR a.ogd_number LIKE :buscar_prefijo)';
                $params[':buscar_prefijo'] = $buscar . '%';
            }
        }

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'a.action_type_id = :tipo';
            $params[':tipo'] = ['value' => (int) $tipo, 'type' => PDO::PARAM_INT];
        }

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($fechaDesde !== '') {
            $where[] = 'a.action_date >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta !== '') {
            $where[] = 'a.action_date <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta;
        }

        $joinActionTypes = $this->tablaExiste('action_types');
        $joinTargetRanks = $this->tablaExiste('ranks');
        $joinTargetUnits = $this->tablaExiste('units');

        $sql = "
            SELECT
                a.id AS accion_id,
                a.employee_id,
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.document_number AS cedula,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                e.sex AS sexo,
                COALESCE(r.legacy_code, '') AS rango_codigo,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_nombre,
                COALESCE(u.legacy_code, '') AS unidad_codigo,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS unidad_nombre,
                a.action_type_id,
                " . ($joinActionTypes ? "COALESCE(at.name, CAST(a.action_type_id AS CHAR))" : "CAST(a.action_type_id AS CHAR)") . " AS tipo_accion,
                a.action_date,
                a.raw_action_date,
                a.start_date,
                a.end_date,
                a.resolution_number,
                a.resolution_date,
                a.ogd_number,
                a.cause_code,
                a.target_position,
                " . ($joinTargetRanks ? "COALESCE(tr.name, CAST(a.target_rank_id AS CHAR))" : "CAST(a.target_rank_id AS CHAR)") . " AS rango_destino,
                " . ($joinTargetUnits ? "COALESCE(tu.name, a.legacy_unit_name, CAST(a.target_unit_id AS CHAR))" : "COALESCE(a.legacy_unit_name, CAST(a.target_unit_id AS CHAR))") . " AS unidad_destino,
                a.duration_value,
                a.duration_unit,
                a.incapacity_number,
                a.doctor_code,
                a.medical_facility,
                a.attachment_path,
                a.notes,
                a.migration_review_status
            FROM employee_actions a
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
        ";

        if ($joinActionTypes) {
            $sql .= ' LEFT JOIN action_types at ON at.id = a.action_type_id';
        }

        if ($joinTargetRanks) {
            $sql .= ' LEFT JOIN ranks tr ON tr.id = a.target_rank_id';
        }

        if ($joinTargetUnits) {
            $sql .= ' LEFT JOIN units tu ON tu.id = a.target_unit_id';
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.action_date DESC, a.id DESC LIMIT :limit';
        $params[':limit'] = ['value' => max(1, min($limit, 100)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function resolverEmployeeIds(string $buscar): array
    {
        $params = [];

        if (ctype_digit($buscar)) {
            $sql = "
                SELECT id
                FROM employees
                WHERE id = :buscar_numero
                   OR legacy_position = :buscar_numero
                   OR external_agent_number = :buscar_texto
                   OR document_number = :buscar_texto
                   OR document_number LIKE :buscar_prefijo
                LIMIT 25
            ";

            $params[':buscar_numero'] = ['value' => (int) $buscar, 'type' => PDO::PARAM_INT];
            $params[':buscar_texto'] = $buscar;
            $params[':buscar_prefijo'] = $buscar . '%';
        } else {
            $sql = "
                SELECT id
                FROM employees
                WHERE document_number LIKE :buscar_prefijo
                   OR first_name LIKE :buscar_prefijo
                   OR last_name LIKE :buscar_prefijo
                LIMIT 25
            ";

            $params[':buscar_prefijo'] = $buscar . '%';
        }

        try {
            $stmt = $this->db->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            return [];
        }
    }

    private function buscarGenerico(array $filtros, int $limit): array
    {
        $tabla = $this->tablaDetectada();
        $columnas = $this->columnas();

        if ($tabla === null || $columnas === []) {
            return [];
        }

        $sql = 'SELECT * FROM ' . $this->id($tabla) . ' WHERE 1 = 1';
        $params = [];

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $or = [];
            $i = 0;

            foreach ($columnas as $columna) {
                $nombre = (string) ($columna['Field'] ?? '');
                $tipo = strtolower((string) ($columna['Type'] ?? ''));

                if ($nombre === '' || str_contains($tipo, 'blob') || str_contains($tipo, 'binary')) {
                    continue;
                }

                $param = ':buscar_' . $i++;
                $or[] = 'CAST(' . $this->id($nombre) . ' AS CHAR) LIKE ' . $param;
                $params[$param] = $buscar . '%';
            }

            if ($or !== []) {
                $sql .= ' AND (' . implode(' OR ', $or) . ')';
            }
        }

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        $tipoColumna = $this->primeraColumnaExistente($columnas, [
            'tipo_accion',
            'action_type',
            'tipo',
            'accion',
            'codigo_accion',
            'codaccion',
            'cod_accion',
            'action_code',
            'action_type_id',
        ]);

        if ($tipo !== '' && $tipoColumna !== null) {
            $sql .= ' AND ' . $this->id($tipoColumna) . ' = :tipo';
            $params[':tipo'] = $tipo;
        }

        $fechaColumna = $this->primeraColumnaExistente($columnas, [
            'fecha',
            'fecha_accion',
            'fecaccion',
            'action_date',
            'created_at',
            'fecha_inicio',
            'fecini',
        ]);

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($fechaColumna !== null && $fechaDesde !== '') {
            $sql .= ' AND DATE(' . $this->id($fechaColumna) . ') >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde;
        }

        if ($fechaColumna !== null && $fechaHasta !== '') {
            $sql .= ' AND DATE(' . $this->id($fechaColumna) . ') <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta;
        }

        if ($fechaColumna !== null) {
            $sql .= ' ORDER BY ' . $this->id($fechaColumna) . ' DESC';
        } else {
            $sql .= ' ORDER BY 1 DESC';
        }

        $sql .= ' LIMIT :limit';
        $params[':limit'] = ['value' => max(1, min($limit, 100)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function tieneFiltros(array $filtros): bool
    {
        return trim((string) ($filtros['buscar'] ?? '')) !== ''
            || trim((string) ($filtros['tipo'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $stmt->bindValue($key, $value['value'], $value['type']);
                continue;
            }

            $stmt->bindValue($key, $value);
        }
    }

    private function tablaExiste(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
            $stmt->execute([':table' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function primeraColumnaExistente(array $columnas, array $candidatas): ?string
    {
        $disponibles = [];

        foreach ($columnas as $columna) {
            $nombre = (string) ($columna['Field'] ?? '');
            if ($nombre !== '') {
                $disponibles[strtolower($nombre)] = $nombre;
            }
        }

        foreach ($candidatas as $candidata) {
            $key = strtolower($candidata);
            if (isset($disponibles[$key])) {
                return $disponibles[$key];
            }
        }

        return null;
    }

    private function id(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
