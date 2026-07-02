<?php
/** @var array $filtros */
/** @var ?array $rows */
/** @var array $metadata */
/** @var ?string $error */
/** @var string $modulo */
/** @var array $modulos */
/** @var array $moduloActual */
$tabla = $metadata['tabla'] ?? null;
$columnas = $metadata['columnas'] ?? [];
$tipos = $metadata['tipos'] ?? [];
$categorias = $metadata['categorias'] ?? [];
$modo = (string) ($metadata['modo'] ?? 'generico');
$buscar = (string) ($filtros['buscar'] ?? '');
$tipo = (string) ($filtros['tipo'] ?? '');
$categoria = (string) ($filtros['categoria'] ?? '');
$fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
$fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
$queryString = http_build_query($filtros);
$categoriaActual = $categoria !== '' && isset($categorias[$categoria]) ? $categorias[$categoria] : null;
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

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Acciones por categoría</h2>
            <p>Accesos directos para cubrir los listados específicos del DLL.</p>
        </div>
    </div>

    <div class="report-menu">
        <?php foreach ($categorias as $clave => $item): ?>
            <a class="report-card <?= $categoria === $clave ? 'active' : '' ?>" href="<?= e(url('/reportes/acciones/resultado?' . http_build_query(['categoria' => $clave]))) ?>">
                <span class="module-status"><?= $categoria === $clave ? 'Activo' : 'Listado' ?></span>
                <strong><?= e($item['titulo'] ?? $clave) ?></strong>
                <small>Filtra acciones relacionadas con <?= e(strtolower((string) ($item['titulo'] ?? $clave))) ?>.</small>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2><?= e($categoriaActual['titulo'] ?? $moduloActual['titulo']) ?></h2>
            <p><?= e($categoriaActual !== null ? 'Reporte específico de acciones filtrado por categoría.' : $moduloActual['descripcion']) ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($tabla === null): ?>
        <div class="alert alert-info">
            No se detectó todavía una tabla de acciones en esta base de datos. El módulo queda preparado para cuando exista una tabla como <code>acciones</code>, <code>actions</code>, <code>personal_actions</code> o equivalente.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Tabla detectada: <strong><?= e($tabla) ?></strong>.
            <?php if ($modo === 'employee_actions'): ?>
                El módulo está usando datos del funcionario mediante relación con <strong>employees</strong>.
            <?php else: ?>
                Columnas encontradas: <?= e(implode(', ', $columnas)) ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/acciones/resultado')) ?>" class="filters">
        <?php if ($categoria !== ''): ?>
            <input type="hidden" name="categoria" value="<?= e($categoria) ?>">
        <?php endif; ?>

        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($buscar) ?>" placeholder="Cédula, posición, nombre, apellido, resolución u OGD">
        </div>

        <div class="field">
            <label for="tipo">Tipo de acción</label>
            <?php if ($tipos !== []): ?>
                <select name="tipo" id="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $item): ?>
                        <?php
                        $codigoTipo = is_array($item) ? (string) ($item['codigo'] ?? '') : (string) $item;
                        $nombreTipo = is_array($item) ? (string) ($item['nombre'] ?? $codigoTipo) : (string) $item;
                        ?>
                        <option value="<?= e($codigoTipo) ?>" <?= $tipo === $codigoTipo ? 'selected' : '' ?>><?= e($codigoTipo . ' - ' . $nombreTipo) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" name="tipo" id="tipo" value="<?= e($tipo) ?>" placeholder="Código o tipo">
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?= e($fechaDesde) ?>">
        </div>

        <div class="field">
            <label for="fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= e($fechaHasta) ?>">
        </div>

        <div class="actions">
            <button type="submit">Buscar acciones</button>
            <?php if ($rows !== null): ?>
                <a href="<?= e(url('/reportes/acciones/exportar-csv?' . $queryString)) ?>" class="button-secondary">Exportar CSV</a>
            <?php endif; ?>
            <a href="<?= e(url('/reportes/acciones')) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<?php if ($rows !== null): ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3>Resultado de acciones<?= $categoriaActual !== null ? ': ' . e($categoriaActual['titulo'] ?? '') : '' ?></h3>
                <p>Mostrando hasta 100 registros según los filtros aplicados.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <?php if ($modo === 'employee_actions'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Cédula</th>
                            <th>N. Emp.</th>
                            <th>Rango actual</th>
                            <th>Dependencia actual</th>
                            <th>Tipo acción</th>
                            <th>Fecha acción</th>
                            <th>Inicio / Fin</th>
                            <th>Resolución / OGD</th>
                            <th>Destino</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="11" class="empty">No se encontraron acciones con los filtros indicados.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['funcionario'] ?? '') ?></strong><br>
                                    <small>ID funcionario: <?= e($row['employee_id'] ?? '') ?></small>
                                </td>
                                <td><?= e($row['cedula'] ?? '') ?></td>
                                <td><?= e($row['nemp'] ?? '') ?></td>
                                <td>
                                    <strong><?= e($row['rango_codigo'] ?? '') ?></strong><br>
                                    <small><?= e($row['rango_nombre'] ?? '') ?></small>
                                </td>
                                <td>
                                    <strong><?= e($row['unidad_codigo'] ?? '') ?></strong><br>
                                    <small><?= e($row['unidad_nombre'] ?? '') ?></small>
                                </td>
                                <td>
                                    <strong><?= e($row['action_type_id'] ?? '') ?></strong><br>
                                    <small><?= e($row['tipo_accion'] ?? '') ?></small>
                                </td>
                                <td><?= e($row['action_date'] ?? '') ?></td>
                                <td>
                                    <?= e($row['start_date'] ?? '') ?><br>
                                    <small><?= e($row['end_date'] ?? '') ?></small>
                                </td>
                                <td>
                                    Res: <?= e($row['resolution_number'] ?? '') ?><br>
                                    <small>OGD: <?= e($row['ogd_number'] ?? '') ?></small>
                                </td>
                                <td>
                                    Pos: <?= e($row['target_position'] ?? '') ?><br>
                                    <small><?= e($row['rango_destino'] ?? '') ?> / <?= e($row['unidad_destino'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?= e($row['notes'] ?? '') ?><br>
                                    <small><?= e($row['migration_review_status'] ?? '') ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php foreach (array_slice($columnas, 0, 12) as $columna): ?>
                                <th><?= e($columna) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="<?= e(max(1, min(12, count($columnas)))) ?>" class="empty">No se encontraron acciones con los filtros indicados.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach (array_slice($columnas, 0, 12) as $columna): ?>
                                    <td><?= e($row[$columna] ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="card muted">
    <h3>Equivalencia con el DLL</h3>
    <p>
        Este bloque cubre la familia de listados de acciones del sistema legado: ascensos, traslados, vacaciones, licencias, sanciones, incapacidades y novedades. Cuando confirmemos los nombres exactos de tablas y columnas del DLL, este módulo se ajusta de genérico a reporte especializado.
    </p>
</section>
