<?php
/** @var array $catalogos */
/** @var array $filtros */
/** @var array $rows */
/** @var ?int $total */
/** @var array $resumen */
/** @var array $columnas */
/** @var ?string $error */
$campos = $filtros['campos'] ?? [];
if (!is_array($campos)) {
    $campos = [];
}

$totalesEstado = [];
foreach ($rows as $row) {
    $codigoEstado = (string) ($row['estado_codigo'] ?? '');
    $nombreEstado = (string) ($row['estado_nombre'] ?? 'Sin estado');
    $claveEstado = $codigoEstado . '|' . $nombreEstado;

    if (!isset($totalesEstado[$claveEstado])) {
        $totalesEstado[$claveEstado] = [
            'codigo' => $codigoEstado,
            'nombre' => $nombreEstado,
            'total' => 0,
        ];
    }

    $totalesEstado[$claveEstado]['total']++;
}

$queryExportar = http_build_query(array_merge($filtros, ['generar' => '1']));
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

        <div class="field">
            <label for="estado_modo">Estatus</label>
            <select name="estado_modo" id="estado_modo">
                <option value="todos" <?= ($filtros['estado_modo'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todas</option>
                <option value="activo" <?= ($filtros['estado_modo'] ?? '') === 'activo' ? 'selected' : '' ?>>Solo activo</option>
                <option value="especifico" <?= ($filtros['estado_modo'] ?? '') === 'especifico' ? 'selected' : '' ?>>Mostrar específico</option>
            </select>
            <small>Todas incluye activos y demás estatus administrativos.</small>
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

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total consultado</h3>
            <p><strong><?= e($total ?? $resumen['total'] ?? 0) ?></strong></p>
            <small>La tabla muestra hasta 500 registros.</small>
        </div>
        <div class="card muted">
            <h3>Resumen visible</h3>
            <p>Masculino: <strong><?= e($resumen['masculino'] ?? 0) ?></strong></p>
            <p>Femenino: <strong><?= e($resumen['femenino'] ?? 0) ?></strong></p>
            <p>Activos: <strong><?= e($resumen['activos'] ?? 0) ?></strong></p>
            <p>Otros estados: <strong><?= e($resumen['otros_estados'] ?? 0) ?></strong></p>
        </div>
    </div>

    <?php if (!empty($totalesEstado)): ?>
        <div class="table-wrapper">
            <h3>Resumen por estatus visible</h3>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estatus</th>
                        <th>Total visible</th>
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
