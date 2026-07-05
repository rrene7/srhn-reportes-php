<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$zona = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['zona'] ?? '')));
$area = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['area'] ?? '')));
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$limit = max(25, min(500, (int) ($_GET['limit'] ?? 200)));

function mapaDirectoUrl(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return url('/reportes/mapa-datos' . ($params ? '?' . http_build_query($params) : ''));
}

function mapaDirectoQuery(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [['codigo' => 'ERROR', 'nombre' => $e->getMessage(), 'total' => 0]];
    }
}

function mapaDirectoScalar(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mapaDirectoTabla(string $titulo, array $rows, array $cols, ?string $filtroCodigo = null): void
{
    ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3><?= e($titulo) ?></h3>
                <p><?= e(count($rows)) ?> registros agrupados</p>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead>
                    <tr>
                        <?php foreach ($cols as $label): ?>
                            <th><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= e(count($cols)) ?>" class="empty">Sin datos</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_keys($cols) as $key): ?>
                                <td>
                                    <?php if ($key === 'codigo' && $filtroCodigo !== null && ($row[$key] ?? '') !== 'ERROR'): ?>
                                        <a href="<?= e(mapaDirectoUrl([$filtroCodigo => $row[$key] ?? '', $filtroCodigo === 'zona' ? 'area' : 'x' => ''])) ?>">
                                            <?= e($row[$key] ?? '') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($row[$key] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

$unitWhere = ['1 = 1'];
$params = [];
if ($zona !== '') {
    $unitWhere[] = "COALESCE(u.legacy_code, '') LIKE :zona";
    $params[':zona'] = $zona . '%';
}
if ($area !== '') {
    $unitWhere[] = "COALESCE(u.legacy_code, '') LIKE :area";
    $params[':area'] = $area . '%';
}
if ($buscar !== '') {
    $unitWhere[] = "(COALESCE(u.legacy_code, '') LIKE :buscar OR COALESCE(u.name, '') LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}
$whereUnits = implode(' AND ', $unitWhere);

$totales = [
    'Funcionarios filtrados' => mapaDirectoScalar($db, "SELECT COUNT(e.id) FROM employees e LEFT JOIN units u ON u.id = e.unit_id WHERE {$whereUnits}", $params),
    'Acciones filtradas' => mapaDirectoScalar($db, "SELECT COUNT(a.id) FROM employee_actions a LEFT JOIN employees e ON e.id = a.employee_id LEFT JOIN units u ON u.id = e.unit_id WHERE {$whereUnits}", $params),
    'Dependencias filtradas' => mapaDirectoScalar($db, "SELECT COUNT(*) FROM units u WHERE {$whereUnits}", $params),
    'Rangos catálogo' => (int) $db->query('SELECT COUNT(*) FROM ranks')->fetchColumn(),
    'Estados catálogo' => (int) $db->query('SELECT COUNT(*) FROM statuses')->fetchColumn(),
    'Tipos de acción' => (int) $db->query('SELECT COUNT(*) FROM action_types')->fetchColumn(),
];

$accionesTipo = mapaDirectoQuery($db, "
    SELECT a.action_type_id AS codigo, COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total, MIN(a.action_date) AS fecha_minima, MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    LEFT JOIN employees e ON e.id = a.employee_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnits}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $params);

$personalEstado = mapaDirectoQuery($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnits}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $params);

$personalRango = mapaDirectoQuery($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnits}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $params);

$dependencias = mapaDirectoQuery($db, "
    SELECT COALESCE(u.legacy_code, 'SIN') AS codigo,
           COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia') AS nombre,
           COUNT(e.id) AS total
    FROM units u
    LEFT JOIN employees e ON e.unit_id = u.id
    WHERE {$whereUnits}
    GROUP BY codigo, nombre
    ORDER BY total DESC
    LIMIT {$limit}
", $params);

$zonas = mapaDirectoQuery($db, "
    SELECT CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 2) END AS codigo,
           CONCAT('Zona ', CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 2) END) AS nombre,
           COUNT(DISTINCT u.id) AS dependencias,
           COUNT(e.id) AS personal
    FROM units u
    LEFT JOIN employees e ON e.unit_id = u.id
    GROUP BY codigo, nombre
    ORDER BY codigo ASC
");

$areas = mapaDirectoQuery($db, "
    SELECT CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 4) END AS codigo,
           CONCAT('Área ', CASE WHEN COALESCE(u.legacy_code, '') = '' THEN 'SIN' ELSE LEFT(u.legacy_code, 4) END) AS nombre,
           COUNT(DISTINCT u.id) AS dependencias,
           COUNT(e.id) AS personal
    FROM units u
    LEFT JOIN employees e ON e.unit_id = u.id
    WHERE " . ($zona !== '' ? "COALESCE(u.legacy_code, '') LIKE :zona" : '1 = 1') . "
    GROUP BY codigo, nombre
    ORDER BY codigo ASC
", $zona !== '' ? [':zona' => $zona . '%'] : []);

$sexo = mapaDirectoQuery($db, "
    SELECT COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN') AS codigo,
           CASE COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN') WHEN 'M' THEN 'Masculino' WHEN 'F' THEN 'Femenino' ELSE 'Sin dato' END AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnits}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $params);

$accionesAnio = mapaDirectoQuery($db, "
    SELECT CASE WHEN a.action_date IS NULL THEN 'SIN FECHA' ELSE CAST(YEAR(a.action_date) AS CHAR) END AS codigo,
           CASE WHEN a.action_date IS NULL THEN 'Sin fecha' ELSE CAST(YEAR(a.action_date) AS CHAR) END AS nombre,
           COUNT(*) AS total
    FROM employee_actions a
    LEFT JOIN employees e ON e.id = a.employee_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnits}
    GROUP BY codigo, nombre
    ORDER BY codigo DESC
    LIMIT 80
", $params);
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Vista interactiva de la base <strong><?= e($base) ?></strong>: personal, zonas, áreas, dependencias, acciones y estados.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos/diagnostico')) ?>">Diagnóstico</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h3>Filtros interactivos</h3>
            <p>Haz clic en una zona o área, o filtra por código/nombre de dependencia.</p>
        </div>
    </div>
    <form method="get" action="<?= e(url('/reportes/mapa-datos')) ?>" class="filters">
        <div class="field">
            <label for="zona">Zona / prefijo nivel 1</label>
            <input type="text" name="zona" id="zona" value="<?= e($zona) ?>" placeholder="Ej. 01">
        </div>
        <div class="field">
            <label for="area">Área / prefijo nivel 2</label>
            <input type="text" name="area" id="area" value="<?= e($area) ?>" placeholder="Ej. 0101">
        </div>
        <div class="field field-wide">
            <label for="buscar">Buscar dependencia</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($buscar) ?>" placeholder="Código o nombre de dependencia">
        </div>
        <div class="field">
            <label for="limit">Límite</label>
            <select name="limit" id="limit">
                <?php foreach ([50, 100, 200, 500] as $opcion): ?>
                    <option value="<?= e($opcion) ?>" <?= $limit === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button type="submit">Aplicar filtros</button>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <h3>Totales según filtro</h3>
    <div class="report-menu">
        <?php foreach ($totales as $label => $total): ?>
            <div class="report-card">
                <span class="module-status">BASE</span>
                <strong><?= e(number_format($total)) ?></strong>
                <small><?= e($label) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card muted">
    <h3>Lectura del mapa</h3>
    <p>Este módulo permite explorar la base por niveles: zona, área y dependencia. Los enlaces en las tablas aplican filtros automáticamente.</p>
</section>

<?php mapaDirectoTabla('Zonas / nivel 1', $zonas, ['codigo' => 'Código', 'nombre' => 'Zona', 'dependencias' => 'Dependencias', 'personal' => 'Personal'], 'zona'); ?>
<?php mapaDirectoTabla('Áreas / nivel 2', $areas, ['codigo' => 'Código', 'nombre' => 'Área', 'dependencias' => 'Dependencias', 'personal' => 'Personal'], 'area'); ?>
<?php mapaDirectoTabla('Dependencias con más personal', $dependencias, ['codigo' => 'Código', 'nombre' => 'Dependencia', 'total' => 'Personal']); ?>

<div class="grid-2">
    <?php mapaDirectoTabla('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total']); ?>
    <?php mapaDirectoTabla('Personal por sexo', $sexo, ['codigo' => 'Código', 'nombre' => 'Sexo', 'total' => 'Total']); ?>
</div>

<?php mapaDirectoTabla('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total']); ?>
<?php mapaDirectoTabla('Acciones por tipo', $accionesTipo, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima']); ?>
<?php mapaDirectoTabla('Acciones por año', $accionesAnio, ['codigo' => 'Año', 'nombre' => 'Descripción', 'total' => 'Total']); ?>
