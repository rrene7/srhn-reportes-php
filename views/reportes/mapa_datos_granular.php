<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$zona = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['zona'] ?? '')));
$area = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['area'] ?? '')));
$dep = preg_replace('/[^0-9A-Za-z]/', '', trim((string) ($_GET['dep'] ?? '')));
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$limit = max(25, min(500, (int) ($_GET['limit'] ?? 100)));

function mgUrl(array $extra = []): string
{
    $params = array_merge($_GET, $extra);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }
    return url('/reportes/mapa-datos' . ($params ? '?' . http_build_query($params) : ''));
}

function mgRows(PDO $db, string $sql, array $params = []): array
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

function mgScalar(PDO $db, string $sql, array $params = []): int
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

function mgNombrePrefix(PDO $db, string $prefix): string
{
    if ($prefix === '') {
        return '';
    }
    $rows = mgRows($db, "
        SELECT name
        FROM units
        WHERE COALESCE(legacy_code, '') LIKE :pref
          AND COALESCE(name, '') <> ''
        ORDER BY
          CASE WHEN RIGHT(COALESCE(legacy_code, ''), GREATEST(CHAR_LENGTH(COALESCE(legacy_code, '')) - CHAR_LENGTH(:code), 0)) REGEXP '^0*$' THEN 0 ELSE 1 END,
          CHAR_LENGTH(name) ASC,
          name ASC
        LIMIT 1
    ", [':pref' => $prefix . '%', ':code' => $prefix]);
    return (string) ($rows[0]['name'] ?? '');
}

function mgTable(string $titulo, array $rows, array $cols): void
{
    ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3><?= e($titulo) ?></h3>
                <p><?= e(count($rows)) ?> registros</p>
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

$nivel = 'zonas';
$prefix = '';
if ($dep !== '') {
    $nivel = 'dependencia';
    $prefix = $dep;
} elseif ($area !== '') {
    $nivel = 'dependencias';
    $prefix = $area;
} elseif ($zona !== '') {
    $nivel = 'areas';
    $prefix = $zona;
}

$whereUnit = ['1 = 1'];
$params = [];
if ($prefix !== '') {
    if ($nivel === 'dependencia') {
        $whereUnit[] = "COALESCE(u.legacy_code, '') = :dep";
        $params[':dep'] = $dep;
    } else {
        $whereUnit[] = "COALESCE(u.legacy_code, '') LIKE :prefix";
        $params[':prefix'] = $prefix . '%';
    }
}
if ($buscar !== '') {
    $whereUnit[] = "(COALESCE(u.legacy_code, '') LIKE :buscar OR COALESCE(u.name, '') LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}
$whereUnitSql = implode(' AND ', $whereUnit);

$zonaNombre = $zona !== '' ? mgNombrePrefix($db, $zona) : '';
$areaNombre = $area !== '' ? mgNombrePrefix($db, $area) : '';
$depNombre = $dep !== '' ? mgNombrePrefix($db, $dep) : '';

$totales = [
    'Dependencias' => mgScalar($db, "SELECT COUNT(*) FROM units u WHERE {$whereUnitSql}", $params),
    'Funcionarios' => mgScalar($db, "SELECT COUNT(e.id) FROM employees e LEFT JOIN units u ON u.id = e.unit_id WHERE {$whereUnitSql}", $params),
    'Acciones' => mgScalar($db, "SELECT COUNT(a.id) FROM employee_actions a LEFT JOIN employees e ON e.id = a.employee_id LEFT JOIN units u ON u.id = e.unit_id WHERE {$whereUnitSql}", $params),
    'Rangos catálogo' => (int) $db->query('SELECT COUNT(*) FROM ranks')->fetchColumn(),
    'Estados catálogo' => (int) $db->query('SELECT COUNT(*) FROM statuses')->fetchColumn(),
    'Tipos acción' => (int) $db->query('SELECT COUNT(*) FROM action_types')->fetchColumn(),
];

$zonas = mgRows($db, "
    SELECT LEFT(COALESCE(u.legacy_code, 'SIN'), 2) AS codigo,
           COALESCE(MAX(CASE WHEN UPPER(u.name) LIKE '%ZONA%' THEN u.name END), MIN(u.name), CONCAT('Zona ', LEFT(COALESCE(u.legacy_code, 'SIN'), 2))) AS nombre,
           COUNT(DISTINCT u.id) AS dependencias,
           COUNT(e.id) AS personal
    FROM units u
    LEFT JOIN employees e ON e.unit_id = u.id
    WHERE COALESCE(u.legacy_code, '') <> ''
    GROUP BY codigo
    ORDER BY codigo ASC
");
foreach ($zonas as &$r) {
    $codigo = (string) ($r['codigo'] ?? '');
    $r['_link'] = '<a href="' . e(mgUrl(['zona' => $codigo, 'area' => '', 'dep' => ''])) . '">Abrir áreas</a>';
}
unset($r);

$areas = [];
if ($zona !== '') {
    $areas = mgRows($db, "
        SELECT LEFT(COALESCE(u.legacy_code, 'SIN'), 4) AS codigo,
               COALESCE(MAX(CASE WHEN UPPER(u.name) LIKE '%AREA%' OR UPPER(u.name) LIKE '%ÁREA%' THEN u.name END), MIN(u.name), CONCAT('Área ', LEFT(COALESCE(u.legacy_code, 'SIN'), 4))) AS nombre,
               COUNT(DISTINCT u.id) AS dependencias,
               COUNT(e.id) AS personal
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        WHERE COALESCE(u.legacy_code, '') LIKE :zona
        GROUP BY codigo
        ORDER BY codigo ASC
    ", [':zona' => $zona . '%']);
    foreach ($areas as &$r) {
        $codigo = (string) ($r['codigo'] ?? '');
        $r['_link'] = '<a href="' . e(mgUrl(['zona' => $zona, 'area' => $codigo, 'dep' => ''])) . '">Abrir dependencias</a>';
    }
    unset($r);
}

$dependencias = [];
if ($area !== '') {
    $dependencias = mgRows($db, "
        SELECT COALESCE(u.legacy_code, 'SIN') AS codigo,
               COALESCE(u.name, 'Sin dependencia') AS nombre,
               COUNT(e.id) AS personal,
               COUNT(a.id) AS acciones
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        LEFT JOIN employee_actions a ON a.employee_id = e.id
        WHERE COALESCE(u.legacy_code, '') LIKE :area
          AND (:buscar = '' OR COALESCE(u.legacy_code, '') LIKE :buscarLike OR COALESCE(u.name, '') LIKE :buscarLike)
        GROUP BY codigo, nombre
        ORDER BY personal DESC, codigo ASC
        LIMIT {$limit}
    ", [':area' => $area . '%', ':buscar' => $buscar, ':buscarLike' => '%' . $buscar . '%']);
    foreach ($dependencias as &$r) {
        $codigo = (string) ($r['codigo'] ?? '');
        $r['_link'] = '<a href="' . e(mgUrl(['zona' => $zona, 'area' => $area, 'dep' => $codigo])) . '">Ver detalle</a>';
    }
    unset($r);
}

$personalEstado = mgRows($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnitSql}
    GROUP BY codigo, nombre
    ORDER BY total DESC
", $params);

$personalRango = mgRows($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnitSql}
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
", $params);

$accionesTipo = mgRows($db, "
    SELECT a.action_type_id AS codigo,
           COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total,
           MIN(a.action_date) AS fecha_minima,
           MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    LEFT JOIN employees e ON e.id = a.employee_id
    LEFT JOIN units u ON u.id = e.unit_id
    WHERE {$whereUnitSql}
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
", $params);

$funcionarios = [];
if ($dep !== '') {
    $funcionarios = mgRows($db, "
        SELECT COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
               e.document_number AS cedula,
               TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
               COALESCE(r.name, e.legacy_rank_name, '') AS rango,
               COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado
        FROM employees e
        LEFT JOIN ranks r ON r.id = e.rank_id
        LEFT JOIN statuses s ON s.id = e.status_id
        LEFT JOIN units u ON u.id = e.unit_id
        WHERE {$whereUnitSql}
        ORDER BY r.sort_order ASC, e.last_name ASC, e.first_name ASC
        LIMIT {$limit}
    ", $params);
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Explorador granular de la base <strong><?= e($base) ?></strong>: zona → área → dependencia → funcionarios/acciones.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos/diagnostico')) ?>">Diagnóstico</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<section class="card no-print">
    <h3>Ruta de navegación</h3>
    <p>
        <a href="<?= e(url('/reportes/mapa-datos')) ?>">Zonas</a>
        <?php if ($zona !== ''): ?>
            › <a href="<?= e(mgUrl(['zona' => $zona, 'area' => '', 'dep' => ''])) ?>"><?= e($zona . ' - ' . ($zonaNombre ?: 'Zona')) ?></a>
        <?php endif; ?>
        <?php if ($area !== ''): ?>
            › <a href="<?= e(mgUrl(['zona' => $zona, 'area' => $area, 'dep' => ''])) ?>"><?= e($area . ' - ' . ($areaNombre ?: 'Área')) ?></a>
        <?php endif; ?>
        <?php if ($dep !== ''): ?>
            › <?= e($dep . ' - ' . ($depNombre ?: 'Dependencia')) ?>
        <?php endif; ?>
    </p>

    <form method="get" action="<?= e(url('/reportes/mapa-datos')) ?>" class="filters">
        <div class="field"><label>Zona</label><input type="text" name="zona" value="<?= e($zona) ?>" placeholder="Ej. 04"></div>
        <div class="field"><label>Área</label><input type="text" name="area" value="<?= e($area) ?>" placeholder="Ej. 0401"></div>
        <div class="field"><label>Dependencia</label><input type="text" name="dep" value="<?= e($dep) ?>" placeholder="Código exacto"></div>
        <div class="field field-wide"><label>Buscar dependencia</label><input type="text" name="buscar" value="<?= e($buscar) ?>" placeholder="Código o nombre"></div>
        <div class="field"><label>Límite</label><select name="limit"><?php foreach ([50,100,200,500] as $n): ?><option value="<?= e($n) ?>" <?= $limit === $n ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="actions"><button type="submit">Aplicar</button><a class="button-secondary" href="<?= e(url('/reportes/mapa-datos')) ?>">Limpiar</a></div>
    </form>
</section>

<section class="card">
    <h3>Totales del nivel actual</h3>
    <div class="report-menu">
        <?php foreach ($totales as $label => $total): ?>
            <div class="report-card"><span class="module-status">NIVEL</span><strong><?= e(number_format($total)) ?></strong><small><?= e($label) ?></small></div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($nivel === 'zonas'): ?>
    <?php mgTable('Zonas: seleccione una zona por código y nombre', $zonas, ['codigo' => 'Código', 'nombre' => 'Nombre', 'dependencias' => 'Dependencias', 'personal' => 'Personal', '_link' => 'Siguiente']); ?>
<?php elseif ($nivel === 'areas'): ?>
    <?php mgTable('Áreas dentro de ' . $zona . ' - ' . ($zonaNombre ?: 'Zona'), $areas, ['codigo' => 'Código', 'nombre' => 'Nombre', 'dependencias' => 'Dependencias', 'personal' => 'Personal', '_link' => 'Siguiente']); ?>
<?php elseif ($nivel === 'dependencias'): ?>
    <?php mgTable('Dependencias dentro de ' . $area . ' - ' . ($areaNombre ?: 'Área'), $dependencias, ['codigo' => 'Código', 'nombre' => 'Nombre', 'personal' => 'Personal', 'acciones' => 'Acciones', '_link' => 'Detalle']); ?>
<?php endif; ?>

<div class="grid-2">
    <?php mgTable('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total']); ?>
    <?php mgTable('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total']); ?>
</div>

<?php mgTable('Acciones por tipo en el nivel actual', $accionesTipo, ['codigo' => 'Código', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima']); ?>

<?php if ($dep !== ''): ?>
    <?php mgTable('Funcionarios de ' . $dep . ' - ' . ($depNombre ?: 'Dependencia'), $funcionarios, ['nemp' => 'N. Emp.', 'cedula' => 'Cédula', 'funcionario' => 'Funcionario', 'rango' => 'Rango', 'estado' => 'Estado']); ?>
<?php endif; ?>
