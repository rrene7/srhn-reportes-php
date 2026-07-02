<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class ProcedenciaOficialModel
{
    public function __construct(
        private PDO $db
    ) {}

    public function buscar(array $filtros = [], int $limit = 500): array
    {
        $procedencia = trim((string) ($filtros['procedencia'] ?? ''));
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        $sql = "
            SELECT
                e.id,
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.document_number AS cedula,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                e.sex AS sexo,
                COALESCE(r.legacy_code, '') AS rango_codigo,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_actual,
                COALESCE(u.legacy_code, '') AS unidad_codigo,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS unidad_actual,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM employee_actions a
                        LEFT JOIN ranks ar ON ar.id = a.target_rank_id
                        WHERE a.employee_id = e.id
                          AND a.deleted_at IS NULL
                          AND (
                                UPPER(COALESCE(ar.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                             OR UPPER(COALESCE(a.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                          )
                    )
                    THEN 'OFICIAL DE TROPA'
                    ELSE 'OFICIAL DE ESCUELA / SIN TROPA PREVIA REGISTRADA'
                END AS procedencia_oficial,
                COALESCE((
                    SELECT COALESCE(ar2.name, a2.legacy_rank_or_charge_code, '')
                    FROM employee_actions a2
                    LEFT JOIN ranks ar2 ON ar2.id = a2.target_rank_id
                    WHERE a2.employee_id = e.id
                      AND a2.deleted_at IS NULL
                      AND (
                            UPPER(COALESCE(ar2.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                         OR UPPER(COALESCE(a2.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                      )
                    ORDER BY a2.action_date ASC, a2.id ASC
                    LIMIT 1
                ), '') AS evidencia_tropa,
                COALESCE((
                    SELECT a3.action_date
                    FROM employee_actions a3
                    LEFT JOIN ranks ar3 ON ar3.id = a3.target_rank_id
                    WHERE a3.employee_id = e.id
                      AND a3.deleted_at IS NULL
                      AND (
                            UPPER(COALESCE(ar3.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                         OR UPPER(COALESCE(a3.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                      )
                    ORDER BY a3.action_date ASC, a3.id ASC
                    LIMIT 1
                ), '') AS fecha_evidencia,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM employee_actions a4
                        LEFT JOIN ranks ar4 ON ar4.id = a4.target_rank_id
                        WHERE a4.employee_id = e.id
                          AND a4.deleted_at IS NULL
                          AND (
                                UPPER(COALESCE(ar4.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                             OR UPPER(COALESCE(a4.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                          )
                    )
                    THEN 'Tiene historial previo con rango de tropa en acciones de personal.'
                    ELSE 'No se encontró historial previo de tropa en acciones de personal.'
                END AS motivo
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            WHERE UPPER(COALESCE(r.name, e.legacy_rank_name, '')) REGEXP 'SUB[- ]?TENIENTE|TENIENTE|CAPITAN|CAPITÁN|MAYOR|SUB[- ]?COMISIONADO|COMISIONADO|SUB[- ]?DIRECTOR|DIRECTOR'
        ";

        $params = [];

        if ($buscar !== '') {
            $sql .= " AND (
                e.document_number LIKE :buscar_documento
                OR e.first_name LIKE :buscar_nombre
                OR e.last_name LIKE :buscar_apellido
                OR e.external_agent_number LIKE :buscar_agente
                OR CAST(e.legacy_position AS CHAR) LIKE :buscar_posicion
                OR CAST(e.id AS CHAR) LIKE :buscar_id
            )";
            $like = $buscar . '%';
            $params[':buscar_documento'] = $like;
            $params[':buscar_nombre'] = $like;
            $params[':buscar_apellido'] = $like;
            $params[':buscar_agente'] = $like;
            $params[':buscar_posicion'] = $like;
            $params[':buscar_id'] = $like;
        }

        if ($procedencia === 'tropa') {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM employee_actions f
                LEFT JOIN ranks fr ON fr.id = f.target_rank_id
                WHERE f.employee_id = e.id
                  AND f.deleted_at IS NULL
                  AND (
                        UPPER(COALESCE(fr.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                     OR UPPER(COALESCE(f.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                  )
            )";
        }

        if ($procedencia === 'escuela') {
            $sql .= " AND NOT EXISTS (
                SELECT 1
                FROM employee_actions f
                LEFT JOIN ranks fr ON fr.id = f.target_rank_id
                WHERE f.employee_id = e.id
                  AND f.deleted_at IS NULL
                  AND (
                        UPPER(COALESCE(fr.name, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                     OR UPPER(COALESCE(f.legacy_rank_or_charge_code, '')) REGEXP 'AGENTE|CABO|SARGENTO|SGTO'
                  )
            )";
        }

        $sql .= " ORDER BY r.sort_order ASC, r.legacy_code ASC, e.legacy_position ASC LIMIT :limit";
        $params[':limit'] = ['value' => max(1, min($limit, 1000)), 'type' => PDO::PARAM_INT];

        try {
            $stmt = $this->db->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function resumen(array $rows): array
    {
        $resumen = [
            'total' => count($rows),
            'escuela' => 0,
            'tropa' => 0,
        ];

        foreach ($rows as $row) {
            if (($row['procedencia_oficial'] ?? '') === 'OFICIAL DE TROPA') {
                $resumen['tropa']++;
                continue;
            }

            $resumen['escuela']++;
        }

        return $resumen;
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
}
