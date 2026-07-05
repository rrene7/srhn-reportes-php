<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$q = trim((string) ($_GET['q'] ?? $_GET['buscar'] ?? ''));
$p = max(0, (int) ($_GET['p'] ?? 0));
$limit = max(25, min(300, (int) ($_GET['limit'] ?? 100)));

function m4Url(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            unset($params[$key]);
        }
    }
    return url('/reportes/mapa-datos' . ($params ? '?' . http_build_query($params) : ''));
}

function m4Rows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [[
            'codigo' => 'ERROR',
            'nombre' => $e->getMessage(),
            'padre' => '',
            'hijos' => '',
            'personal' => '',
            '_link' => '',
        ]];
    }
}

function m4Scalar(PDO $db, string $sql, array $params = []): int
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

function m4TreeIds(PDO $db, int $id): array
{
    if ($id <= 0) {
        return [];
    }

    $rows = m4Rows($db, "
        WITH RECURSIVE arbol AS (
            SELECT id FROM units WHERE id = :root_id
            UNION ALL
            SELECT u.id FROM units u INNER JOIN arbol a ON u.parent_id = a.id
        )
        SELECT id FROM arbol
    ", [':root_id' => $id]);

    $ids = [];
    foreach ($rows as $row) {
        if (isset($row['id']) && is_numeric($row['id'])) {
            $ids[] = (int) $row['id'];
        }
    }
    return array_values(array_unique($ids));
}

function m4InClause(array $ids, string $column): array
{
    if ($ids === []) {
        return ['1 = 1', []];
    }

    $params = [];
    $marks = [];
    foreach ($ids as $i => $id) {
        $key = ':tree_id_' . $i;
        $marks[] = $key;
        $params[$key] = (int) $id;
    }

    return [$column . ' IN (' . implode(',', $marks) . ')', $params];
}

function m4Tokens(string $text): array
{
    $text = mb_strtoupper($text, 'UTF-8');
    $text = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $text);
    $tokens = preg_split('/[^A-Z0-9Ñ]+/u', $text) ?: [];
    $stop = ['ZONA','POL','POLICIA','POLICIAL','DIRECCION','DIR','DEPTO','DEPARTAMENTO','SECCION','SECCIONAL','UNIDAD','UNID','PMI','DE','DEL','LA','EL','LOS','LAS','NAC','NAL','PARA','CON'];
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

function m4Table(string $title, array $rows, array $cols, string $id): void
{
    ?>
    <section class="card" id="<?= e($id) ?>">
        <div class="card-header"><div><h3><?= e($title) ?></h3><p><?= e(count($rows)) ?> registros</p></div></div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead><tr><?php foreach ($cols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="<?= e(count($cols)) ?>" class="empty">Sin datos</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_keys($cols) as $key): ?>
                                <td><?= $key === '_link' ? ($row[$key] ?? '') : e($row[$key] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

function m4Card(string $label, int $total, string $href): void
{
    ?>
    <a class="report-card" href="<?= e($href) ?>" style="display:block;text-decoration:none;color:inherit;">
        <span class="module-status">ABRIR</span>
        <strong><?= e(number_format($total)) ?></strong>
        <small><?= e($label) ?></small>
    </a>
    <?php
}

$selected = null;
if ($p > 0) {
    $selectedRows = m4Rows($db, "
        SELECT u.id, u.parent_id, u.legacy_code AS codigo, u.name AS nombre
        FROM units u
        WHERE u.id = :selected_id
        LIMIT 1
    ", [':selected_id' => $p]);
    $selected = $selectedRows[0] ?? null;
}

$path = [];
$cursor = $selected;
while ($cursor && count($path) < 30) {
    array_unshift($path, $cursor);
    $parentId = (int) ($cursor['parent_id'] ?? 0);
    if ($parentId <= 0) {
        break;
    }
    $parentRows = m4Rows($db, "
        SELECT u.id, u.parent_id, u.legacy_code AS codigo, u.name AS nombre
        FROM units u
        WHERE u.id = :parent_id
        LIMIT 1
    ", [':parent_id' => $parentId]);
    $cursor = $parentRows[0] ?? null;
}

$searchRows = [];
if ($q !== '') {
    $searchRows = m4Rows($db, "
        SELECT
            u.id,
            COALESCE(u.legacy_code, '') AS codigo,
            COALESCE(u.name, 'Sin nombre') AS nombre,
            COALESCE(pu.name, '') AS padre,
            (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
            COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN units pu ON pu.id = u.parent_id
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE COALESCE(u.legacy_code, '') LIKE :q_code
           OR UPPER(COALESCE(u.name, '')) LIKE UPPER(:q_name)
        GROUP BY u.id, codigo, nombre, padre, hijos
        ORDER BY hijos DESC, personal DESC, codigo ASC
        LIMIT 100
    ", [':q_code' => '%' . $q . '%', ':q_name' => '%' . $q . '%']);

    foreach ($searchRows as &$row) {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(m4Url(['p' => $id, 'q' => ''])) . '">Seleccionar</a>' : '';
    }
    unset($row);
}

$treeIds = m4TreeIds($db, $p);
[$employeeWhere, $employeeParams] = m4InClause($treeIds, 'e.unit_id');

$childRows = [];
if ($p > 0) {
    $childRows = m4Rows($db, "
        SELECT
            u.id,
            COALESCE(u.legacy_code, '') AS codigo,
            COALESCE(u.name, 'Sin nombre') AS nombre,
            (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
            COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE u.parent_id = :current_parent_id
        GROUP BY u.id, codigo, nombre, hijos
        ORDER BY codigo ASC, nombre ASC
        LIMIT {$limit}
    ", [':current_parent_id' => $p]);
    foreach ($childRows as &$row) {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(m4Url(['p' => $id])) . '">Abrir</a>' : '';
    }
    unset($row);
}

$rootRows = [];
if ($p <= 0 && $q === '') {
    $rootRows = m4Rows($db, "
        SELECT
            u.id,
            COALESCE(u.legacy_code, '') AS codigo,
            COALESCE(u.name, 'Sin nombre') AS nombre,
            (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
            COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE u.parent_id IS NULL OR u.parent_id = 0
        GROUP BY u.id, codigo, nombre, hijos
        ORDER BY codigo ASC, nombre ASC
        LIMIT {$limit}
    ");
    foreach ($rootRows as &$row) {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(m4Url(['p' => $id])) . '">Abrir</a>' : '';
    }
    unset($row);
}

$relatedRows = [];
if ($p > 0 && $childRows === [] && $selected) {
    $tokens = m4Tokens((string) ($selected['nombre'] ?? ''));
    $conditions = [];
    $params = [':current_id' => $p];
    foreach (array_slice($tokens, 0, 5) as $i => $token) {
        $key = ':token_' . $i;
        $conditions[] = 'UPPER(COALESCE(u.name, \'\')) LIKE ' . $key;
        $params[$key] = '%' . $token . '%';
    }
    if ($conditions !== []) {
        $relatedRows = m4Rows($db, "
            SELECT
                u.id,
                COALESCE(u.legacy_code, '') AS codigo,
                COALESCE(u.name, 'Sin nombre') AS nombre,
                COALESCE(pu.name, '') AS padre,
                (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
                COUNT(e.id) AS personal
            FROM units u
            LEFT JOIN units pu ON pu.id = u.parent_id
            LEFT JOIN employees e ON e.unit_id = u.id
            WHERE u.id <> :current_id
              AND (" . implode(' OR ', $conditions) . ")
            GROUP BY u.id, codigo, nombre, padre, hijos
            ORDER BY hijos DESC, personal DESC, codigo ASC
            LIMIT {$limit}
        ", $params);
        foreach ($relatedRows as &$row) {
            $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
            $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(m4Url(['p' => $id])) . '">Seleccionar</a>' : '';
        }
        unset($row);
    }
}

$totalUnitsScope = $p > 0 ? count($treeIds) : (int) $db->query('SELECT COUNT(*) FROM units')->fetchColumn();
$totalDirectChildren = count($childRows);
$totalEmployeesScope = $p > 0 ? m4Scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$employeeWhere}", $employeeParams) : (int) $db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$totalActionsScope = $p > 0 ? m4Scalar($db, "SELECT COUNT(a.id) FROM employee_actions a INNER JOIN employees e ON e.id = a.employee_id WHERE {$employeeWhere}", $employeeParams) : (int) $db->query('SELECT COUNT(*) FROM employee_actions')->fetchColumn();
$totalDirectEmployees = $p > 0 ? m4Scalar($db, 'SELECT COUNT(*) FROM employees WHERE unit_id = :unit_id', [':unit_id' => $p]) : 0;
$totalDirectActions = $p > 0 ? m4Scalar($db, 'SELECT COUNT(a.id) FROM employee_actions a INNER JOIN employees e ON e.id = a.employee_id WHERE e.unit_id = :unit_id', [':unit_id' => $p]) : 0;

$statusRows = m4Rows($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    WHERE {$employeeWhere}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $employeeParams);

$rankRows = m4Rows($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    WHERE {$employeeWhere}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $employeeParams);

$actionRows = m4Rows($db, "
    SELECT a.action_type_id AS codigo,
           COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total,
           MIN(a.action_date) AS fecha_minima,
           MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    INNER JOIN employees e ON e.id = a.employee_id
    WHERE {$employeeWhere}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $employeeParams);

$directEmployees = [];
$recentActions = [];
if ($p > 0) {
    $directEmployees = m4Rows($db, "
        SELECT
            COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
            e.document_number AS cedula,
            TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
            COALESCE(r.name, e.legacy_rank_name, '') AS rango,
            COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado
        FROM employees e
        LEFT JOIN ranks r ON r.id = e.rank_id
        LEFT JOIN statuses s ON s.id = e.status_id
        WHERE e.unit_id = :unit_id
        ORDER BY r.sort_order ASC, e.last_name ASC, e.first_name ASC
        LIMIT {$limit}
    ", [':unit_id' => $p]);

    $recentActions = m4Rows($db, "
        SELECT
            COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
            TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
            COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS accion,
            a.action_date AS fecha,
            a.id AS id_accion
        FROM employee_actions a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN action_types at ON at.id = a.action_type_id
        WHERE e.unit_id = :unit_id_actions
        ORDER BY a.action_date DESC, a.id DESC
        LIMIT {$limit}
    ", [':unit_id_actions' => $p]);
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Explorador por búsqueda, padres reales y posibles hijas. Base: <strong><?= e($base) ?></strong>.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos/exportar-excel')) ?>">Exportar Excel ordenado</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<section class="card no-print">
    <h3>Búsqueda por nombre o código</h3>
    <p>Escribe una coincidencia real como <strong>Panamá Oeste</strong>, <strong>Chiriquí</strong>, <strong>Dirección General</strong>, <strong>David</strong> o un código.</p>
    <form method="get" action="<?= e(url('/reportes/mapa-datos')) ?>" class="filters" id="mapaSearchForm">
        <div class="field field-wide">
            <label for="q">Buscar en unidades</label>
            <input type="text" name="q" id="q" value="<?= e($q) ?>" placeholder="Ej. Panamá Oeste, Chiriquí, Dirección General, Telemática" autocomplete="off">
        </div>
        <div class="field">
            <label for="limit">Límite</label>
            <select name="limit" id="limit">
                <?php foreach ([50, 100, 200, 300] as $op): ?>
                    <option value="<?= e($op) ?>" <?= $limit === $op ? 'selected' : '' ?>><?= e($op) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit">Buscar</button>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card no-print">
    <h3>Ruta de navegación</h3>
    <p>
        <a href="<?= e(url('/reportes/mapa-datos')) ?>">Inicio</a>
        <?php foreach ($path as $item): ?>
            › <a href="<?= e(m4Url(['p' => (int) $item['id'], 'q' => ''])) ?>"><?= e(($item['codigo'] ?? '') . ' - ' . ($item['nombre'] ?? '')) ?></a>
        <?php endforeach; ?>
    </p>
</section>

<?php if ($p > 0 && $totalDirectChildren === 0): ?>
    <section class="card muted">
        <h3>Unidad terminal</h3>
        <p>Esta unidad no tiene hijos reales por <strong>parent_id</strong>. Se muestra su personal directo, sus acciones directas y, si existen, posibles unidades relacionadas por nombre.</p>
    </section>
<?php endif; ?>

<section class="card">
    <h3>Botones interactivos del nivel actual</h3>
    <div class="report-menu">
        <?php m4Card('Hijos directos reales', $totalDirectChildren, '#unidades'); ?>
        <?php m4Card('Unidades en alcance', $totalUnitsScope, '#unidades'); ?>
        <?php m4Card('Funcionarios directos', $totalDirectEmployees, '#funcionarios-directos'); ?>
        <?php m4Card('Acciones directas', $totalDirectActions, '#acciones-recientes'); ?>
        <?php m4Card('Funcionarios en alcance', $totalEmployeesScope, '#personal-por-estado'); ?>
        <?php m4Card('Acciones en alcance', $totalActionsScope, '#acciones-por-tipo'); ?>
    </div>
</section>

<?php if ($q !== ''): ?>
    <?php m4Table('Resultados para: ' . $q, $searchRows, ['codigo' => 'Código', 'nombre' => 'Nombre', 'padre' => 'Padre actual', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Seleccionar'], 'resultados'); ?>
<?php endif; ?>

<?php if ($p <= 0 && $q === ''): ?>
    <?php m4Table('Inicio: unidades sin padre', $rootRows, ['codigo' => 'Código', 'nombre' => 'Nombre', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
<?php endif; ?>

<?php if ($p > 0): ?>
    <?php m4Table('Hijos reales por parent_id', $childRows, ['codigo' => 'Código', 'nombre' => 'Nombre', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
    <?php if ($childRows === [] && $relatedRows !== []): ?>
        <section class="card muted"><p>Posibles relacionadas: estas unidades coinciden por nombre, pero no están enlazadas como hijas por <strong>parent_id</strong>.</p></section>
        <?php m4Table('Posibles hijas / relacionadas por nombre', $relatedRows, ['codigo' => 'Código', 'nombre' => 'Nombre', 'padre' => 'Padre actual', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Seleccionar'], 'relacionadas'); ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($p > 0): ?>
    <?php m4Table('Funcionarios directos de la unidad seleccionada', $directEmployees, ['nemp' => 'N. Emp.', 'cedula' => 'Cédula', 'funcionario' => 'Funcionario', 'rango' => 'Rango', 'estado' => 'Estado'], 'funcionarios-directos'); ?>
    <?php m4Table('Acciones directas recientes', $recentActions, ['fecha' => 'Fecha', 'accion' => 'Acción', 'nemp' => 'N. Emp.', 'funcionario' => 'Funcionario', 'id_accion' => 'ID acción'], 'acciones-recientes'); ?>
<?php endif; ?>

<div class="grid-2">
    <?php m4Table('Personal por estado', $statusRows, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total'], 'personal-por-estado'); ?>
    <?php m4Table('Personal por rango', $rankRows, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total'], 'personal-por-rango'); ?>
</div>

<?php m4Table('Acciones por tipo', $actionRows, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima'], 'acciones-por-tipo'); ?>

<script>
(() => {
    const form = document.getElementById('mapaSearchForm');
    const input = document.getElementById('q');
    if (!form || !input) return;
    let timer = null;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const value = input.value.trim();
        if (value.length < 3) return;
        timer = setTimeout(() => form.submit(), 900);
    });
})();
</script>
