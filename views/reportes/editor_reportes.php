<?php
/** @var array $filtros */
/** @var array $fuentes */
/** @var string $fuenteActualCodigo */
/** @var array $fuenteActual */
/** @var array $columnasSeleccionadas */
/** @var array $catalogos */
/** @var ?array $resultado */
/** @var ?string $error */
$rangos = $catalogos['rangos'] ?? [];
$unidades = $catalogos['unidades'] ?? [];
$estados = $catalogos['estados'] ?? [];
$tiposAccion = $catalogos['tiposAccion'] ?? [];
$queryPlantilla = http_build_query(array_merge($filtros, ['generar' => '1']));
$queryExportar = http_build_query(array_merge($filtros, ['generar' => '1']));
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>EDITOR DE REP avanzado</h2>
            <p>Constructor seguro de reportes: elige origen, columnas, filtros, vista previa y exportación.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Diseñar reporte</h2>
            <p>La URL generada funciona como plantilla reutilizable del reporte.</p>
        </div>
        <?php if ($resultado !== null): ?>
            <div class="toolbar no-print">
                <button onclick="window.print()">Imprimir</button>
                <a class="button-secondary" href="<?= e(url('/reportes/editor/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/editor')) ?>" class="filters no-print">
        <input type="hidden" name="generar" value="1">

        <div class="field field-wide">
            <label for="fuente">Origen de datos</label>
            <select name="fuente" id="fuente">
                <?php foreach ($fuentes as $codigo => $fuente): ?>
                    <option value="<?= e($codigo) ?>" <?= $fuenteActualCodigo === $codigo ? 'selected' : '' ?>>
                        <?= e($fuente['titulo'] ?? $codigo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small><?= e($fuenteActual['descripcion'] ?? '') ?></small>
        </div>

        <div class="field field-wide">
            <label>Columnas del reporte</label>
            <div class="checkbox-grid">
                <?php foreach (($fuenteActual['columnas'] ?? []) as $codigo => $columna): ?>
                    <label>
                        <input type="checkbox" name="columnas[]" value="<?= e($codigo) ?>" <?= in_array($codigo, $columnasSeleccionadas, true) ? 'checked' : '' ?>>
                        <?= e($columna['titulo'] ?? $codigo) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label for="rango_desde">Rango desde</label>
            <select name="rango_desde" id="rango_desde">
                <option value="">Todos</option>
                <?php foreach ($rangos as $rango): ?>
                    <option value="<?= e($rango['codigo'] ?? '') ?>" <?= ($filtros['rango_desde'] ?? '') === (string) ($rango['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($rango['codigo'] ?? '') . ' - ' . ($rango['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="rango_hasta">Rango hasta</label>
            <select name="rango_hasta" id="rango_hasta">
                <option value="">Todos</option>
                <?php foreach ($rangos as $rango): ?>
                    <option value="<?= e($rango['codigo'] ?? '') ?>" <?= ($filtros['rango_hasta'] ?? '') === (string) ($rango['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($rango['codigo'] ?? '') . ' - ' . ($rango['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field field-wide">
            <label for="unidad">Dependencia</label>
            <select name="unidad" id="unidad">
                <option value="">Todas</option>
                <?php foreach ($unidades as $unidad): ?>
                    <option value="<?= e($unidad['codigo'] ?? '') ?>" <?= ($filtros['unidad'] ?? '') === (string) ($unidad['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($unidad['codigo'] ?? '') . ' - ' . ($unidad['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="sexo">Sexo</label>
            <select name="sexo" id="sexo">
                <option value="A" <?= ($filtros['sexo'] ?? 'A') === 'A' ? 'selected' : '' ?>>Ambos</option>
                <option value="M" <?= ($filtros['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= ($filtros['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
            </select>
        </div>

        <div class="field">
            <label for="estado_modo">Estatus</label>
            <select name="estado_modo" id="estado_modo">
                <option value="activo" <?= ($filtros['estado_modo'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Solo activo</option>
                <option value="todos" <?= ($filtros['estado_modo'] ?? '') === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="especifico" <?= ($filtros['estado_modo'] ?? '') === 'especifico' ? 'selected' : '' ?>>Estado específico</option>
            </select>
        </div>

        <div class="field">
            <label for="estado">Estado específico</label>
            <select name="estado" id="estado">
                <option value="">Seleccione</option>
                <?php foreach ($estados as $estado): ?>
                    <option value="<?= e($estado['codigo'] ?? '') ?>" <?= ($filtros['estado'] ?? '') === (string) ($estado['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($estado['codigo'] ?? '') . ' - ' . ($estado['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="tipo_accion">Tipo de acción</label>
            <select name="tipo_accion" id="tipo_accion">
                <option value="">Todas</option>
                <?php foreach ($tiposAccion as $tipo): ?>
                    <option value="<?= e($tipo['codigo'] ?? '') ?>" <?= ($filtros['tipo_accion'] ?? '') === (string) ($tipo['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e(($tipo['codigo'] ?? '') . ' - ' . ($tipo['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="fecha_desde">Fecha acción desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($filtros['fecha_desde'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="fecha_hasta">Fecha acción hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
        </div>

        <div class="field field-wide">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre, apellido o número de empleado">
        </div>

        <div class="actions">
            <button type="submit">Generar vista previa</button>
            <a class="button-secondary" href="<?= e(url('/reportes/editor')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<?php if ($resultado !== null): ?>
    <section class="card no-print">
        <h3>Plantilla reutilizable</h3>
        <p>Copia esta ruta para abrir el mismo reporte con los mismos filtros y columnas.</p>
        <pre><?= e(url('/reportes/editor?' . $queryPlantilla)) ?></pre>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2>Vista previa del reporte</h2>
                <p>Mostrando hasta 300 registros. El CSV exporta hasta 1000 registros.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <?php foreach (($resultado['headers'] ?? []) as $header): ?>
                            <th><?= e($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultado['rows'] ?? [])): ?>
                        <tr><td colspan="<?= e(max(1, count($resultado['headers'] ?? []))) ?>" class="empty">Sin datos con los filtros indicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($resultado['rows'] ?? []) as $row): ?>
                        <tr>
                            <?php foreach (($resultado['columnas'] ?? []) as $columna): ?>
                                <td><?= e($row[$columna] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card muted no-print">
    <h3>Equivalencia con el DLL</h3>
    <p>Este módulo cubre el EDITOR DE REP en versión avanzada: permite diseñar reportes personalizados sin escribir SQL.</p>
</section>
