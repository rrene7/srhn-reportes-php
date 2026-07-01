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
        try {
            $stmt = $this->db->query("
                SELECT codigoran AS codigo, rangocorto AS nombre
                FROM tabran
                ORDER BY codigoran ASC
            ");

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function listarCuarteles(): array
    {
        $consultas = [
            "SELECT codigocuar AS codigo, descricuar AS nombre FROM tabcuar WHERE vigente = 1 ORDER BY codigocuar ASC",
            "SELECT codigocuar AS codigo, descricuar AS nombre FROM tabcuar ORDER BY codigocuar ASC",
            "SELECT codigocuar AS codigo, codigocuar AS nombre FROM tabcuar ORDER BY codigocuar ASC",
        ];

        foreach ($consultas as $sql) {
            try {
                $stmt = $this->db->query($sql);
                return $stmt->fetchAll();
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    public function listarEstados(): array
    {
        $consultas = [
            "SELECT codigo AS codigo, descripcion AS nombre FROM tabstatus ORDER BY codigo ASC",
            "SELECT codigosta AS codigo, descripsta AS nombre FROM tabstatus ORDER BY codigosta ASC",
            "SELECT estado AS codigo, estado AS nombre FROM tabstatus ORDER BY estado ASC",
        ];

        foreach ($consultas as $sql) {
            try {
                $stmt = $this->db->query($sql);
                return $stmt->fetchAll();
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    public function buscarPersonal(array $filtros): array
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

        usort($totales, fn ($a, $b) => strcmp($a['codigo'], $b['codigo']));

        return $totales;
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
