@echo off
echo Iniciando extraccion automatica de numeros de loteria...
echo.
echo Este script ejecutara la extraccion automatica cada 30 segundos
echo Presiona Ctrl+C para detener
echo.
php artisan lottery:auto-extract --interval=30
pause
