<?php
/** @var array $filtros */
/** @var array $estadisticas */
/** @var ?string $error */
$estadisticas = is_array($estadisticas ?? null) ? $estadisticas : [];
$porTipo = is_array($estadisticas['porTipo'] ?? null) ? $estadisticas['porTipo'] : [];
$porMes = is_array($estadisticas['porMes'] ?? null) ? $estadisticas['porMes'] : [];
$porAnio = is_array($estadisticas['porAnio'] ?? null) ? $estadisticas['porAnio'] : [];
$queryExportar = http_build_query(array_merge($filtros, ['vista' => 'sanciones', 'tipo' => '19']));
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Estadísticas / Sanciones</h2>
            <p>Equivalente base a EST. DE SANCIONES del DLL.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Estadísticas de sanciones</h2>
            <p>Resume sanciones por año, mes y total general.</p>
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
        <input type="hidden" name="vista" value="sanciones">
        <input type="hidden" name="tipo" value="19">

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
            <a class="button-secondary" href="<?= e(url('/reportes/estadisticas-acciones?vista=sanciones')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total de sanciones</h3>
            <p><strong><?= e($estadisticas['total'] ?? 0) ?></strong></p>
            <small>Total según filtros aplicados.</small>
        </div>
        <div class="card muted">
            <h3>Reporte DLL</h3>
            <p>EST. DE SANCIONES / action_type_id 19</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <div class="table-wrapper">
            <h3>Resumen por año</h3>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>Tipo</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($porAnio)): ?>
                        <tr><td colspan="3" class="empty">Sin datos</td></tr>
                    <?php endif; ?>
                    <?php foreach ($porAnio as $row): ?>
                        <tr>
                            <td><?= e($row['anio'] ?? '') ?></td>
                            <td><?= e($row['tipo_accion'] ?? '') ?></td>
                            <td><?= e($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-wrapper">
            <h3>Resumen por tipo</h3>
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
    </div>
</section>

<section class="card">
    <div class="table-wrapper">
        <h3>Desglose mensual de sanciones</h3>
        <table class="mini-table">
            <thead>
                <tr>
                    <th>Año</th>
                    <th>Mes</th>
                    <th>Tipo</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($porMes)): ?>
                    <tr><td colspan="4" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach ($porMes as $row): ?>
                    <tr>
                        <td><?= e($row['anio'] ?? '') ?></td>
                        <td><?= e($row['mes_numero'] ?? '') ?></td>
                        <td><?= e($row['tipo_accion'] ?? '') ?></td>
                        <td><?= e($row['total'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
