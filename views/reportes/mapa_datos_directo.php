<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();

$totales = [
    'Funcionarios' => (int) $db->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'Acciones de personal' => (int) $db->query('SELECT COUNT(*) FROM employee_actions')->fetchColumn(),
    'Rangos' => (int) $db->query('SELECT COUNT(*) FROM ranks')->fetchColumn(),
    'Dependencias / unidades' => (int) $db->query('SELECT COUNT(*) FROM units')->fetchColumn(),
    'Estados de personal' => (int) $db->query('SELECT COUNT(*) FROM statuses')->fetchColumn(),
    'Tipos de acción' => (int) $db->query('SELECT COUNT(*) FROM action_types')->fetchColumn(),
];

function mapaDirectoQuery(PDO $db, string $sql): array
{
    try {
        return $db->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [['codigo' => 'ERROR', 'nombre' => $e->getMessage(), 'total' => 0]];
    }
}

function mapaDirectoTabla(string $titulo, array $rows, array $cols): void
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
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_keys($cols) as $key): ?>
                                <td><?= e($row[$key] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

$accionesTipo = mapaDirectoQuery($db, "
    SELECT a.action_type_id AS codigo, COALESCE(at.name, CONCAT('Tipo ', a.action_type_id)) AS nombre,
           COUNT(*) AS total, MIN(a.action_date) AS fecha_minima, MAX(a.action_date) AS fecha_maxima
    FROM employee_actions a
    LEFT JOIN action_types at ON at.id = a.action_type_id
    GROUP BY a.action_type_id, at.name
    ORDER BY total DESC
");

$personalEstado = mapaDirectoQuery($db, "
    SELECT COALESCE(s.legacy_code, e.legacy_status_code, 'SIN') AS codigo,
           COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN statuses s ON s.id = e.status_id
    GROUP BY codigo, nombre
    ORDER BY total DESC
");

$personalRango = mapaDirectoQuery($db, "
    SELECT COALESCE(r.legacy_code, 'SIN') AS codigo,
           COALESCE(r.name, e.legacy_rank_name, 'Sin rango') AS nombre,
           COUNT(*) AS total
    FROM employees e
    LEFT JOIN ranks r ON r.id = e.rank_id
    GROUP BY codigo, nombre
    ORDER BY CAST(codigo AS UNSIGNED) ASC
");

$dependencias = mapaDirectoQuery($db, "
    SELECT COALESCE(u.legacy_code, 'SIN') AS codigo,
           COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, 'Sin dependencia') AS nombre,
           COUNT(e.id) AS total
    FROM units u
    LEFT JOIN employees e ON e.unit_id = u.id
    GROUP BY codigo, nombre
    ORDER BY total DESC
    LIMIT 200
");

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
    GROUP BY codigo, nombre
    ORDER BY codigo ASC
");

$sexo = mapaDirectoQuery($db, "
    SELECT COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN') AS codigo,
           CASE COALESCE(NULLIF(TRIM(e.sex), ''), 'SIN') WHEN 'M' THEN 'Masculino' WHEN 'F' THEN 'Femenino' ELSE 'Sin dato' END AS nombre,
           COUNT(*) AS total
    FROM employees e
    GROUP BY codigo, nombre
    ORDER BY total DESC
");

$accionesAnio = mapaDirectoQuery($db, "
    SELECT CASE WHEN action_date IS NULL THEN 'SIN FECHA' ELSE CAST(YEAR(action_date) AS CHAR) END AS codigo,
           CASE WHEN action_date IS NULL THEN 'Sin fecha' ELSE CAST(YEAR(action_date) AS CHAR) END AS nombre,
           COUNT(*) AS total
    FROM employee_actions
    GROUP BY codigo, nombre
    ORDER BY codigo DESC
    LIMIT 80
");
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Vista maestra directa de la base <strong><?= e($base) ?></strong>: personal, zonas, áreas, dependencias, acciones y estados.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos/diagnostico')) ?>">Diagnóstico</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<section class="card">
    <h3>Totales generales</h3>
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
    <p>Este módulo no reemplaza los reportes finales. Sirve para auditar y entender cómo está sectorizada la información antes de crear nuevos módulos.</p>
</section>

<?php mapaDirectoTabla('Zonas / nivel 1', $zonas, ['codigo' => 'Código', 'nombre' => 'Zona', 'dependencias' => 'Dependencias', 'personal' => 'Personal']); ?>
<?php mapaDirectoTabla('Áreas / nivel 2', $areas, ['codigo' => 'Código', 'nombre' => 'Área', 'dependencias' => 'Dependencias', 'personal' => 'Personal']); ?>
<?php mapaDirectoTabla('Dependencias con más personal', $dependencias, ['codigo' => 'Código', 'nombre' => 'Dependencia', 'total' => 'Personal']); ?>

<div class="grid-2">
    <?php mapaDirectoTabla('Personal por estado', $personalEstado, ['codigo' => 'Código', 'nombre' => 'Estado', 'total' => 'Total']); ?>
    <?php mapaDirectoTabla('Personal por sexo', $sexo, ['codigo' => 'Código', 'nombre' => 'Sexo', 'total' => 'Total']); ?>
</div>

<?php mapaDirectoTabla('Personal por rango', $personalRango, ['codigo' => 'Código', 'nombre' => 'Rango', 'total' => 'Total']); ?>
<?php mapaDirectoTabla('Acciones por tipo', $accionesTipo, ['codigo' => 'Tipo', 'nombre' => 'Acción', 'total' => 'Total', 'fecha_minima' => 'Fecha mínima', 'fecha_maxima' => 'Fecha máxima']); ?>
<?php mapaDirectoTabla('Acciones por año', $accionesAnio, ['codigo' => 'Año', 'nombre' => 'Descripción', 'total' => 'Total']); ?>
