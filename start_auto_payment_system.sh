#!/bin/bash

echo "========================================"
echo "   SISTEMA AUTOMATICO DE PAGOS"
echo "========================================"
echo ""
echo "Iniciando sistema automatico de pagos..."
echo "El sistema detectara automaticamente cuando se inserten"
echo "numeros ganadores y calculara los pagos al instante."
echo ""
echo "Presiona Ctrl+C para detener el sistema"
echo ""

php artisan lottery:auto-payment --interval=5
