<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$q = trim((string) ($_GET['q'] ?? ''));
$p = max(0, (int) ($_GET['p'] ?? 0));
$limit = max(25, min(300, (int) ($_GET['limit'] ?? 100)));

function mdUrl(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || $v === 0 || $v === '0') {
            unset($params[$k]);
        }
    }
    return url('/reportes/mapa-datos' . ($params ? '?' . http_build_query($params) : ''));
}

function mdRows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [['codigo' => 'ERROR', 'nombre' => $e->getMessage()]];
    }
}

function mdScalar(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mdTreeIds(PDO $db, int $id): array
{
    if ($id <= 0) {
        return [];
    }

    $rows = mdRows($db, "
        WITH RECURSIVE arbol AS (
            SELECT id FROM units WHERE id = :id
            UNION ALL
            SELECT u.id FROM units u
            INNER JOIN arbol a ON u.parent_id = a.id
        )
        SELECT id FROM arbol
    ", [':id' => $id]);

    $ids = [];
    foreach ($rows as $row) {
        if (isset($row['id']) && is_numeric($row['id'])) {
            $ids[] = (int) $row['id'];
        }
    }

    return array_values(array_unique($ids));
}

function mdInClause(array $ids, string $columna): array
{
    if ($ids === []) {
        return ['1 = 1', []];
    }

    $params = [];
    $marks = [];
    foreach ($ids as $i => $id) {
        $key = ':u' . $i;
        $marks[] = $key;
        $params[$key] = (int) $id;
    }

    return [$columna . ' IN (' . implode(',', $marks) . ')', $params];
}

function mdCardLink(string $label, int $total, string $href): void
{
    ?>
    <a class="report-card" href="<?= e($href) ?>" style="text-decoration:none; color:inherit; display:block;">
        <span class="module-status">ABRIR</span>
        <strong><?= e(number_format($total)) ?></strong>
        <small><?= e($label) ?></small>
    </a>
    <?php
}

function mdTable(string $titulo, array $rows, array $cols, string $id = ''): void
{
    $sectionId = $id !== '' ? $id : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $titulo));
    ?>
    <section class="card" id="<?= e($sectionId) ?>">
        <div class="card-header"><div><h3><?= e($titulo) ?></h3><p><?= e(count($rows)) ?> registros</p></div></div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead><tr><?php foreach ($cols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="<?= e(count($cols)) ?>" class="empty">Sin datos</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr><?php foreach (array_keys($cols) as $key): ?><td><?= $key === '_link' ? ($row[$key] ?? '') : e($row[$key] ?? '') ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

$seleccion = null;
if ($p > 0) {
    $rows = mdRows($db, 'SELECT id, legacy_code AS codigo, name AS nombre, parent_id FROM units WHERE id = :id LIMIT 1', [':id' => $p]);
    $seleccion = $rows[0] ?? null;
}

$ruta = [];
$cursor = $seleccion;
$guard = 0;
while ($cursor && $guard < 20) {
    array_unshift($ruta, $cursor);
    $parentId = (int) ($cursor['parent_id'] ?? 0);
    if ($parentId <= 0) {
        break;
    }
    $parent = mdRows($db, 'SELECT id, legacy_code AS codigo, name AS nombre, parent_id FROM units WHERE id = :id LIMIT 1', [':id' => $parentId]);
    $cursor = $parent[0] ?? null;
    $guard++;
}

$resultadosBusqueda = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $resultadosBusqueda = mdRows($db, "
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
        WHERE COALESCE(u.legacy_code, '') LIKE :like
           OR UPPER(COALESCE(u.name, '')) LIKE UPPER(:like)
        GROUP BY u.id, codigo, nombre, padre, hijos
        ORDER BY
            CASE WHEN UPPER(COALESCE(u.name, '')) LIKE UPPER(:start) THEN 0 ELSE 1 END,
            hijos DESC,
            personal DESC,
            codigo ASC
        LIMIT 100
    ", [':like' => $like, ':start' => $q . '%']);

    foreach ($resultadosBusqueda as &$row) {
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => (int) $row['id'], 'q' => ''])) . '">Seleccionar</a>';
    }
    unset($row);
}

$idsArbol = mdTreeIds($db, $p);
[$whereUnit, $whereParams] = mdInClause($idsArbol, 'u.id');
[$whereEmployeeUnit, $whereEmployeeParams] = mdInClause($idsArbol, 'e.unit_id');

