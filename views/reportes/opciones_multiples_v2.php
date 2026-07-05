<?php
/**
 * Wrapper visual para que el filtro de tipo de policía use los valores reales existentes en employees.external_user_type.
 * La vista principal queda intacta y esta capa solo reemplaza el combo hardcodeado.
 */

ob_start();
require __DIR__ . '/opciones_multiples.php';
$html = (string) ob_get_clean();

$seleccionado = strtoupper(trim((string) ($filtros['tipo_policia'] ?? 'todos')));
$tipos = $catalogos['tiposPolicia'] ?? [];

$options = '<option value="todos"' . ($seleccionado === 'TODOS' || $seleccionado === '' ? ' selected' : '') . '>Todos</option>';
foreach ($tipos as $tipo) {
    $codigo = strtoupper(trim((string) ($tipo['codigo'] ?? '')));
    if ($codigo === '') {
        continue;
    }
    $total = (int) ($tipo['total'] ?? 0);
    $label = $codigo . ($total > 0 ? ' (' . number_format($total) . ')' : '');
    $selected = $seleccionado === $codigo ? ' selected' : '';
    $options .= '<option value="' . e($codigo) . '"' . $selected . '>' . e($label) . '</option>';
}

$replacement = '<div class="field">'
    . '<label for="tipo_policia">Tipo de policía</label>'
    . '<select name="tipo_policia" id="tipo_policia">'
    . $options
    . '</select>'
    . '<small>Valores reales leídos de employees.external_user_type.</small>'
    . '</div>';

$html = preg_replace('/<div class="field">\s*<label for="tipo_policia">.*?<\/select>\s*<\/div>/s', $replacement, $html, 1) ?? $html;

echo $html;
