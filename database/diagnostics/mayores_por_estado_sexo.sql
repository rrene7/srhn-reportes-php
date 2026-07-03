-- Diagnóstico general de MAYOR por sexo y estado.
-- Ejecutar en la base rhhgith.

SELECT
    COALESCE(r.legacy_code, '') AS codigo_rango,
    COALESCE(r.name, e.legacy_rank_name, '') AS rango,
    COALESCE(e.sex, 'SIN SEXO') AS sexo,
    COALESCE(s.legacy_code, e.legacy_status_code, '') AS codigo_estado,
    COALESCE(s.name, e.external_user_status, e.external_agent_status, 'SIN ESTADO') AS estado,
    COUNT(*) AS total
FROM employees e
LEFT JOIN ranks r ON r.id = e.rank_id
LEFT JOIN statuses s ON s.id = e.status_id
WHERE
    CAST(COALESCE(r.legacy_code, 0) AS UNSIGNED) = 50
    OR UPPER(TRIM(COALESCE(e.legacy_rank_name, ''))) = 'MAYOR'
GROUP BY
    codigo_rango,
    rango,
    sexo,
    codigo_estado,
    estado
ORDER BY
    sexo,
    codigo_estado;

-- Diagnóstico exacto del filtro que usaste en pantalla:
-- MAYOR + F + ACTIVO + T. Servicio mínimo 3
SELECT
    COUNT(*) AS total_mayor_femenino_activo_ts3
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
    )
    AND e.hire_date IS NOT NULL
    AND TIMESTAMPDIFF(YEAR, e.hire_date, CURDATE()) >= 3;
