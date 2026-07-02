<?php
/** @var string $viewPath */
/** @var string $title */
$cssPath = BASE_PATH . '/public/assets/app.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$printedAt = date('d/m/Y h:i A');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($title ?? 'SRHN Reportes PHP') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= e(url('/assets/app.css') . '?v=' . $cssVersion) ?>" rel="stylesheet">
</head>
<body>
    <header class="topbar">
        <div>
            <h1>SRHN Reportes PHP</h1>
            <p>Reconstrucción del módulo de reportes generales</p>
        </div>
        <nav>
            <a href="<?= e(url('/reportes')) ?>">Reportes</a>
        </nav>
    </header>

    <div class="print-header">
        <div class="print-brand">POLICÍA NACIONAL</div>
        <div class="print-subtitle">Dirección Nacional de Telemática / Recursos Humanos</div>
        <div class="print-report-title"><?= e($title ?? 'Reporte') ?></div>
        <div class="print-meta">Generado: <?= e($printedAt) ?> | Sistema SRHN Reportes PHP</div>
    </div>

    <main class="container">
        <?php require $viewPath; ?>
    </main>

    <div class="print-footer">
        <span>Documento generado por el sistema SRHN Reportes PHP.</span>
        <span>Uso interno institucional.</span>
    </div>

    <footer class="footer">
        <span>Dirección Nacional de Telemática / Recursos Humanos</span>
    </footer>
</body>
</html>
