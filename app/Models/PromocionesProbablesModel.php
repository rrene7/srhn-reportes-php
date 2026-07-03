<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class PromocionesProbablesModel
{
    public function __construct(
        private PDO $db
    ) {}

    public function grupos(string $fechaBase, int $minimoIntegrantes, int $promocionInicial): array
    {
        $sql = "
            SELECT
                a.action_date AS fecha_nombramiento,
                COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') AS ogd,
                COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') AS resolucion_decreto,
                COALESCE(a.resolution_date, '') AS fecha_resolucion,
                COALESCE(tr.name, '') AS rango_nombramiento,
                COALESCE(a.target_rank_id, 0) AS target_rank_id,
                COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), 'SIN CODIGO') AS codigo_rango_legacy,
                COUNT(DISTINCT a.employee_id) AS total_integrantes
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN ranks tr ON tr.id = a.target_rank_id
            WHERE
                LOWER(COALESCE(at.name, '')) LIKE '%nombr%'
                AND a.action_date >= :fecha_base
                AND (
                    UPPER(COALESCE(tr.name, '')) LIKE '%AGENTE%'
                    OR TRIM(COALESCE(a.legacy_rank_or_charge_code, '')) IN ('130', '0130')
                )
                AND (
                    COALESCE(NULLIF(TRIM(a.ogd_number), ''), '0') <> '0'
                    OR COALESCE(NULLIF(TRIM(a.resolution_number), ''), '') <> ''
                )
            GROUP BY
                a.action_date,
                ogd,
                resolucion_decreto,
                fecha_resolucion,
                rango_nombramiento,
                target_rank_id,
                codigo_rango_legacy
            HAVING COUNT(DISTINCT a.employee_id) >= :minimo_integrantes
            ORDER BY
                a.action_date ASC,
                CAST(COALESCE(NULLIF(TRIM(a.ogd_number), ''), '0') AS UNSIGNED) ASC,
                ogd ASC,
                resolucion_decreto ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':fecha_base', $fechaBase);
        $stmt->bindValue(':minimo_integrantes', $minimoIntegrantes, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $numero = $promocionInicial;

        foreach ($rows as &$row) {
            $row['numero_promocion_probable'] = $numero;
            $row['nombre_hoja'] = $numero . 'AVA PROM';
            $numero++;
        }
        unset($row);

        return $rows;
    }

    public function integrantes(array $grupo): array
    {
        $sql = "
            SELECT
                e.legacy_position AS posicion,
                e.document_number AS cedula,
                UPPER(COALESCE(e.last_name, '')) AS apellidos,
                UPPER(COALESCE(e.first_name, '')) AS nombres,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_actual,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS dependencia_actual,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_actual,
                a.action_date AS fecha_nombramiento,
                COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') AS ogd,
                COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') AS resolucion_decreto,
                COALESCE(a.resolution_date, '') AS fecha_resolucion,
                COALESCE(tr.name, '') AS rango_nombramiento,
                COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), '') AS codigo_rango_legacy
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN ranks tr ON tr.id = a.target_rank_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE
                LOWER(COALESCE(at.name, '')) LIKE '%nombr%'
                AND a.action_date = :fecha_nombramiento
                AND COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') = :ogd
                AND COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') = :resolucion_decreto
                AND COALESCE(a.target_rank_id, 0) = :target_rank_id
                AND COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), 'SIN CODIGO') = :codigo_rango_legacy
                AND (
                    UPPER(COALESCE(tr.name, '')) LIKE '%AGENTE%'
                    OR TRIM(COALESCE(a.legacy_rank_or_charge_code, '')) IN ('130', '0130')
                )
            GROUP BY
                e.id,
                e.legacy_position,
                e.document_number,
                e.last_name,
                e.first_name,
                rango_actual,
                dependencia_actual,
                estado_actual,
                a.action_date,
                ogd,
                resolucion_decreto,
                fecha_resolucion,
                rango_nombramiento,
                codigo_rango_legacy
            ORDER BY CAST(COALESCE(e.legacy_position, 0) AS UNSIGNED) ASC, apellidos ASC, nombres ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':fecha_nombramiento', (string) ($grupo['fecha_nombramiento'] ?? ''));
        $stmt->bindValue(':ogd', (string) ($grupo['ogd'] ?? ''));
        $stmt->bindValue(':resolucion_decreto', (string) ($grupo['resolucion_decreto'] ?? ''));
        $stmt->bindValue(':target_rank_id', (int) ($grupo['target_rank_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':codigo_rango_legacy', (string) ($grupo['codigo_rango_legacy'] ?? ''));
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
