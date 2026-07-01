<?php
/** @var array $filtros */
/** @var ?array $rows */
/** @var array $metadata */
/** @var ?string $error */
/** @var string $modulo */
/** @var array $modulos */
/** @var array $moduloActual */
$tabla = $metadata['tabla'] ?? null;
$columnas = $metadata['columnas'] ?? [];
$tipos = $metadata['tipos'] ?? [];
$buscar = (string) ($filtros['buscar'] ?? '');
$tipo = (string) ($filtros['tipo'] ?? '');
$fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
$fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Reportes disponibles</h2>
            <p>Reconstrucción progresiva de los reportes identificados en el módulo DLL/legado.</p>
        </div>
    </div>

    <div class="report-menu">
        <?php foreach ($modulos as $clave => $item): ?>
            <a class="report-card <?= $modulo === $clave ? 'active' : '' ?>" href="<?= e(url($item['ruta'])) ?>">
                <span class="module-status"><?= e($item['estado']) ?></span>
                <strong><?= e($item['titulo']) ?></strong>
                <small><?= e($item['descripcion']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2><?= e($moduloActual['titulo']) ?></h2>
            <p><?= e($moduloActual['descripcion']) ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($tabla === null): ?>
        <div class="alert alert-info">
            No se detectó todavía una tabla de acciones en esta base de datos. El módulo queda preparado para cuando exista una tabla como <code>acciones</code>, <code>actions</code>, <code>personal_actions</code> o equivalente.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Tabla detectada: <strong><?= e($tabla) ?></strong>. Columnas encontradas: <?= e(implode(', ', $columnas)) ?>.
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/acciones/resultado')) ?>" class="filters">
        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($buscar) ?>" placeholder="Cédula, posición, nombre, número o texto de la acción">
        </div>

        <div class="field">
            <label for="tipo">Tipo de acción</label>
            <?php if ($tipos !== []): ?>
                <select name="tipo" id="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $item): ?>
                        <option value="<?= e($item) ?>" <?= $tipo === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" name="tipo" id="tipo" value="<?= e($tipo) ?>" placeholder="Código o tipo">
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($fechaDesde) ?>">
        </div>

        <div class="field">
            <label for="fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($fechaHasta) ?>">
        </div>

        <div class="actions">
            <button type="submit">Buscar acciones</button>
            <a href="<?= e(url('/reportes/acciones')) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<?php if ($rows !== null): ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3>Resultado de acciones</h3>
                <p>Mostrando hasta 100 registros según los filtros aplicados.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <?php foreach (array_slice($columnas, 0, 12) as $columna): ?>
                            <th><?= e($columna) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?= e(max(1, min(12, count($columnas)))) ?>" class="empty">No se encontraron acciones con los filtros indicados.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_slice($columnas, 0, 12) as $columna): ?>
                                <td><?= e($row[$columna] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card muted">
    <h3>Equivalencia con el DLL</h3>
    <p>
        Este bloque cubre la familia de listados de acciones del sistema legado: ascensos, traslados, vacaciones, licencias, sanciones, incapacidades y novedades. Cuando confirmemos los nombres exactos de tablas y columnas del DLL, este módulo se ajusta de genérico a reporte especializado.
    </p>
</section>
