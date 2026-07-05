<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$q = trim((string) ($_GET['q'] ?? $_GET['buscar'] ?? ''));
$p = max(0, (int) ($_GET['p'] ?? 0));
$limit = max(25, min(300, (int) ($_GET['limit'] ?? 100)));

function md2Url(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || $v === 0 || $v === '0') {
            unset($params[$k]);
        }
    }
    return url('/reportes/mapa-datos' . ($params ? '?' . http_build_query($params) : ''));
}

function md2Rows(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [['codigo' => 'ERROR', 'nombre' => $e->getMessage(), '_link' => '']];
    }
}

function md2Scalar(PDO $db, string $sql, array $params = []): int
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

function md2TreeIds(PDO $db, int $id): array
{
    if ($id <= 0) {
        return [];
    }

    $rows = md2Rows($db, "
        WITH RECURSIVE arbol AS (
            SELECT id FROM units WHERE id = :id
            UNION ALL
            SELECT u.id FROM units u INNER JOIN arbol a ON u.parent_id = a.id
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

function md2In(array $ids, string $column): array
{
    if ($ids === []) {
        return ['1 = 1', []];
    }

    $params = [];
    $marks = [];
    foreach ($ids as $i => $id) {
        $key = ':id' . $i;
        $marks[] = $key;
        $params[$key] = (int) $id;
    }
    return [$column . ' IN (' . implode(',', $marks) . ')', $params];
}

function md2Tokens(string $name): array
{
    $name = mb_strtoupper($name, 'UTF-8');
    $name = str_replace(['Á','É','Í','Ó','Ú'], ['A','E','I','O','U'], $name);
    $tokens = preg_split('/[^A-Z0-9Ñ]+/u', $name) ?: [];
    $stop = ['ZONA','POL','POLICIA','POLICIAL','DIRECCION','DIRECCIÓN','DIR','DEPTO','DEPARTAMENTO','SECCION','SECCIONAL','UNIDAD','UNID','DE','DEL','LA','EL','LOS','LAS','PARA','CON','NAC','NAL'];
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

function md2Table(string $title, array $rows, array $cols, string $id): void
{
    ?>
    <section class="card" id="<?= e($id) ?>">
        <div class="card-header"><div><h3><?= e($title) ?></h3><p><?= e(count($rows)) ?> registros</p></div></div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead><tr><?php foreach ($cols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="<?= e(count($cols)) ?>" class="empty">Sin datos</td></tr><?php endif; ?>
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

function md2Card(string $label, int $total, string $href): void
{
    ?>
    <a class="report-card" href="<?= e($href) ?>" style="display:block;text-decoration:none;color:inherit;">
        <span class="module-status">ABRIR</span>
        <strong><?= e(number_format($total)) ?></strong>
        <small><?= e($label) ?></small>
    </a>
    <?php
}

$seleccion = null;
if ($p > 0) {
    $rows = md2Rows($db, 'SELECT id, parent_id, legacy_code AS codigo, name AS nombre FROM units WHERE id = :id LIMIT 1', [':id' => $p]);
    $seleccion = $rows[0] ?? null;
}

$ruta = [];
$cursor = $seleccion;
while ($cursor && count($ruta) < 30) {
    array_unshift($ruta, $cursor);
    $parentId = (int) ($cursor['parent_id'] ?? 0);
    if ($parentId <= 0) {
        break;
    }
    $parent = md2Rows($db, 'SELECT id, parent_id, legacy_code AS codigo, name AS nombre FROM units WHERE id = :id LIMIT 1', [':id' => $parentId]);
    $cursor = $parent[0] ?? null;
}

$resultados = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $resultados = md2Rows($db, "
        SELECT u.id, COALESCE(u.legacy_code, '') AS codigo, COALESCE(u.name, 'Sin nombre') AS nombre,
               COALESCE(pu.name, '') AS padre,
               (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
               COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN units pu ON pu.id = u.parent_id
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE COALESCE(u.legacy_code, '') LIKE :like
           OR UPPER(COALESCE(u.name, '')) LIKE UPPER(:like)
        GROUP BY u.id, codigo, nombre, padre, hijos
        ORDER BY hijos DESC, personal DESC, codigo ASC
        LIMIT 100
    ", [':like' => $like]);

    foreach ($resultados as &$row) {
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(md2Url(['p' => $id, 'q' => ''])) . '">Seleccionar</a>' : '';
    }
    unset($row);
}

$idsArbol = md2TreeIds($db, $p);
[$whereEmployee, $paramsEmployee] = md2In($idsArbol, 'e.unit_id');

$totalUnidades = $p > 0 ? count($idsArbol) : (int) $db->query('SELECT COUNT(*) FROM units')->fetchColumn();
$totalPersonal = $p > 0
    ? md2Scalar($db, "SELECT COUNT(*) FROM employees e WHERE {$whereEmployee}", $paramsEmployee)
    : (int) $db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$totalAcciones = $p > 0
    ? md2Scalar($db, "SELECT COUNT(a.id) FROM employee_actions a INNER JOIN employees e ON e.id = a.employee_id WHERE {$whereEmployee}", $paramsEmployee)
    : (int) $db->query('SELECT COUNT(*) FROM employee_actions')->fetchColumn();

$inicio = [];
if ($p <= 0 && $q === '') {
    $inicio = md2Rows($db, "
        SELECT u.id, COALESCE(u.legacy_code, '') AS codigo, COALESCE(u.name, 'Sin nombre') AS nombre,
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
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(md2Url(['p' => $id])) . '">Abrir</a>' : '';
    }
    unset($row);
}

$hijos = [];
$relacionadas = [];
if ($p > 0) {
    $hijos = md2Rows($db, "
        SELECT u.id, COALESCE(u.legacy_code, '') AS codigo, COALESCE(u.name, 'Sin nombre') AS nombre,
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
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
        $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(md2Url(['p' => $id])) . '">Abrir lo que contiene</a>' : '';
    }
    unset($row);

    if (empty($hijos) && $seleccion) {
        $tokens = md2Tokens((string) ($seleccion['nombre'] ?? ''));
        $where = [];
        $params = [':actual' => $p];
        foreach (array_slice($tokens, 0, 5) as $i => $token) {
            $key = ':tok' . $i;
            $where[] = 'UPPER(COALESCE(u.name, \'\')) LIKE ' . $key;
            $params[$key] = '%' . $token . '%';
        }
        if ($where !== []) {
            $relacionadas = md2Rows($db, "
                SELECT u.id, COALESCE(u.legacy_code, '') AS codigo, COALESCE(u.name, 'Sin nombre') AS nombre,
                       COALESCE(pu.name, '') AS padre,
                       (SELECT COUNT(*) FROM units h WHERE h.parent_id = u.id) AS hijos,
                       COUNT(e.id) AS personal
                FROM units u
                LEFT JOIN units pu ON pu.id = u.parent_id
                LEFT JOIN employees e ON e.unit_id = u.id
                WHERE u.id <> :actual AND (" . implode(' OR ', $where) . ")
                GROUP BY u.id, codigo, nombre, padre, hijos
                ORDER BY hijos DESC, personal DESC, codigo ASC
                LIMIT {$limit}
            ", $params);
            foreach ($relacionadas as &$row) {
                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
                $row['_link'] = $id > 0 ? '<a class="button-secondary" href="' . e(md2Url(['p' => $id])) . '">Seleccionar</a>' : '';
            }
            unset($row);
        }
    }
}

$personalEstado = md2Rows($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    WHERE {$whereEmployee}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $paramsEmployee);

$personalRango = md2Rows($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    WHERE {$whereEmployee}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $paramsEmployee);

$accionesTipo = md2Rows($db, "
    SELECT a.action_type_id AS codigo,
           COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total,
           MIN(a.action_date) AS fecha_minima,
           MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    INNER JOIN employees e ON e.id = a.employee_id
    WHERE {$whereEmployee}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $paramsEmployee);
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Explorador por búsqueda, padres reales y posibles hijas por coincidencia. Base: <strong><?= e($base) ?></strong>.</p>
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
        <?php foreach ($ruta as $item): ?>
            › <a href="<?= e(md2Url(['p' => (int) $item['id'], 'q' => ''])) ?>"><?= e(($item['codigo'] ?? '') . ' - ' . ($item['nombre'] ?? '')) ?></a>
        <?php endforeach; ?>
    </p>
</section>

<section class="card">
    <h3>Botones interactivos del nivel actual</h3>
    <div class="report-menu">
        <?php md2Card('Unidades dentro', $totalUnidades, '#unidades'); ?>
        <?php md2Card('Funcionarios dentro', $totalPersonal, '#personal-por-estado'); ?>
        <?php md2Card('Acciones dentro', $totalAcciones, '#acciones-por-tipo'); ?>
        <?php md2Card('Estados del personal', count($personalEstado), '#personal-por-estado'); ?>
        <?php md2Card('Rangos del personal', count($personalRango), '#personal-por-rango'); ?>
    </div>
</section>

<?php if ($q !== ''): ?>
    <?php md2Table('Resultados para: ' . $q, $resultados, ['codigo' => 'Código', 'nombre' => 'Nombre', 'padre' => 'Padre actual', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Seleccionar'], 'resultados'); ?>
<?php endif; ?>

<?php if ($p <= 0 && $q === ''): ?>
    <?php md2Table('Inicio: unidades sin padre', $inicio, ['codigo' => 'Código', 'nombre' => 'Nombre', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
<?php endif; ?>

<?php if ($p > 0): ?>
    <?php md2Table('Hijos reales por parent_id', $hijos, ['codigo' => 'Código', 'nombre' => 'Nombre', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Abrir'], 'unidades'); ?>
    <?php if (empty($hijos) && !empty($relacionadas)): ?>
        <section class="card muted"><p>Esta selección no tiene hijos reales por <strong>parent_id</strong>. Abajo aparecen posibles hijas por coincidencia de nombre para revisar si deben ser enlazadas.</p></section>
        <?php md2Table('Posibles hijas / relacionadas por nombre', $relacionadas, ['codigo' => 'Código', 'nombre' => 'Nombre', 'padre' => 'Padre actual', 'hijos' => 'Hijos reales', 'personal' => 'Personal directo', '_link' => 'Seleccionar'], 'relacionadas'); ?>
    <?php endif; ?>
<?php endif; ?>

<div class="grid-2">
    <?php md2Table('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total'], 'personal-por-estado'); ?>
    <?php md2Table('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total'], 'personal-por-rango'); ?>
</div>

<?php md2Table('Acciones por tipo', $accionesTipo, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima'], 'acciones-por-tipo'); ?>

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
