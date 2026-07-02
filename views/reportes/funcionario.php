<?php
/** @var ?array $funcionario */
/** @var array $acciones */
/** @var array $complementaria */
/** @var string $buscar */
/** @var ?string $error */
/** @var string $modulo */
/** @var array $modulos */
/** @var array $moduloActual */
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
            <h2>Ficha individual de funcionario</h2>
            <p>Vista base para reconstruir la consulta individual del módulo legado.</p>
        </div>
        <div class="toolbar no-print">
            <a class="button-secondary" href="<?= e(url('/reportes/consulta-funcionario')) ?>">Nueva consulta</a>
            <?php if ($buscar !== ''): ?>
                <a class="button-secondary" href="<?= e(url('/reportes/consulta-funcionario/resultado?' . http_build_query(['buscar' => $buscar]))) ?>">Volver al resultado</a>
            <?php endif; ?>
            <button onclick="window.print()">Imprimir ficha</button>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($funcionario === null && empty($error)): ?>
        <div class="empty">No se encontró el funcionario solicitado.</div>
    <?php endif; ?>

    <?php if ($funcionario !== null): ?>
        <div class="print-title">
            <h2>Ficha individual de funcionario</h2>
            <p>SRHN / Recursos Humanos</p>
        </div>

        <div class="mini-table-wrapper">
            <table class="mini-table">
                <tbody>
                    <tr>
                        <th>Nombre completo</th>
                        <td><?= e(trim(($funcionario['nombre'] ?? '') . ' ' . ($funcionario['apellido'] ?? ''))) ?></td>
                        <th>Cédula</th>
                        <td><?= e($funcionario['cedula'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Número de empleado</th>
                        <td><?= e($funcionario['nemp'] ?? '') ?></td>
                        <th>Sexo</th>
                        <td><?= e($funcionario['sexo'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Rango</th>
                        <td><?= e(($funcionario['rango'] ?? '') . ' - ' . ($funcionario['rango_nombre'] ?? '')) ?></td>
                        <th>Estado</th>
                        <td><?= e(($funcionario['estado'] ?? '') . ' - ' . ($funcionario['estado_nombre'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th>Dependencia</th>
                        <td colspan="3"><?= e(($funcionario['cuartel'] ?? '') . ' - ' . ($funcionario['cuartel_nombre'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <th>Posición PN</th>
                        <td><?= e($funcionario['posicipn'] ?? '') ?></td>
                        <th>Posición MI</th>
                        <td><?= e($funcionario['posicimi'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Fecha ingreso</th>
                        <td><?= e($funcionario['fecing'] ?? '') ?></td>
                        <th>Fecha ascenso</th>
                        <td><?= e($funcionario['fecascen'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Fecha traslado / estado</th>
                        <td><?= e($funcionario['fectras'] ?? '') ?></td>
                        <th>Fecha vacaciones</th>
                        <td><?= e($funcionario['fecvac'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Fecha nacimiento</th>
                        <td><?= e($funcionario['fecnac'] ?? '') ?></td>
                        <th>Tipo policía</th>
                        <td><?= e($funcionario['tipopol'] ?? '') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($funcionario !== null): ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3>Acciones recientes</h3>
                <p>Últimas acciones vinculadas al funcionario según la tabla de acciones disponible.</p>
            </div>
            <div class="toolbar no-print">
                <a class="button-secondary" href="<?= e(url('/reportes/acciones/resultado?' . http_build_query(['buscar' => (($funcionario['nemp'] ?? '') !== '' ? $funcionario['nemp'] : ($funcionario['cedula'] ?? $buscar))]))) ?>">Ver todas las acciones</a>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Tipo acción</th>
                        <th>Fecha acción</th>
                        <th>Inicio / Fin</th>
                        <th>Resolución / OGD</th>
                        <th>Destino</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($acciones)): ?>
                        <tr>
                            <td colspan="6" class="empty">No se encontraron acciones recientes para este funcionario.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($acciones as $accion): ?>
                        <tr>
                            <td>
                                <strong><?= e($accion['action_type_id'] ?? '') ?></strong><br>
                                <small><?= e($accion['tipo_accion'] ?? '') ?></small>
                            </td>
                            <td><?= e($accion['action_date'] ?? '') ?></td>
                            <td>
                                <?= e($accion['start_date'] ?? '') ?><br>
                                <small><?= e($accion['end_date'] ?? '') ?></small>
                            </td>
                            <td>
                                Res: <?= e($accion['resolution_number'] ?? '') ?><br>
                                <small>OGD: <?= e($accion['ogd_number'] ?? '') ?></small>
                            </td>
                            <td>
                                Pos: <?= e($accion['target_position'] ?? '') ?><br>
                                <small><?= e($accion['rango_destino'] ?? '') ?> / <?= e($accion['unidad_destino'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= e($accion['notes'] ?? '') ?><br>
                                <small><?= e($accion['migration_review_status'] ?? '') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h3>Hoja de vida complementaria</h3>
                <p>Bloques preparados para cubrir estudios, familia, direcciones, conducta y condición física del sistema legado.</p>
            </div>
        </div>

        <?php if (empty($complementaria)): ?>
            <div class="empty">No hay secciones complementarias cargadas.</div>
        <?php endif; ?>

        <?php foreach ($complementaria as $seccion): ?>
            <div class="card muted">
                <h3><?= e($seccion['titulo'] ?? 'Sección') ?></h3>
                <?php if (empty($seccion['tabla'])): ?>
                    <p>No se detectó tabla para esta sección.</p>
                <?php else: ?>
                    <p>Tabla detectada: <strong><?= e($seccion['tabla']) ?></strong></p>
                    <div class="table-wrapper">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <?php foreach (array_slice($seccion['columnas'] ?? [], 0, 8) as $columna): ?>
                                        <th><?= e($columna) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($seccion['rows'])): ?>
                                    <tr>
                                        <td colspan="<?= e(max(1, min(8, count($seccion['columnas'] ?? [])))) ?>" class="empty">Sin registros encontrados para este funcionario.</td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($seccion['rows'] ?? [] as $row): ?>
                                    <tr>
                                        <?php foreach (array_slice($seccion['columnas'] ?? [], 0, 8) as $columna): ?>
                                            <td><?= e($row[$columna] ?? '') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="card muted no-print">
    <h3>Siguiente fase</h3>
    <p>
        Esta ficha queda lista para especializar cada bloque complementario cuando confirmemos los nombres exactos de columnas de las tablas heredadas.
    </p>
</section>
