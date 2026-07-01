<?php
/** @var string $buscar */
/** @var array $rows */
/** @var ?int $total */
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
            <h2><?= e($moduloActual['titulo']) ?></h2>
            <p><?= e($moduloActual['descripcion']) ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/consulta-funcionario/resultado')) ?>" class="filters consulta-form">
        <div class="field consulta-field">
            <label for="buscar">Buscar funcionario</label>
            <input
                type="text"
                name="buscar"
                id="buscar"
                value="<?= e($buscar) ?>"
                placeholder="Cédula, posición, nombre o apellido"
                autofocus
            >
        </div>

        <div class="actions">
            <button type="submit">Consultar</button>
            <a href="<?= e(url('/reportes/consulta-funcionario')) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<?php if ($total !== null): ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3>Resultado de consulta</h3>
                <p>Total encontrado: <strong><?= e($total) ?></strong></p>
                <?php if ($total > 25): ?>
                    <p>Se muestran los primeros 25 registros. Para ver todos, usa el reporte general con el mismo criterio.</p>
                <?php endif; ?>
            </div>
            <div class="toolbar no-print">
                <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . http_build_query(['buscar' => $buscar]))) ?>">Ver en reporte general</a>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Funcionario</th>
                        <th>Cédula</th>
                        <th>N. Emp.</th>
                        <th>Rango</th>
                        <th>Dependencia</th>
                        <th>Estado</th>
                        <th>Ingreso</th>
                        <th>Ascenso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="empty">No se encontraron registros para la consulta indicada.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <?php $identificador = (string) (($row['nemp'] ?? '') !== '' ? $row['nemp'] : ($row['cedula'] ?? '')); ?>
                        <tr>
                            <td>
                                <strong><?= e(trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''))) ?></strong><br>
                                <small>Sexo: <?= e($row['sexo'] ?? '') ?></small>
                            </td>
                            <td><?= e($row['cedula'] ?? '') ?></td>
                            <td><?= e($row['nemp'] ?? '') ?></td>
                            <td>
                                <strong><?= e($row['rango'] ?? '') ?></strong><br>
                                <small><?= e($row['rango_nombre'] ?? '') ?></small>
                            </td>
                            <td>
                                <strong><?= e($row['cuartel'] ?? '') ?></strong><br>
                                <small><?= e($row['cuartel_nombre'] ?? '') ?></small>
                            </td>
                            <td>
                                <strong><?= e($row['estado'] ?? '') ?></strong><br>
                                <small><?= e($row['estado_nombre'] ?? '') ?></small>
                            </td>
                            <td><?= e($row['fecing'] ?? '') ?></td>
                            <td><?= e($row['fecascen'] ?? '') ?></td>
                            <td>
                                <a class="button-secondary" href="<?= e(url('/reportes/funcionario?' . http_build_query(['buscar' => $identificador]))) ?>">Ver ficha</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card muted">
    <h3>Equivalencia con el DLL</h3>
    <p>
        Esta pantalla corresponde a la consulta individual del sistema legado. Permite ubicar rápidamente funcionarios por datos básicos y luego abrir el resultado completo en el reporte general.
    </p>
</section>
