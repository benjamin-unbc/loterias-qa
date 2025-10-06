#!/bin/bash

# Script para iniciar extracción automática en cPanel
# Este script ejecuta la extracción cada 30 segundos durante los horarios de lotería

echo "🚀 Iniciando extracción automática para cPanel..."
echo "📅 Fecha: $(date)"
echo "⏰ Horarios de funcionamiento: 10:30 AM - 11:59 PM"
echo "🔄 Intervalo: 30 segundos"
echo "⏹️  Presiona Ctrl+C para detener"
echo ""

# Función para verificar si estamos en horario de funcionamiento
is_working_hour() {
    current_hour=$(date +%H)
    current_minute=$(date +%M)
    current_time=$((current_hour * 100 + current_minute))
    
    # Horario: 10:30 (1030) a 23:59 (2359)
    if [ $current_time -ge 1030 ] && [ $current_time -le 2359 ]; then
        return 0  # true
    else
        return 1  # false
    fi
}

# Función para extraer números
extract_numbers() {
    echo "🔍 [$(date '+%H:%M:%S')] Extrayendo números..."
    php artisan lottery:auto-update --force > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ [$(date '+%H:%M:%S')] Extracción completada"
    else
        echo "❌ [$(date '+%H:%M:%S')] Error en extracción"
    fi
}

# Bucle principal
while true; do
    if is_working_hour; then
        extract_numbers
    else
        echo "⏸️  [$(date '+%H:%M:%S')] Fuera de horario de funcionamiento"
    fi
    
    # Esperar 30 segundos
    sleep 30
done
