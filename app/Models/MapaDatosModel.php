<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class MapaDatosModel
{
    public function __construct(private PDO $db) {}

    public function resumenGeneral(): array
    {
        return [
            'funcionarios' => $this->scalar('SELECT COUNT(*) FROM employees'),
            'acciones' => $this->scalar('SELECT COUNT(*) FROM employee_actions'),
            'rangos' => $this->scalar('SELECT COUNT(*) FROM ranks'),
            'dependencias' => $this->scalar('SELECT COUNT(*) FROM units'),
            'estados_personal' => $this->scalar('SELECT COUNT(*) FROM statuses'),
            'tipos_accion' => $this->scalar('SELECT COUNT(*) FROM action_types'),
            'acciones_activas' => $this->scalar('SELECT COUNT(*) FROM employee_actions WHERE deleted_at IS NULL'),
            'acciones_eliminadas' => $this->scalar('SELECT COUNT(*) FROM employee_actions WHERE deleted_at IS NOT NULL'),
        ];
    }

    public function personalPorEstado(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,\n                COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,\n                COUNT(*) AS total\n            FROM employees e\n            LEFT JOIN statuses s ON s.id = e.status_id\n            GROUP BY codigo, nombre\n            ORDER BY total DESC, codigo ASC\n        ");
    }

    public function personalPorRango(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(r.legacy_code, 'SIN') AS codigo,\n                COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,\n                COUNT(*) AS total\n            FROM employees e\n            LEFT JOIN ranks r ON r.id = e.rank_id\n            GROUP BY codigo, nombre\n            ORDER BY CAST(codigo AS UNSIGNED) ASC, total DESC\n        ");
    }

    public function personalPorSexo(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN') AS codigo,\n                CASE COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN')\n                    WHEN 'M' THEN 'Masculino'\n                    WHEN 'F' THEN 'Femenino'\n                    ELSE 'Sin dato'\n                END AS nombre,\n                COUNT(*) AS total\n            FROM employees e\n            GROUP BY codigo, nombre\n            ORDER BY total DESC\n        ");
    }

    public function personalPorTipoPolicia(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(NULLIF(TRIM(e.external_user_type), ''), 'SIN') AS codigo,\n                COALESCE(NULLIF(TRIM(e.external_user_type), ''), 'Sin tipo') AS nombre,\n                COUNT(*) AS total\n            FROM employees e\n            GROUP BY codigo, nombre\n            ORDER BY total DESC\n        ");
    }

    public function zonas(): array
    {
        return $this->query("\n            SELECT\n                CASE\n                    WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN'\n                    ELSE LEFT(u.legacy_code, 2)\n                END AS codigo,\n                CONCAT('Zona / nivel 1 ', CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 2) END) AS nombre,\n                COUNT(DISTINCT u.id) AS dependencias,\n                COUNT(e.id) AS personal\n            FROM units u\n            LEFT JOIN employees e ON e.unit_id = u.id\n            GROUP BY codigo, nombre\n            ORDER BY codigo ASC\n        ");
    }

    public function areas(): array
    {
        return $this->query("\n            SELECT\n                CASE\n                    WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN'\n                    ELSE LEFT(u.legacy_code, 4)\n                END AS codigo,\n                CONCAT('Área / nivel 2 ', CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 4) END) AS nombre,\n                COUNT(DISTINCT u.id) AS dependencias,\n                COUNT(e.id) AS personal\n            FROM units u\n            LEFT JOIN employees e ON e.unit_id = u.id\n            GROUP BY codigo, nombre\n            ORDER BY codigo ASC\n        ");
    }

    public function dependencias(int $limit = 200): array
    {
        return $this->query("\n            SELECT\n                COALESCE(u.legacy_code, 'SIN') AS codigo,\n                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia') AS nombre,\n                COUNT(e.id) AS personal,\n                SUM(CASE WHEN TRIM(COALESCE(s.legacy_code, e.legacy_status_code, '')) IN ('10','010') THEN 1 ELSE 0 END) AS activos\n            FROM units u\n            LEFT JOIN employees e ON e.unit_id = u.id\n            LEFT JOIN statuses s ON s.id = e.status_id\n            GROUP BY codigo, nombre\n            ORDER BY personal DESC, codigo ASC\n            LIMIT " . max(1, min($limit, 500)));
    }

    public function accionesPorTipo(): array
    {
        return $this->query("\n            SELECT\n                a.action_type_id AS codigo,\n                COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,\n                COUNT(*) AS total,\n                MIN(a.action_date) AS fecha_minima,\n                MAX(a.action_date) AS fecha_maxima\n            FROM employee_actions a\n            LEFT JOIN action_types at ON at.id = a.action_type_id\n            GROUP BY a.action_type_id, at.name\n            ORDER BY total DESC\n        ");
    }

    public function accionesPorEstadoRevision(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(NULLIF(TRIM(a.migration_review_status), ''), 'SIN') AS codigo,\n                COALESCE(NULLIF(TRIM(a.migration_review_status), ''), 'Sin estado de revisión') AS nombre,\n                COUNT(*) AS total\n            FROM employee_actions a\n            GROUP BY codigo, nombre\n            ORDER BY total DESC\n        ");
    }

    public function accionesPorAnio(): array
    {
        return $this->query("\n            SELECT\n                CASE WHEN a.action_date IS NULL THEN 'SIN FECHA' ELSE CAST(YEAR(a.action_date) AS CHAR) END AS codigo,\n                CASE WHEN a.action_date IS NULL THEN 'Sin fecha' ELSE CAST(YEAR(a.action_date) AS CHAR) END AS nombre,\n                COUNT(*) AS total\n            FROM employee_actions a\n            GROUP BY codigo, nombre\n            ORDER BY codigo DESC\n            LIMIT 80\n        ");
    }

    public function catalogoEstados(): array
    {
        return $this->query("\n            SELECT\n                COALESCE(s.legacy_code, CAST(s.id AS CHAR)) AS codigo,\n                s.name AS nombre,\n                COUNT(e.id) AS personal\n            FROM statuses s\n            LEFT JOIN employees e ON e.status_id = s.id\n            GROUP BY codigo, nombre\n            ORDER BY codigo ASC\n        ");
    }

    private function scalar(string $sql): int
    {
        try {
            return (int) $this->db->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function query(string $sql): array
    {
        try {
            return $this->db->query($sql)->fetchAll();
        } catch (Throwable $e) {
            return [[
                'codigo' => 'ERROR',
                'nombre' => $e->getMessage(),
                'total' => 0,
            ]];
        }
    }
}
