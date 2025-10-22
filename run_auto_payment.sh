#!/bin/bash

# Script para ejecutar el sistema automático de pagos
# Ubicación: /home/tuusuario/public_html/run_auto_payment.sh

# Cambiar al directorio del proyecto
cd /home/tuusuario/public_html

# Ejecutar el comando con logging
php artisan lottery:auto-payment --interval=5 >> storage/logs/auto_payment.log 2>&1

# Log de ejecución
echo "[" $(date) "] Auto payment system ejecutado" >> storage/logs/cron_execution.log
