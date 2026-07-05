<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class VacacionesModel
{
    private const VACACIONES_ACTION_TYPE_ID = 4;

    public function __construct(private PDO $db) {}

    public function catalogos(): array
    {
        return [
            'rangos' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC')->fetchAll(),
            'unidades' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC')->fetchAll(),
            'estados' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC')->fetchAll(),
        ];
    }

    public function diagnostico(array $filtros): array
    {
        $base = $this->db->query("SELECT COUNT(*) FROM employee_actions")->fetchColumn();
        $tipo4 = $this->db->query("SELECT COUNT(*) FROM employee_actions WHERE action_type_id = 4")->fetchColumn();
        $porTipos = $this->db->query("
            SELECT a.action_type_id, COALESCE(at.name, '') AS nombre, COUNT(*) AS total, MIN(a.action_date) AS fecha_min, MAX(a.action_date) AS fecha_max
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            GROUP BY a.action_type_id, at.name
            ORDER BY total DESC
            LIMIT 10
        ")->fetchAll();

        [$where, $params] = $this->where($filtros);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total_filtrado
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
        ");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return [
            'total_employee_actions' => (int) $base,
            'total_action_type_4' => (int) $tipo4,
            'total_filtrado_actual' => (int) $stmt->fetchColumn(),
            'top_tipos' => $porTipos,
        ];
    }

    public function resumen(array $filtros): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN a.action_date IS NOT NULL THEN 1 ELSE 0 END) AS con_fecha_vacaciones,
                SUM(CASE WHEN a.action_date IS NULL THEN 1 ELSE 0 END) AS sin_fecha_vacaciones,
                SUM(CASE WHEN a.action_date IS NOT NULL AND a.action_date <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 1 ELSE 0 END) AS mas_de_un_anio,
                SUM(CASE WHEN a.action_date IS NOT NULL AND a.action_date <= DATE_SUB(CURDATE(), INTERVAL 2 YEAR) THEN 1 ELSE 0 END) AS mas_de_dos_anios
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
        ";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'con_fecha_vacaciones' => (int) ($row['con_fecha_vacaciones'] ?? 0),
            'sin_fecha_vacaciones' => (int) ($row['sin_fecha_vacaciones'] ?? 0),
            'mas_de_un_anio' => (int) ($row['mas_de_un_anio'] ?? 0),
            'mas_de_dos_anios' => (int) ($row['mas_de_dos_anios'] ?? 0),
        ];
    }

    public function porRango(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(r.legacy_code, '')", "COALESCE(r.name, e.legacy_rank_name, 'Sin rango')", 'CAST(codigo AS UNSIGNED) ASC, nombre ASC');
    }

    public function porDependencia(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(u.legacy_code, '')", "COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia')", 'codigo ASC, nombre ASC');
    }

    public function listado(array $filtros, int $limit = 300): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.document_number AS cedula,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                COALESCE(r.legacy_code, '') AS rango_codigo,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_nombre,
                COALESCE(u.legacy_code, '') AS unidad_codigo,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS unidad_nombre,
                COALESCE(s.legacy_code, e.legacy_status_code, '') AS estado_codigo,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_nombre,
                e.sex AS sexo,
                e.hire_date AS fecha_ingreso,
                a.action_date AS fecha_ultimas_vacaciones,
                a.start_date AS fecha_inicio_vacaciones,
                a.end_date AS fecha_fin_vacaciones,
                a.duration_value AS duracion,
                a.duration_unit AS unidad_duracion,
                a.resolution_number AS resolucion,
                a.ogd_number AS ogd,
                CASE WHEN a.action_date IS NULL THEN NULL ELSE DATEDIFF(CURDATE(), a.action_date) END AS dias_desde_vacaciones,
                CASE WHEN e.hire_date IS NULL THEN NULL ELSE TIMESTAMPDIFF(YEAR, e.hire_date, CURDATE()) END AS anios_servicio,
                CASE WHEN e.hire_date IS NULL THEN NULL ELSE ROUND(TIMESTAMPDIFF(DAY, e.hire_date, CURDATE()) * 2.5 / 30, 2) END AS dias_teoricos_generados
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            ORDER BY a.action_date DESC, a.id DESC
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
        $sql = "
            SELECT
                {$codigoSql} AS codigo,
                {$nombreSql} AS nombre,
                COUNT(*) AS total,
                SUM(CASE WHEN a.action_date IS NULL THEN 1 ELSE 0 END) AS sin_fecha,
                SUM(CASE WHEN a.action_date IS NOT NULL AND a.action_date <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 1 ELSE 0 END) AS mas_de_un_anio
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN employees e ON e.id = a.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            GROUP BY codigo, nombre
            ORDER BY {$orderBy}
        ";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function where(array $filtros): array
    {
        $where = ["(a.action_type_id = :vacaciones_tipo OR LOWER(COALESCE(at.name, '')) LIKE '%vacacion%')"];
        $params = [':vacaciones_tipo' => ['value' => self::VACACIONES_ACTION_TYPE_ID, 'type' => PDO::PARAM_INT]];

        $rangoDesde = trim((string) ($filtros['rango_desde'] ?? ''));
        $rangoHasta = trim((string) ($filtros['rango_hasta'] ?? ''));
        if ($rangoDesde !== '' && $rangoHasta !== '') {
            $desde = min((int) $rangoDesde, (int) $rangoHasta);
            $hasta = max((int) $rangoDesde, (int) $rangoHasta);
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) BETWEEN :rango_desde AND :rango_hasta';
            $params[':rango_desde'] = ['value' => $desde, 'type' => PDO::PARAM_INT];
            $params[':rango_hasta'] = ['value' => $hasta, 'type' => PDO::PARAM_INT];
        } elseif ($rangoDesde !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) >= :rango_desde';
            $params[':rango_desde'] = ['value' => (int) $rangoDesde, 'type' => PDO::PARAM_INT];
        } elseif ($rangoHasta !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) <= :rango_hasta';
            $params[':rango_hasta'] = ['value' => (int) $rangoHasta, 'type' => PDO::PARAM_INT];
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

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $where[] = 'a.action_date >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde;
        }

        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $where[] = 'a.action_date <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta;
        }

        $estadoVacaciones = trim((string) ($filtros['estado_vacaciones'] ?? 'todos'));
        if ($estadoVacaciones === 'sin_fecha') {
            $where[] = 'a.action_date IS NULL';
        } elseif ($estadoVacaciones === 'mas_un_anio') {
            $where[] = 'a.action_date IS NOT NULL AND a.action_date <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)';
        } elseif ($estadoVacaciones === 'mas_dos_anios') {
            $where[] = 'a.action_date IS NOT NULL AND a.action_date <= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)';
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $where[] = "(e.document_number LIKE :buscar OR e.first_name LIKE :buscar OR e.last_name LIKE :buscar OR e.external_agent_number LIKE :buscar OR CAST(e.legacy_position AS CHAR) LIKE :buscar OR a.resolution_number LIKE :buscar OR a.ogd_number LIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }

        return [implode(' AND ', $where), $params];
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
