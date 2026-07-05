<?php
/** @var array $filtros */
/** @var array $catalogos */
/** @var array $data */
/** @var ?string $error */
$rangos = $catalogos['rangos'] ?? [];
$unidades = $catalogos['unidades'] ?? [];
$estados = $catalogos['estados'] ?? [];
$resumen = $data['resumen'] ?? [];
$queryExportar = http_build_query($filtros);

function renderVacacionesResumen(string $titulo, array $rows): void
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
                    <th>Sin fecha</th>
                    <th>Más de 1 año</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['codigo'] ?? '') ?></td>
                        <td><?= e($row['nombre'] ?? '') ?></td>
                        <td><?= e($row['total'] ?? 0) ?></td>
                        <td><?= e($row['sin_fecha'] ?? 0) ?></td>
                        <td><?= e($row['mas_de_un_anio'] ?? 0) ?></td>
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
            <h2>Reporte de Vacaciones</h2>
            <p>Reconstrucción inicial basada en los módulos antiguos srhn_reporte_vac / vacaciones.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Consulta de vacaciones</h2>
            <p>Filtro predeterminado: personal activo. Usa la fecha de últimas vacaciones disponible en la base actual.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/vacaciones/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/vacaciones')) ?>" class="filters no-print">
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
            <label for="estado_vacaciones">Condición vacaciones</label>
            <select name="estado_vacaciones" id="estado_vacaciones">
                <option value="todos" <?= ($filtros['estado_vacaciones'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todas</option>
                <option value="sin_fecha" <?= ($filtros['estado_vacaciones'] ?? '') === 'sin_fecha' ? 'selected' : '' ?>>Sin fecha registrada</option>
                <option value="mas_un_anio" <?= ($filtros['estado_vacaciones'] ?? '') === 'mas_un_anio' ? 'selected' : '' ?>>Más de 1 año</option>
                <option value="mas_dos_anios" <?= ($filtros['estado_vacaciones'] ?? '') === 'mas_dos_anios' ? 'selected' : '' ?>>Más de 2 años</option>
            </select>
        </div>

        <div class="field">
            <label for="fecha_desde">Últimas vacaciones desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($filtros['fecha_desde'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="fecha_hasta">Últimas vacaciones hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($filtros['fecha_hasta'] ?? '') ?>">
        </div>

        <div class="field field-wide">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="actions">
            <button type="submit">Generar reporte</button>
            <a class="button-secondary" href="<?= e(url('/reportes/vacaciones')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total</h3>
            <p><strong><?= e($resumen['total'] ?? 0) ?></strong></p>
            <small>Total según filtros.</small>
        </div>
        <div class="card muted">
            <h3>Estado de vacaciones</h3>
            <p>Con fecha: <strong><?= e($resumen['con_fecha_vacaciones'] ?? 0) ?></strong></p>
            <p>Sin fecha: <strong><?= e($resumen['sin_fecha_vacaciones'] ?? 0) ?></strong></p>
            <p>Más de 1 año: <strong><?= e($resumen['mas_de_un_anio'] ?? 0) ?></strong></p>
            <p>Más de 2 años: <strong><?= e($resumen['mas_de_dos_anios'] ?? 0) ?></strong></p>
        </div>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <?php renderVacacionesResumen('Resumen por rango', $data['porRango'] ?? []); ?>
        <?php renderVacacionesResumen('Resumen por dependencia', $data['porDependencia'] ?? []); ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Listado de vacaciones</h3>
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
                    <th>Estado</th>
                    <th>Ingreso</th>
                    <th>Últimas vacaciones</th>
                    <th>Días desde vacaciones</th>
                    <th>Días teóricos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['listado'] ?? [])): ?>
                    <tr><td colspan="10" class="empty">Sin datos</td></tr>
                <?php endif; ?>
                <?php foreach (($data['listado'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td><?= e($row['funcionario'] ?? '') ?></td>
                        <td><?= e(trim((string) (($row['rango_codigo'] ?? '') . ' - ' . ($row['rango_nombre'] ?? '')))) ?></td>
                        <td><?= e(trim((string) (($row['unidad_codigo'] ?? '') . ' - ' . ($row['unidad_nombre'] ?? '')))) ?></td>
                        <td><?= e(trim((string) (($row['estado_codigo'] ?? '') . ' - ' . ($row['estado_nombre'] ?? '')))) ?></td>
                        <td><?= e($row['fecha_ingreso'] ?? '') ?></td>
                        <td><?= e($row['fecha_ultimas_vacaciones'] ?? '') ?></td>
                        <td><?= e($row['dias_desde_vacaciones'] ?? '') ?></td>
                        <td><?= e($row['dias_teoricos_generados'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card muted no-print">
    <h3>Nota técnica</h3>
    <p>El cálculo de días teóricos usa 2.5 días por cada 30 días de servicio. Para cálculo legal final se debe cruzar con vacaciones realmente gozadas cuando esa tabla histórica esté identificada.</p>
</section>
