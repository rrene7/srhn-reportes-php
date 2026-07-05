<?php
/** @var array $catalogos */
/** @var array $filtros */
/** @var array $rows */
/** @var ?int $total */
/** @var array $resumen */
/** @var array $resumenEstados */
/** @var array $columnas */
/** @var ?string $error */
$campos = $filtros['campos'] ?? [];
if (!is_array($campos)) {
    $campos = [];
}

$totalesEstado = $resumenEstados ?? [];
$filtrosTodos = array_merge($filtros, ['generar' => '1', 'estado_modo' => 'todos', 'estado' => '']);
$filtrosActivos = array_merge($filtros, ['generar' => '1', 'estado_modo' => 'activo', 'estado' => '']);
$queryTodosEstatus = http_build_query($filtrosTodos);
$querySoloActivos = http_build_query($filtrosActivos);
$queryExportar = http_build_query(array_merge($filtros, ['generar' => '1']));

if (!function_exists('omGroupCounts')) {
    function omGroupCounts(array $rows, string $field, string $emptyLabel = 'Sin dato', int $limit = 10): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row[$field] ?? ''));
            $label = $label !== '' ? $label : $emptyLabel;
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $label => $value) {
            $out[] = ['label' => $label, 'value' => (int) $value];
        }
        return $out;
    }
}

if (!function_exists('omPercent')) {
    function omPercent(int|float $value, int|float $total): int
    {
        if ($total <= 0) {
            return 0;
        }
        return max(0, min(100, (int) round(($value / $total) * 100)));
    }
}

if (!function_exists('omDashboardCard')) {
    function omDashboardCard(string $label, int|float $value, string $hint, int $percent): void
    {
        ?>
        <div class="om-kpi-card">
            <div class="om-kpi-top">
                <div>
                    <span><?= e($label) ?></span>
                    <strong><?= e(number_format((float) $value)) ?></strong>
                </div>
                <div class="om-circle" style="--p:<?= e(max(4, $percent)) ?>"><em><?= e($percent) ?>%</em></div>
            </div>
            <small><?= e($hint) ?></small>
            <div class="om-progress"><i style="width: <?= e(max(4, $percent)) ?>%"></i></div>
        </div>
        <?php
    }
}

