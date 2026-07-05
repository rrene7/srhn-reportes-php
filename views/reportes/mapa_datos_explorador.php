<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$q = trim((string) ($_GET['q'] ?? ''));
$p = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['p'] ?? '')));
$limit = max(25, min(300, (int) ($_GET['limit'] ?? 100)));

function mdUrl(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
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
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [['codigo' => 'ERROR', 'nombre' => $e->getMessage(), 'total' => 0]];
    }
}

function mdScalar(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
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

function mdTable(string $titulo, array $rows, array $cols): void
{
    ?>
    <section class="card" id="<?= e(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $titulo))) ?>">
        <div class="card-header">
            <div>
                <h3><?= e($titulo) ?></h3>
                <p><?= e(count($rows)) ?> registros</p>
            </div>
        </div>
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
if ($p !== '') {
    $sel = mdRows($db, "
        SELECT legacy_code AS codigo, name AS nombre
        FROM units
        WHERE COALESCE(legacy_code, '') = :p
        LIMIT 1
    ", [':p' => $p]);
    $seleccion = $sel[0] ?? null;
}

$resultadosBusqueda = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $resultadosBusqueda = mdRows($db, "
        SELECT
            COALESCE(u.legacy_code, '') AS codigo,
            COALESCE(u.name, 'Sin nombre') AS nombre,
            COUNT(e.id) AS personal,
            CASE
                WHEN CHAR_LENGTH(COALESCE(u.legacy_code, '')) <= 2 THEN 'Zona / nivel superior'
                WHEN CHAR_LENGTH(COALESCE(u.legacy_code, '')) <= 4 THEN 'Área / nivel intermedio'
                ELSE 'Unidad / dependencia'
            END AS tipo
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE COALESCE(u.legacy_code, '') LIKE :like
           OR UPPER(COALESCE(u.name, '')) LIKE UPPER(:like)
        GROUP BY codigo, nombre, tipo
        ORDER BY
            CASE WHEN UPPER(COALESCE(u.name, '')) LIKE UPPER(:exactStart) THEN 0 ELSE 1 END,
            personal DESC,
            CHAR_LENGTH(codigo) ASC,
            codigo ASC
        LIMIT 100
    ", [':like' => $like, ':exactStart' => $q . '%']);

    foreach ($resultadosBusqueda as &$row) {
        $codigo = (string) ($row['codigo'] ?? '');
        $row['_link'] = $codigo !== ''
            ? '<a class="button-secondary" href="' . e(mdUrl(['p' => $codigo, 'q' => ''])) . '">Seleccionar</a>'
            : '';
    }
    unset($row);
}

$wherePrefix = '1 = 1';
$paramsPrefix = [];
if ($p !== '') {
    $wherePrefix = "COALESCE(u.legacy_code, '') LIKE :pref";
    $paramsPrefix[':pref'] = $p . '%';
}

$totalDependencias = mdScalar($db, "SELECT COUNT(*) FROM units u WHERE {$wherePrefix}", $paramsPrefix);
$totalPersonal = mdScalar($db, "SELECT COUNT(e.id) FROM employees e LEFT JOIN units u ON u.id = e.unit_id WHERE {$wherePrefix}", $paramsPrefix);
$totalAcciones = mdScalar($db, "SELECT COUNT(a.id) FROM employee_actions a LEFT JOIN employees e ON e.id = a.employee_id LEFT JOIN units u ON u.id = e.unit_id WHERE {$wherePrefix}", $paramsPrefix);

$inicio = [];
if ($p === '' && $q === '') {
    $inicio = mdRows($db, "
        SELECT LEFT(COALESCE(u.legacy_code, ''), 2) AS codigo,
               COALESCE(MAX(CASE WHEN UPPER(u.name) LIKE '%ZONA%' THEN u.name END), MIN(u.name), CONCAT('Zona ', LEFT(COALESCE(u.legacy_code, ''), 2))) AS nombre,
               COUNT(DISTINCT u.id) AS dependencias,
               COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE COALESCE(u.legacy_code, '') <> ''
        GROUP BY codigo
        ORDER BY codigo ASC
    ");
    foreach ($inicio as &$row) {
        $codigo = (string) ($row['codigo'] ?? '');
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => $codigo])) . '">Abrir</a>';
    }
    unset($row);
}

$hijos = [];
$descendientes = [];
if ($p !== '') {
    $nextLen = mdScalar($db, "
        SELECT MIN(CHAR_LENGTH(COALESCE(legacy_code, '')))
        FROM units
        WHERE COALESCE(legacy_code, '') LIKE :pref
          AND COALESCE(legacy_code, '') <> :p
          AND CHAR_LENGTH(COALESCE(legacy_code, '')) > CHAR_LENGTH(:p)
    ", [':pref' => $p . '%', ':p' => $p]);

    if ($nextLen > 0) {
        $hijos = mdRows($db, "
            SELECT COALESCE(u.legacy_code, '') AS codigo,
                   COALESCE(u.name, 'Sin nombre') AS nombre,
                   COUNT(e.id) AS personal,
                   COUNT(DISTINCT ux.id) AS dentro
            FROM units u
            LEFT JOIN employees e ON e.unit_id = u.id
            LEFT JOIN units ux ON COALESCE(ux.legacy_code, '') LIKE CONCAT(COALESCE(u.legacy_code, ''), '%')
            WHERE COALESCE(u.legacy_code, '') LIKE :pref
              AND COALESCE(u.legacy_code, '') <> :p
              AND CHAR_LENGTH(COALESCE(u.legacy_code, '')) = :nextLen
            GROUP BY codigo, nombre
            ORDER BY codigo ASC
            LIMIT {$limit}
        ", [':pref' => $p . '%', ':p' => $p, ':nextLen' => $nextLen]);
    }

    if (empty($hijos)) {
        $descendientes = mdRows($db, "
            SELECT COALESCE(u.legacy_code, '') AS codigo,
                   COALESCE(u.name, 'Sin nombre') AS nombre,
                   COUNT(e.id) AS personal
            FROM units u
            LEFT JOIN employees e ON e.unit_id = u.id
            WHERE COALESCE(u.legacy_code, '') LIKE :pref
            GROUP BY codigo, nombre
            ORDER BY personal DESC, codigo ASC
            LIMIT {$limit}
        ", [':pref' => $p . '%']);
    }

    foreach ($hijos as &$row) {
        $codigo = (string) ($row['codigo'] ?? '');
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => $codigo])) . '">Abrir lo que contiene</a>';
    }
    unset($row);

    foreach ($descendientes as &$row) {
        $codigo = (string) ($row['codigo'] ?? '');
        $row['_link'] = '<a class="button-secondary" href="' . e(mdUrl(['p' => $codigo])) . '">Ver</a>';
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
    WHERE {$wherePrefix}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $paramsPrefix);

$personalRango = mdRows($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$wherePrefix}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $paramsPrefix);

$accionesTipo = mdRows($db, "
    SELECT a.action_type_id AS codigo,
           COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total,
           MIN(a.action_date) AS fecha_minima,
           MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    LEFT JOIN employees e ON e.id = a.employee_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$wherePrefix}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $paramsPrefix);
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Explorador por coincidencia y selección. Base: <strong><?= e($base) ?></strong>.</p>
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
    <p>Escribe por ejemplo <strong>Chiriquí</strong>, <strong>David</strong>, <strong>Panamá Oeste</strong> o un código. Luego selecciona el resultado correcto.</p>
    <form method="get" action="<?= e(url('/reportes/mapa-datos')) ?>" class="filters">
        <div class="field field-wide">
            <label for="q">Buscar zona, área, sector o dependencia</label>
            <input type="text" name="q" id="q" value="<?= e($q) ?>" placeholder="Ej. Chiriquí, David, Policía de David Centro">
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
    <h3>Selección actual</h3>
    <p>
        <a href="<?= e(url('/reportes/mapa-datos')) ?>">Inicio</a>
        <?php if ($seleccion): ?>
            › <?= e(($seleccion['codigo'] ?? '') . ' - ' . ($seleccion['nombre'] ?? '')) ?>
        <?php elseif ($p !== ''): ?>
            › <?= e($p) ?>
        <?php endif; ?>
    </p>
