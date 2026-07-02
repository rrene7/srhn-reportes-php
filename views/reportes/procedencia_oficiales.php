<?php
/** @var array $filtros */
/** @var array $rows */
/** @var array $resumen */
/** @var ?string $error */
/** @var string $modulo */
/** @var array $modulos */
/** @var array $moduloActual */
$buscar = (string) ($filtros['buscar'] ?? '');
$procedencia = (string) ($filtros['procedencia'] ?? '');
$estadoLaboral = (string) ($filtros['estado_laboral'] ?? '');
$queryString = http_build_query($filtros);
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
            <h2>Procedencia de oficiales</h2>
            <p>Clasifica oficiales como escuela o tropa usando evidencia registrada en acciones de personal.</p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/procedencia-oficiales/exportar-csv?' . $queryString)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>Regla aplicada:</strong>
        Si el funcionario actualmente es oficial y su historial registra rango previo como agente, cabo o sargento, se marca como <strong>Oficial de tropa</strong>. Si no hay evidencia previa de tropa, se marca como <strong>Oficial de escuela / sin tropa previa registrada</strong>.
        El estado laboral sale de <strong>employees.status_id</strong> contra <strong>statuses</strong>; código <strong>10</strong> o descripción <strong>ACTIVO</strong> se considera activo.
    </div>

    <form method="get" action="<?= e(url('/reportes/procedencia-oficiales')) ?>" class="filters no-print">
        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($buscar) ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="field">
            <label for="procedencia">Procedencia</label>
            <select name="procedencia" id="procedencia">
                <option value="" <?= $procedencia === '' ? 'selected' : '' ?>>Todos</option>
                <option value="escuela" <?= $procedencia === 'escuela' ? 'selected' : '' ?>>Escuela / sin tropa previa registrada</option>
                <option value="tropa" <?= $procedencia === 'tropa' ? 'selected' : '' ?>>Tropa</option>
            </select>
        </div>

        <div class="field">
            <label for="estado_laboral">Estado laboral</label>
            <select name="estado_laboral" id="estado_laboral">
                <option value="" <?= $estadoLaboral === '' ? 'selected' : '' ?>>Todos</option>
                <option value="activo" <?= $estadoLaboral === 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivo" <?= $estadoLaboral === 'inactivo' ? 'selected' : '' ?>>Inactivos / otros estados</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit">Generar reporte</button>
            <a href="<?= e(url('/reportes/procedencia-oficiales')) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="grid-2">
        <div class="card muted">
            <h3>Total de oficiales</h3>
            <p><strong><?= e($resumen['total'] ?? 0) ?></strong></p>
        </div>
        <div class="card muted">
            <h3>Resumen</h3>
            <p>Escuela / sin tropa previa registrada: <strong><?= e($resumen['escuela'] ?? 0) ?></strong></p>
            <p>Tropa: <strong><?= e($resumen['tropa'] ?? 0) ?></strong></p>
            <p>Activos: <strong><?= e($resumen['activos'] ?? 0) ?></strong></p>
            <p>Inactivos / otros estados: <strong><?= e($resumen['inactivos'] ?? 0) ?></strong></p>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Resultado</h3>
            <p>Mostrando hasta 500 oficiales según los filtros aplicados.</p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="report-table">
            <thead>
                <tr>
                    <th>N. empleado</th>
                    <th>Cédula</th>
                    <th>Funcionario</th>
                    <th>Rango actual</th>
                    <th>Dependencia actual</th>
                    <th>Estado</th>
                    <th>Procedencia</th>
                    <th>Evidencia</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="9" class="empty">No se encontraron oficiales con los filtros indicados.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td>
                            <strong><?= e($row['funcionario'] ?? '') ?></strong><br>
                            <small><?= e($row['sexo'] ?? '') ?></small>
                        </td>
                        <td>
                            <strong><?= e($row['rango_codigo'] ?? '') ?></strong><br>
                            <small><?= e($row['rango_actual'] ?? '') ?></small>
                        </td>
                        <td>
                            <strong><?= e($row['unidad_codigo'] ?? '') ?></strong><br>
                            <small><?= e($row['unidad_actual'] ?? '') ?></small>
                        </td>
                        <td>
                            <strong><?= e($row['estado_laboral'] ?? '') ?></strong><br>
                            <small><?= e(($row['estado_codigo'] ?? '') . ' - ' . ($row['estado_nombre'] ?? '')) ?></small>
                        </td>
                        <td><strong><?= e($row['procedencia_oficial'] ?? '') ?></strong></td>
                        <td>
                            <?= e($row['evidencia_tropa'] ?? '') ?><br>
                            <small><?= e($row['fecha_evidencia'] ?? '') ?></small>
                        </td>
                        <td><?= e($row['motivo'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
