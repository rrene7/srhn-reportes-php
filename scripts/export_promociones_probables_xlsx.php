<?php

declare(strict_types=1);

use App\Support\Database;
use App\Support\Env;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Exporta promociones probables a un solo libro Excel.
 *
 * Regla institucional reconstruida:
 * - Acción de Nombramiento.
 * - Nombrado como AGENTE.
 * - Misma fecha de nombramiento.
 * - Misma OGD o resolución.
 * - Grupo mínimo configurable, por defecto 40 funcionarios.
 * - La primera promoción secuencial se toma como 20AVA PROM desde 1997-03-01.
 *
 * Uso:
 * php scripts/export_promociones_probables_xlsx.php --start-promotion=20 --start-date=1997-03-01 --min-members=40
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');

$options = parseOptions($argv);
$startPromotion = (int) ($options['start-promotion'] ?? 20);
$startDate = (string) ($options['start-date'] ?? '1997-03-01');
$minMembers = (int) ($options['min-members'] ?? 40);
$output = (string) ($options['output'] ?? BASE_PATH . '/storage/exports/promociones_probables.xlsx');

if ($startPromotion < 1) {
    throw new RuntimeException('El número inicial de promoción debe ser mayor a cero.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    throw new RuntimeException('La fecha inicial debe tener formato YYYY-MM-DD.');
}

if ($minMembers < 1) {
    throw new RuntimeException('El mínimo de integrantes debe ser mayor a cero.');
}

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('La extensión PHP zip/ZipArchive no está habilitada. Active extension=zip en php.ini.');
}

$db = Database::connect();
$groups = findPromotionGroups($db, $startDate, $minMembers);

if ($groups === []) {
    throw new RuntimeException('No se encontraron promociones probables con los filtros indicados.');
}

$workbookSheets = [];
$indexRows = [];
$promotionNumber = $startPromotion;

foreach ($groups as $group) {
    $sheetName = promotionSheetName($promotionNumber);
    $members = findMembersForGroup($db, $group);

    $workbookSheets[] = [
        'name' => $sheetName,
        'promotion_number' => $promotionNumber,
        'group' => $group,
        'members' => $members,
    ];

    $indexRows[] = [
        $promotionNumber,
        $sheetName,
        $group['action_date'],
        $group['ogd_key'],
        $group['resolution_key'],
        $group['resolution_date'] ?? '',
        $group['rank_name'] ?: $group['legacy_rank_code'],
        count($members),
    ];

    $promotionNumber++;
}

array_unshift($workbookSheets, [
    'name' => 'INDICE',
    'promotion_number' => null,
    'group' => null,
    'members' => $indexRows,
]);

writeWorkbook($workbookSheets, $output, $startPromotion, $startDate, $minMembers);

echo 'Excel generado: ' . $output . PHP_EOL;
echo 'Promociones exportadas: ' . (count($workbookSheets) - 1) . PHP_EOL;

function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        if (!str_contains($arg, '=')) {
            $options[$arg] = true;
            continue;
        }

        [$key, $value] = explode('=', $arg, 2);
        $options[$key] = $value;
    }

    return $options;
}

