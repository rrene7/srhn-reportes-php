<?php
/** @var array $filtros */
/** @var array $tiposAccion */
/** @var array $estadisticas */
/** @var ?string $error */
$estadisticas = is_array($estadisticas ?? null) ? $estadisticas : [];
$porTipo = is_array($estadisticas['porTipo'] ?? null) ? $estadisticas['porTipo'] : [];
$porRangoMes = is_array($estadisticas['porRangoMes'] ?? null) ? $estadisticas['porRangoMes'] : [];
$queryExportar = http_build_query(array_merge($filtros, ['vista' => 'rango-mes']));
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Estadísticas / Acciones por rango y mes</h2>
            <p>Equivalente base a EST.ACC. del DLL: rango - desglose en mes.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>EST.ACC. / Rango - desglose en mes</h2>
            <p>Agrupa acciones de personal por año, mes, rango y tipo de acción.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estadisticas-acciones/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/estadisticas-acciones')) ?>" class="filters no-print">
        <input type="hidden" name="vista" value="rango-mes">

        <div class="field field-wide">
            <label for="tipo">Tipo de acción</label>
            <select name="tipo" id="tipo">
                <option value="">Todas</option>
                <?php foreach ($tiposAccion as $tipo): ?>
                    <option value="<?= e($tipo['id'] ?? '') ?>" <?= ($filtros['tipo'] ?? '') === (string) ($tipo['id'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($tipo['id'] ?? '') . ' - ' . ($tipo['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($filtros['fecha_desde'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
        </div>

        <div class="actions">
            <button type="submit">Generar estadística</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estadisticas-acciones?vista=rango-mes')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total de acciones</h3>
            <p><strong><?= e($estadisticas['total'] ?? 0) ?></strong></p>
            <small>Total según filtros aplicados.</small>
        </div>
        <div class="card muted">
            <h3>Reporte DLL</h3>
            <p>EST.ACC. / Rango - desglose en mes</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <div class="table-wrapper">
            <h3>Resumen por tipo de acción</h3>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($porTipo)): ?>
                        <tr><td colspan="3" class="empty">Sin datos</td></tr>
                    <?php endif; ?>
                    <?php foreach ($porTipo as $row): ?>
                        <tr>
                            <td><?= e($row['codigo'] ?? '') ?></td>
                            <td><?= e($row['tipo_accion'] ?? '') ?></td>
                            <td><?= e($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card muted">
            <h3>Base de agrupación</h3>
            <p>Usa rango destino de la acción cuando existe; si no existe, usa el código legado de rango o cargo.</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="table-wrapper">
        <h3>Desglose mensual por rango</h3>
        <table class="mini-table">
            <thead>
                <tr>
                    <th>Año</th>
                    <th>Mes</th>
                    <th>Código rango</th>
                    <th>Rango</th>
                    <th>Tipo</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($porRangoMes)): ?>
                    <tr><td colspan="6" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach ($porRangoMes as $row): ?>
                    <tr>
                        <td><?= e($row['anio'] ?? '') ?></td>
                        <td><?= e($row['mes_numero'] ?? '') ?></td>
                        <td><?= e($row['codigo_rango'] ?? '') ?></td>
                        <td><?= e($row['rango'] ?? '') ?></td>
                        <td><?= e($row['tipo_accion'] ?? '') ?></td>
                        <td><?= e($row['total'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
