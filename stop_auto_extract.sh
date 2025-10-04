#!/bin/bash

# Script para detener la extracción automática

echo "⏹️  Deteniendo extracción automática..."

# Verificar si existe el archivo PID
if [ -f "storage/logs/auto_extract.pid" ]; then
    PID=$(cat storage/logs/auto_extract.pid)
    echo "🔍 PID encontrado: $PID"
    
    # Verificar si el proceso está corriendo
    if ps -p $PID > /dev/null; then
        echo "🛑 Deteniendo proceso $PID..."
        kill $PID
        echo "✅ Proceso detenido exitosamente"
    else
        echo "⚠️  El proceso ya no está corriendo"
    fi
    
    # Eliminar archivo PID
    rm storage/logs/auto_extract.pid
    echo "🗑️  Archivo PID eliminado"
else
    echo "⚠️  No se encontró archivo PID"
    echo "🔍 Buscando procesos de extracción automática..."
    
    # Buscar y detener procesos manualmente
    PIDS=$(ps aux | grep "lottery:auto-extract" | grep -v grep | awk '{print $2}')
    
    if [ -z "$PIDS" ]; then
        echo "ℹ️  No hay procesos de extracción automática corriendo"
    else
        echo "🛑 Deteniendo procesos: $PIDS"
        echo $PIDS | xargs kill
        echo "✅ Procesos detenidos"
    fi
fi

echo ""
echo "🎯 Extracción automática detenida"
