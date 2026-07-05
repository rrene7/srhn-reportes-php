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
        'tipo_policia' => 'Tipo policía',
        'estado' => 'Estatus',
    ];

    public function __construct(
        private PDO $db
    ) {}

    public function catalogos(): array
    {
        return [
            'rangos' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM ranks ORDER BY sort_order ASC, legacy_code ASC'),
            'unidades' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM units ORDER BY legacy_code ASC, name ASC'),
            'estados' => $this->catalogo('SELECT legacy_code AS codigo, name AS nombre FROM statuses ORDER BY legacy_code ASC'),
            'camposOpcionales' => self::CAMPOS_OPCIONALES,
        ];
    }

    public function buscar(array $filtros, int $limit = 500): array
    {
        [$where, $params] = $this->where($filtros);
        $fechaCorte = $this->fechaCorte($filtros);
        $orden = $this->ordenSql((string) ($filtros['ordenar_por'] ?? 'rango'));

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
                e.external_user_type AS tipo_policia,
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
        $resumen = [
            'total' => count($rows),
            'masculino' => 0,
            'femenino' => 0,
            'activos' => 0,
            'otros_estados' => 0,
        ];

        foreach ($rows as $row) {
            $sexo = strtoupper((string) ($row['sexo'] ?? ''));
            if ($sexo === 'M') {
                $resumen['masculino']++;
            }
            if ($sexo === 'F') {
                $resumen['femenino']++;
            }

            $estadoCodigo = (string) ($row['estado_codigo'] ?? '');
            $estadoNombre = strtoupper((string) ($row['estado_nombre'] ?? ''));
            if ($estadoCodigo === '10' || $estadoNombre === 'ACTIVO') {
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

        $rangoInicial = trim((string) ($filtros['rango_inicial'] ?? ''));
        $rangoFinal = trim((string) ($filtros['rango_final'] ?? ''));
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
            $where[] = 'COALESCE(u.legacy_code, \'\') = :unidad';
            $params[':unidad'] = $unidad;
        }

        $sexo = strtoupper(trim((string) ($filtros['sexo'] ?? 'A')));
        if (in_array($sexo, ['M', 'F'], true)) {
            $where[] = 'TRIM(UPPER(COALESCE(e.sex, \'\'))) = :sexo';
            $params[':sexo'] = $sexo;
        }

        $tipoPolicia = strtoupper(trim((string) ($filtros['tipo_policia'] ?? 'todos')));
        if (in_array($tipoPolicia, ['OO', 'NO', 'OA'], true)) {
            $tipoExpr = "TRIM(UPPER(COALESCE(e.external_user_type, '')))";

            if ($tipoPolicia === 'OO') {
                $where[] = "({$tipoExpr} = 'OO' OR {$tipoExpr} = 'OPERATIVO')";
            } elseif ($tipoPolicia === 'NO') {
                $where[] = "({$tipoExpr} = 'NO' OR {$tipoExpr} LIKE 'NO%OPERATIVO%')";
            } elseif ($tipoPolicia === 'OA') {
                $where[] = "({$tipoExpr} = 'OA' OR {$tipoExpr} LIKE 'OPERATIVO%ADMINISTRATIVO%')";
            }
        }

        $estadoModo = trim((string) ($filtros['estado_modo'] ?? 'todos'));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estadoModo === 'activo') {
            $where[] = "(COALESCE(s.legacy_code, e.legacy_status_code, '') = '10' OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) = 'ACTIVO')";
        } elseif ($estadoModo === 'especifico' && $estado !== '') {
            $where[] = 'COALESCE(s.legacy_code, e.legacy_status_code, \'\') = :estado';
            $params[':estado'] = $estado;
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $where[] = '(
                e.document_number LIKE :buscar_documento
                OR e.first_name LIKE :buscar_nombre
                OR e.last_name LIKE :buscar_apellido
                OR e.external_agent_number LIKE :buscar_agente
                OR CAST(e.legacy_position AS CHAR) LIKE :buscar_posicion
                OR CAST(e.id AS CHAR) LIKE :buscar_id
            )';
            $like = $buscar . '%';
            $params[':buscar_documento'] = $like;
            $params[':buscar_nombre'] = $like;
            $params[':buscar_apellido'] = $like;
            $params[':buscar_agente'] = $like;
            $params[':buscar_posicion'] = $like;
            $params[':buscar_id'] = $like;
        }

        $tsMin = trim((string) ($filtros['ts_min'] ?? ''));
        $tsMax = trim((string) ($filtros['ts_max'] ?? ''));
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

        $trMin = trim((string) ($filtros['tr_min'] ?? ''));
        $trMax = trim((string) ($filtros['tr_max'] ?? ''));
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
