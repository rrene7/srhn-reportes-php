<?php
/** @var string $buscar */
/** @var ?array $funcionario */
/** @var array $acciones */
/** @var array $complementaria */
/** @var ?string $error */
$buscar = (string) ($buscar ?? '');
$acciones = is_array($acciones ?? null) ? $acciones : [];
$complementaria = is_array($complementaria ?? null) ? $complementaria : [];
$queryExportar = http_build_query(['buscar' => $buscar]);
$nombreCompleto = $funcionario !== null ? trim((string) (($funcionario['nombre'] ?? '') . ' ' . ($funcionario['apellido'] ?? ''))) : '';
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Hoja de Vida para la placa</h2>
            <p>Reconstrucción del reporte del DLL para consulta resumida e imprimible del funcionario.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <a class="button-secondary" href="<?= e(url('/reportes/acciones')) ?>">Lista de acciones</a>
        </div>
    </div>
</section>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Buscar funcionario</h2>
            <p>Use cédula, posición policial, número de empleado, nombre o apellido.</p>
        </div>
        <?php if ($funcionario !== null): ?>
            <div class="toolbar">
                <button onclick="window.print()">Imprimir</button>
                <a class="button-secondary" href="<?= e(url('/reportes/hoja-vida-placa/exportar-csv?' . $queryExportar)) ?>">Exportar CSV</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/hoja-vida-placa')) ?>" class="filters">
        <div class="field field-wide">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($buscar) ?>" placeholder="Ejemplo: cédula, posición o apellido">
        </div>
        <div class="actions">
            <button type="submit">Generar hoja de vida</button>
            <a class="button-secondary" href="<?= e(url('/reportes/hoja-vida-placa')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<?php if ($funcionario !== null): ?>
    <section class="card print-area">
        <div class="card-header">
            <div>
                <h2>Hoja de Vida Institucional</h2>
                <p>Formato resumido para la placa / expediente.</p>
            </div>
            <div class="toolbar no-print">
                <button onclick="window.print()">Imprimir</button>
            </div>
        </div>

        <div class="grid-2">
            <div class="card muted">
                <h3>Funcionario</h3>
                <p><strong><?= e($nombreCompleto) ?></strong></p>
                <p>Cédula: <strong><?= e($funcionario['cedula'] ?? '') ?></strong></p>
                <p>N. empleado: <strong><?= e($funcionario['nemp'] ?? '') ?></strong></p>
                <p>Sexo: <strong><?= e($funcionario['sexo'] ?? '') ?></strong></p>
            </div>
            <div class="card muted">
                <h3>Datos institucionales</h3>
                <p>Rango: <strong><?= e(trim((string) (($funcionario['rango'] ?? '') . ' - ' . ($funcionario['rango_nombre'] ?? '')))) ?></strong></p>
                <p>Estado: <strong><?= e(trim((string) (($funcionario['estado'] ?? '') . ' - ' . ($funcionario['estado_nombre'] ?? '')))) ?></strong></p>
                <p>Dependencia: <strong><?= e(trim((string) (($funcionario['cuartel'] ?? '') . ' - ' . ($funcionario['cuartel_nombre'] ?? '')))) ?></strong></p>
                <p>Tipo policía: <strong><?= e($funcionario['tipopol'] ?? '') ?></strong></p>
            </div>
        </div>

        <div class="grid-2">
            <div class="card muted">
                <h3>Fechas</h3>
                <p>Ingreso: <strong><?= e($funcionario['fecing'] ?? '') ?></strong></p>
                <p>Ascenso: <strong><?= e($funcionario['fecascen'] ?? '') ?></strong></p>
                <p>Traslado / estado: <strong><?= e($funcionario['fectras'] ?? '') ?></strong></p>
                <p>Vacaciones: <strong><?= e($funcionario['fecvac'] ?? '') ?></strong></p>
                <p>Nacimiento: <strong><?= e($funcionario['fecnac'] ?? '') ?></strong></p>
            </div>
            <div class="card muted">
                <h3>Posiciones</h3>
                <p>Posición PN: <strong><?= e($funcionario['posicipn'] ?? '') ?></strong></p>
                <p>Posición MI: <strong><?= e($funcionario['posicimi'] ?? '') ?></strong></p>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h3>Últimas acciones de personal</h3>
                <p>Acciones relacionadas al funcionario para sustentar la hoja de vida.</p>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Resolución / OGD</th>
                        <th>Destino</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($acciones)): ?>
                        <tr><td colspan="5" class="empty">Sin acciones relacionadas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($acciones as $accion): ?>
                        <tr>
                            <td><?= e($accion['action_date'] ?? '') ?></td>
                            <td><?= e(trim((string) (($accion['action_type_id'] ?? '') . ' - ' . ($accion['tipo_accion'] ?? '')))) ?></td>
                            <td>
                                Res: <?= e($accion['resolution_number'] ?? '') ?><br>
                                <small>OGD: <?= e($accion['ogd_number'] ?? '') ?></small>
                            </td>
                            <td><?= e(trim((string) (($accion['target_position'] ?? '') . ' / ' . ($accion['rango_destino'] ?? '') . ' / ' . ($accion['unidad_destino'] ?? '')))) ?></td>
                            <td><?= e($accion['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h3>Datos complementarios</h3>
                <p>Secciones detectadas para hoja de vida: estudios, familia, direcciones, conducta y condición física.</p>
            </div>
        </div>

        <?php foreach ($complementaria as $clave => $seccion): ?>
            <div class="table-wrapper" style="margin-bottom: 1rem;">
                <h4><?= e($seccion['titulo'] ?? $clave) ?></h4>
                <p><small>Tabla: <?= e($seccion['tabla'] ?? 'No detectada') ?> | <?= e($seccion['estado'] ?? '') ?></small></p>
                <?php $columnas = array_slice($seccion['columnas'] ?? [], 0, 6); ?>
                <?php if (empty($seccion['rows']) || empty($columnas)): ?>
                    <p class="empty">Sin registros visibles.</p>
                <?php else: ?>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <?php foreach ($columnas as $columna): ?>
                                    <th><?= e($columna) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($seccion['rows'] ?? []) as $row): ?>
                                <tr>
                                    <?php foreach ($columnas as $columna): ?>
                                        <td><?= e($row[$columna] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="card muted no-print">
    <h3>Equivalencia con el DLL</h3>
    <p>Este reporte cubre el módulo “Hoja de Vida para la placa”, combinando datos generales, fechas institucionales, acciones y secciones complementarias detectadas.</p>
</section>