function findPromotionGroups(PDO $db, string $startDate, int $minMembers): array
{
    $sql = "
        SELECT
            a.action_date,
            COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') AS ogd_key,
            COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') AS resolution_key,
            COALESCE(a.resolution_date, '') AS resolution_date,
            COALESCE(a.target_rank_id, 0) AS target_rank_key,
            COALESCE(tr.name, '') AS rank_name,
            COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), 'SIN CODIGO') AS legacy_rank_code,
            COUNT(DISTINCT a.employee_id) AS total_members
        FROM employee_actions a
        LEFT JOIN action_types at ON at.id = a.action_type_id
        LEFT JOIN ranks tr ON tr.id = a.target_rank_id
        WHERE
            LOWER(COALESCE(at.name, '')) LIKE '%nombr%'
            AND a.action_date >= :start_date
            AND (
                UPPER(COALESCE(tr.name, '')) LIKE '%AGENTE%'
                OR TRIM(COALESCE(a.legacy_rank_or_charge_code, '')) IN ('130', '0130')
            )
            AND (
                COALESCE(NULLIF(TRIM(a.ogd_number), ''), '0') <> '0'
                OR COALESCE(NULLIF(TRIM(a.resolution_number), ''), '') <> ''
            )
        GROUP BY
            a.action_date,
            ogd_key,
            resolution_key,
            resolution_date,
            target_rank_key,
            rank_name,
            legacy_rank_code
        HAVING COUNT(DISTINCT a.employee_id) >= :min_members
        ORDER BY
            a.action_date ASC,
            CAST(COALESCE(NULLIF(TRIM(a.ogd_number), ''), '0') AS UNSIGNED) ASC,
            ogd_key ASC,
            resolution_key ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':start_date', $startDate);
    $stmt->bindValue(':min_members', $minMembers, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function findMembersForGroup(PDO $db, array $group): array
{
    $sql = "
        SELECT
            e.legacy_position AS posicion,
            e.document_number AS cedula,
            UPPER(COALESCE(e.last_name, '')) AS apellidos,
            UPPER(COALESCE(e.first_name, '')) AS nombres,
            COALESCE(r.name, e.legacy_rank_name, '') AS rango_actual,
            COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS dependencia_actual,
            COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_actual,
            a.action_date AS fecha_nombramiento,
            COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') AS ogd,
            COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') AS resolucion_decreto,
            COALESCE(a.resolution_date, '') AS fecha_resolucion,
            COALESCE(tr.name, '') AS rango_nombramiento,
            COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), '') AS codigo_rango_legacy
        FROM employee_actions a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN action_types at ON at.id = a.action_type_id
        LEFT JOIN ranks tr ON tr.id = a.target_rank_id
        LEFT JOIN ranks r ON r.id = e.rank_id
        LEFT JOIN units u ON u.id = e.unit_id
        LEFT JOIN statuses s ON s.id = e.status_id
        WHERE
            LOWER(COALESCE(at.name, '')) LIKE '%nombr%'
            AND a.action_date = :action_date
            AND COALESCE(NULLIF(TRIM(a.ogd_number), ''), 'SIN OGD') = :ogd_key
            AND COALESCE(NULLIF(TRIM(a.resolution_number), ''), 'SIN RESOLUCION') = :resolution_key
            AND COALESCE(a.target_rank_id, 0) = :target_rank_key
            AND COALESCE(NULLIF(TRIM(a.legacy_rank_or_charge_code), ''), 'SIN CODIGO') = :legacy_rank_code
            AND (
                UPPER(COALESCE(tr.name, '')) LIKE '%AGENTE%'
                OR TRIM(COALESCE(a.legacy_rank_or_charge_code, '')) IN ('130', '0130')
            )
        GROUP BY
            e.id,
            e.legacy_position,
            e.document_number,
            e.last_name,
            e.first_name,
            rango_actual,
            dependencia_actual,
            estado_actual,
            a.action_date,
            ogd,
            resolucion_decreto,
            fecha_resolucion,
            rango_nombramiento,
            codigo_rango_legacy
        ORDER BY CAST(COALESCE(e.legacy_position, 0) AS UNSIGNED) ASC, apellidos ASC, nombres ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':action_date', $group['action_date']);
    $stmt->bindValue(':ogd_key', $group['ogd_key']);
    $stmt->bindValue(':resolution_key', $group['resolution_key']);
    $stmt->bindValue(':target_rank_key', (int) $group['target_rank_key'], PDO::PARAM_INT);
    $stmt->bindValue(':legacy_rank_code', $group['legacy_rank_code']);
    $stmt->execute();

    return $stmt->fetchAll();
}

function promotionSheetName(int $number): string
{
    return $number . 'AVA PROM';
}

