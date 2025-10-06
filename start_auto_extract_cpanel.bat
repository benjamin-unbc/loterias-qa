@echo off
REM Script para iniciar extracción automática en Windows/cPanel
REM Este script ejecuta la extracción cada 30 segundos durante los horarios de lotería

echo 🚀 Iniciando extracción automática para cPanel...
echo 📅 Fecha: %date% %time%
echo ⏰ Horarios de funcionamiento: 10:30 AM - 11:59 PM
echo 🔄 Intervalo: 30 segundos
echo ⏹️  Presiona Ctrl+C para detener
echo.

:loop
REM Obtener hora actual
for /f "tokens=1-3 delims=:" %%a in ("%time%") do (
    set current_hour=%%a
    set current_minute=%%b
)

REM Remover espacios de la hora
set current_hour=%current_hour: =0%

REM Verificar si estamos en horario de funcionamiento (10:30 a 23:59)
if %current_hour% geq 10 (
    if %current_hour% leq 23 (
        echo 🔍 [%time%] Extrayendo números...
        php artisan lottery:auto-update --force > nul 2>&1
        if %errorlevel% equ 0 (
            echo ✅ [%time%] Extracción completada
        ) else (
            echo ❌ [%time%] Error en extracción
        )
    ) else (
        echo ⏸️  [%time%] Fuera de horario de funcionamiento
    )
) else (
    echo ⏸️  [%time%] Fuera de horario de funcionamiento
)

REM Esperar 30 segundos
timeout /t 30 /nobreak > nul
goto loop
