<?php
/** @var array $filtros */
/** @var array $catalogos */
/** @var array $data */
/** @var ?string $error */
$data = is_array($data ?? null) ? $data : [];
$rangos = $catalogos['rangos'] ?? [];
$unidades = $catalogos['unidades'] ?? [];
$estados = $catalogos['estados'] ?? [];
$tiposPolicia = $catalogos['tiposPolicia'] ?? [];
$queryExportar = http_build_query($filtros);

function renderOperativosTablaResumen(string $titulo, array $rows): void
{
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
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>OPERATIVOS</h2>
            <p>Equivalente base al módulo OPERATIVOS del DLL: resumen operativo por rango, dependencia, sexo, estatus y tipo de policía.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Reporte de Operativos</h2>
            <p>Filtro predeterminado: personal activo. Puede ampliar a todos los estatus si se requiere auditoría completa.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/operativos/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/operativos')) ?>" class="filters no-print">
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

        <div class="field">
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
            <label for="tipo_policia">Tipo de policía</label>
            <select name="tipo_policia" id="tipo_policia">
                <option value="">Todos</option>
                <?php foreach ($tiposPolicia as $tipo): ?>
                    <option value="<?= e($tipo['codigo'] ?? '') ?>" <?= ($filtros['tipo_policia'] ?? '') === (string) ($tipo['codigo'] ?? '') ? 'selected' : '' ?>>
                        <?= e($tipo['nombre'] ?? $tipo['codigo'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="actions">
            <button type="submit">Generar operativo</button>
            <a class="button-secondary" href="<?= e(url('/reportes/operativos')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total operativo</h3>
            <p><strong><?= e($data['total'] ?? 0) ?></strong></p>
            <small>Total según filtros aplicados.</small>
        </div>
        <div class="card muted">
            <h3>Reporte DLL</h3>
            <p>OPERATIVOS / resumen por rango, dependencia, sexo, estatus y tipo de policía.</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <?php renderOperativosTablaResumen('Resumen por rango', $data['porRango'] ?? []); ?>
        <?php renderOperativosTablaResumen('Resumen por dependencia', $data['porDependencia'] ?? []); ?>
        <?php renderOperativosTablaResumen('Resumen por sexo', $data['porSexo'] ?? []); ?>
        <?php renderOperativosTablaResumen('Resumen por estatus', $data['porEstatus'] ?? []); ?>
        <?php renderOperativosTablaResumen('Resumen por tipo de policía', $data['porTipoPolicia'] ?? []); ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Listado operativo</h3>
            <p>Mostrando hasta 300 funcionarios según filtros aplicados.</p>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="report-table">
            <thead>
                <tr>
                    <th>N. Emp.</th>
                    <th>Cédula</th>
                    <th>Funcionario</th>
                    <th>Rango</th>
                    <th>Dependencia</th>
                    <th>Sexo</th>
                    <th>Estado</th>
                    <th>Tipo policía</th>
                    <th>Ingreso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['listado'] ?? [])): ?>
                    <tr><td colspan="9" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach (($data['listado'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td><?= e($row['funcionario'] ?? '') ?></td>
                        <td><?= e(trim((string) (($row['rango_codigo'] ?? '') . ' - ' . ($row['rango_nombre'] ?? '')))) ?></td>
                        <td><?= e(trim((string) (($row['unidad_codigo'] ?? '') . ' - ' . ($row['unidad_nombre'] ?? '')))) ?></td>
                        <td><?= e($row['sexo'] ?? '') ?></td>
                        <td><?= e(trim((string) (($row['estado_codigo'] ?? '') . ' - ' . ($row['estado_nombre'] ?? '')))) ?></td>
                        <td><?= e($row['tipo_policia'] ?? '') ?></td>
                        <td><?= e($row['fecha_ingreso'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
