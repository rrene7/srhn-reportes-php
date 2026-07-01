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

3. Edita `.env` con los datos de tu base:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tu_base_srhn
DB_USER=root
DB_PASS=
APP_BASE_PATH=/srhn-reportes-php/public
```

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