function writeWorkbook(array $sheets, string $output, int $startPromotion, string $startDate, int $minMembers): void
{
    $outputDir = dirname($output);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        throw new RuntimeException('No se pudo crear el directorio de salida: ' . $outputDir);
    }

    $tmpDir = sys_get_temp_dir() . '/srhn_promociones_' . bin2hex(random_bytes(6));
    mkdir($tmpDir, 0777, true);
    mkdir($tmpDir . '/_rels', 0777, true);
    mkdir($tmpDir . '/docProps', 0777, true);
    mkdir($tmpDir . '/xl/_rels', 0777, true);
    mkdir($tmpDir . '/xl/worksheets', 0777, true);

    file_put_contents($tmpDir . '/[Content_Types].xml', contentTypesXml(count($sheets)));
    file_put_contents($tmpDir . '/_rels/.rels', rootRelsXml());
    file_put_contents($tmpDir . '/docProps/core.xml', coreXml());
    file_put_contents($tmpDir . '/docProps/app.xml', appXml($sheets));
    file_put_contents($tmpDir . '/xl/workbook.xml', workbookXml($sheets));
    file_put_contents($tmpDir . '/xl/_rels/workbook.xml.rels', workbookRelsXml($sheets));
    file_put_contents($tmpDir . '/xl/styles.xml', stylesXml());

    foreach ($sheets as $index => $sheet) {
        $xml = $sheet['name'] === 'INDICE'
            ? indexSheetXml($sheet['members'], $startPromotion, $startDate, $minMembers)
            : promotionSheetXml($sheet);

        file_put_contents($tmpDir . '/xl/worksheets/sheet' . ($index + 1) . '.xml', $xml);
    }

    if (is_file($output)) {
        unlink($output);
    }

    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE) !== true) {
        removeDirectory($tmpDir);
        throw new RuntimeException('No se pudo crear el archivo Excel: ' . $output);
    }

    addDirectoryToZip($zip, $tmpDir, $tmpDir);
    $zip->close();

    removeDirectory($tmpDir);
}

function indexSheetXml(array $rows, int $startPromotion, string $startDate, int $minMembers): string
{
    $matrix = [];
    $matrix[] = ['PROMOCIONES PROBABLES POR NOMBRAMIENTO COMO AGENTE'];
    $matrix[] = ['Inicio secuencial', $startPromotion, 'Fecha base', $startDate, 'Mínimo integrantes', $minMembers];
    $matrix[] = [];
    $matrix[] = ['Promoción', 'Hoja', 'Fecha nombramiento', 'OGD', 'Resolución/Decreto', 'Fecha resolución', 'Rango nombramiento', 'Total'];

    foreach ($rows as $row) {
        $matrix[] = $row;
    }

    return worksheetXml($matrix, 'A4:H' . max(4, count($matrix)), [
        1 => 42,
        2 => 16,
        3 => 18,
        4 => 12,
        5 => 22,
        6 => 18,
        7 => 24,
        8 => 10,
    ], 4, 'A1:H1');
}

function promotionSheetXml(array $sheet): string
{
    $group = $sheet['group'];
    $members = $sheet['members'];
    $sheetName = $sheet['name'];

    $matrix = [];
    $matrix[] = [
        $sheetName . ' - NOMBRAMIENTO ' . $group['action_date'] . ' - OGD ' . $group['ogd_key'] . ' - ' . count($members) . ' AGENTES'
    ];
    $matrix[] = [
        'Fecha nombramiento', $group['action_date'],
        'OGD', $group['ogd_key'],
        'Resolución/Decreto', $group['resolution_key'],
        'Rango', $group['rank_name'] ?: $group['legacy_rank_code'],
    ];
    $matrix[] = [];
    $matrix[] = [
        '#',
        'Posición',
        'Cédula',
        'Apellidos',
        'Nombres',
        'Rango actual',
        'Dependencia actual',
        'Estado actual',
        'Fecha nombramiento',
        'OGD',
        'Resolución/Decreto',
    ];

    $counter = 1;
    foreach ($members as $member) {
        $matrix[] = [
            $counter,
            $member['posicion'] ?? '',
            $member['cedula'] ?? '',
            $member['apellidos'] ?? '',
            $member['nombres'] ?? '',
            $member['rango_actual'] ?? '',
            $member['dependencia_actual'] ?? '',
            $member['estado_actual'] ?? '',
            $member['fecha_nombramiento'] ?? '',
            $member['ogd'] ?? '',
            $member['resolucion_decreto'] ?? '',
        ];
        $counter++;
    }

    return worksheetXml($matrix, 'A4:K' . max(4, count($matrix)), [
        1 => 6,
        2 => 12,
        3 => 15,
        4 => 28,
        5 => 28,
        6 => 22,
        7 => 34,
        8 => 20,
        9 => 18,
        10 => 12,
        11 => 22,
    ], 4, 'A1:K1');
}

