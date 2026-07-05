<?php
/** @var array $data */
/** @var ?string $error */
$resumen = $data['resumen'] ?? [];

function mapaTabla(string $titulo, array $rows, array $columnas): void
{
    ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h3><?= e($titulo) ?></h3>
                <p><?= e(count($rows)) ?> registros agrupados</p>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="mini-table">
                <thead>
                    <tr>
                        <?php foreach ($columnas as $label): ?>
                            <th><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= e(count($columnas)) ?>" class="empty">Sin datos</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach (array_keys($columnas) as $key): ?>
                                <td><?= e($row[$key] ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}
?>

<section class="card no-print">
    <div class="card-header">
        <div>
            <h2>Mapa General de Datos</h2>
            <p>Vista maestra para categorizar y sectorizar la información: personal, zonas, áreas, dependencias, acciones y estados.</p>
        </div>
        <div class="toolbar">
            <a class="button-secondary" href="<?= e(url('/reportes')) ?>">Volver a reportes</a>
            <button onclick="window.print()">Imprimir</button>
        </div>
    </div>
</section>

<?php if (!empty($error)): ?>
    <section class="card">
        <div class="alert alert-error"><?= e($error) ?></div>
    </section>
<?php endif; ?>

<section class="card">
    <h3>Totales generales</h3>
    <div class="report-menu">
        <div class="report-card"><span class="module-status">Personal</span><strong><?= e($resumen['funcionarios'] ?? 0) ?></strong><small>Funcionarios registrados</small></div>
        <div class="report-card"><span class="module-status">Acciones</span><strong><?= e($resumen['acciones'] ?? 0) ?></strong><small>Acciones de personal</small></div>
        <div class="report-card"><span class="module-status">Catálogo</span><strong><?= e($resumen['rangos'] ?? 0) ?></strong><small>Rangos</small></div>
        <div class="report-card"><span class="module-status">Catálogo</span><strong><?= e($resumen['dependencias'] ?? 0) ?></strong><small>Dependencias / unidades</small></div>
        <div class="report-card"><span class="module-status">Estados</span><strong><?= e($resumen['estados_personal'] ?? 0) ?></strong><small>Estados de personal</small></div>
        <div class="report-card"><span class="module-status">Acciones</span><strong><?= e($resumen['tipos_accion'] ?? 0) ?></strong><small>Tipos de acción</small></div>
        <div class="report-card"><span class="module-status">Activas</span><strong><?= e($resumen['acciones_activas'] ?? 0) ?></strong><small>Acciones no eliminadas</small></div>
        <div class="report-card"><span class="module-status">Eliminadas</span><strong><?= e($resumen['acciones_eliminadas'] ?? 0) ?></strong><small>Acciones con deleted_at</small></div>
    </div>
</section>

<section class="card muted">
    <h3>Lectura del mapa</h3>
    <p>Zona y área se calculan por prefijos del código de dependencia: nivel 1 usa los primeros 2 caracteres y nivel 2 usa los primeros 4. Si luego identificamos una tabla oficial de zonas o áreas, este módulo se puede ajustar para usar nombres oficiales.</p>
</section>

<?php mapaTabla('Zonas / nivel 1', $data['zonas'] ?? [], [
    'codigo' => 'Código',
    'nombre' => 'Descripción',
    'dependencias' => 'Dependencias',
    'personal' => 'Personal',
]); ?>

<?php mapaTabla('Áreas / nivel 2', $data['areas'] ?? [], [
    'codigo' => 'Código',
    'nombre' => 'Descripción',
    'dependencias' => 'Dependencias',
    'personal' => 'Personal',
]); ?>

<?php mapaTabla('Dependencias con más personal', $data['dependencias'] ?? [], [
    'codigo' => 'Código',
    'nombre' => 'Dependencia',
    'personal' => 'Personal',
    'activos' => 'Activos',
]); ?>

<div class="grid-2">
    <?php mapaTabla('Personal por estado', $data['estadosPersonal'] ?? [], [
        'codigo' => 'Código',
        'nombre' => 'Estado',
        'total' => 'Total',
    ]); ?>

    <?php mapaTabla('Personal por sexo', $data['sexo'] ?? [], [
        'codigo' => 'Código',
        'nombre' => 'Sexo',
        'total' => 'Total',
    ]); ?>
</div>

<div class="grid-2">
    <?php mapaTabla('Personal por rango', $data['rangos'] ?? [], [
        'codigo' => 'Código',
        'nombre' => 'Rango',
        'total' => 'Total',
    ]); ?>

    <?php mapaTabla('Personal por tipo de policía', $data['tipoPolicia'] ?? [], [
        'codigo' => 'Código',
        'nombre' => 'Tipo',
        'total' => 'Total',
    ]); ?>
</div>

<?php mapaTabla('Acciones por tipo', $data['accionesTipo'] ?? [], [
    'codigo' => 'Tipo',
    'nombre' => 'Acción',
    'total' => 'Total',
    'fecha_minima' => 'Fecha mínima',
    'fecha_maxima' => 'Fecha máxima',
]); ?>

<div class="grid-2">
    <?php mapaTabla('Acciones por estado de revisión', $data['accionesEstadoRevision'] ?? [], [
        'codigo' => 'Código',
        'nombre' => 'Estado',
        'total' => 'Total',
    ]); ?>

    <?php mapaTabla('Acciones por año', $data['accionesAnio'] ?? [], [
        'codigo' => 'Año',
        'nombre' => 'Descripción',
        'total' => 'Total',
    ]); ?>
</div>

<?php mapaTabla('Catálogo de estados de personal', $data['catalogoEstados'] ?? [], [
    'codigo' => 'Código',
    'nombre' => 'Estado',
    'personal' => 'Personal asociado',
]); ?>
