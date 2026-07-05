<?php
use App\Support\Database;

$db = Database::connect();
$base = (string) $db->query('SELECT DATABASE()')->fetchColumn();

function ejemploRows($db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [['legacy_code' => 'ERROR', 'name' => $e->getMessage()]];
    }
}

function ejemploScalar($db, string $sql, array $params = []): int
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

$candidatos = ejemploRows($db, "
    SELECT legacy_code, name
    FROM units
    WHERE UPPER(COALESCE(name, '')) LIKE '%CHIRIQUI%'
       OR UPPER(COALESCE(name, '')) LIKE '%CHIRIQUÍ%'
       OR UPPER(COALESCE(name, '')) LIKE '%DAVID%'
    ORDER BY
        CASE WHEN UPPER(COALESCE(name, '')) LIKE '%DAVID%' THEN 0 ELSE 1 END,
        CHAR_LENGTH(COALESCE(legacy_code, '')) DESC,
        legacy_code ASC
    LIMIT 20
");

$seleccion = $candidatos[0] ?? null;
$codigoFinal = (string) ($seleccion['legacy_code'] ?? '');
$niveles = [];
$longitudes = [2, 4, 6, 8, 10, 12, 14, 16];

foreach ($longitudes as $len) {
    if ($codigoFinal === '' || strlen($codigoFinal) < $len) {
        continue;
    }

    $prefijo = substr($codigoFinal, 0, $len);
    $representante = ejemploRows($db, "
        SELECT legacy_code, name
        FROM units
        WHERE COALESCE(legacy_code, '') LIKE :prefijo
          AND COALESCE(name, '') <> ''
        ORDER BY
          CASE WHEN TRIM(TRAILING '0' FROM COALESCE(legacy_code, '')) = :codigo THEN 0 ELSE 1 END,
          CHAR_LENGTH(COALESCE(legacy_code, '')) ASC,
          name ASC
        LIMIT 1
    ", [':prefijo' => $prefijo . '%', ':codigo' => $prefijo]);

    $personal = ejemploScalar($db, "
        SELECT COUNT(e.id)
        FROM employees e
        LEFT JOIN units u ON u.id = e.unit_id
        WHERE COALESCE(u.legacy_code, '') LIKE :prefijo
    ", [':prefijo' => $prefijo . '%']);

    $acciones = ejemploScalar($db, "
        SELECT COUNT(a.id)
        FROM employee_actions a
        LEFT JOIN employees e ON e.id = a.employee_id
        LEFT JOIN units u ON u.id = e.unit_id
        WHERE COALESCE(u.legacy_code, '') LIKE :prefijo
    ", [':prefijo' => $prefijo . '%']);

    $niveles[] = [
        'nivel' => count($niveles) + 1,
        'prefijo' => $prefijo,
        'codigo_real' => $representante[0]['legacy_code'] ?? $prefijo,
        'nombre' => $representante[0]['name'] ?? ('Código ' . $prefijo),
        'personal' => $personal,
        'acciones' => $acciones,
    ];
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Ejemplo real de navegación jerárquica</h2>
            <p>Base conectada: <strong><?= e($base) ?></strong>. Este ejemplo se toma directamente de la tabla <strong>units</strong>.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes/mapa-datos')) ?>">Volver al mapa</a>
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <h3>Candidatos encontrados</h3>
    <p>El sistema buscó nombres que contengan <strong>CHIRIQUI</strong>, <strong>CHIRIQUÍ</strong> o <strong>DAVID</strong>.</p>
    <div class="table-wrapper">
        <table class="mini-table">
            <thead><tr><th>Código</th><th>Nombre</th></tr></thead>
            <tbody>
                <?php if (empty($candidatos)): ?>
                    <tr><td colspan="2" class="empty">No se encontraron unidades con Chiriquí o David.</td></tr>
                <?php endif; ?>
                <?php foreach ($candidatos as $row): ?>
                    <tr>
                        <td><?= e($row['legacy_code'] ?? '') ?></td>
                        <td><?= e($row['name'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h3>Ejemplo de cadena real</h3>
    <?php if ($codigoFinal === ''): ?>
        <div class="alert alert-info">No hay código para construir la cadena.</div>
    <?php else: ?>
        <p>Se tomó como ejemplo el código más específico encontrado:</p>
        <pre><?= e(($seleccion['legacy_code'] ?? '') . ' - ' . ($seleccion['name'] ?? '')) ?></pre>
        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Nivel</th>
                        <th>Prefijo</th>
                        <th>Código representante</th>
                        <th>Nombre detectado</th>
                        <th>Personal dentro</th>
                        <th>Acciones dentro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($niveles as $nivel): ?>
                        <tr>
                            <td><?= e($nivel['nivel']) ?></td>
                            <td><?= e($nivel['prefijo']) ?></td>
                            <td><?= e($nivel['codigo_real']) ?></td>
                            <td><?= e($nivel['nombre']) ?></td>
                            <td><?= e(number_format((int) $nivel['personal'])) ?></td>
                            <td><?= e(number_format((int) $nivel['acciones'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card muted">
    <h3>Lectura</h3>
    <p>Esta pantalla muestra cómo se puede bajar por niveles usando código + nombre. Si la cadena no corresponde a la jerarquía real esperada, entonces hay que identificar una columna padre/hijo real en la tabla <strong>units</strong> o en otra tabla de organización.</p>
</section>