function worksheetXml(array $matrix, string $autoFilterRef, array $widths, int $freezeRow, string $mergeRef): string
{
    $rowsXml = '';
    foreach ($matrix as $rowIndex => $row) {
        $r = $rowIndex + 1;
        $height = $r === 1 ? 24 : 18;
        $rowsXml .= '<row r="' . $r . '" ht="' . $height . '" customHeight="1">';
        foreach ($row as $colIndex => $value) {
            $style = 0;
            if ($r === 1) {
                $style = 1;
            } elseif ($r === 4) {
                $style = 2;
            }
            $rowsXml .= cellXml($r, $colIndex + 1, $value, $style);
        }
        $rowsXml .= '</row>';
    }

    $colsXml = '<cols>';
    foreach ($widths as $col => $width) {
        $colsXml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $freezeRow . '" topLeftCell="A' . ($freezeRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . $colsXml
        . '<sheetData>' . $rowsXml . '</sheetData>'
        . '<autoFilter ref="' . xml($autoFilterRef) . '"/>'
        . '<mergeCells count="1"><mergeCell ref="' . xml($mergeRef) . '"/></mergeCells>'
        . '</worksheet>';
}

function cellXml(int $row, int $col, mixed $value, int $style = 0): string
{
    $cellRef = columnName($col) . $row;

    if ($value === null) {
        $value = '';
    }

    if (is_int($value) || is_float($value)) {
        return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
    }

    return '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t>' . xml((string) $value) . '</t></is></c>';
}

function columnName(int $number): string
{
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }

    return $name;
}

function contentTypesXml(int $sheetCount): string
{
    $overrides = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . $overrides
        . '</Types>';
}

function rootRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function coreXml(): string
{
    $now = gmdate('Y-m-d\TH:i:s\Z');

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:creator>SRHN Reportes PHP</dc:creator>'
        . '<cp:lastModifiedBy>SRHN Reportes PHP</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function appXml(array $sheets): string
{
    $titles = '';
    foreach ($sheets as $sheet) {
        $titles .= '<vt:lpstr>' . xml($sheet['name']) . '</vt:lpstr>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Microsoft Excel</Application>'
        . '<DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
        . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($sheets) . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
        . '<TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts>'
        . '<Company>Policía Nacional</Company>'
        . '</Properties>';
}

function workbookXml(array $sheets): string
{
    $sheetXml = '';
    foreach ($sheets as $index => $sheet) {
        $sheetXml .= '<sheet name="' . xml(safeSheetName($sheet['name'])) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView activeTab="0"/></bookViews>'
        . '<sheets>' . $sheetXml . '</sheets>'
        . '</workbook>';
}

function workbookRelsXml(array $sheets): string
{
    $rels = '';
    foreach ($sheets as $index => $_sheet) {
        $rels .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
    }

    $styleRid = count($sheets) + 1;

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $rels
        . '<Relationship Id="rId' . $styleRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF9BBB59"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right><top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function safeSheetName(string $name): string
{
    $name = preg_replace('/[\\\/\?\*\[\]\:]/', ' ', $name) ?? $name;
    $name = trim($name);
    if ($name === '') {
        $name = 'Hoja';
    }

    return mb_substr($name, 0, 31);
}

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function addDirectoryToZip(ZipArchive $zip, string $dir, string $baseDir): void
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            addDirectoryToZip($zip, $path, $baseDir);
            continue;
        }

        $localName = str_replace('\\', '/', substr($path, strlen($baseDir) + 1));
        $zip->addFile($path, $localName);
    }
}

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}
