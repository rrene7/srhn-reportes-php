<?php
/** @var array $rangos */
/** @var array $cuarteles */
/** @var array $estados */
/** @var array $filtros */
/** @var ?string $error */
?>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Reportes generales de personal</h2>
            <p>Filtros reconstruidos desde el módulo original de reportes.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/reportes/resultado')) ?>" class="filters">
        <div class="field">
            <label for="rango_desde">Rango desde</label>
            <select name="rango_desde" id="rango_desde">
                <option value="">Todos</option>
                <?php foreach ($rangos as $rango): ?>
                    <option value="<?= e($rango['codigo']) ?>" <?= (($filtros['rango_desde'] ?? '') == $rango['codigo']) ? 'selected' : '' ?>>
                        <?= e($rango['codigo']) ?> - <?= e($rango['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="rango_hasta">Rango hasta</label>
            <select name="rango_hasta" id="rango_hasta">
                <option value="">Todos</option>
                <?php foreach ($rangos as $rango): ?>
                    <option value="<?= e($rango['codigo']) ?>" <?= (($filtros['rango_hasta'] ?? '') == $rango['codigo']) ? 'selected' : '' ?>>
                        <?= e($rango['codigo']) ?> - <?= e($rango['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="cuartel_desde">Dependencia desde</label>
            <select name="cuartel_desde" id="cuartel_desde">
                <option value="">Todas</option>
                <?php foreach ($cuarteles as $cuartel): ?>
                    <option value="<?= e($cuartel['codigo']) ?>" <?= (($filtros['cuartel_desde'] ?? '') == $cuartel['codigo']) ? 'selected' : '' ?>>
                        <?= e($cuartel['codigo']) ?> - <?= e($cuartel['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="cuartel_hasta">Dependencia hasta</label>
            <select name="cuartel_hasta" id="cuartel_hasta">
                <option value="">Todas</option>
                <?php foreach ($cuarteles as $cuartel): ?>
                    <option value="<?= e($cuartel['codigo']) ?>" <?= (($filtros['cuartel_hasta'] ?? '') == $cuartel['codigo']) ? 'selected' : '' ?>>
                        <?= e($cuartel['codigo']) ?> - <?= e($cuartel['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="estado">Estado</label>
            <select name="estado" id="estado">
                <option value="">Todos permitidos</option>
                <?php foreach ($estados as $estado): ?>
                    <option value="<?= e($estado['codigo']) ?>" <?= (($filtros['estado'] ?? '') == $estado['codigo']) ? 'selected' : '' ?>>
                        <?= e($estado['codigo']) ?> - <?= e($estado['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="buscar">Buscar</label>
            <input type="text" name="buscar" id="buscar" value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Cédula, posición, nombre o apellido">
        </div>

        <div class="actions">
            <button type="submit">Generar reporte</button>
            <a href="<?= e(url('/reportes')) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<section class="card muted">
    <h3>Nota técnica</h3>
    <p>
        Si algún catálogo no carga, puede ser porque el nombre de columna real sea diferente.
        En ese caso se ajusta el modelo <code>ReportePersonalModel.php</code> según la estructura real de la base.
    </p>
</section>
