<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class EstadisticasAccionesModel
{
    public function __construct(private PDO $db) {}

    public function tiposAccion(): array
    {
        return $this->db
            ->query('SELECT id, name FROM action_types ORDER BY name ASC')
            ->fetchAll();
    }

    public function total(array $filtros): int
    {
        [$where, $params] = $this->where($filtros);
        $sql = "SELECT COUNT(*) FROM employee_actions a LEFT JOIN action_types at ON at.id = a.action_type_id WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function porMes(array $filtros): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT
                YEAR(a.action_date) AS anio,
                MONTH(a.action_date) AS mes,
                LPAD(MONTH(a.action_date), 2, '0') AS mes_numero,
                COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS tipo_accion,
                COUNT(*) AS total
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            WHERE {$where}
              AND a.action_date IS NOT NULL
            GROUP BY anio, mes, mes_numero, tipo_accion
            ORDER BY anio DESC, mes DESC, tipo_accion ASC
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function porTipo(array $filtros): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT
                COALESCE(a.action_type_id, 0) AS codigo,
                COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS tipo_accion,
                COUNT(*) AS total
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            WHERE {$where}
            GROUP BY codigo, tipo_accion
            ORDER BY total DESC, tipo_accion ASC
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function where(array $filtros): array
    {
        $where = ['1 = 1'];
        $params = [];

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'a.action_type_id = :tipo';
            $params[':tipo'] = ['value' => (int) $tipo, 'type' => PDO::PARAM_INT];
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
