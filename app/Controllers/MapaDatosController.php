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
        View::render('reportes/mapa_datos_explorador', [
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

        $writer->addSheet('Indice', [[
            'Tipo' => 'Base',
            'Dato' => 'Unidades sin padre',
            'Total' => count($roots),
            'Nota' => 'La tabla units tiene parent_id, pero muchas unidades aparecen sin padre. Este archivo ayuda a revisar y ordenar la estructura.',
        ]]);

        $indexRows = [];
        foreach ($roots as $root) {
            $indexRows[] = [
                'ID' => (int) $root['id'],
                'Codigo' => (string) $root['legacy_code'],
                'Nombre' => (string) $root['name'],
                'Hijos directos' => $this->scalar($db, 'SELECT COUNT(*) FROM units WHERE parent_id = :id', [':id' => (int) $root['id']]),
                'Personal directo' => $this->scalar($db, 'SELECT COUNT(*) FROM employees WHERE unit_id = :id', [':id' => (int) $root['id']]),
            ];
        }
        $writer->addSheet('Unidades sin padre', $indexRows);

        $candidatas = $db->query("\n            SELECT id, legacy_code, name\n            FROM units\n            WHERE (parent_id IS NULL OR parent_id = 0)\n              AND (\n                    UPPER(name) LIKE '%ZONA%'\n                 OR UPPER(name) LIKE 'DIR%'\n                 OR UPPER(name) LIKE '%DIRECCION%'\n                 OR UPPER(name) LIKE '%DIRECCIÓN%'\n              )\n            ORDER BY legacy_code ASC, name ASC\n            LIMIT 120\n        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidatas as $root) {
            $rows = $this->treeRows($db, (int) $root['id']);
            $writer->addSheet((string) $root['legacy_code'] . ' ' . (string) $root['name'], $rows);
        }

        $writer->output('mapa_zonas_direcciones_' . date('Ymd_His') . '.xlsx');
    }

    public function diagnostico(): void
    {
        View::render('reportes/mapa_datos_ejemplo', [
            'title' => 'Ejemplo Real Mapa de Datos',
        ]);
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
