<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class ReportePersonalModel
{
    public function __construct(
        private PDO $db
    ) {}

    public function listarRangos(): array
    {
        if ($this->usarModeloNormalizado()) {
            return $this->consultarCatalogo("
                SELECT legacy_code AS codigo, name AS nombre
                FROM ranks
                ORDER BY sort_order ASC, legacy_code ASC
            ");
        }

        return $this->consultarCatalogo("
            SELECT codigoran AS codigo, rangocorto AS nombre
            FROM tabran
            ORDER BY codigoran ASC
        ");
    }

    public function listarCuarteles(): array
    {
        if ($this->usarModeloNormalizado()) {
            return $this->consultarCatalogo("
                SELECT legacy_code AS codigo, name AS nombre
                FROM units
                ORDER BY legacy_code ASC, name ASC
            ");
        }

        $consultas = [
            "SELECT codigocuar AS codigo, descricuar AS nombre FROM tabcuar WHERE vigente = 1 ORDER BY codigocuar ASC",
            "SELECT codigocuar AS codigo, descricuar AS nombre FROM tabcuar ORDER BY codigocuar ASC",
        ];

        foreach ($consultas as $sql) {
            $rows = $this->consultarCatalogo($sql);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    public function listarEstados(): array
    {
        if ($this->usarModeloNormalizado()) {
            return $this->consultarCatalogo("
                SELECT legacy_code AS codigo, name AS nombre
                FROM statuses
                ORDER BY legacy_code ASC
            ");
        }

        $consultas = [
            "SELECT codigo AS codigo, descripcion AS nombre FROM tabstatus ORDER BY codigo ASC",
            "SELECT codigosta AS codigo, descripsta AS nombre FROM tabstatus ORDER BY codigosta ASC",
        ];

        foreach ($consultas as $sql) {
            $rows = $this->consultarCatalogo($sql);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    public function buscarPersonal(array $filtros): array
    {
        if ($this->usarModeloNormalizado()) {
            return $this->buscarPersonalNormalizado($filtros);
        }

        return $this->buscarPersonalLegacy($filtros);
    }

    public function totalesPorCampo(array $rows, string $field): array
    {
        $totales = [];

        foreach ($rows as $row) {
            $codigo = (string) ($row[$field] ?? 'SIN DATO');
            $nombreKey = $field . '_nombre';
            $nombre = (string) ($row[$nombreKey] ?? $codigo);
            $key = $codigo . '|' . $nombre;

            if (!isset($totales[$key])) {
                $totales[$key] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'total' => 0,
                ];
            }

            $totales[$key]['total']++;
        }

        usort($totales, fn ($a, $b) => strcmp((string) $a['codigo'], (string) $b['codigo']));

        return $totales;
    }

    private function buscarPersonalNormalizado(array $filtros): array
    {
        $sql = "
            SELECT
                COALESCE(r.legacy_code, '') AS rango,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_nombre,
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.first_name AS nombre,
                e.last_name AS apellido,
                COALESCE(u.legacy_code, '') AS cuartel,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS cuartel_nombre,
                e.document_number AS cedula,
                e.sex AS sexo,
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS posicipn,
                e.external_profile_id AS posicimi,
                e.hire_date AS fecing,
                e.promotion_date AS fecascen,
                e.status_date AS fectras,
                e.vacation_date AS fecvac,
                COALESCE(s.legacy_code, e.legacy_status_code, '') AS estado,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_nombre,
                e.birth_date AS fecnac,
                e.external_user_type AS tipopol
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filtros['rango_desde']) && !empty($filtros['rango_hasta'])) {
            $sql .= " AND r.legacy_code BETWEEN :rango_desde AND :rango_hasta";
            $params[':rango_desde'] = $filtros['rango_desde'];
            $params[':rango_hasta'] = $filtros['rango_hasta'];
        }

        if (!empty($filtros['cuartel_desde']) && !empty($filtros['cuartel_hasta'])) {
            $sql .= " AND u.legacy_code BETWEEN :cuartel_desde AND :cuartel_hasta";
            $params[':cuartel_desde'] = $filtros['cuartel_desde'];
            $params[':cuartel_hasta'] = $filtros['cuartel_hasta'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND s.legacy_code = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['buscar'])) {
            $sql .= " AND (
                e.document_number LIKE :buscar
                OR e.first_name LIKE :buscar
                OR e.last_name LIKE :buscar
                OR e.external_agent_number LIKE :buscar
                OR CAST(e.legacy_position AS CHAR) LIKE :buscar
                OR CAST(e.id AS CHAR) LIKE :buscar
            )";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        $sql .= " ORDER BY r.sort_order ASC, u.legacy_code ASC, e.last_name ASC, e.first_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function buscarPersonalLegacy(array $filtros): array
    {
        $sql = "
            SELECT
                d.rango,
                d.nemp,
                d.nombre,
                d.apellido,
                d.cuartel,
                d.cedula,
                d.sexo,
                d.posicipn,
                d.posicimi,
                d.fecing,
                d.fecascen,
                d.fectras,
                d.fecvac,
                d.estado,
                d.fecnac,
                d.tipopol
            FROM dota d
            WHERE d.nemp <> 0
              AND d.estado NOT IN ('00','01','02','03')
        ";

        $params = [];

        if (!empty($filtros['rango_desde']) && !empty($filtros['rango_hasta'])) {
            $sql .= " AND d.rango BETWEEN :rango_desde AND :rango_hasta";
            $params[':rango_desde'] = $filtros['rango_desde'];
            $params[':rango_hasta'] = $filtros['rango_hasta'];
        }

        if (!empty($filtros['cuartel_desde']) && !empty($filtros['cuartel_hasta'])) {
            $sql .= " AND d.cuartel BETWEEN :cuartel_desde AND :cuartel_hasta";
            $params[':cuartel_desde'] = $filtros['cuartel_desde'];
            $params[':cuartel_hasta'] = $filtros['cuartel_hasta'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND d.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['buscar'])) {
            $sql .= " AND (
                d.cedula LIKE :buscar
                OR d.nombre LIKE :buscar
                OR d.apellido LIKE :buscar
                OR CAST(d.nemp AS CHAR) LIKE :buscar
            )";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        $sql .= " ORDER BY d.rango ASC, d.cuartel ASC, d.apellido ASC, d.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        return $this->enriquecerConCatalogos($rows);
    }

    private function usarModeloNormalizado(): bool
    {
        try {
            if (!$this->tablaExiste('employees')) {
                return false;
            }

            $stmt = $this->db->query('SELECT COUNT(*) AS total FROM employees');
            $row = $stmt->fetch();

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function tablaExiste(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE :table');
            $stmt->execute([':table' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function consultarCatalogo(string $sql): array
    {
        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function enriquecerConCatalogos(array $rows): array
    {
        $rangos = $this->mapearCatalogo($this->listarRangos());
        $cuarteles = $this->mapearCatalogo($this->listarCuarteles());
        $estados = $this->mapearCatalogo($this->listarEstados());

        foreach ($rows as &$row) {
            $rango = (string) ($row['rango'] ?? '');
            $cuartel = (string) ($row['cuartel'] ?? '');
            $estado = (string) ($row['estado'] ?? '');

            $row['rango_nombre'] = $rangos[$rango] ?? $rango;
            $row['cuartel_nombre'] = $cuarteles[$cuartel] ?? $cuartel;
            $row['estado_nombre'] = $estados[$estado] ?? $estado;
        }

        return $rows;
    }

    private function mapearCatalogo(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $codigo = (string) ($item['codigo'] ?? '');
            $nombre = (string) ($item['nombre'] ?? $codigo);

            if ($codigo !== '') {
                $map[$codigo] = $nombre;
            }
        }

        return $map;
    }
}