if ($p <= 0) {
    $totalDependencias = (int) $db->query('SELECT COUNT(*) FROM units')->fetchColumn();
    $totalPersonal = (int) $db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    $totalAcciones = (int) $db->query('SELECT COUNT(*) FROM employee_actions')->fetchColumn();
} else {
    $totalDependencias = count($idsArbol);
    $totalPersonal = mdScalar($db, "SELECT COUNT(*) FROM employees e WHERE {$whereEmployeeUnit}", $whereEmployeeParams);
    $totalAcciones = mdScalar($db, "SELECT COUNT(a.id) FROM employee_actions a INNER JOIN employees e ON e.id = a.employee_id WHERE {$whereEmployeeUnit}", $whereEmployeeParams);
}

$inicio = [];
if ($p <= 0 && $q === '') {
    $inicio = mdRows($db, "
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
    foreach ($inicio as &$row) {
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => (int) $row['id']])) . '">Abrir</a>';
    }
    unset($row);
}

$hijos = [];
if ($p > 0) {
    $hijos = mdRows($db, "
        SELECT
            u.id,
            COALESCE(u.legacy_code, '') AS codigo,
            COALESCE(u.name, 'Sin nombre') AS nombre,
            (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
            COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE u.parent_id = :parent_id
        GROUP BY u.id, codigo, nombre, hijos
        ORDER BY codigo ASC, nombre ASC
        LIMIT {$limit}
    ", [':parent_id' => $p]);
    foreach ($hijos as &$row) {
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => (int) $row['id']])) . '">Abrir lo que contiene</a>';
    }
    unset($row);
}

$personalEstado = mdRows($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereEmployeeUnit}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $whereEmployeeParams);

$personalRango = mdRows($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereEmployeeUnit}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $whereEmployeeParams);

$accionesTipo = mdRows($db, "
    SELECT a.action_type_id AS codigo,
           COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total,
           MIN(a.action_date) AS fecha_minima,
           MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    INNER JOIN employees e ON e.id = a.employee_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereEmployeeUnit}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $whereEmployeeParams);
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Explorador por estructura real <strong>parent_id</strong>. Base: <strong><?= e($base) ?></strong>.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos/ejemplo')) ?>">Ejemplo real</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<section class="card no-print">
    <h3>Búsqueda por nombre o código</h3>
    <p>Escribe cualquier coincidencia real: provincia, zona, área, estación, unidad o código.</p>
    <form method="get" action="<?= e(url('/reportes/mapa-datos')) ?>" class="filters">
        <div class="field field-wide">
            <label for="q">Buscar en unidades</label>
            <input type="text" name="q" id="q" value="<?= e($q) ?>" placeholder="Ej. Panamá Oeste, Chiriquí, David, Colón, 100060">
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
        <?php foreach ($ruta as $item): ?>
            › <a href="<?= e(mdUrl(['p' => (int) $item['id'], 'q' => ''])) ?>"><?= e(($item['codigo'] ?? '') . ' - ' . ($item['nombre'] ?? '')) ?></a>
        <?php endforeach; ?>
    </p>
</section>

<section class="card">
    <h3>Botones interactivos del nivel actual</h3>
    <div class="report-menu">
        <?php mdCardLink('Unidades dentro', $totalDependencias, '#unidades'); ?>
        <?php mdCardLink('Funcionarios dentro', $totalPersonal, '#personal-por-estado'); ?>
        <?php mdCardLink('Acciones dentro', $totalAcciones, '#acciones-por-tipo'); ?>
        <?php mdCardLink('Estados del personal', count($personalEstado), '#personal-por-estado'); ?>
        <?php mdCardLink('Rangos del personal', count($personalRango), '#personal-por-rango'); ?>
    </div>
</section>

<?php if ($q !== ''): ?>
    <?php mdTable('Resultados para: ' . $q, $resultadosBusqueda, ['codigo' => 'Código', 'nombre' => 'Nombre real', 'padre' => 'Padre', 'hijos' => 'Hijos', 'personal' => 'Personal directo', '_link' => 'Seleccionar'], 'resultados'); ?>
<?php endif; ?>

<?php if ($p <= 0 && $q === ''): ?>
    <?php mdTable('Inicio: unidades sin padre', $inicio, ['codigo' => 'Código', 'nombre' => 'Nombre', 'hijos' => 'Hijos', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
<?php endif; ?>

<?php if ($p > 0): ?>
    <?php mdTable('Hijos directos de la selección actual', $hijos, ['codigo' => 'Código', 'nombre' => 'Nombre real', 'hijos' => 'Hijos', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
<?php endif; ?>

<div class="grid-2">
    <?php mdTable('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total'], 'personal-por-estado'); ?>
    <?php mdTable('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total'], 'personal-por-rango'); ?>
</div>

<?php mdTable('Acciones por tipo', $accionesTipo, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima'], 'acciones-por-tipo'); ?>