if (!function_exists('omBarList')) {
    function omBarList(string $title, array $items, string $empty = 'Sin datos'): void
    {
        $max = 1;
        foreach ($items as $item) {
            $max = max($max, (int) ($item['value'] ?? 0));
        }
        ?>
        <div class="om-viz-card">
            <h3><?= e($title) ?></h3>
            <?php if (empty($items)): ?>
                <p class="empty"><?= e($empty) ?></p>
            <?php else: ?>
                <div class="om-bar-list">
                    <?php foreach ($items as $item): ?>
                        <?php $pct = omPercent((int) ($item['value'] ?? 0), $max); ?>
                        <div class="om-bar-row">
                            <span title="<?= e($item['label'] ?? '') ?>"><?= e($item['label'] ?? '') ?></span>
                            <strong><?= e(number_format((int) ($item['value'] ?? 0))) ?></strong>
                            <div><i style="width: <?= e(max(3, $pct)) ?>%"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

$visibleTotal = count($rows);
$totalReal = (int) ($total ?? $visibleTotal);
$masculino = (int) ($resumen['masculino'] ?? 0);
$femenino = (int) ($resumen['femenino'] ?? 0);
$activos = (int) ($resumen['activos'] ?? 0);
$otrosEstados = (int) ($resumen['otros_estados'] ?? 0);
$porRango = omGroupCounts($rows, 'rango_nombre', 'Sin rango', 10);
$porUnidad = omGroupCounts($rows, 'unidad_nombre', 'Sin ubicación', 10);
$porTipoPolicia = omGroupCounts($rows, 'tipo_policia', 'Sin tipo', 8);
$porSexo = omGroupCounts($rows, 'sexo', 'Sin sexo', 4);
$porEstadoVisible = omGroupCounts($rows, 'estado_nombre', 'Sin estado', 10);
$porEstadoReal = [];
foreach ($totalesEstado as $estado) {
    $porEstadoReal[] = [
        'label' => trim((string) (($estado['codigo'] ?? '') . ' - ' . ($estado['nombre'] ?? ''))),
        'value' => (int) ($estado['total'] ?? 0),
    ];
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Reportes varios / Reporte Opciones Múltiples</h2>
            <p>Pantalla base reconstruida según el formulario del DLL legado.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Reporte Opciones Múltiples</h2>
            <p>Combine rango, ubicación, sexo, tipo de policía, estatus, fechas, campos opcionales y ordenamiento.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/opciones-multiples/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/opciones-multiples')) ?>" class="filters no-print">
        <input type="hidden" name="generar" value="1">

        <fieldset class="filter-group">
            <legend>Reporte por</legend>
            <label><input type="radio" name="reporte_por" value="rango" <?= ($filtros['reporte_por'] ?? '') === 'rango' ? 'checked' : '' ?>> Rango</label>
            <label><input type="radio" name="reporte_por" value="ubicacion" <?= ($filtros['reporte_por'] ?? '') === 'ubicacion' ? 'checked' : '' ?>> Ubicación</label>
            <label><input type="radio" name="reporte_por" value="ambos" <?= ($filtros['reporte_por'] ?? 'ambos') === 'ambos' ? 'checked' : '' ?>> Ambos</label>
        </fieldset>

        <div class="field">
            <label for="rango_inicial">Rango inicial</label>
            <select name="rango_inicial" id="rango_inicial">
                <option value="">Todos</option>
                <?php foreach ($catalogos['rangos'] ?? [] as $rango): ?>
                    <option value="<?= e($rango['codigo'] ?? '') ?>" <?= ($filtros['rango_inicial'] ?? '') === (string) ($rango['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($rango['codigo'] ?? '') . ' - ' . ($rango['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="rango_final">Rango final</label>
            <select name="rango_final" id="rango_final">
                <option value="">Todos</option>
                <?php foreach ($catalogos['rangos'] ?? [] as $rango): ?>
                    <option value="<?= e($rango['codigo'] ?? '') ?>" <?= ($filtros['rango_final'] ?? '') === (string) ($rango['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($rango['codigo'] ?? '') . ' - ' . ($rango['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field field-wide">
            <label for="unidad">Dependencia / ubicación</label>
            <select name="unidad" id="unidad">
                <option value="">Todas</option>
                <?php foreach ($catalogos['unidades'] ?? [] as $unidad): ?>
                    <option value="<?= e($unidad['codigo'] ?? '') ?>" <?= ($filtros['unidad'] ?? '') === (string) ($unidad['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($unidad['codigo'] ?? '') . ' - ' . ($unidad['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="sexo">Sexo</label>
            <select name="sexo" id="sexo">
                <option value="A" <?= ($filtros['sexo'] ?? 'A') === 'A' ? 'selected' : '' ?>>A - Ambos</option>
                <option value="M" <?= ($filtros['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>M - Masculino</option>
                <option value="F" <?= ($filtros['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>F - Femenino</option>
            </select>
        </div>

        <div class="field">
            <label for="tipo_policia">Tipo de policía</label>
            <select name="tipo_policia" id="tipo_policia">
                <option value="todos" <?= ($filtros['tipo_policia'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="OO" <?= strtoupper((string) ($filtros['tipo_policia'] ?? '')) === 'OO' ? 'selected' : '' ?>>OO</option>
                <option value="NO" <?= strtoupper((string) ($filtros['tipo_policia'] ?? '')) === 'NO' ? 'selected' : '' ?>>NO</option>
                <option value="OA" <?= strtoupper((string) ($filtros['tipo_policia'] ?? '')) === 'OA' ? 'selected' : '' ?>>OA</option>
            </select>
        </div>

        <div class="field field-wide">
            <label for="estado_modo">Estatus</label>
            <select name="estado_modo" id="estado_modo">
                <option value="todos" <?= ($filtros['estado_modo'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todas</option>
                <option value="activo" <?= ($filtros['estado_modo'] ?? '') === 'activo' ? 'selected' : '' ?>>Solo activo</option>
                <option value="especifico" <?= ($filtros['estado_modo'] ?? '') === 'especifico' ? 'selected' : '' ?>>Mostrar específico</option>
            </select>
            <small>Todas incluye activos y demás estatus administrativos.</small>
            <div class="toolbar quick-actions">
                <a class="button-secondary" href="<?= e(url('/reportes/opciones-multiples?' . $queryTodosEstatus)) ?>">Ver todos los estatus</a>
                <a class="button-secondary" href="<?= e(url('/reportes/opciones-multiples?' . $querySoloActivos)) ?>">Ver solo activos</a>
            </div>
        </div>

        <div class="field">
            <label for="estado">Estado específico</label>
            <select name="estado" id="estado">
                <option value="">Seleccione</option>
                <?php foreach ($catalogos['estados'] ?? [] as $estado): ?>
                    <option value="<?= e($estado['codigo'] ?? '') ?>" <?= ($filtros['estado'] ?? '') === (string) ($estado['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($estado['codigo'] ?? '') . ' - ' . ($estado['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="fecha_modo">Opciones de fecha</label>
            <select name="fecha_modo" id="fecha_modo">
                <option value="actual" <?= ($filtros['fecha_modo'] ?? 'actual') === 'actual' ? 'selected' : '' ?>>Fecha actual</option>
                <option value="especificar" <?= ($filtros['fecha_modo'] ?? '') === 'especificar' ? 'selected' : '' ?>>Especificar fecha</option>
            </select>
        </div>

        <div class="field">
            <label for="fecha_corte">Fecha específica</label>
            <input type="date" name="fecha_corte" id="fecha_corte" value="<?= e($filtros['fecha_corte'] ?? '') ?>">
        </div>

        <fieldset class="filter-group">
            <legend>Filtrar por tiempo</legend>
            <div class="inline-fields">
                <label>T. Servicio mín. <input type="number" name="ts_min" min="0" value="<?= e($filtros['ts_min'] ?? '') ?>"></label>
                <label>T. Servicio máx. <input type="number" name="ts_max" min="0" value="<?= e($filtros['ts_max'] ?? '') ?>"></label>
                <label>T. Rango mín. <input type="number" name="tr_min" min="0" value="<?= e($filtros['tr_min'] ?? '') ?>"></label>
                <label>T. Rango máx. <input type="number" name="tr_max" min="0" value="<?= e($filtros['tr_max'] ?? '') ?>"></label>
            </div>
        </fieldset>

        <fieldset class="filter-group field-wide">
            <legend>Campos opcionales, máximo 4</legend>
            <?php foreach ($catalogos['camposOpcionales'] ?? [] as $codigo => $nombre): ?>
                <label><input type="checkbox" name="campos[]" value="<?= e($codigo) ?>" <?= in_array((string) $codigo, $campos, true) ? 'checked' : '' ?>> <?= e($nombre) ?></label>
            <?php endforeach; ?>
        </fieldset>

        <div class="field">
            <label for="ordenar_por">Ordenamiento / agrupación</label>
            <select name="ordenar_por" id="ordenar_por">
                <option value="rango" <?= ($filtros['ordenar_por'] ?? 'rango') === 'rango' ? 'selected' : '' ?>>Agrupar por rango</option>
                <option value="ubicacion" <?= ($filtros['ordenar_por'] ?? '') === 'ubicacion' ? 'selected' : '' ?>>Agrupar por ubicación</option>
                <option value="posicion" <?= ($filtros['ordenar_por'] ?? '') === 'posicion' ? 'selected' : '' ?>>Agrupar por posición</option>
                <option value="tiempo_servicio" <?= ($filtros['ordenar_por'] ?? '') === 'tiempo_servicio' ? 'selected' : '' ?>>Ordenar por TS</option>
                <option value="tiempo_rango" <?= ($filtros['ordenar_por'] ?? '') === 'tiempo_rango' ? 'selected' : '' ?>>Ordenar por TR</option>
                <option value="apellido" <?= ($filtros['ordenar_por'] ?? '') === 'apellido' ? 'selected' : '' ?>>Apellido / nombre</option>
            </select>
        </div>

        <div class="field">
            <label for="tipo_papel">Tipo de papel</label>
            <select name="tipo_papel" id="tipo_papel">
                <option value="carta" <?= ($filtros['tipo_papel'] ?? 'carta') === 'carta' ? 'selected' : '' ?>>8.5 x 11</option>
                <option value="continuo" <?= ($filtros['tipo_papel'] ?? '') === 'continuo' ? 'selected' : '' ?>>Continuo</option>
            </select>
        </div>

        <div class="field">
            <label for="clasificacion">Clasificación</label>
            <select name="clasificacion" id="clasificacion">
                <option value="ambos" <?= ($filtros['clasificacion'] ?? 'ambos') === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                <option value="operativas" <?= ($filtros['clasificacion'] ?? '') === 'operativas' ? 'selected' : '' ?>>Operativas</option>
                <option value="administrativas" <?= ($filtros['clasificacion'] ?? '') === 'administrativas' ? 'selected' : '' ?>>Administrativas</option>
            </select>
        </div>

        <div class="field">
            <label for="sectorizacion">Sectorización</label>
            <select name="sectorizacion" id="sectorizacion">
                <option value="" <?= ($filtros['sectorizacion'] ?? '') === '' ? 'selected' : '' ?>>Todas</option>
                <option value="capital" <?= ($filtros['sectorizacion'] ?? '') === 'capital' ? 'selected' : '' ?>>Área Capital / Metropolitana</option>
                <option value="penitenciaria" <?= ($filtros['sectorizacion'] ?? '') === 'penitenciaria' ? 'selected' : '' ?>>Penitenciaria</option>
                <option value="interior" <?= ($filtros['sectorizacion'] ?? '') === 'interior' ? 'selected' : '' ?>>Área del Interior</option>
                <option value="exterior" <?= ($filtros['sectorizacion'] ?? '') === 'exterior' ? 'selected' : '' ?>>Exterior</option>
            </select>
        </div>

        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="actions">
            <button type="submit">Generar reporte</button>
            <a class="button-secondary" href="<?= e(url('/reportes/opciones-multiples')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="om-dashboard">
    <div class="om-dashboard-head">
        <div>
            <span>Dashboard del reporte</span>
            <h2>Resumen visual de Opciones Múltiples</h2>
            <p>Los indicadores se calculan con el resultado consultado. La tabla muestra hasta 500 registros visibles.</p>
        </div>
        <div class="om-filter-pill">
            Sexo: <?= e($filtros['sexo'] ?: 'A') ?> · Rango <?= e(($filtros['rango_inicial'] ?: 'Todos') . ' - ' . ($filtros['rango_final'] ?: 'Todos')) ?>
        </div>
    </div>

    <div class="om-kpi-grid">
        <?php omDashboardCard('Total real', $totalReal, 'Total encontrado por la consulta', 100); ?>
        <?php omDashboardCard('Visible', $visibleTotal, 'Registros cargados en pantalla', omPercent($visibleTotal, max(1, $totalReal))); ?>
        <?php omDashboardCard('Femenino', $femenino, 'Dentro del resultado visible', omPercent($femenino, max(1, $visibleTotal))); ?>
        <?php omDashboardCard('Masculino', $masculino, 'Dentro del resultado visible', omPercent($masculino, max(1, $visibleTotal))); ?>
        <?php omDashboardCard('Activos', $activos, 'Activos visibles', omPercent($activos, max(1, $visibleTotal))); ?>
        <?php omDashboardCard('Otros estados', $otrosEstados, 'No activos visibles', omPercent($otrosEstados, max(1, $visibleTotal))); ?>
    </div>

    <div class="om-viz-grid">
        <?php omBarList('Distribución por rango', $porRango); ?>
        <?php omBarList('Distribución por estatus', $porEstadoReal ?: $porEstadoVisible); ?>
        <?php omBarList('Top ubicaciones visibles', $porUnidad); ?>
        <?php omBarList('Tipo de policía', $porTipoPolicia); ?>
    </div>

    <div class="om-mini-grid">
        <?php omBarList('Sexo', $porSexo); ?>
        <div class="om-viz-card om-note-card">
            <h3>Lectura rápida</h3>
            <p><strong>Consulta:</strong> <?= e($totalReal) ?> registros encontrados.</p>
            <p><strong>Vista:</strong> <?= e($visibleTotal) ?> registros cargados para análisis rápido.</p>
            <p><strong>Filtro principal:</strong> <?= e($filtros['sexo'] === 'F' ? 'Femenino' : ($filtros['sexo'] === 'M' ? 'Masculino' : 'Ambos sexos')) ?>.</p>
            <p><strong>Orden:</strong> <?= e($filtros['ordenar_por'] ?? 'rango') ?>.</p>
        </div>
    </div>
</section>

<section class="card">
    <?php if (!empty($totalesEstado)): ?>
        <div class="table-wrapper">
            <h3>Resumen total por estatus</h3>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estatus</th>
                        <th>Total real</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($totalesEstado as $estado): ?>
                        <tr>
                            <td><?= e($estado['codigo']) ?></td>
                            <td><?= e($estado['nombre']) ?></td>
                            <td><?= e($estado['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Resultado</h3>
            <p>Reporte generado con los filtros seleccionados.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="report-table">
            <thead>
                <tr>
                    <?php foreach ($columnas as $titulo): ?>
                        <th><?= e($titulo) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= e(max(1, count($columnas))) ?>" class="empty">Use los filtros y presione Generar reporte.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (array_keys($columnas) as $campo): ?>
                            <td><?= e($row[$campo] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
    .om-dashboard {
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        border: 1px solid #d9e2ef;
        border-radius: 24px;
        padding: 1.2rem;
        margin: 1rem 0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
    }

    .om-dashboard-head {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .om-dashboard-head span {
        color: #1d4f88;
        font-weight: 900;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .om-dashboard-head h2 {
        margin: .25rem 0;
        color: #0f172a;
    }

    .om-dashboard-head p {
        color: #64748b;
        margin: 0;
    }

    .om-filter-pill {
        background: #eaf2fb;
        color: #17375f;
        padding: .65rem .85rem;
        border-radius: 999px;
        font-size: .86rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .om-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .om-kpi-card {
        background: #fff;
        border: 1px solid #d9e2ef;
        border-radius: 20px;
        padding: 1rem;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }

    .om-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .85rem;
    }

    .om-kpi-card span {
        display: block;
        color: #64748b;
        font-size: .76rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .om-kpi-card strong {
        display: block;
        color: #0f172a;
        font-size: 1.85rem;
        font-weight: 950;
        line-height: 1.05;
        margin-top: .25rem;
    }

    .om-kpi-card small {
        display: block;
        color: #64748b;
        margin: .7rem 0 .55rem;
    }

    .om-circle {
        --size: 60px;
        width: var(--size);
        height: var(--size);
        border-radius: 50%;
        background: conic-gradient(#17375f calc(var(--p) * 1%), #e6eef8 0);
        position: relative;
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }

    .om-circle::before {
        content: '';
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        position: absolute;
        box-shadow: inset 0 0 0 1px #e8eef5;
    }

    .om-circle em {
        position: relative;
        z-index: 1;
        font-style: normal;
        color: #17375f;
        font-size: .7rem;
        font-weight: 950;
    }

    .om-progress {
        width: 100%;
        height: 8px;
        border-radius: 999px;
        background: #e8eef5;
        overflow: hidden;
    }

    .om-progress i {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #17375f, #3d7fd6);
    }

    .om-viz-grid,
    .om-mini-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .om-viz-card {
        background: #fff;
        border: 1px solid #d9e2ef;
        border-radius: 20px;
        padding: 1rem;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
        min-width: 0;
    }

    .om-viz-card h3 {
        margin: 0 0 1rem;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 900;
    }

    .om-bar-list {
        display: flex;
        flex-direction: column;
        gap: .78rem;
    }

    .om-bar-row {
        display: grid;
        grid-template-columns: minmax(130px, 1.3fr) 70px minmax(130px, 1fr);
        gap: .7rem;
        align-items: center;
    }

    .om-bar-row span {
        color: #0f172a;
        font-size: .88rem;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .om-bar-row strong {
        color: #334155;
        text-align: right;
        font-size: .85rem;
        font-weight: 900;
    }

    .om-bar-row div {
        height: 10px;
        background: #e8eef5;
        border-radius: 999px;
        overflow: hidden;
    }

    .om-bar-row i {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #17375f, #3d7fd6);
        border-radius: 999px;
    }

    .om-note-card p {
        color: #475569;
        margin: .55rem 0;
    }

    @media (max-width: 900px) {
        .om-dashboard-head,
        .om-viz-grid,
        .om-mini-grid {
            grid-template-columns: 1fr;
            flex-direction: column;
        }
        .om-filter-pill { white-space: normal; }
        .om-bar-row { grid-template-columns: 1fr; gap: .35rem; }
        .om-bar-row strong { text-align: left; }
    }
</style>
