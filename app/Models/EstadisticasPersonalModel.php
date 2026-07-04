<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class EstadisticasPersonalModel
{
    public function __construct(private PDO $db) {}

    public function catalogos(): array
    {
        return [
            'rangos' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC')->fetchAll(),
            'unidades' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC')->fetchAll(),
            'estados' => $this->db->query('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC')->fetchAll(),
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

    public function porRango(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(r.legacy_code, '')", "COALESCE(r.name, e.legacy_rank_name, 'Sin rango')", 'codigo ASC');
    }

    public function porDependencia(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(u.legacy_code, '')", "COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia')", 'codigo ASC, nombre ASC');
    }

    public function porEstatus(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(s.legacy_code, e.legacy_status_code, '')", "COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado')", 'codigo ASC, nombre ASC');
    }

    public function porSexo(array $filtros): array
    {
        return $this->agrupar($filtros, "COALESCE(NULLIF(e.sex, ''), 'SIN SEXO')", "COALESCE(NULLIF(e.sex, ''), 'SIN SEXO')", 'codigo ASC');
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

        $rangoDesde = trim((string) ($filtros['rango_desde'] ?? ''));
        $rangoHasta = trim((string) ($filtros['rango_hasta'] ?? ''));
        if ($rangoDesde !== '' && $rangoHasta === '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) = CAST(:rango_desde AS UNSIGNED)';
            $params[':rango_desde'] = $rangoDesde;
        } elseif ($rangoDesde === '' && $rangoHasta !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) = CAST(:rango_hasta AS UNSIGNED)';
            $params[':rango_hasta'] = $rangoHasta;
        } else {
            if ($rangoDesde !== '') {
                $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) >= CAST(:rango_desde AS UNSIGNED)';
                $params[':rango_desde'] = $rangoDesde;
            }
            if ($rangoHasta !== '') {
                $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) <= CAST(:rango_hasta AS UNSIGNED)';
                $params[':rango_hasta'] = $rangoHasta;
            }
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
            $where[] = "(COALESCE(s.legacy_code, e.legacy_status_code, '') = '10' OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) = 'ACTIVO')";
        } elseif ($estadoModo === 'especifico' && $estado !== '') {
            $where[] = "COALESCE(s.legacy_code, e.legacy_status_code, '') = :estado";
            $params[':estado'] = $estado;
        }

        return [implode(' AND ', $where), $params];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }
}
