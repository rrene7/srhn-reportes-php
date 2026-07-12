<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOStatement;

final class OpcionesMultiplesModel
{
    private const CAMPOS_OPCIONALES = [
        'cedula' => 'Cédula',
        'edad' => 'Edad',
        'fecha_ingreso' => 'F. Ingreso',
        'fecha_ascenso' => 'F. Ascenso',
        'fecha_nacimiento' => 'F. Nacimiento',
        'fecha_jubilacion' => 'F. Jubilación futura',
        'tipo_policia' => 'Clasificación operativa',
        'estado' => 'Estatus',
        'operatividad_motivo' => 'Motivo operatividad',
        'operatividad_referencia' => 'Referencia operatividad',
        'operatividad_fecha' => 'Fecha efectiva',
        'operatividad_notas' => 'Notas operatividad',
    ];

    private ?array $employeeColumns = null;

    public function __construct(private PDO $db) {}

    public function catalogos(): array
    {
        $tipoExpr = $this->operatividadTipoSql();

        return [
            'rangos' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC'),
            'unidades' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC'),
            'estados' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC'),
            'tiposPolicia' => $this->catalogo("
                SELECT tipos.codigo, tipos.nombre, COUNT(e.id) AS total
                FROM (
                    SELECT 'OO' AS codigo, 'Operativo' AS nombre, 1 AS orden
                    UNION ALL SELECT 'OA', 'Operativo administrativo', 2
                    UNION ALL SELECT 'NO', 'No operativo', 3
                    UNION ALL SELECT 'SIN DEFINIR', 'Sin clasificación', 4
                ) tipos
                LEFT JOIN employees e ON {$tipoExpr} = tipos.codigo
                GROUP BY tipos.codigo, tipos.nombre, tipos.orden
                ORDER BY tipos.orden ASC
            "),
            'camposOpcionales' => self::CAMPOS_OPCIONALES,
            'fuenteOperatividad' => $this->fuenteOperatividad(),
            'camposOperatividadNuevos' => $this->hasEmployeeColumn('police_operativity_type'),
        ];
    }

    public function buscar(array $filtros, int $limit = 500): array
    {
        [$where, $params] = $this->where($filtros);
        $fechaCorte = $this->fechaCorte($filtros);
        $orden = $this->ordenSql((string) ($filtros['ordenar_por'] ?? 'rango'));

        $tipoExpr = $this->operatividadTipoSql();
        $motivoExpr = $this->employeeTextSql('police_operativity_reason');
        $referenciaExpr = $this->employeeTextSql('police_operativity_reference');
        $fechaExpr = $this->employeeDateSql('police_operativity_effective_date');
        $notasExpr = $this->employeeTextSql('police_operativity_notes');

        $sql = "
            SELECT
                COALESCE(CAST(e.legacy_position AS CHAR), e.external_agent_number, CAST(e.id AS CHAR)) AS nemp,
                e.document_number AS cedula,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
                e.first_name AS nombre,
                e.last_name AS apellido,
                e.sex AS sexo,
                COALESCE(r.legacy_code, '') AS rango_codigo,
                COALESCE(r.name, e.legacy_rank_name, '') AS rango_nombre,
                COALESCE(u.legacy_code, '') AS unidad_codigo,
                COALESCE(u.name, e.legacy_unit_name, e.external_substation_name, '') AS unidad_nombre,
                COALESCE(s.legacy_code, e.legacy_status_code, '') AS estado_codigo,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, '') AS estado_nombre,
                CONCAT(COALESCE(s.legacy_code, e.legacy_status_code, ''), ' - ', COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) AS estado,
                {$tipoExpr} AS tipo_policia,
                {$motivoExpr} AS operatividad_motivo,
                {$referenciaExpr} AS operatividad_referencia,
                {$fechaExpr} AS operatividad_fecha,
                {$notasExpr} AS operatividad_notas,
                e.external_profile_id AS posicion_mi,
                e.hire_date AS fecha_ingreso,
                e.promotion_date AS fecha_ascenso,
                e.status_date AS fecha_estado,
                e.vacation_date AS fecha_vacaciones,
                e.birth_date AS fecha_nacimiento,
                CASE WHEN e.birth_date IS NULL THEN NULL ELSE TIMESTAMPDIFF(YEAR, e.birth_date, :fecha_corte_edad) END AS edad,
                CASE WHEN e.hire_date IS NULL THEN NULL ELSE TIMESTAMPDIFF(YEAR, e.hire_date, :fecha_corte_servicio_select) END AS tiempo_servicio,
                CASE WHEN e.promotion_date IS NULL THEN NULL ELSE TIMESTAMPDIFF(YEAR, e.promotion_date, :fecha_corte_rango_select) END AS tiempo_rango,
                CASE WHEN e.hire_date IS NULL THEN NULL ELSE DATE_ADD(e.hire_date, INTERVAL 30 YEAR) END AS fecha_jubilacion
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            ORDER BY {$orden}
            LIMIT :limit
        ";

        $params[':fecha_corte_edad'] = $fechaCorte;
        $params[':fecha_corte_servicio_select'] = $fechaCorte;
        $params[':fecha_corte_rango_select'] = $fechaCorte;
        $params[':limit'] = ['value' => max(1, min($limit, 2000)), 'type' => PDO::PARAM_INT];

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contar(array $filtros): int
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT COUNT(*)
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
        ";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function resumenPorEstatus(array $filtros): array
    {
        [$where, $params] = $this->where($filtros);
        $sql = "
            SELECT
                COALESCE(s.legacy_code, e.legacy_status_code, '') AS codigo,
                COALESCE(s.name, e.external_user_status, e.external_agent_status, 'Sin estado') AS nombre,
                COUNT(*) AS total
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN units u ON u.id = e.unit_id
            LEFT JOIN statuses s ON s.id = e.status_id
            WHERE {$where}
            GROUP BY codigo, nombre
            ORDER BY codigo ASC, nombre ASC
        ";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function resumen(array $rows): array
    {
        $resumen = ['total' => count($rows), 'masculino' => 0, 'femenino' => 0, 'activos' => 0, 'otros_estados' => 0];

        foreach ($rows as $row) {
            $sexo = strtoupper((string) ($row['sexo'] ?? ''));
            if ($sexo === 'M') {
                $resumen['masculino']++;
            }
            if ($sexo === 'F') {
                $resumen['femenino']++;
            }

            $estadoCodigo = trim((string) ($row['estado_codigo'] ?? ''));
            $estadoNombre = strtoupper(trim((string) ($row['estado_nombre'] ?? '')));
            if (in_array($estadoCodigo, ['10', '010'], true) || str_starts_with($estadoNombre, 'ACTIVO') || str_contains($estadoNombre, 'EN SERVICIO')) {
                $resumen['activos']++;
            } else {
                $resumen['otros_estados']++;
            }
        }

        return $resumen;
    }

    public function columnas(array $camposOpcionales): array
    {
        $campos = array_values(array_intersect($camposOpcionales, array_keys(self::CAMPOS_OPCIONALES)));
        $campos = array_slice($campos, 0, 4);
        $columnas = [
            'nemp' => 'N. empleado',
            'funcionario' => 'Funcionario',
            'rango_nombre' => 'Rango',
            'unidad_nombre' => 'Ubicación',
            'sexo' => 'Sexo',
        ];

        foreach ($campos as $campo) {
            $columnas[$campo] = self::CAMPOS_OPCIONALES[$campo];
        }

        return $columnas;
    }

    private function where(array $filtros): array
    {
        $where = ['1 = 1'];
        $params = [];
        $fechaCorte = $this->fechaCorte($filtros);

        [$rangoInicial, $rangoFinal] = $this->limitesNumericos(
            trim((string) ($filtros['rango_inicial'] ?? '')),
            trim((string) ($filtros['rango_final'] ?? ''))
        );
        if ($rangoInicial !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) >= CAST(:rango_inicial AS UNSIGNED)';
            $params[':rango_inicial'] = $rangoInicial;
        }
        if ($rangoFinal !== '') {
            $where[] = 'CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) <= CAST(:rango_final AS UNSIGNED)';
            $params[':rango_final'] = $rangoFinal;
        }

        $unidad = trim((string) ($filtros['unidad'] ?? ''));
        if ($unidad !== '') {
            $where[] = "COALESCE(u.legacy_code, '') = :unidad";
            $params[':unidad'] = $unidad;
        }

        $sexo = strtoupper(trim((string) ($filtros['sexo'] ?? 'A')));
        if (in_array($sexo, ['M', 'F'], true)) {
            $where[] = "TRIM(UPPER(COALESCE(e.sex, ''))) = :sexo";
            $params[':sexo'] = $sexo;
        }

        $tipoPolicia = strtoupper(trim((string) ($filtros['tipo_policia'] ?? 'TODOS')));
        $tipoExpr = $this->operatividadTipoSql();
        if (in_array($tipoPolicia, ['OO', 'OA', 'NO'], true)) {
            $where[] = "{$tipoExpr} = :tipo_policia";
            $params[':tipo_policia'] = $tipoPolicia;
        } elseif ($tipoPolicia === 'SIN DEFINIR') {
            $where[] = "{$tipoExpr} = 'SIN DEFINIR'";
        }

        $estadoModo = trim((string) ($filtros['estado_modo'] ?? 'todos'));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estadoModo === 'activo') {
            $where[] = "(TRIM(COALESCE(s.legacy_code, e.legacy_status_code, '')) IN ('10', '010') OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) LIKE 'ACTIVO%' OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) LIKE '%EN SERVICIO%')";
        } elseif ($estadoModo === 'especifico' && $estado !== '') {
            $where[] = "TRIM(COALESCE(s.legacy_code, e.legacy_status_code, '')) = :estado";
            $params[':estado'] = $estado;
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $searchClauses = [
                'e.document_number LIKE :buscar_documento',
                'e.first_name LIKE :buscar_nombre',
                'e.last_name LIKE :buscar_apellido',
                'e.external_agent_number LIKE :buscar_agente',
                'CAST(e.legacy_position AS CHAR) LIKE :buscar_posicion',
                'CAST(e.id AS CHAR) LIKE :buscar_id',
            ];
            $like = '%' . $buscar . '%';
            $params[':buscar_documento'] = $like;
            $params[':buscar_nombre'] = $like;
            $params[':buscar_apellido'] = $like;
            $params[':buscar_agente'] = $like;
            $params[':buscar_posicion'] = $like;
            $params[':buscar_id'] = $like;

            $camposBusqueda = [
                'police_operativity_reason' => 'motivo',
                'police_operativity_reference' => 'referencia',
                'police_operativity_notes' => 'notas',
            ];
            foreach ($camposBusqueda as $column => $suffix) {
                if (!$this->hasEmployeeColumn($column)) {
                    continue;
                }
                $placeholder = ':buscar_' . $suffix;
                $searchClauses[] = "COALESCE(e.{$column}, '') LIKE {$placeholder}";
                $params[$placeholder] = $like;
            }

            $where[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        [$tsMin, $tsMax] = $this->limitesNumericos(
            trim((string) ($filtros['ts_min'] ?? '')),
            trim((string) ($filtros['ts_max'] ?? ''))
        );
        if ($tsMin !== '') {
            $where[] = 'e.hire_date IS NOT NULL AND TIMESTAMPDIFF(YEAR, e.hire_date, :fecha_corte_servicio_min) >= :ts_min';
            $params[':fecha_corte_servicio_min'] = $fechaCorte;
            $params[':ts_min'] = ['value' => (int) $tsMin, 'type' => PDO::PARAM_INT];
        }
        if ($tsMax !== '') {
            $where[] = 'e.hire_date IS NOT NULL AND TIMESTAMPDIFF(YEAR, e.hire_date, :fecha_corte_servicio_max) <= :ts_max';
            $params[':fecha_corte_servicio_max'] = $fechaCorte;
            $params[':ts_max'] = ['value' => (int) $tsMax, 'type' => PDO::PARAM_INT];
        }

        [$trMin, $trMax] = $this->limitesNumericos(
            trim((string) ($filtros['tr_min'] ?? '')),
            trim((string) ($filtros['tr_max'] ?? ''))
        );
        if ($trMin !== '') {
            $where[] = 'e.promotion_date IS NOT NULL AND TIMESTAMPDIFF(YEAR, e.promotion_date, :fecha_corte_rango_min) >= :tr_min';
            $params[':fecha_corte_rango_min'] = $fechaCorte;
            $params[':tr_min'] = ['value' => (int) $trMin, 'type' => PDO::PARAM_INT];
        }
        if ($trMax !== '') {
            $where[] = 'e.promotion_date IS NOT NULL AND TIMESTAMPDIFF(YEAR, e.promotion_date, :fecha_corte_rango_max) <= :tr_max';
            $params[':fecha_corte_rango_max'] = $fechaCorte;
            $params[':tr_max'] = ['value' => (int) $trMax, 'type' => PDO::PARAM_INT];
        }

        return [implode(' AND ', $where), $params];
    }

    private function limitesNumericos(string $desde, string $hasta): array
    {
        if ($desde !== '' && $hasta !== '' && is_numeric($desde) && is_numeric($hasta) && (float) $desde > (float) $hasta) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function fechaCorte(array $filtros): string
    {
        $fechaModo = (string) ($filtros['fecha_modo'] ?? 'actual');
        $fecha = (string) ($filtros['fecha_corte'] ?? '');
        if ($fechaModo === 'especificar' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        return date('Y-m-d');
    }

    private function ordenSql(string $orden): string
    {
        return match ($orden) {
            'ubicacion' => 'u.legacy_code ASC, r.sort_order ASC, e.legacy_position ASC',
            'posicion' => 'e.legacy_position ASC',
            'apellido' => 'e.last_name ASC, e.first_name ASC',
            'tiempo_servicio' => 'e.hire_date ASC, r.sort_order ASC',
            'tiempo_rango' => 'e.promotion_date ASC, r.sort_order ASC',
            default => 'r.sort_order ASC, r.legacy_code ASC, u.legacy_code ASC, e.legacy_position ASC',
        };
    }

    private function fuenteOperatividad(): string
    {
        if ($this->hasEmployeeColumn('police_operativity_type')) {
            return 'employees.police_operativity_type';
        }

        if ($this->hasEmployeeColumn('external_user_type')) {
            return 'employees.external_user_type (compatibilidad temporal)';
        }

        return 'sin campo de operatividad disponible';
    }

    private function operatividadTipoSql(string $alias = 'e'): string
    {
        $column = null;
        if ($this->hasEmployeeColumn('police_operativity_type')) {
            $column = 'police_operativity_type';
        } elseif ($this->hasEmployeeColumn('external_user_type')) {
            $column = 'external_user_type';
        }

        if ($column === null) {
            return "'SIN DEFINIR'";
        }

        return "CASE
            WHEN TRIM(UPPER(COALESCE({$alias}.{$column}, ''))) IN ('OO', 'OA', 'NO')
                THEN TRIM(UPPER({$alias}.{$column}))
            ELSE 'SIN DEFINIR'
        END";
    }

    private function employeeTextSql(string $column, string $alias = 'e'): string
    {
        if (!$this->hasEmployeeColumn($column)) {
            return "''";
        }

        return "COALESCE({$alias}.{$column}, '')";
    }

    private function employeeDateSql(string $column, string $alias = 'e'): string
    {
        if (!$this->hasEmployeeColumn($column)) {
            return 'NULL';
        }

        return "{$alias}.{$column}";
    }

    private function hasEmployeeColumn(string $column): bool
    {
        return isset($this->employeeColumns()[strtolower($column)]);
    }

    private function employeeColumns(): array
    {
        if ($this->employeeColumns !== null) {
            return $this->employeeColumns;
        }

        $this->employeeColumns = [];

        try {
            $rows = $this->db->query('SHOW COLUMNS FROM employees')->fetchAll();
            foreach ($rows as $row) {
                $name = strtolower(trim((string) ($row['Field'] ?? '')));
                if ($name !== '') {
                    $this->employeeColumns[$name] = true;
                }
            }
        } catch (\Throwable) {
            // La consulta principal mostrará el error real si employees no existe.
        }

        return $this->employeeColumns;
    }

    private function catalogo(string $sql): array
    {
        try {
            return $this->db->query($sql)->fetchAll();
        } catch (\Throwable) {
            return [];
        }
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
}
