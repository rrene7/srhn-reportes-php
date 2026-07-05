<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\View;
use PDO;
use Throwable;

final class ReportesMultiplesController
{
    public function index(): void
    {
        $db = Database::connect();

        View::render('reportes/multiples_dashboard', [
            'title' => 'Dashboard de Reportes Múltiples',
            'statuses' => $this->rows($db, "SELECT id, legacy_code, name FROM statuses ORDER BY legacy_code ASC, name ASC LIMIT 200"),
            'ranks' => $this->rows($db, "SELECT id, legacy_code, name FROM ranks ORDER BY sort_order ASC, legacy_code ASC LIMIT 200"),
            'actionTypes' => $this->rows($db, "SELECT id, name FROM action_types ORDER BY name ASC LIMIT 300"),
            'years' => $this->rows($db, "
                SELECT DISTINCT YEAR(action_date) AS year
                FROM employee_actions
                WHERE action_date IS NOT NULL
                ORDER BY year DESC
                LIMIT 20
            "),
        ]);
    }

    public function data(): void
    {
        $db = Database::connect();
        $filters = [
            'unidad' => trim((string) ($_GET['unidad'] ?? '')),
            'year' => max(0, (int) ($_GET['year'] ?? 0)),
            'month' => max(0, min(12, (int) ($_GET['month'] ?? 0))),
            'status_id' => max(0, (int) ($_GET['status_id'] ?? 0)),
            'rank_id' => max(0, (int) ($_GET['rank_id'] ?? 0)),
            'action_type_id' => max(0, (int) ($_GET['action_type_id'] ?? 0)),
        ];

        try {
            $unitIds = $this->unitScopeIds($db, $filters['unidad']);
            [$employeeWhere, $employeeParams] = $this->employeeWhere($unitIds, $filters);
            [$actionWhere, $actionParams] = $this->actionWhere($unitIds, $filters);
            [$unitWhere, $unitParams] = $this->unitWhere($unitIds);

            $payload = [
                'ok' => true,
                'updated_at' => date('H:i:s'),
                'scope' => $this->scopeLabel($filters, $unitIds),
                'kpis' => $this->kpis($db, $employeeWhere, $employeeParams, $actionWhere, $actionParams, $unitWhere, $unitParams),
                'blocks' => [
                    'estadoFuerza' => $this->blockEstadoFuerza($db, $employeeWhere, $employeeParams),
                    'rangos' => $this->blockRangos($db, $employeeWhere, $employeeParams),
                    'accionesTipo' => $this->blockAccionesTipo($db, $actionWhere, $actionParams),
                    'accionesMes' => $this->blockAccionesMes($db, $actionWhere, $actionParams),
                    'mapaDatos' => $this->blockMapaDatos($db, $unitWhere, $unitParams),
                    'calidad' => $this->blockCalidad($db, $employeeWhere, $employeeParams, $unitWhere, $unitParams),
                    'recientes' => $this->blockRecientes($db, $actionWhere, $actionParams),
                ],
            ];
        } catch (Throwable $e) {
            http_response_code(500);
            $payload = [
                'ok' => false,
                'updated_at' => date('H:i:s'),
                'error' => $e->getMessage(),
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function kpis(PDO $db, string $employeeWhere, array $employeeParams, string $actionWhere, array $actionParams, string $unitWhere, array $unitParams): array
    {
        $totalEmployees = $this->scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$employeeWhere}", $employeeParams);
        $activeEmployees = $this->scalar($db, "
            SELECT COUNT(*)
            FROM employees e
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$employeeWhere}
              AND (
                    UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) LIKE '%ACTIV%'
                 OR COALESCE(s.legacy_code, e.legacy_status_code, '') IN ('1', '01', 'ACT')
              )
        ", $employeeParams);
        $totalActions = $this->scalar($db, "
            SELECT COUNT(a.id)
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE {$actionWhere}
        ", $actionParams);
        $actionsCurrentMonth = $this->scalar($db, "
            SELECT COUNT(a.id)
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE {$actionWhere}
              AND YEAR(a.action_date) = YEAR(CURRENT_DATE())
              AND MONTH(a.action_date) = MONTH(CURRENT_DATE())
        ", $actionParams);
        $totalUnits = $this->scalar($db, "SELECT COUNT(*) FROM units u WHERE {$unitWhere}", $unitParams);
        $withoutParent = $this->scalar($db, "SELECT COUNT(*) FROM units u WHERE {$unitWhere} AND (u.parent_id IS NULL OR u.parent_id = 0)", $unitParams);
        $terminalUnits = $this->scalar($db, "
            SELECT COUNT(*)
            FROM units u
            WHERE {$unitWhere}
              AND NOT EXISTS (SELECT 1 FROM units h WHERE h.parent_id = u.id)
        ", $unitParams);
        $actionTypes = $this->scalar($db, "
            SELECT COUNT(DISTINCT a.action_type_id)
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE {$actionWhere}
        ", $actionParams);

        return [
            ['label' => 'Funcionarios', 'value' => $totalEmployees, 'hint' => 'Según filtros globales', 'target' => 'estado-fuerza'],
            ['label' => 'Activos', 'value' => $activeEmployees, 'hint' => 'Funcionarios activos en alcance', 'target' => 'estado-fuerza'],
            ['label' => 'Acciones', 'value' => $totalActions, 'hint' => 'Acciones según filtros', 'target' => 'acciones-tipo'],
            ['label' => 'Acciones del mes', 'value' => $actionsCurrentMonth, 'hint' => 'Mes actual', 'target' => 'acciones-mes'],
            ['label' => 'Unidades', 'value' => $totalUnits, 'hint' => 'Unidades en alcance', 'target' => 'mapa-datos'],
            ['label' => 'Sin padre', 'value' => $withoutParent, 'hint' => 'parent_id vacío', 'target' => 'calidad'],
            ['label' => 'Terminales', 'value' => $terminalUnits, 'hint' => 'Sin hijas reales', 'target' => 'mapa-datos'],
            ['label' => 'Tipos acción', 'value' => $actionTypes, 'hint' => 'Tipos usados', 'target' => 'acciones-tipo'],
        ];
    }

    private function blockEstadoFuerza(PDO $db, string $where, array $params): array
    {
        return $this->block('Estado de fuerza por estado', ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total'], $this->rows($db, "
            SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
                   COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
                   COUNT(*) AS total
            FROM employees e
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            GROUP BY codigo, nombre
            ORDER BY total DESC
            LIMIT 30
        ", $params));
    }

    private function blockRangos(PDO $db, string $where, array $params): array
    {
        return $this->block('Estado de fuerza por rango', ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total'], $this->rows($db, "
            SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
                   COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
                   COUNT(*) AS total
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            WHERE {$where}
            GROUP BY codigo, nombre
            ORDER BY CAST(codigo AS UNSIGNED) ASC, nombre ASC
            LIMIT 40
        ", $params));
    }

    private function blockAccionesTipo(PDO $db, string $where, array $params): array
    {
        return $this->block('Acciones por tipo', ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total'], $this->rows($db, "
            SELECT COALESCE(CAST(a.action_type_id AS CHAR), 'SIN') AS codigo,
                   COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
                   COUNT(*) AS total
            FROM employee_actions a
            LEFT JOIN action_types at ON at.id = a.action_type_id
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE {$where}
            GROUP BY a.action_type_id, at.name
            ORDER BY total DESC
            LIMIT 30
        ", $params));
    }

    private function blockAccionesMes(PDO $db, string $where, array $params): array
    {
        return $this->block('Acciones por mes', ['periodo' => 'Periodo', 'total' => 'Total'], $this->rows($db, "
            SELECT DATE_FORMAT(a.action_date, '%Y-%m') AS periodo,
                   COUNT(*) AS total
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE {$where}
              AND a.action_date IS NOT NULL
            GROUP BY periodo
            ORDER BY periodo DESC
            LIMIT 18
        ", $params));
    }

    private function blockMapaDatos(PDO $db, string $unitWhere, array $unitParams): array
    {
        return $this->block('Mapa / MOI: unidades con más personal', ['codigo' => 'Código', 'nombre' => 'Unidad', 'hijos' => 'Hijos', 'personal' => 'Personal'], $this->rows($db, "
            SELECT COALESCE(u.legacy_code, '') AS codigo,
                   COALESCE(u.name, 'Sin nombre') AS nombre,
                   (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
                   COUNT(e.id) AS personal
            FROM units u
            LEFT JOIN employees e ON e.unit_id = u.id
            WHERE {$unitWhere}
            GROUP BY u.id, codigo, nombre, hijos
            ORDER BY personal DESC, hijos DESC, codigo ASC
            LIMIT 25
        ", $unitParams));
    }

    private function blockCalidad(PDO $db, string $employeeWhere, array $employeeParams, string $unitWhere, array $unitParams): array
    {
        $rows = [
            ['indicador' => 'Unidades sin padre', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM units u WHERE {$unitWhere} AND (u.parent_id IS NULL OR u.parent_id = 0)", $unitParams), 'detalle' => 'parent_id vacío o cero'],
            ['indicador' => 'Unidades terminales', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM units u WHERE {$unitWhere} AND NOT EXISTS (SELECT 1 FROM units h WHERE h.parent_id = u.id)", $unitParams), 'detalle' => 'No tienen hijas reales'],
            ['indicador' => 'Padres sin personal directo', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM units u WHERE {$unitWhere} AND EXISTS (SELECT 1 FROM units h WHERE h.parent_id = u.id) AND NOT EXISTS (SELECT 1 FROM employees e WHERE e.unit_id = u.id)", $unitParams), 'detalle' => 'Tienen hijas, pero no personal directo'],
            ['indicador' => 'Funcionarios sin unidad', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$employeeWhere} AND (e.unit_id IS NULL OR e.unit_id = 0)", $employeeParams), 'detalle' => 'unit_id vacío'],
            ['indicador' => 'Funcionarios sin rango', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$employeeWhere} AND (e.rank_id IS NULL OR e.rank_id = 0)", $employeeParams), 'detalle' => 'rank_id vacío'],
            ['indicador' => 'Funcionarios sin estado', 'total' => $this->scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$employeeWhere} AND (e.status_id IS NULL OR e.status_id = 0)", $employeeParams), 'detalle' => 'status_id vacío'],
        ];

        return $this->block('Calidad de datos', ['indicador' => 'Indicador', 'total' => 'Total', 'detalle' => 'Detalle'], $rows);
    }

    private function blockRecientes(PDO $db, string $where, array $params): array
    {
        return $this->block('Últimas acciones registradas', ['fecha' => 'Fecha', 'accion' => 'Acción', 'funcionario' => 'Funcionario', 'unidad' => 'Unidad'], $this->rows($db, "
            SELECT a.action_date AS fecha,
                   COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS accion,
                   TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                   COALESCE(u.name, '') AS unidad
            FROM employee_actions a
            INNER JOIN employees e ON e.id = a.employee_id
            LEFT JOIN action_types at ON at.id = a.action_type_id
            LEFT JOIN units u ON u.id = e.unit_id
            WHERE {$where}
            ORDER BY a.action_date DESC, a.id DESC
            LIMIT 20
        ", $params));
    }

    private function unitScopeIds(PDO $db, string $unidad): ?array
    {
        if ($unidad === '') {
            return null;
        }

        $like = '%' . $unidad . '%';
        $params = [':u_code' => $like, ':u_name' => $like];
        $idSql = '';
        if (ctype_digit($unidad)) {
            $idSql = ' OR id = :u_id';
            $params[':u_id'] = (int) $unidad;
        }

        $rows = $this->rows($db, "
            WITH RECURSIVE base AS (
                SELECT id
                FROM units
                WHERE COALESCE(legacy_code, '') LIKE :u_code
                   OR UPPER(COALESCE(name, '')) LIKE UPPER(:u_name)
                   {$idSql}
            ), arbol AS (
                SELECT id FROM base
                UNION ALL
                SELECT u.id FROM units u INNER JOIN arbol a ON u.parent_id = a.id
            )
            SELECT DISTINCT id FROM arbol
            LIMIT 5000
        ", $params);

        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id']) && is_numeric($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids === [] ? [0] : array_values(array_unique($ids));
    }

    private function employeeWhere(?array $unitIds, array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($unitIds !== null) {
            [$sql, $inParams] = $this->inClause($unitIds, 'e.unit_id', 'emp_unit');
            $where[] = $sql;
            $params += $inParams;
        }
        if ($filters['status_id'] > 0) {
            $where[] = 'e.status_id = :emp_status_id';
            $params[':emp_status_id'] = $filters['status_id'];
        }
        if ($filters['rank_id'] > 0) {
            $where[] = 'e.rank_id = :emp_rank_id';
            $params[':emp_rank_id'] = $filters['rank_id'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function actionWhere(?array $unitIds, array $filters): array
    {
        [$where, $params] = $this->employeeWhere($unitIds, $filters);
        $whereParts = [$where];

        if ($filters['action_type_id'] > 0) {
            $whereParts[] = 'a.action_type_id = :act_type_id';
            $params[':act_type_id'] = $filters['action_type_id'];
        }
        if ($filters['year'] > 0) {
            $whereParts[] = 'YEAR(a.action_date) = :act_year';
            $params[':act_year'] = $filters['year'];
        }
        if ($filters['month'] > 0) {
            $whereParts[] = 'MONTH(a.action_date) = :act_month';
            $params[':act_month'] = $filters['month'];
        }

        return [implode(' AND ', $whereParts), $params];
    }

    private function unitWhere(?array $unitIds): array
    {
        if ($unitIds === null) {
            return ['1 = 1', []];
        }
        return $this->inClause($unitIds, 'u.id', 'unit_scope');
    }

    private function inClause(array $ids, string $column, string $prefix): array
    {
        if ($ids === []) {
            return ['1 = 0', []];
        }

        $marks = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = ':' . $prefix . '_' . $i;
            $marks[] = $key;
            $params[$key] = (int) $id;
        }

        return [$column . ' IN (' . implode(',', $marks) . ')', $params];
    }

    private function scopeLabel(array $filters, ?array $unitIds): string
    {
        $parts = [];
        if ($filters['unidad'] !== '') {
            $parts[] = 'Unidad/Zona: ' . $filters['unidad'];
            $parts[] = 'Unidades encontradas: ' . ($unitIds === null ? 'todas' : count($unitIds));
        }
        if ($filters['year'] > 0) {
            $parts[] = 'Año: ' . $filters['year'];
        }
        if ($filters['month'] > 0) {
            $parts[] = 'Mes: ' . str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT);
        }
        return $parts === [] ? 'Alcance general' : implode(' | ', $parts);
    }

    private function block(string $title, array $columns, array $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    private function scalar(PDO $db, string $sql, array $params = []): int
    {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function rows(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
