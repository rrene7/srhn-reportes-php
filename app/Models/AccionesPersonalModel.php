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
        ]);

        if ($tipoColumna === null) {
            return [];
        }

        try {
            $sql = 'SELECT DISTINCT ' . $this->id($tipoColumna) . ' AS tipo FROM ' . $this->id($tabla) . ' WHERE ' . $this->id($tipoColumna) . ' IS NOT NULL ORDER BY ' . $this->id($tipoColumna) . ' ASC LIMIT 200';
            $stmt = $this->db->query($sql);

            return array_values(array_filter(array_map(static fn (array $row): string => trim((string) ($row['tipo'] ?? '')), $stmt->fetchAll())));
        } catch (Throwable) {
            return [];
        }
    }

    public function buscar(array $filtros, int $limit = 100): array
    {
        $tabla = $this->tablaDetectada();
        $columnas = $this->columnas();

        if ($tabla === null || $columnas === []) {
            return [];
        }

        $nombresColumnas = array_map(static fn (array $col): string => (string) ($col['Field'] ?? ''), $columnas);
        $nombresColumnas = array_values(array_filter($nombresColumnas));

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
                $params[$param] = '%' . $buscar . '%';
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
        $params[':limit'] = ['value' => max(1, min($limit, 500)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $stmt->bindValue($key, $value['value'], $value['type']);
                continue;
            }

            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function metadata(): array
    {
        $tabla = $this->tablaDetectada();
        $columnas = $this->columnas();

        return [
            'tabla' => $tabla,
            'columnas' => array_map(static fn (array $col): string => (string) ($col['Field'] ?? ''), $columnas),
            'tipos' => $this->tiposAccion(),
        ];
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
