<?php
/** @var array $filtros */
/** @var array $catalogos */
/** @var array $data */
/** @var ?string $error */
$data = is_array($data ?? null) ? $data : [];
$rangos = $catalogos['rangos'] ?? [];
$unidades = $catalogos['unidades'] ?? [];
$estados = $catalogos['estados'] ?? [];
$tiposOperatividad = $catalogos['tiposOperatividad'] ?? [];
$queryExportar = http_build_query($filtros);

if (!function_exists('operatividadLabel')) {
    function operatividadLabel(string $codigo): string
    {
        return match (strtoupper(trim($codigo))) {
            'OO' => 'Operativo',
            'OA' => 'Operativo administrativo',
            'NO' => 'No operativo',
            default => 'Sin definir',
        };
    }
}

if (!function_exists('operatividadTotal')) {
    function operatividadTotal(array $rows, string $codigo): int
    {
        foreach ($rows as $row) {
            if (strtoupper((string) ($row['codigo'] ?? '')) === strtoupper($codigo)) {
                return (int) ($row['total'] ?? 0);
            }
        }
        return 0;
    }
}

if (!function_exists('renderOperatividadTablaResumen')) {
    function renderOperatividadTablaResumen(string $titulo, array $rows): void
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
                            <td><?= e(number_format((int) ($row['total'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

$resumenOperatividad = $data['resumenOperatividad'] ?? [];
$totalOO = operatividadTotal($resumenOperatividad, 'OO');
$totalOA = operatividadTotal($resumenOperatividad, 'OA');
$totalNO = operatividadTotal($resumenOperatividad, 'NO');
$totalSinDefinir = operatividadTotal($resumenOperatividad, 'SIN DEFINIR');
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <span class="operatividad-eyebrow">RECURSOS HUMANOS · SOLO LECTURA</span>
            <h2>Operatividad policial</h2>
            <p>Vista temporal de personal operativo, operativo administrativo y no operativo basada en los campos de <strong>employees</strong>.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
        </div>
    </div>
</section>

<section class="operatividad-summary">
    <a class="operatividad-card is-oo" href="<?= e(url('/reportes/operativos?' . http_build_query(array_merge($filtros, ['operatividad' => 'OO'])))) ?>">
        <span>OO</span>
        <strong><?= e(number_format($totalOO)) ?></strong>
        <small>Operativos</small>
    </a>
    <a class="operatividad-card is-oa" href="<?= e(url('/reportes/operativos?' . http_build_query(array_merge($filtros, ['operatividad' => 'OA'])))) ?>">
        <span>OA</span>
        <strong><?= e(number_format($totalOA)) ?></strong>
        <small>Operativos administrativos</small>
    </a>
    <a class="operatividad-card is-no" href="<?= e(url('/reportes/operativos?' . http_build_query(array_merge($filtros, ['operatividad' => 'NO'])))) ?>">
        <span>NO</span>
        <strong><?= e(number_format($totalNO)) ?></strong>
        <small>No operativos</small>
    </a>
    <a class="operatividad-card is-empty" href="<?= e(url('/reportes/operativos?' . http_build_query(array_merge($filtros, ['operatividad' => 'SIN DEFINIR'])))) ?>">
        <span>—</span>
        <strong><?= e(number_format($totalSinDefinir)) ?></strong>
        <small>Sin clasificación</small>
    </a>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Consulta de operatividad</h2>
            <p>Consulta <code>police_operativity_type</code>, motivo, referencia, fecha efectiva y notas. Esta pantalla no modifica registros.</p>
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
            <label for="operatividad">Clasificación operativa</label>
            <select name="operatividad" id="operatividad">
                <option value="">Todas</option>
                <?php foreach ($tiposOperatividad as $tipo): ?>
                    <?php $codigo = (string) ($tipo['codigo'] ?? ''); ?>
                    <option value="<?= e($codigo) ?>" <?= ($filtros['operatividad'] ?? '') === $codigo ? 'selected' : '' ?>>
                        <?= e($codigo . ' - ' . ($tipo['nombre'] ?? operatividadLabel($codigo))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

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
            <label for="motivo">Motivo contiene</label>
            <input type="text" name="motivo" id="motivo" value="<?= e($filtros['motivo'] ?? '') ?>" placeholder="Ej.: restricción médica">
        </div>

        <div class="field field-wide">
            <label for="buscar">Buscar funcionario o referencia</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre, referencia o nota">
        </div>

        <div class="actions">
            <button type="submit">Consultar</button>
            <a class="button-secondary" href="<?= e(url('/reportes/operativos')) ?>">Limpiar</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="operatividad-result-head">
        <div>
            <span>RESULTADO ACTUAL</span>
            <strong><?= e(number_format((int) ($data['total'] ?? 0))) ?></strong>
            <small>funcionarios según los filtros aplicados</small>
        </div>
        <p>El resumen superior conserva rango, dependencia, sexo y estatus, pero muestra todas las clasificaciones para facilitar la comparación.</p>
    </div>
</section>

<section class="card">
    <div class="grid-2">
        <?php renderOperatividadTablaResumen('Resumen por rango', $data['porRango'] ?? []); ?>
        <?php renderOperatividadTablaResumen('Resumen por dependencia', $data['porDependencia'] ?? []); ?>
        <?php renderOperatividadTablaResumen('Resumen por sexo', $data['porSexo'] ?? []); ?>
        <?php renderOperatividadTablaResumen('Resumen por estatus', $data['porEstatus'] ?? []); ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h3>Detalle de operatividad</h3>
            <p>Mostrando hasta 300 funcionarios. La información es únicamente de consulta.</p>
        </div>
    </div>
    <div class="table-wrapper operatividad-table-wrapper">
        <table class="report-table operatividad-table">
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Cédula</th>
                    <th>Funcionario</th>
                    <th>Rango</th>
                    <th>Dependencia</th>
                    <th>Estado</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <th>Referencia</th>
                    <th>Fecha efectiva</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['listado'] ?? [])): ?>
                    <tr><td colspan="11" class="empty">No se encontraron registros con los filtros seleccionados.</td></tr>
                <?php endif; ?>
                <?php foreach (($data['listado'] ?? []) as $row): ?>
                    <?php $tipo = (string) ($row['operatividad_tipo'] ?? 'SIN DEFINIR'); ?>
                    <tr>
                        <td><?= e($row['nemp'] ?? '') ?></td>
                        <td><?= e($row['cedula'] ?? '') ?></td>
                        <td><strong><?= e($row['funcionario'] ?? '') ?></strong></td>
                        <td><?= e(trim((string) (($row['rango_codigo'] ?? '') . ' - ' . ($row['rango_nombre'] ?? '')))) ?></td>
                        <td><?= e(trim((string) (($row['unidad_codigo'] ?? '') . ' - ' . ($row['unidad_nombre'] ?? '')))) ?></td>
                        <td><?= e(trim((string) (($row['estado_codigo'] ?? '') . ' - ' . ($row['estado_nombre'] ?? '')))) ?></td>
                        <td><span class="operatividad-badge badge-<?= e(strtolower(str_replace(' ', '-', $tipo))) ?>"><?= e($tipo . ' · ' . operatividadLabel($tipo)) ?></span></td>
                        <td><?= e($row['operatividad_motivo'] ?? '') ?></td>
                        <td><?= e($row['operatividad_referencia'] ?? '') ?></td>
                        <td><?= e($row['operatividad_fecha_efectiva'] ?? '') ?></td>
                        <td class="operatividad-notes"><?= e($row['operatividad_notas'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
    .operatividad-eyebrow {
        display: block;
        margin-bottom: .35rem;
        color: #1d4f88;
        font-size: .75rem;
        font-weight: 900;
        letter-spacing: .08em;
    }

    .operatividad-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
    }

    .operatividad-card {
        display: grid;
        grid-template-columns: auto 1fr;
        grid-template-rows: auto auto;
        gap: .15rem .8rem;
        align-items: center;
        min-height: 105px;
        padding: 1rem;
        border: 1px solid #d9e2ef;
        border-radius: 20px;
        background: #fff;
        color: #0f172a;
        text-decoration: none;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .operatividad-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 34px rgba(15, 23, 42, .1);
    }

    .operatividad-card > span {
        grid-row: 1 / 3;
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: #eaf2fb;
        color: #17375f;
        font-weight: 900;
    }

    .operatividad-card strong {
        font-size: 1.9rem;
        line-height: 1;
    }

    .operatividad-card small {
        color: #64748b;
        font-weight: 700;
    }

    .operatividad-card.is-oo { border-top: 4px solid #15803d; }
    .operatividad-card.is-oa { border-top: 4px solid #1d4ed8; }
    .operatividad-card.is-no { border-top: 4px solid #b91c1c; }
    .operatividad-card.is-empty { border-top: 4px solid #64748b; }

    .operatividad-result-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
    }

    .operatividad-result-head > div {
        display: grid;
        grid-template-columns: auto auto;
        align-items: end;
        gap: 0 .65rem;
    }

    .operatividad-result-head span {
        grid-column: 1 / 3;
        color: #1d4f88;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .08em;
    }

    .operatividad-result-head strong {
        font-size: 2.2rem;
        line-height: 1;
    }

    .operatividad-result-head small,
    .operatividad-result-head p {
        color: #64748b;
    }

    .operatividad-result-head p {
        max-width: 580px;
        margin: 0;
    }

    .operatividad-table-wrapper {
        overflow-x: auto;
    }

    .operatividad-table {
        min-width: 1650px;
    }

    .operatividad-table td {
        vertical-align: top;
    }

    .operatividad-badge {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        padding: .32rem .55rem;
        border-radius: 999px;
        background: #e2e8f0;
        color: #334155;
        font-size: .75rem;
        font-weight: 900;
    }

    .operatividad-badge.badge-oo { background: #dcfce7; color: #166534; }
    .operatividad-badge.badge-oa { background: #dbeafe; color: #1e40af; }
    .operatividad-badge.badge-no { background: #fee2e2; color: #991b1b; }

    .operatividad-notes {
        min-width: 220px;
        white-space: normal;
    }

    @media (max-width: 980px) {
        .operatividad-summary {
            grid-template-columns: repeat(2, minmax(160px, 1fr));
        }

        .operatividad-result-head {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (max-width: 560px) {
        .operatividad-summary {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        .operatividad-summary {
            grid-template-columns: repeat(4, 1fr);
        }

        .operatividad-card {
            box-shadow: none;
            min-height: auto;
        }

        .operatividad-table {
            min-width: 0;
            font-size: 8px;
        }
    }
</style>
