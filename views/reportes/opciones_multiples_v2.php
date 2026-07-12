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
$totalSeleccionadoBase = 0;

$options = '<option value="todos"' . ($seleccionado === 'TODOS' || $seleccionado === '' ? ' selected' : '') . '>Todas las clasificaciones</option>';
foreach ($tipos as $tipo) {
    $codigo = strtoupper(trim((string) ($tipo['codigo'] ?? '')));
    if ($codigo === '') {
        continue;
    }

    $nombre = trim((string) ($tipo['nombre'] ?? ''));
    $totalTipo = (int) ($tipo['total'] ?? 0);
    if ($seleccionado === $codigo) {
        $totalSeleccionadoBase = $totalTipo;
    }

    $label = $codigo . ($nombre !== '' ? ' - ' . $nombre : '') . ' (' . number_format($totalTipo) . ')';
    $selected = $seleccionado === $codigo ? ' selected' : '';
    $options .= '<option value="' . e($codigo) . '"' . $selected . '>' . e($label) . '</option>';
}

$quickAction = '';
if ($seleccionado !== '' && $seleccionado !== 'TODOS') {
    $sinRango = array_merge($filtros, [
        'generar' => '1',
        'rango_inicial' => '',
        'rango_final' => '',
    ]);
    $quickAction = '<div class="toolbar quick-actions">'
        . '<a class="button-secondary" href="' . e(url('/reportes/opciones-multiples?' . http_build_query($sinRango))) . '">'
        . 'Ver ' . e($seleccionado) . ' en todos los rangos'
        . '</a></div>';
}

$selectReplacement = '<select name="tipo_policia" id="tipo_policia">'
    . $options
    . '</select>'
    . '<small>OO: Operativo · OA: Operativo administrativo · NO: No operativo. Fuente: employees.police_operativity_type.</small>'
    . $quickAction;

$html = preg_replace_callback(
    '/<select\s+name="tipo_policia"\s+id="tipo_policia">.*?<\/select>/s',
    static fn (): string => $selectReplacement,
    $html,
    1
) ?? $html;

$html = str_replace('tipo de policía', 'clasificación operativa', $html);
$html = str_replace('Tipo de policía', 'Clasificación operativa', $html);
$html = str_replace('Sin tipo', 'Sin clasificación', $html);

if ($seleccionado !== '' && $seleccionado !== 'TODOS' && (int) ($total ?? 0) === 0) {
    if ($totalSeleccionadoBase > 0) {
        $diagnostico = '<div class="alert alert-info">'
            . 'La base contiene <strong>' . e(number_format($totalSeleccionadoBase)) . '</strong> registros con clasificación '
            . '<strong>' . e($seleccionado) . '</strong>, pero ninguno coincide con los demás filtros seleccionados. '
            . 'Pruebe el botón “Ver ' . e($seleccionado) . ' en todos los rangos”.'
            . '</div>';
    } else {
        $diagnostico = '<div class="alert alert-info">'
            . 'Actualmente no existen registros con clasificación <strong>' . e($seleccionado) . '</strong> en '
            . '<code>employees.police_operativity_type</code>. La pantalla está activa, pero no hay datos para esa clasificación.'
            . '</div>';
    }

    $html = preg_replace_callback(
        '/(<form\s+method="get"\s+action="[^"]*"\s+class="filters no-print">)/',
        static fn (array $matches): string => $diagnostico . $matches[1],
        $html,
        1
    ) ?? $html;
}

echo $html;
