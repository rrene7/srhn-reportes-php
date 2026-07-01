<?php
/** @var string $viewPath */
/** @var string $title */
$cssPath = BASE_PATH . '/public/assets/app.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
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

    <main class="container">
        <?php require $viewPath; ?>
    </main>

    <footer class="footer">
        <span>Dirección Nacional de Telemática / Recursos Humanos</span>
    </footer>
</body>
</html>
