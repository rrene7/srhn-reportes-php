@echo off
echo Publicando proyecto SRHN Reportes PHP en GitHub...
echo.
echo IMPORTANTE:
echo 1. Primero crea un repositorio vacío en GitHub llamado srhn-reportes-php.
echo 2. Cambia TU_USUARIO por tu usuario real de GitHub en este archivo.
echo.
pause

git init
git add .
git commit -m "Version inicial del modulo SRHN reportes PHP"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/srhn-reportes-php.git
git push -u origin main

pause
