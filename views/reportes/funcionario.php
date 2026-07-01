<?php
/** @var ?array $funcionario */
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

<section class="card muted no-print">
    <h3>Siguiente fase</h3>
    <p>
        Esta ficha queda lista para ampliar con acciones, estudios, familia, direcciones, conducta, evaluación física y otros apartados identificados en el sistema legado.
    </p>
</section>
