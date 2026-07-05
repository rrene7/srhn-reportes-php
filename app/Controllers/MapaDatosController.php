<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MapaDatosModel;
use App\Support\Database;
use App\Support\SimpleXlsxWriter;
use App\Support\View;
use PDO;
use Throwable;

final class MapaDatosController
{
    private MapaDatosModel $model;

    public function __construct()
    {
        $this->model = new MapaDatosModel(Database::connect());
    }

    public function index(): void
    {
        View::render('reportes/mapa_datos_explorador_v2', [
            'title' => 'Mapa General de Datos',
        ]);
    }

    public function ejemplo(): void
    {
        View::render('reportes/mapa_datos_ejemplo', [
            'title' => 'Ejemplo Real Mapa de Datos',
        ]);
    }

    public function exportarExcel(): void
    {
        $db = Database::connect();
        $writer = new SimpleXlsxWriter();

        $roots = $db->query("\n            SELECT id, legacy_code, name\n            FROM units\n            WHERE parent_id IS NULL OR parent_id = 0\n            ORDER BY legacy_code ASC, name ASC\n        ")->fetchAll(PDO::FETCH_ASSOC);

        $writer->addSheet('Resumen', [[
            'Total unidades' => $this->scalar($db, 'SELECT COUNT(*) FROM units'),
            'Unidades sin padre' => count($roots),
            'Funcionarios' => $this->scalar($db, 'SELECT COUNT(*) FROM employees'),
            'Nota' => 'Libro para auditar padres, hijas reales por parent_id y posibles hijas por coincidencia de nombre.',
        ]]);

        $writer->addSheet('Unidades sin padre', $this->rootRows($db, $roots));

        $grupos = [
            ['Direccion General', ['DIRECCION GENERAL', 'DIRECCIÓN GENERAL', 'DIR.GRAL', 'DIR. GRAL', 'DIR.GEN', 'DIR. GEN', 'DIRECCION GRAL']],
            ['Panama Oeste', ['PANAMA OESTE', 'PANAMÁ OESTE', 'PMA.OESTE', 'PMA OESTE', 'CHORRERA', 'ARRAIJAN']],
            ['Chiriqui', ['CHIRIQUI', 'CHIRIQUÍ', 'DAVID', 'BOQUETE']],
            ['Colon', ['COLON', 'COLÓN']],
            ['Cocle', ['COCLE', 'COCLÉ']],
            ['Veraguas', ['VERAGUAS']],
            ['Herrera', ['HERRERA']],
            ['Los Santos', ['LOS SANTOS']],
            ['Bocas del Toro', ['BOCAS DEL TORO', 'B.TORO']],
            ['Darien', ['DARIEN', 'DARIÉN']],
            ['San Miguelito', ['SAN MIGUELITO', 'S.MIGUELITO', 'S.MGLTO']],
            ['Panama Este', ['PANAMA ESTE', 'PANAMÁ ESTE', 'PMA.ESTE']],
            ['Panama Norte', ['PANAMA NORTE', 'PANAMÁ NORTE', 'PMA.NORTE']],
            ['Metro Oeste', ['METRO OESTE', 'M.OESTE']],
            ['Metro Norte', ['METRO NORTE', 'M.NORTE']],
            ['Metro Este', ['METRO ESTE', 'M.ESTE']],
            ['Telematica', ['TELEMATICA', 'TELEMÁTICA', 'TELEM']],
        ];

        foreach ($grupos as [$nombre, $patrones]) {
            $writer->addSheet($nombre, $this->groupRows($db, $nombre, $patrones));
        }

        $candidatas = $db->query("\n            SELECT id, legacy_code, name\n            FROM units\n            WHERE (parent_id IS NULL OR parent_id = 0)\n              AND (\n                    UPPER(name) LIKE '%ZONA%'\n                 OR UPPER(name) LIKE 'DIR%'\n                 OR UPPER(name) LIKE '%DIRECCION%'\n                 OR UPPER(name) LIKE '%DIRECCIÓN%'\n              )\n            ORDER BY legacy_code ASC, name ASC\n            LIMIT 80\n        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidatas as $root) {
            $rows = $this->treeAndRelatedRows($db, (int) $root['id'], (string) $root['name']);
            $writer->addSheet((string) $root['legacy_code'] . ' ' . (string) $root['name'], $rows);
        }

        $writer->output('mapa_zonas_direcciones_ordenado_' . date('Ymd_His') . '.xlsx');
    }

    public function diagnostico(): void
    {
        View::render('reportes/mapa_datos_ejemplo', [
            'title' => 'Ejemplo Real Mapa de Datos',
        ]);
    }

