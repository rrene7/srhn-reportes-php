<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;
use Throwable;

final class EstudiosGeneralesModel
{
    private const TABLAS_ESTUDIOS = [
        'employee_studies',
        'employee_education',
        'studies',
        'educations',
        'estudios',
        'ESTUDIOS',
    ];

    public function __construct(
        private PDO $db
    ) {}

    public function metadata(): array
    {
        $tabla = $this->detectarTabla();

        return [
            'tabla' => $tabla,
            'columnas' => $tabla !== null ? $this->columnas($tabla) : [],
        ];
    }

    public function buscar(array $filtros, int $limit = 500): array
    {
        $tabla = $this->detectarTabla();
        if ($tabla === null) {
            return [];
        }

        $columnas = $this->columnas($tabla);
        if ($columnas === []) {
            return [];
        }

        [$join, $employeeSelect] = $this->employeeJoin($columnas);
        [$where, $params] = $this->where($columnas, $filtros);
        $selectMap = $this->selectMap($columnas);
        $order = $this->orderSql($columnas);

        $sql = "
            SELECT
                {$employeeSelect['nemp']} AS nemp,
                {$employeeSelect['cedula']} AS cedula,
                {$employeeSelect['funcionario']} AS funcionario,
                {$employeeSelect['rango']} AS rango_actual,
                {$employeeSelect['unidad']} AS dependencia_actual,
                {$selectMap['estudio']} AS estudio,
                {$selectMap['nivel']} AS nivel,
                {$selectMap['institucion']} AS institucion,
                {$selectMap['fecha']} AS fecha_estudio,
                {$selectMap['estado']} AS estado_estudio,
                {$selectMap['observacion']} AS observacion
            FROM {$this->id($tabla)} t
            {$join}
            {$where}
            {$order}
            LIMIT :limit
        ";

        $params[':limit'] = ['value' => max(1, min($limit, 2000)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contar(array $filtros): int
    {
        $tabla = $this->detectarTabla();
        if ($tabla === null) {
            return 0;
        }

        $columnas = $this->columnas($tabla);
        if ($columnas === []) {
            return 0;
        }

        [$join] = $this->employeeJoin($columnas);
        [$where, $params] = $this->where($columnas, $filtros);

        $sql = "
            SELECT COUNT(*)
            FROM {$this->id($tabla)} t
            {$join}
            {$where}
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function resumen(array $rows): array
    {
        $resumen = [
            'total' => count($rows),
            'por_nivel' => [],
            'por_estado' => [],
        ];

        foreach ($rows as $row) {
            $nivel = trim((string) ($row['nivel'] ?? ''));
            $estado = trim((string) ($row['estado_estudio'] ?? ''));

            $nivel = $nivel !== '' ? $nivel : 'Sin nivel';
            $estado = $estado !== '' ? $estado : 'Sin estado';

            $resumen['por_nivel'][$nivel] = ($resumen['por_nivel'][$nivel] ?? 0) + 1;
            $resumen['por_estado'][$estado] = ($resumen['por_estado'][$estado] ?? 0) + 1;
        }

        arsort($resumen['por_nivel']);
        arsort($resumen['por_estado']);

        return $resumen;
    }

    private function where(array $columnas, array $filtros): array
    {
        $where = [];
        $params = [];

        $deletedAt = $this->primeraColumna($columnas, ['deleted_at']);
        if ($deletedAt !== null) {
            $where[] = 't.' . $this->id($deletedAt) . ' IS NULL';
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $partes = [];
            $like = $buscar . '%';
            $contains = '%' . $buscar . '%';

            $partes[] = 'e.document_number LIKE :buscar_cedula';
            $partes[] = 'CAST(e.legacy_position AS CHAR) LIKE :buscar_posicion';
            $partes[] = 'e.first_name LIKE :buscar_nombre';
            $partes[] = 'e.last_name LIKE :buscar_apellido';

            $params[':buscar_cedula'] = $like;
            $params[':buscar_posicion'] = $like;
            $params[':buscar_nombre'] = $like;
            $params[':buscar_apellido'] = $like;

            foreach (['cedula', 'document_number', 'nombre', 'apellido', 'name', 'description', 'descripcion', 'titulo', 'title', 'estudio', 'carrera'] as $candidate) {
                $col = $this->columnaReal($columnas, $candidate);
                if ($col === null) {
                    continue;
                }

                $param = ':buscar_t_' . count($params);
                $partes[] = 't.' . $this->id($col) . ' LIKE ' . $param;
                $params[$param] = $contains;
            }

            $where[] = '(' . implode(' OR ', $partes) . ')';
        }

        $estudio = trim((string) ($filtros['estudio'] ?? ''));
        if ($estudio !== '') {
            $cols = $this->columnasExistentes($columnas, ['estudio', 'study', 'titulo', 'title', 'degree', 'carrera', 'descripcion', 'description']);
            if ($cols !== []) {
                $partes = [];
                foreach ($cols as $col) {
                    $param = ':estudio_' . count($params);
                    $partes[] = 't.' . $this->id($col) . ' LIKE ' . $param;
                    $params[$param] = '%' . $estudio . '%';
                }
                $where[] = '(' . implode(' OR ', $partes) . ')';
            }
        }

        $institucion = trim((string) ($filtros['institucion'] ?? ''));
        if ($institucion !== '') {
            $cols = $this->columnasExistentes($columnas, ['institucion', 'institution', 'universidad', 'university', 'school', 'centro', 'plantel']);
            if ($cols !== []) {
                $partes = [];
                foreach ($cols as $col) {
                    $param = ':institucion_' . count($params);
                    $partes[] = 't.' . $this->id($col) . ' LIKE ' . $param;
                    $params[$param] = '%' . $institucion . '%';
                }
                $where[] = '(' . implode(' OR ', $partes) . ')';
            }
        }

        $fechaCol = $this->primeraColumna($columnas, ['fecha', 'date', 'study_date', 'graduation_date', 'fecha_graduacion', 'fecha_inicio', 'start_date', 'created_at']);
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaCol !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $where[] = 't.' . $this->id($fechaCol) . ' >= :fecha_desde';
            $params[':fecha_desde'] = $fechaDesde;
        }
        if ($fechaCol !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $where[] = 't.' . $this->id($fechaCol) . ' <= :fecha_hasta';
            $params[':fecha_hasta'] = $fechaHasta;
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }

    private function employeeJoin(array $columnas): array
    {
        $employeeCol = $this->primeraColumna($columnas, ['employee_id', 'funcionario_id', 'employeeid', 'id_employee', 'person_id', 'personnel_id']);
        $cedulaCol = $this->primeraColumna($columnas, ['cedula', 'document_number']);
        $posicionCol = $this->primeraColumna($columnas, ['nemp', 'posicion', 'posicipn', 'legacy_position', 'employee_number', 'position_number']);

        $joinCondition = '1 = 0';
        if ($employeeCol !== null) {
            $joinCondition = 'e.id = t.' . $this->id($employeeCol);
        } elseif ($cedulaCol !== null) {
            $joinCondition = 'e.document_number = t.' . $this->id($cedulaCol);
        } elseif ($posicionCol !== null) {
            $joinCondition = 'e.legacy_position = t.' . $this->id($posicionCol);
        }

        $join = "
            LEFT JOIN employees e ON {$joinCondition}
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
        ";

        $select = [
            'nemp' => 'COALESCE(CAST(e.legacy_position AS CHAR), ' . $this->fallback($columnas, ['nemp', 'posicion', 'posicipn', 'legacy_position', 'employee_number', 'position_number']) . ')',
            'cedula' => 'COALESCE(e.document_number, ' . $this->fallback($columnas, ['cedula', 'document_number']) . ')',
            'funcionario' => "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), " . $this->fallback($columnas, ['funcionario', 'nombre_completo', 'nombre', 'name']) . ')',
            'rango' => "COALESCE(r.name, e.legacy_rank_name, '')",
            'unidad' => "COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '')",
        ];

        return [$join, $select];
    }

    private function selectMap(array $columnas): array
    {
        return [
            'estudio' => $this->fallback($columnas, ['estudio', 'study', 'titulo', 'title', 'degree', 'carrera', 'descripcion', 'description']),
            'nivel' => $this->fallback($columnas, ['nivel', 'level', 'tipo', 'type', 'grado', 'degree_type']),
            'institucion' => $this->fallback($columnas, ['institucion', 'institution', 'universidad', 'university', 'school', 'centro', 'plantel']),
            'fecha' => $this->fallback($columnas, ['fecha', 'date', 'study_date', 'graduation_date', 'fecha_graduacion', 'fecha_inicio', 'start_date', 'created_at']),
            'estado' => $this->fallback($columnas, ['estado', 'status', 'state', 'condicion', 'condition']),
            'observacion' => $this->fallback($columnas, ['observacion', 'observaciones', 'notes', 'nota', 'comentario', 'comments']),
        ];
    }

    private function fallback(array $columnas, array $candidatas): string
    {
        $parts = [];
        foreach ($candidatas as $candidate) {
            $col = $this->columnaReal($columnas, $candidate);
            if ($col !== null) {
                $parts[] = 't.' . $this->id($col);
            }
        }

        if ($parts === []) {
            return "''";
        }

        return 'COALESCE(' . implode(', ', $parts) . ", '')";
    }

    private function orderSql(array $columnas): string
    {
        $fechaCol = $this->primeraColumna($columnas, ['fecha', 'date', 'study_date', 'graduation_date', 'fecha_graduacion', 'fecha_inicio', 'start_date', 'created_at']);
        if ($fechaCol !== null) {
            return 'ORDER BY t.' . $this->id($fechaCol) . ' DESC, e.legacy_position ASC';
        }

        return 'ORDER BY e.legacy_position ASC';
    }

    private function detectarTabla(): ?string
    {
        foreach (self::TABLAS_ESTUDIOS as $tabla) {
            if ($this->tablaExiste($tabla)) {
                return $tabla;
            }
        }

        return null;
    }

    private function tablaExiste(string $table): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
            $stmt->execute([':table' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function columnas(string $tabla): array
    {
        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM ' . $this->id($tabla));
            return array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $stmt->fetchAll())));
        } catch (Throwable) {
            return [];
        }
    }

    private function primeraColumna(array $columnas, array $candidatas): ?string
    {
        foreach ($candidatas as $candidate) {
            $col = $this->columnaReal($columnas, $candidate);
            if ($col !== null) {
                return $col;
            }
        }

        return null;
    }

    private function columnasExistentes(array $columnas, array $candidatas): array
    {
        $resultado = [];
        foreach ($candidatas as $candidate) {
            $col = $this->columnaReal($columnas, $candidate);
            if ($col !== null && !in_array($col, $resultado, true)) {
                $resultado[] = $col;
            }
        }

        return $resultado;
    }

    private function columnaReal(array $columnas, string $candidate): ?string
    {
        $needle = strtolower($candidate);
        foreach ($columnas as $columna) {
            if (strtolower($columna) === $needle) {
                return $columna;
            }
        }

        return null;
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $stmt->bindValue($key, $value['value'], $value['type']);
                continue;
            }

            $stmt->bindValue($key, $value);
        }
    }

    private function id(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
