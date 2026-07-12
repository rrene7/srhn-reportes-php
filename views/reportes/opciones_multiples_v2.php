<?php
/**
 * Capa visual temporal para que Opciones Múltiples use la clasificación
 * real almacenada en employees.police_operativity_type.
 */

ob_start();
require __DIR__ . '/opciones_multiples.php';
$html = (string) ob_get_clean();

$seleccionado = strtoupper(trim((string) ($filtros['tipo_policia'] ?? 'todos')));
$tipos = $catalogos['tiposPolicia'] ?? [];

$options = '<option value="todos"' . ($seleccionado === 'TODOS' || $seleccionado === '' ? ' selected' : '') . '>Todas</option>';
foreach ($tipos as $tipo) {
    $codigo = strtoupper(trim((string) ($tipo['codigo'] ?? '')));
    if ($codigo === '') {
        continue;
    }

    $nombre = trim((string) ($tipo['nombre'] ?? ''));
    $total = (int) ($tipo['total'] ?? 0);
    $label = $codigo . ($nombre !== '' ? ' - ' . $nombre : '') . ' (' . number_format($total) . ')';
    $selected = $seleccionado === $codigo ? ' selected' : '';
    $options .= '<option value="' . e($codigo) . '"' . $selected . '>' . e($label) . '</option>';
}

$replacement = '<div class="field">'
    . '<label for="tipo_policia">Clasificación operativa</label>'
    . '<select name="tipo_policia" id="tipo_policia">'
    . $options
    . '</select>'
    . '<small>OO: Operativo · OA: Operativo administrativo · NO: No operativo. Fuente: employees.police_operativity_type.</small>'
    . '</div>';

$html = preg_replace('/<div class="field">\s*<label for="tipo_policia">.*?<\/select>\s*<\/div>/s', $replacement, $html, 1) ?? $html;
$html = str_replace('tipo de policía', 'clasificación operativa', $html);
$html = str_replace('Tipo de policía', 'Clasificación operativa', $html);

echo $html;
