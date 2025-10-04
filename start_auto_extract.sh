#!/bin/bash

echo "🔄 Iniciando extracción automática de números de lotería..."
echo "📅 Fecha: $(date)"
echo "⏹️  Presiona Ctrl+C para detener"
echo ""

# Ejecutar el comando de extracción automática
php artisan lottery:auto-extract --interval=30
