# Análisis inicial de `srhn_reportes_gral.dll`

El archivo cargado es una librería Windows compilada, no código PHP. Por las cadenas internas detectadas, parece una librería hecha en PowerBuilder 7 con objetos DataWindow.

## Evidencias detectadas

Referencias principales:

```text
PBVM70.dll
datawindow
.dwo
.win
w_reportes_total
w_reportes_total_ori
w_reportes_total_view
dw_rep_total
dw_rep_total2
dw_rep_total_4opc
dw_dota_rango
dw_dota_cuartel
dw_dota_rango_dep
dw_status_gen
dw_esta_esp
dw_tipo_cuartel
```

## Tablas detectadas

```text
dota
tabran
tabcuar
tabcar
tabstatus
tabdrp
tabfun
```

## Campos detectados en `dota`

```text
dota.rango
dota.nemp
dota.nombre
dota.apellido
dota.cuartel
dota.cedula
dota.sexo
dota.posicipn
dota.posicimi
dota.fecing
dota.fecascen
dota.fectras
dota.fecvac
dota.estado
dota.fecnac
dota.tipopol
```

## Consulta base reconstruida

```sql
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
  AND d.cuartel BETWEEN :cuartel_desde AND :cuartel_hasta;
```

## Interpretación funcional

El módulo original probablemente generaba reportes de dotación/personal con múltiples opciones:

- Por rango.
- Por cuartel/dependencia.
- Por estado.
- Por estatus especial.
- Por tipo de cuartel.
- Vista previa.
- Impresión en formatos tipo 8x11 y 8x14.

## Estrategia PHP

Como el DLL no se puede convertir directamente a PHP, se reconstruye la funcionalidad usando:

- PHP 8.
- PDO.
- MySQL/MariaDB.
- MVC ligero.
- Vistas HTML imprimibles.
- Exportación CSV.
