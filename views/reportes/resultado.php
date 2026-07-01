<?php
/** @var array $rows */
/** @var int $total */
/** @var array $totalesRango */
/** @var array $totalesCuartel */
/** @var array $filtros */
/** @var int $pagina */
/** @var int $porPagina */
/** @var array $porPaginaOpciones */
/** @var int $totalPaginas */
/** @var int $offset */
$queryString = http_build_query($filtros);
$desde = $total > 0 ? $offset + 1 : 0;
$hasta = min($offset + $porPagina, $total);
$baseQueryParams = array_merge($filtros, ['per_page' => $porPagina]);
$firstQuery = http_build_query(array_merge($baseQueryParams, ['page' => 1]));
$prevQuery = http_build_query(array_merge($baseQueryParams, ['page' => max(1, $pagina - 1)]));
$nextQuery = http_build_query(array_merge($baseQueryParams, ['page' => min($totalPaginas, $pagina + 1)]));
$lastQuery = http_build_query(array_merge($baseQueryParams, ['page' => $totalPaginas]));

$rangoDesde = trim((string) ($filtros['rango_desde'] ?? ''));
$rangoHasta = trim((string) ($filtros['rango_hasta'] ?? ''));
$cuartelDesde = trim((string) ($filtros['cuartel_desde'] ?? ''));
$cuartelHasta = trim((string) ($filtros['cuartel_hasta'] ?? ''));
$estadoFiltro = trim((string) ($filtros['estado'] ?? ''));
$buscarFiltro = trim((string) ($filtros['buscar'] ?? ''));

$formatearFiltro = static fn (string $valor): string => $valor !== '' ? $valor : 'Todos';
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Resultado del reporte</h2>
            <p>Total encontrado: <strong><?= e($total) ?></strong></p>
            <p>Mostrando <strong><?= e($desde) ?></strong> a <strong><?= e($hasta) ?></strong> de <strong><?= e($total) ?></strong> registros.</p>
            <p>Página <strong><?= e($pagina) ?></strong> de <strong><?= e($totalPaginas) ?></strong></p>
        </div>
        <div class="toolbar">
            <button onclick="window.print()">Imprimir</button>
            <a class="button-secondary" href="<?= e(url('/reportes/exportar-csv?' . $queryString)) ?>">Exportar CSV</a>
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Nuevo filtro</a>
        </div>
    </div>

    <form class="toolbar" method="get" action="<?= e(url('/reportes/resultado')) ?>">
        <?php foreach ($filtros as $name => $value): ?>
            <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="page" value="1">

        <label for="per_page">Registros por página</label>
        <select id="per_page" name="per_page">
            <?php foreach ($porPaginaOpciones as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= (int) $opcion === (int) $porPagina ? 'selected' : '' ?>>
                    <?= e($opcion) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Aplicar</button>
    </form>

    <div class="toolbar">
        <?php if ($pagina > 1): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $firstQuery)) ?>">Primera página</a>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $prevQuery)) ?>">← Página anterior</a>
        <?php endif; ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $nextQuery)) ?>">Página siguiente →</a>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $lastQuery)) ?>">Última página</a>
        <?php endif; ?>
    </div>
</section>

<section class="card no-print">
    <h3>Filtros aplicados</h3>
    <div class="mini-table-wrapper">
        <table class="mini-table">
            <tbody>
                <tr>
                    <th>Rango desde</th>
                    <td><?= e($formatearFiltro($rangoDesde)) ?></td>
                    <th>Rango hasta</th>
                    <td><?= e($formatearFiltro($rangoHasta)) ?></td>
                </tr>
                <tr>
                    <th>Dependencia desde</th>
                    <td><?= e($formatearFiltro($cuartelDesde)) ?></td>
                    <th>Dependencia hasta</th>
                    <td><?= e($formatearFiltro($cuartelHasta)) ?></td>
                </tr>
                <tr>
                    <th>Estado</th>
                    <td><?= e($formatearFiltro($estadoFiltro)) ?></td>
                    <th>Búsqueda</th>
                    <td><?= e($formatearFiltro($buscarFiltro)) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="grid-2">
    <div class="card">
        <h3>Totales por rango</h3>
        <div class="mini-table-wrapper">
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Rango</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($totalesRango as $item): ?>
                        <tr>
                            <td><?= e($item['codigo']) ?></td>
                            <td><?= e($item['nombre']) ?></td>
                            <td><?= e($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Totales por dependencia</h3>
        <div class="mini-table-wrapper">
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Dependencia</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($totalesCuartel as $item): ?>
                        <tr>
                            <td><?= e($item['codigo']) ?></td>
                            <td><?= e($item['nombre']) ?></td>
                            <td><?= e($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card">
    <div class="print-title">
        <h2>Reporte General de Personal</h2>
        <p>SRHN / Recursos Humanos</p>
    </div>

    <div class="table-wrapper">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Rango</th>
                    <th>Nombre completo</th>
                    <th>Cédula</th>
                    <th>N. Emp.</th>
                    <th>Dependencia</th>
                    <th>Sexo</th>
                    <th>Pos. PN</th>
                    <th>Ingreso</th>
                    <th>Ascenso</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="10" class="empty">No se encontraron registros.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['rango']) ?></strong><br>
                            <small><?= e($row['rango_nombre']) ?></small>
                        </td>
                        <td><?= e(trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''))) ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td>
                            <strong><?= e($row['cuartel'] ?? '') ?></strong><br>
                            <small><?= e($row['cuartel_nombre'] ?? '') ?></small>
                        </td>
                        <td><?= e($row['sexo'] ?? '') ?></td>
                        <td><?= e($row['posicipn'] ?? '') ?></td>
                        <td><?= e($row['fecing'] ?? '') ?></td>
                        <td><?= e($row['fecascen'] ?? '') ?></td>
                        <td>
                            <strong><?= e($row['estado'] ?? '') ?></strong><br>
                            <small><?= e($row['estado_nombre'] ?? '') ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card no-print">
    <div class="toolbar">
        <?php if ($pagina > 1): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $firstQuery)) ?>">Primera página</a>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $prevQuery)) ?>">← Página anterior</a>
        <?php endif; ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $nextQuery)) ?>">Página siguiente →</a>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $lastQuery)) ?>">Última página</a>
        <?php endif; ?>
    </div>
</section>
