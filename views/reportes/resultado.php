<?php
/** @var array $rows */
/** @var int $total */
/** @var array $totalesRango */
/** @var array $totalesCuartel */
/** @var array $filtros */
/** @var int $pagina */
/** @var int $porPagina */
/** @var int $totalPaginas */
/** @var int $offset */
$queryString = http_build_query($filtros);
$desde = $total > 0 ? $offset + 1 : 0;
$hasta = min($offset + $porPagina, $total);
$prevQuery = http_build_query(array_merge($filtros, ['page' => max(1, $pagina - 1)]));
$nextQuery = http_build_query(array_merge($filtros, ['page' => min($totalPaginas, $pagina + 1)]));
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

    <div class="toolbar">
        <?php if ($pagina > 1): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $prevQuery)) ?>">← Página anterior</a>
        <?php endif; ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $nextQuery)) ?>">Página siguiente →</a>
        <?php endif; ?>
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
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $prevQuery)) ?>">← Página anterior</a>
        <?php endif; ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a class="button-secondary" href="<?= e(url('/reportes/resultado?' . $nextQuery)) ?>">Página siguiente →</a>
        <?php endif; ?>
    </div>
</section>
