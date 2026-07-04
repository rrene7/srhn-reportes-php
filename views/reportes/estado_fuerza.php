<?php
/** @var array $filtros */
/** @var array $catalogos */
/** @var array $data */
/** @var ?string $error */
$queryExportar = http_build_query($filtros);
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Estadísticas / Estado de Fuerza</h2>
            <p>Resumen estadístico del personal por rango, dependencia, sexo y estatus.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Estado de Fuerza</h2>
            <p>Filtro predeterminado: solo personal activo.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estado-fuerza/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/estado-fuerza')) ?>" class="filters no-print">
        <div class="field">
            <label for="rango_desde">Rango desde</label>
            <select name="rango_desde" id="rango_desde">
                <option value="">Todos</option>
                <?php foreach ($catalogos['rangos'] ?? [] as $rango): ?>
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
                <?php foreach ($catalogos['rangos'] ?? [] as $rango): ?>
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
                <option value="especifico" <?= ($filtros['estado_modo'] ?? '') === 'especifico' ? 'selected' : '' ?>>Mostrar específico</option>
            </select>
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

        <div class="actions">
            <button type="submit">Generar estadística</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estado-fuerza')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total general</h3>
            <p><strong><?= e($data['total'] ?? 0) ?></strong></p>
            <small>Total según filtros aplicados.</small>
        </div>
        <div class="card muted">
            <h3>Filtro actual</h3>
            <p>Estatus: <strong><?= e($filtros['estado_modo'] ?? 'activo') ?></strong></p>
            <p>Sexo: <strong><?= e($filtros['sexo'] ?? 'A') ?></strong></p>
        </div>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <?= renderTablaResumen('Resumen por rango', $data['porRango'] ?? []) ?>
        <?= renderTablaResumen('Resumen por sexo', $data['porSexo'] ?? []) ?>
    </div>

    <div class="grid-2">
        <?= renderTablaResumen('Resumen por estatus', $data['porEstatus'] ?? []) ?>
        <?= renderTablaResumen('Resumen por dependencia', $data['porDependencia'] ?? []) ?>
    </div>
</section>

<?php
function renderTablaResumen(string $titulo, array $rows): string
{
    ob_start();
    ?>
    <div class="table-wrapper">
        <h3><?= e($titulo) ?></h3>
        <table class="mini-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="3" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['codigo'] ?? '') ?></td>
                        <td><?= e($row['nombre'] ?? '') ?></td>
                        <td><?= e($row['total'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}
?>
