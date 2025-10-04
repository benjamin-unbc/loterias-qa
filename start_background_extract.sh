#!/bin/bash

# Script para ejecutar extracción automática en segundo plano
# Este script mantiene el proceso ejecutándose incluso si cierras la terminal

echo "🚀 Iniciando extracción automática en segundo plano..."
echo "📅 Fecha: $(date)"
echo "📝 Logs: storage/logs/laravel.log"
echo "🔄 Proceso ejecutándose cada 30 segundos"
echo ""

# Crear directorio de logs si no existe
mkdir -p storage/logs

# Ejecutar en segundo plano con nohup
nohup php artisan lottery:auto-extract --interval=30 > storage/logs/auto_extract.log 2>&1 &

# Obtener el PID del proceso
PID=$!
echo "✅ Proceso iniciado con PID: $PID"
echo "📄 PID guardado en: storage/logs/auto_extract.pid"
echo $PID > storage/logs/auto_extract.pid

echo ""
echo "🎯 El sistema ahora extrae números automáticamente cada 30 segundos"
echo "📊 Para ver logs en tiempo real: tail -f storage/logs/auto_extract.log"
echo "⏹️  Para detener: kill $PID"
echo "🔍 Para verificar si está corriendo: ps aux | grep lottery:auto-extract"
