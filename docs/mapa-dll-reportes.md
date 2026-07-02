# Mapa real del menú Reportes del DLL legado

Este documento separa las funciones reales observadas en el DLL de las mejoras nuevas agregadas durante la reconstrucción.

## Menú principal: Reportes

### 1. Estudios Realizados

Submenú observado:

1. Estudios Generales

Estado en PHP:

- Pendiente de reconstrucción específica.
- Se debe identificar la tabla real de estudios y reproducir el reporte del DLL.

---

### 2. Lista de Acciones

Submenú observado:

- Pendiente de completar con capturas del submenú.

Estado en PHP:

- Base implementada usando `employee_actions`.
- Ya muestra acciones por funcionario.
- Ya separa categorías: ascensos, traslados, vacaciones, licencias, sanciones e incapacidades.
- Falta validar contra el menú exacto del DLL.

---

### 3. Estadísticas

Submenú observado:

1. Estado de Fuerza
2. OPERATIVOS
5. EDITOR DE REP
6. EST.ACC. (ACCION - DESGLOSE EN MES)
7. EST.ACC. (ACCION - DESGLOSE EN AÑOS)
8. EST. DE SANCIONES
9. EST.ACC. (RANGO - DESGLOSE EN MES)

Estado en PHP:

- Estado de Fuerza: parcialmente cubierto por reportes general/rango/dependencia, pero falta formato estadístico fiel.
- OPERATIVOS: pendiente.
- EDITOR DE REP: pendiente; posible editor/constructor de reportes.
- EST.ACC. por mes: pendiente de reporte estadístico.
- EST.ACC. por año: pendiente de reporte estadístico.
- EST. de sanciones: pendiente de reporte estadístico especializado.
- EST.ACC. rango por mes: pendiente.

---

### 4. Reportes Varios

Submenú observado:

1. Reporte Opciones Múltiples

Estado en PHP:

- Pendiente de reconstrucción fiel.
- Es la pantalla compleja observada con filtros por rango, ubicación, ambos, sexo, tipo de policía, estatus, tiempo de servicio, tiempo en rango, campos opcionales, ordenamiento y tipo de papel.
- Este debe convertirse en el módulo central de reconstrucción.

---

### 6. Hoja de Vida para la placa

Estado en PHP:

- Parcialmente cubierto por la ficha individual de funcionario.
- Falta confirmar si el DLL genera hoja de vida por placa/posición y qué campos exactos imprime.

---

## Funciones nuevas agregadas que NO forman parte confirmada del DLL

Estas funciones son útiles, pero no deben contarse como reconstrucción fiel hasta validarlas:

- Exportar CSV de acciones.
- Exportar CSV de ficha individual.
- Encabezado institucional moderno para impresión.
- Tarjetas modernas de navegación.
- Índices SQL de rendimiento.
- Búsqueda optimizada.

---

## Prioridad de reconstrucción

1. Reporte Opciones Múltiples.
2. Estudios Generales.
3. Lista de Acciones con submenú exacto.
4. Estadísticas: Estado de Fuerza.
5. Estadísticas de acciones por mes/año.
6. Estadísticas de sanciones.
7. Hoja de Vida para la placa.

## Regla de validación

Cada reporte debe compararse contra el DLL con los mismos filtros:

- Misma cantidad de registros.
- Mismos campos visibles.
- Mismo criterio de ordenamiento.
- Mismo agrupamiento.
- Misma salida impresa.