    private function rootRows(PDO $db, array $roots): array
    {
        $rows = [];
        foreach ($roots as $root) {
            $rows[] = [
                'Tipo' => 'UNIDAD SIN PADRE',
                'ID' => (int) $root['id'],
                'Parent_ID_actual' => '',
                'Codigo' => (string) $root['legacy_code'],
                'Nombre' => (string) $root['name'],
                'Padre actual' => '',
                'Hijos reales' => $this->scalar($db, 'SELECT COUNT(*) FROM units WHERE parent_id = :id', [':id' => (int) $root['id']]),
                'Personal directo' => $this->scalar($db, 'SELECT COUNT(*) FROM employees WHERE unit_id = :id', [':id' => (int) $root['id']]),
                'Revision sugerida' => 'Revisar si debe quedar como padre raiz o si pertenece a otra direccion/zona.',
            ];
        }
        return $rows;
    }

    private function groupRows(PDO $db, string $grupo, array $patterns): array
    {
        $where = [];
        $params = [];
        foreach ($patterns as $i => $pattern) {
            $key = ':p' . $i;
            $where[] = 'UPPER(COALESCE(u.name, \'\')) LIKE ' . $key;
            $params[$key] = '%' . mb_strtoupper($pattern, 'UTF-8') . '%';
        }

        $sql = "\n            SELECT\n                u.id,\n                u.parent_id,\n                u.legacy_code,\n                u.name,\n                pu.legacy_code AS padre_codigo,\n                pu.name AS padre_nombre,\n                (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,\n                (SELECT COUNT(*) FROM employees e WHERE e.unit_id = u.id) AS personal\n            FROM units u\n            LEFT JOIN units pu ON pu.id = u.parent_id\n            WHERE " . implode(' OR ', $where) . "\n            ORDER BY\n                CASE WHEN u.parent_id IS NULL OR u.parent_id = 0 THEN 0 ELSE 1 END,\n                u.legacy_code ASC,\n                u.name ASC\n            LIMIT 5000\n        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($items as $item) {
            $sinPadre = empty($item['parent_id']);
            $rows[] = [
                'Grupo' => $grupo,
                'Tipo' => $sinPadre ? 'SIN PADRE / POSIBLE PADRE' : 'HIJA REAL DE OTRO PADRE',
                'ID' => (int) $item['id'],
                'Parent_ID_actual' => $item['parent_id'] ?? '',
                'Codigo' => (string) $item['legacy_code'],
                'Nombre' => (string) $item['name'],
                'Padre actual' => trim((string) ($item['padre_codigo'] ?? '') . ' ' . (string) ($item['padre_nombre'] ?? '')),
                'Hijos reales' => (int) $item['hijos'],
                'Personal directo' => (int) $item['personal'],
                'Revision sugerida' => $sinPadre
                    ? 'Si pertenece a ' . $grupo . ', asignar parent_id al padre correcto.'
                    : 'Ya tiene padre. Verificar si el padre actual corresponde al grupo.',
            ];
        }

