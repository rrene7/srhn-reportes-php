-- Consulta reconstruida desde el DLL original.
-- Ajustar nombres de columnas según la base real.

SELECT
    d.rango,
    d.nemp,
    d.nombre,
    d.apellido,
    d.cuartel,
    d.cedula,
    d.sexo,
    d.posicipn,
    d.posicimi,
    d.fecing,
    d.fecascen,
    d.fectras,
    d.fecvac,
    d.estado,
    d.fecnac,
    d.tipopol
FROM dota d
WHERE d.nemp <> 0
  AND d.estado NOT IN ('00','01','02','03')
  AND d.rango BETWEEN :rango_desde AND :rango_hasta
  AND d.cuartel BETWEEN :cuartel_desde AND :cuartel_hasta
ORDER BY d.rango ASC, d.cuartel ASC, d.apellido ASC, d.nombre ASC;
