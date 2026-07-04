<?php
/** @var array $rangos */
/** @var array $cuarteles */
/** @var array $estados */
/** @var array $filtros */
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

        <a class="report-card" href="<?= e(url('/reportes/estudios-generales')) ?>">
            <span class="module-status">DLL</span>
            <strong>Estudios Generales</strong>
            <small>Equivalente a Estudios Realizados: consulta estudios por funcionario, carrera, institución y fechas.</small>
        </a>

        <a class="report-card" href="<?= e(url('/reportes/estado-fuerza')) ?>">
            <span class="module-status">DLL</span>
            <strong>Estado de Fuerza</strong>
            <small>Equivalente a Estadísticas: resume personal por rango, dependencia, sexo y estatus.</small>
        </a>

        <a class="report-card" href="<?= e(url('/reportes/operativos')) ?>">
            <span class="module-status">DLL</span>
            <strong>OPERATIVOS</strong>
            <small>Resumen operativo por rango, dependencia, sexo, estatus, tipo de policía y listado exportable.</small>
        </a>

        <a class="report-card" href="<?= e(url('/reportes/estadisticas-acciones')) ?>">
            <span class="module-status">DLL</span>
            <strong>Estadísticas de acciones</strong>
            <small>Equivalente a EST.ACC.: desglose mensual, anual, sanciones y rango por mes.</small>
        </a>

        <a class="report-card" href="<?= e(url('/reportes/opciones-multiples')) ?>">
            <span class="module-status">DLL</span>
            <strong>Reporte Opciones Múltiples</strong>
            <small>Equivalente a Reportes Varios: filtros por rango, ubicación, sexo, estatus, tipo de policía y campos opcionales.</small>
        </a>

        <a class="report-card" href="<?= e(url('/reportes/hoja-vida')) ?>">
            <span class="module-status">DLL</span>
            <strong>Hoja de Vida para la placa</strong>
            <small>Consulta imprimible del funcionario con datos generales, acciones y secciones complementarias.</small>
        </a>
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

    <?php if ($modulo === 'acciones'): ?>
        <div class="alert alert-info">
            Este reporte cubre la Lista de Acciones completa del DLL: ascensos, traslados, vacaciones, licencias, sanciones, incapacidades, nombramientos y novedades.
        </div>
    <?php endif; ?>

    <?php if ($modulo === 'consulta'): ?>
        <div class="alert alert-info">
            Para consulta individual, usa el campo Buscar con cédula, posición, nombre o apellido.
        </div>
    <?php endif; ?>

    <?php if ($modulo === 'rango'): ?>
        <div class="alert alert-info">
            Para el reporte por rango, selecciona Rango desde, Rango hasta o ambos. Si solo llenas uno, el sistema filtra desde o hasta ese rango.
        </div>
    <?php endif; ?>

    <?php if ($modulo === 'dependencia'): ?>
        <div class="alert alert-info">
            Para el reporte por dependencia, selecciona Dependencia desde, Dependencia hasta o ambas. Si solo llenas una, el sistema filtra desde o hasta esa dependencia.
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
            <a href="<?= e(url($moduloActual['ruta'])) ?>" class="button-secondary">Limpiar</a>
        </div>
    </form>
</section>

<section class="card muted">
    <h3>Mapa de reconstrucción del DLL</h3>
    <p>
        Este menú deja organizada la migración por módulos: primero reportes generales, luego rangos y dependencias, y después acciones de personal y consulta individual.
    </p>
</section>
