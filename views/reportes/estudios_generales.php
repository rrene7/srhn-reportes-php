<?php
/** @var array $filtros */
/** @var array $rows */
/** @var ?int $total */
/** @var array $resumen */
/** @var array $metadata */
/** @var ?string $error */
$queryExportar = http_build_query(array_merge($filtros, ['generar' => '1']));
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Estudios Realizados / Estudios Generales</h2>
            <p>Reconstrucción del reporte del DLL para consultar estudios registrados en la hoja de vida.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Estudios Generales</h2>
            <p>
                Tabla detectada:
                <strong><?= e($metadata['tabla'] ?? 'No detectada') ?></strong>
            </p>
        </div>
        <div class="toolbar no-print">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estudios-generales/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($metadata['tabla'])): ?>
        <div class="alert alert-info">
            No se detectó una tabla de estudios. El sistema buscó: employee_studies, employee_education, studies, educations, estudios y ESTUDIOS.
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/estudios-generales')) ?>" class="filters no-print">
        <input type="hidden" name="generar" value="1">

        <div class="field">
            <label for="buscar">Buscar funcionario</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="field">
            <label for="estudio">Estudio / carrera</label>
            <input type="text" name="estudio" id="estudio" value="<?= e($filtros['estudio'] ?? '') ?>" placeholder="Ejemplo: Derecho, Sistemas, Licenciatura">
        </div>

        <div class="field">
            <label for="institucion">Institución</label>
            <input type="text" name="institucion" id="institucion" value="<?= e($filtros['institucion'] ?? '') ?>" placeholder="Universidad, centro o institución">
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
            <button type="submit">Generar reporte</button>
            <a class="button-secondary" href="<?= e(url('/reportes/estudios-generales')) ?>">Limpiar</a>
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
            <h3>Columnas detectadas</h3>
            <p><?= e(implode(', ', array_slice($metadata['columnas'] ?? [], 0, 12))) ?></p>
        </div>
    </div>

    <div class="grid-2">
        <?php if (!empty($resumen['por_nivel'])): ?>
            <div class="table-wrapper">
                <h3>Resumen por nivel</h3>
                <table class="mini-table">
                    <thead>
                        <tr><th>Nivel</th><th>Total visible</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen['por_nivel'] as $nivel => $cantidad): ?>
                            <tr>
                                <td><?= e($nivel) ?></td>
                                <td><?= e($cantidad) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($resumen['por_estado'])): ?>
            <div class="table-wrapper">
                <h3>Resumen por estado</h3>
                <table class="mini-table">
                    <thead>
                        <tr><th>Estado</th><th>Total visible</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen['por_estado'] as $estado => $cantidad): ?>
                            <tr>
                                <td><?= e($estado) ?></td>
                                <td><?= e($cantidad) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Resultado</h3>
            <p>Listado de estudios registrados.</p>
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
                    <th>Estudio</th>
                    <th>Nivel</th>
                    <th>Institución</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="11" class="empty">Use los filtros y presione Generar reporte.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td><?= e($row['funcionario'] ?? '') ?></td>
                        <td><?= e($row['rango_actual'] ?? '') ?></td>
                        <td><?= e($row['dependencia_actual'] ?? '') ?></td>
                        <td><?= e($row['estudio'] ?? '') ?></td>
                        <td><?= e($row['nivel'] ?? '') ?></td>
                        <td><?= e($row['institucion'] ?? '') ?></td>
                        <td><?= e($row['fecha_estudio'] ?? '') ?></td>
                        <td><?= e($row['estado_estudio'] ?? '') ?></td>
                        <td><?= e($row['observacion'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
