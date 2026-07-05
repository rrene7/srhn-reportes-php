<?php
/**
 * Wrapper visual para mostrar etiquetas claras de clasificación sin duplicar la vista principal.
 * La lógica y los filtros siguen en opciones_multiples.php.
 */

ob_start();
require __DIR__ . '/opciones_multiples.php';
$html = (string) ob_get_clean();

$html = str_replace('>OO</option>', '>OO - Operativo</option>', $html);
$html = str_replace('>NO</option>', '>NO - No operativo</option>', $html);
$html = str_replace('>OA</option>', '>OA - Operativo administrativo</option>', $html);
$html = str_replace('Tipo de policía', 'Clasificación operativa', $html);

echo $html;
