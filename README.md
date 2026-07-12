# SRHN Reportes PHP

Proyecto base en PHP para reconstruir el módulo de reportes generales detectado dentro del archivo `srhn_reportes_gral.dll`.

El `.dll` original parece provenir de PowerBuilder 7 / DataWindow. Este proyecto no intenta ejecutar el DLL; reconstruye la funcionalidad en PHP usando consultas SQL sobre las tablas detectadas.

## Módulo inicial

- Reportes generales de personal.
- Filtros por rango desde / hasta.
- Filtros por cuartel o dependencia desde / hasta.
- Filtro opcional por estado.
- Vista en pantalla.
- Totales por rango.
- Totales por cuartel/dependencia.
- Exportación CSV.
- Diseño web sencillo para XAMPP.

## Tablas detectadas en el DLL

- `dota`
- `tabran`
- `tabcuar`
- `tabstatus`
- `tabcar`
- `tabdrp`
- `tabfun`

## Requisitos

- PHP 8.1 o superior.
- MySQL / MariaDB.
- XAMPP, Laragon o servidor Apache/PHP.
- Extensión PDO MySQL activa.

## Instalación local en XAMPP

1. Copia la carpeta `srhn-reportes-php` dentro de:

```bash
C:\xampp\htdocs\
```

2. Copia el archivo `.env.example` como `.env`.

3. Edita `.env` con las dos conexiones:

```env
APP_BASE_PATH=/srhn-reportes-php/public

# Base principal del sistema
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=rhhgith
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Base nueva de RRHH usada temporalmente por los módulos de operatividad
RRHH_DB_HOST=127.0.0.1
RRHH_DB_PORT=3306
RRHH_DB_NAME=rrhh2029
RRHH_DB_USER=root
RRHH_DB_PASS=
RRHH_DB_CHARSET=utf8mb4
```

La conexión principal `DB_*` continúa siendo usada por el sistema general. Los módulos `/reportes/operativos` y `/reportes/opciones-multiples` usan la conexión `RRHH_DB_*` para consultar `rrhh2029` sin modificar registros.

4. Abre en el navegador:

```text
http://localhost/srhn-reportes-php/public/reportes
```

## Subir a GitHub

Desde la carpeta del proyecto:

```bash
git init
git add .
git commit -m "Versión inicial del módulo SRHN reportes PHP"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/srhn-reportes-php.git
git push -u origin main
```

También puedes ejecutar en Windows el archivo:

```text
publicar-github.bat
```

Primero edítalo y coloca tu usuario real de GitHub.

## Seguridad

Este proyecto incluye:

- Consultas preparadas con PDO.
- Archivo `.env` excluido por `.gitignore`.
- Manejo básico de errores.
- Separación MVC ligera.

## Próximos pasos recomendados

1. Confirmar nombres reales de columnas en `tabcuar` y `tabstatus`.
2. Agregar autenticación institucional.
3. Agregar permisos por rol.
4. Agregar exportación PDF.
5. Integrar con el sistema principal de RRHH.