        return $rows ?: [[
            'Grupo' => $grupo,
            'Tipo' => 'SIN COINCIDENCIAS',
            'ID' => '',
            'Parent_ID_actual' => '',
            'Codigo' => '',
            'Nombre' => '',
            'Padre actual' => '',
            'Hijos reales' => 0,
            'Personal directo' => 0,
            'Revision sugerida' => 'No se encontraron unidades con los patrones de este grupo.',
        ]];
    }

    private function treeAndRelatedRows(PDO $db, int $rootId, string $rootName): array
    {
        $tree = $this->treeRows($db, $rootId);
        $treeIds = [];
        foreach ($tree as $row) {
            if (isset($row['ID']) && is_numeric($row['ID'])) {
                $treeIds[] = (int) $row['ID'];
            }
        }

        $rows = [];
        foreach ($tree as $row) {
            $rows[] = [
                'Tipo' => 'HIJA REAL POR parent_id',
                'Nivel' => $row['Nivel'] ?? 0,
                'ID' => $row['ID'] ?? '',
                'Parent_ID_actual' => $row['Parent_ID'] ?? '',
                'Codigo' => $row['Codigo'] ?? '',
                'Nombre' => $row['Nombre'] ?? '',
                'Padre actual' => '',
                'Hijos reales' => $row['Hijos'] ?? 0,
                'Personal directo' => $row['Personal_directo'] ?? 0,
                'Revision sugerida' => 'Relación real existente por parent_id.',
            ];
        }

        $related = $this->relatedRows($db, $rootName, $treeIds, $rootId);
        foreach ($related as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function relatedRows(PDO $db, string $name, array $excludeIds, int $rootId): array
    {
        $tokens = $this->tokens($name);
        if ($tokens === []) {
            return [];
        }

        $where = [];
        $params = [':root_id' => $rootId];
        foreach (array_slice($tokens, 0, 5) as $i => $token) {
            $key = ':t' . $i;
            $where[] = 'UPPER(COALESCE(u.name, \'\')) LIKE ' . $key;
            $params[$key] = '%' . $token . '%';
        }

        $excludeSql = '';
        foreach ($excludeIds as $i => $id) {
            $key = ':ex' . $i;
            $params[$key] = $id;
            $excludeSql .= ' AND u.id <> ' . $key;
        }

        $stmt = $db->prepare("\n            SELECT\n                u.id,\n                u.parent_id,\n                u.legacy_code,\n                u.name,\n                pu.legacy_code AS padre_codigo,\n                pu.name AS padre_nombre,\n                (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,\n                (SELECT COUNT(*) FROM employees e WHERE e.unit_id = u.id) AS personal\n            FROM units u\n            LEFT JOIN units pu ON pu.id = u.parent_id\n            WHERE u.id <> :root_id\n              {$excludeSql}\n              AND (" . implode(' OR ', $where) . ")\n            ORDER BY\n                CASE WHEN u.parent_id IS NULL OR u.parent_id = 0 THEN 0 ELSE 1 END,\n                u.legacy_code ASC,\n                u.name ASC\n            LIMIT 1000\n        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $rows[] = [
                'Tipo' => 'RELACIONADA / POSIBLE HIJA',
                'Nivel' => '',
                'ID' => (int) $item['id'],
                'Parent_ID_actual' => $item['parent_id'] ?? '',
                'Codigo' => (string) $item['legacy_code'],
                'Nombre' => (string) $item['name'],
                'Padre actual' => trim((string) ($item['padre_codigo'] ?? '') . ' ' . (string) ($item['padre_nombre'] ?? '')),
                'Hijos reales' => (int) $item['hijos'],
                'Personal directo' => (int) $item['personal'],
                'Revision sugerida' => 'Coincide por nombre con el padre/grupo, pero no cuelga de él por parent_id. Revisar si debe ser hija.',
            ];
        }
        return $rows;
    }

    private function treeRows(PDO $db, int $rootId): array
    {
        try {
            $stmt = $db->prepare("\n                WITH RECURSIVE arbol AS (\n                    SELECT id, parent_id, legacy_code, name, 0 AS nivel\n                    FROM units\n                    WHERE id = :id\n                    UNION ALL\n                    SELECT u.id, u.parent_id, u.legacy_code, u.name, a.nivel + 1\n                    FROM units u\n                    INNER JOIN arbol a ON u.parent_id = a.id\n                )\n                SELECT\n                    a.nivel AS Nivel,\n                    a.id AS ID,\n                    a.parent_id AS Parent_ID,\n                    a.legacy_code AS Codigo,\n                    a.name AS Nombre,\n                    (SELECT COUNT(*) FROM units h WHERE h.parent_id = a.id) AS Hijos,\n                    (SELECT COUNT(*) FROM employees e WHERE e.unit_id = a.id) AS Personal_directo\n                FROM arbol a\n                ORDER BY a.nivel ASC, a.legacy_code ASC, a.name ASC\n            ");
            $stmt->execute([':id' => $rootId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [[
                'Nivel' => 0,
                'ID' => $rootId,
                'Parent_ID' => '',
                'Codigo' => 'ERROR',
                'Nombre' => $e->getMessage(),
                'Hijos' => 0,
                'Personal_directo' => 0,
            ]];
        }
    }

    private function tokens(string $name): array
    {
        $name = mb_strtoupper($name, 'UTF-8');
        $name = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $name);
        $tokens = preg_split('/[^A-Z0-9Ñ]+/u', $name) ?: [];
        $stop = ['ZONA', 'POL', 'POLICIA', 'POLICIAL', 'DIRECCION', 'DIRECCIÓN', 'DIR', 'DEPTO', 'DEPARTAMENTO', 'SECCION', 'SECCIONAL', 'UNIDAD', 'UNID', 'DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS', 'PARA', 'CON', 'NAC', 'NAL'];
        $out = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token, 'UTF-8') < 4 || in_array($token, $stop, true)) {
                continue;
            }
            $out[] = $token;
        }
        return array_values(array_unique($out));
    }

    private function scalar(PDO $db, string $sql, array $params = []): int
    {
        try {
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