</section>

<section class="card">
    <h3>Botones interactivos del nivel actual</h3>
    <div class="report-menu">
        <?php mdCardLink('Dependencias / unidades', $totalDependencias, '#hijos-directos'); ?>
        <?php mdCardLink('Funcionarios relacionados', $totalPersonal, '#personal-por-estado'); ?>
        <?php mdCardLink('Acciones relacionadas', $totalAcciones, '#acciones-por-tipo'); ?>
        <?php mdCardLink('Estados del personal', count($personalEstado), '#personal-por-estado'); ?>
        <?php mdCardLink('Rangos del personal', count($personalRango), '#personal-por-rango'); ?>
    </div>
</section>

<?php if ($q !== ''): ?>
    <?php mdTable('Resultados para: ' . $q, $resultadosBusqueda, ['codigo' => 'Código', 'nombre' => 'Nombre real', 'tipo' => 'Tipo estimado', 'personal' => 'Personal', '_link' => 'Seleccionar']); ?>
<?php endif; ?>

<?php if ($p === '' && $q === ''): ?>
    <?php mdTable('Inicio: zonas / grupos principales', $inicio, ['codigo' => 'Código', 'nombre' => 'Nombre', 'dependencias' => 'Dentro', 'personal' => 'Personal', '_link' => 'Abrir']); ?>
<?php endif; ?>

<?php if ($p !== ''): ?>
    <?php if (!empty($hijos)): ?>
        <?php mdTable('Hijos directos / siguiente nivel dentro de la selección', $hijos, ['codigo' => 'Código', 'nombre' => 'Nombre real', 'personal' => 'Personal directo', 'dentro' => 'Unidades dentro', '_link' => 'Abrir']); ?>
    <?php else: ?>
        <?php mdTable('Unidades dentro de la selección', $descendientes, ['codigo' => 'Código', 'nombre' => 'Nombre real', 'personal' => 'Personal', '_link' => 'Ver']); ?>
    <?php endif; ?>
<?php endif; ?>

<div class="grid-2">
    <?php mdTable('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total']); ?>
    <?php mdTable('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total']); ?>
</div>

<?php mdTable('Acciones por tipo', $accionesTipo, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima']); ?>
