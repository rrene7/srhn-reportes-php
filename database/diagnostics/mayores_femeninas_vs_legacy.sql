-- Diagnóstico para comparar el reporte de MAYOR femenino contra datos legacy.
-- Ejecutar en la base rhhgith.

-- 1. Conteo que usa principalmente rank_id/ranks.
SELECT
    COUNT(*) AS total_por_rank_id
FROM employees e
LEFT JOIN ranks r ON r.id = e.rank_id
LEFT JOIN statuses s ON s.id = e.status_id
WHERE
    CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) = 50
    AND UPPER(COALESCE(e.sex, '')) = 'F'
    AND (
        COALESCE(s.legacy_code, e.legacy_status_code, '') = '10'
        OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) = 'ACTIVO'
    );

-- 2. Conteo incluyendo respaldo legacy del rango en employees.
SELECT
    COUNT(*) AS total_por_rank_id_mas_legacy_name
FROM employees e
LEFT JOIN ranks r ON r.id = e.rank_id
LEFT JOIN statuses s ON s.id = e.status_id
WHERE
    (
        CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) = 50
        OR UPPER(TRIM(COALESCE(e.legacy_rank_name, ''))) = 'MAYOR'
    )
    AND UPPER(COALESCE(e.sex, '')) = 'F'
    AND (
        COALESCE(s.legacy_code, e.legacy_status_code, '') = '10'
        OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) = 'ACTIVO'
    );

-- 3. Registros que faltan porque no están enlazados a ranks como código 50.
SELECT
    e.id,
    e.legacy_position AS nemp,
    e.document_number AS cedula,
    TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS funcionario,
    e.sex,
    r.legacy_code AS rank_code_normalizado,
    r.name AS rank_name_normalizado,
    e.legacy_rank_name,
    s.legacy_code AS estado_codigo,
    s.name AS estado_nombre
FROM employees e
LEFT JOIN ranks r ON r.id = e.rank_id
LEFT JOIN statuses s ON s.id = e.status_id
WHERE
    UPPER(TRIM(COALESCE(e.legacy_rank_name, ''))) = 'MAYOR'
    AND (r.legacy_code IS NULL OR CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) <> 50)
    AND UPPER(COALESCE(e.sex, '')) = 'F'
    AND (
        COALESCE(s.legacy_code, e.legacy_status_code, '') = '10'
        OR UPPER(COALESCE(s.name, e.external_user_status, e.external_agent_status, '')) = 'ACTIVO'
    )
ORDER BY e.legacy_position ASC;
