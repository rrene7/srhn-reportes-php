<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class HojaVidaComplementariaModel
{
    private const SECCIONES = [
        'estudios' => [
            'titulo' => 'Estudios',
            'tablas' => ['employee_studies', 'employee_education', 'studies', 'educations', 'estudios', 'ESTUDIOS'],
        ],
        'familia' => [
            'titulo' => 'Familia',
            'tablas' => ['employee_family', 'family_members', 'employee_relatives', 'family', 'fam', 'familiares', 'FAM'],
        ],
        'direcciones' => [
            'titulo' => 'Direcciones',
            'tablas' => ['employee_addresses', 'addresses', 'address_records', 'direcciones', 'dir', 'DIR'],
        ],
        'conducta' => [
            'titulo' => 'Conducta',
            'tablas' => ['employee_conduct', 'conduct_records', 'disciplinary_records', 'conducta', 'CONDUCTA'],
        ],
        'condicion_fisica' => [
            'titulo' => 'Condición física',
            'tablas' => ['employee_physical', 'physical_tests', 'physical_assessments', 'pfisica', 'PFISICA'],
        ],
    ];

    public function __construct(
        private PDO $db
    ) {}

    public function obtener(string $buscar): array
    {
        $buscar = trim($buscar);
        $employeeIds = $buscar !== '' ? $this->resolverEmployeeIds($buscar) : [];
        $resultado = [];

        foreach (self::SECCIONES as $clave => $config) {
            $tabla = $this->detectarTabla($config['tablas']);

            if ($tabla === null) {
                $resultado[$clave] = [
                    'titulo' => $config['titulo'],
                    'tabla' => null,
                    'columnas' => [],
                    'rows' => [],
                    'estado' => 'Tabla no detectada',
                ];
                continue;
            }

            $columnas = $this->columnas($tabla);
            $rows = $this->buscarEnTabla($tabla, $columnas, $buscar, $employeeIds);

            $resultado[$clave] = [
                'titulo' => $config['titulo'],
                'tabla' => $tabla,
                'columnas' => $columnas,
                'rows' => $rows,
                'estado' => 'Tabla detectada',
            ];
        }

        return $resultado;
    }

    private function resolverEmployeeIds(string $buscar): array
    {
        if (!$this->tablaExiste('employees')) {
            return [];
        }

        $params = [];

        if (ctype_digit($buscar)) {
            $sql = "
                SELECT id
                FROM employees
                WHERE id = :buscar_id
                   OR legacy_position = :buscar_posicion
                   OR external_agent_number = :buscar_agente
                   OR document_number = :buscar_documento
                   OR document_number LIKE :buscar_documento_prefijo
                LIMIT 25
            ";
            $params[':buscar_id'] = ['value' => (int) $buscar, 'type' => PDO::PARAM_INT];
            $params[':buscar_posicion'] = ['value' => (int) $buscar, 'type' => PDO::PARAM_INT];
            $params[':buscar_agente'] = $buscar;
            $params[':buscar_documento'] = $buscar;
            $params[':buscar_documento_prefijo'] = $buscar . '%';
        } else {
            $sql = "
                SELECT id
                FROM employees
                WHERE document_number LIKE :buscar_documento_prefijo
                   OR first_name LIKE :buscar_nombre_prefijo
                   OR last_name LIKE :buscar_apellido_prefijo
                LIMIT 25
            ";
            $params[':buscar_documento_prefijo'] = $buscar . '%';
            $params[':buscar_nombre_prefijo'] = $buscar . '%';
            $params[':buscar_apellido_prefijo'] = $buscar . '%';
        }

        try {
            $stmt = $this->db->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            return [];
        }
    }

    private function buscarEnTabla(string $tabla, array $columnas, string $buscar, array $employeeIds): array
    {
        if ($columnas === []) {
            return [];
        }

        $disponibles = array_change_key_case(array_flip($columnas), CASE_LOWER);
        $where = [];
        $params = [];

        $employeeColumn = $this->primeraColumna($columnas, ['employee_id', 'funcionario_id', 'employeeid', 'id_employee', 'person_id', 'personnel_id']);
        if ($employeeColumn !== null && $employeeIds !== []) {
            $placeholders = [];
            foreach ($employeeIds as $index => $employeeId) {
                $param = ':employee_id_' . $index;
                $placeholders[] = $param;
                $params[$param] = ['value' => $employeeId, 'type' => PDO::PARAM_INT];
            }
            $where[] = $this->id($employeeColumn) . ' IN (' . implode(',', $placeholders) . ')';
        }

        if (ctype_digit($buscar)) {
            $numericCandidates = ['nemp', 'posicion', 'posicipn', 'legacy_position', 'employee_number', 'position_number', 'cedula', 'document_number'];
            foreach ($numericCandidates as $candidate) {
                $col = $this->columnaReal($disponibles, $columnas, $candidate);
                if ($col === null) {
                    continue;
                }

                $param = ':num_' . count($params);
                $where[] = $this->id($col) . ' = ' . $param;
                $params[$param] = $buscar;
            }
        } else {
            $textCandidates = ['cedula', 'document_number', 'nombre', 'apellido', 'name', 'description', 'descripcion'];
            foreach ($textCandidates as $candidate) {
                $col = $this->columnaReal($disponibles, $columnas, $candidate);
                if ($col === null) {
                    continue;
                }

                $param = ':txt_' . count($params);
                $where[] = $this->id($col) . ' LIKE ' . $param;
                $params[$param] = $buscar . '%';
            }
        }

        if ($where === []) {
            return [];
        }

        $orderColumn = $this->primeraColumna($columnas, ['fecha', 'date', 'created_at', 'updated_at', 'start_date', 'fecha_inicio', 'fecini']);
        $sql = 'SELECT * FROM ' . $this->id($tabla) . ' WHERE (' . implode(' OR ', $where) . ')';
        $sql .= $this->columnaReal($disponibles, $columnas, 'deleted_at') !== null ? ' AND deleted_at IS NULL' : '';
        $sql .= $orderColumn !== null ? ' ORDER BY ' . $this->id($orderColumn) . ' DESC' : ' ORDER BY 1 DESC';
        $sql .= ' LIMIT 10';

        try {
            $stmt = $this->db->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function detectarTabla(array $candidatas): ?string
    {
        foreach ($candidatas as $tabla) {
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
        $disponibles = array_change_key_case(array_flip($columnas), CASE_LOWER);

        foreach ($candidatas as $candidate) {
            $col = $this->columnaReal($disponibles, $columnas, $candidate);
            if ($col !== null) {
                return $col;
            }
        }

        return null;
    }

    private function columnaReal(array $disponibles, array $columnas, string $candidate): ?string
    {
        $key = strtolower($candidate);
        if (!isset($disponibles[$key])) {
            return null;
        }

        return $columnas[(int) $disponibles[$key]] ?? null;
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
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
