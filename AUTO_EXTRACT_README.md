# 🔄 Sistema de Extracción Automática de Números de Lotería

Este sistema extrae automáticamente los números ganadores de lotería cada 30 segundos sin intervención manual.

## 🚀 Configuración en cPanel

### Opción 1: Cron Jobs (Recomendado)

1. **Accede a cPanel** → **Cron Jobs**
2. **Agrega esta tarea**:
   ```
   0,30 9-21 * * * /usr/bin/php /home/tu_usuario/public_html/artisan lottery:auto-extract --interval=30
   ```
3. **Reemplaza** `tu_usuario` con tu usuario de cPanel
4. **Ajusta la ruta** si tu proyecto está en una subcarpeta

### Opción 2: Ejecución en Segundo Plano

1. **Sube los archivos** al servidor
2. **Ejecuta en terminal SSH**:
   ```bash
   chmod +x start_background_extract.sh
   ./start_background_extract.sh
   ```

## 📋 Comandos Disponibles

### Ejecutar Extracción Automática
```bash
php artisan lottery:auto-extract --interval=30
```

### Parámetros
- `--interval=30`: Intervalo en segundos (por defecto: 30)

### Detener Extracción
```bash
./stop_auto_extract.sh
```

## 📊 Monitoreo

### Ver Logs en Tiempo Real
```bash
tail -f storage/logs/laravel.log
```

### Verificar si Está Corriendo
```bash
ps aux | grep lottery:auto-extract
```

### Ver Logs de Extracción
```bash
tail -f storage/logs/auto_extract.log
```

## ⏰ Horarios de Funcionamiento

- **10:30-11:30**: Primera extracción
- **12:00-13:00**: Segunda extracción  
- **15:00-16:00**: Tercera extracción
- **18:00-19:00**: Cuarta extracción
- **21:00-22:00**: Quinta extracción
- **22:00-23:00**: Sexta extracción
- **Frecuencia**: Cada 30 segundos durante los horarios activos
- **El sistema verifica automáticamente si está en horario de funcionamiento**

## 🎲 Horarios de Lotería

- **La Previa**: 09:00-11:00
- **Primera**: 11:00-13:00
- **Matutina**: 13:00-15:00
- **Vespertina**: 15:00-17:00
- **Nocturna**: 17:00-19:00

## 🎯 Funcionamiento

1. **Extrae números** desde vivitusuerte.com
2. **Los guarda automáticamente** en la base de datos
3. **Actualiza números existentes** si cambian
4. **Registra logs** de todas las operaciones
5. **Funciona 24/7** sin intervención manual

## 📝 Logs

- **Laravel logs**: `storage/logs/laravel.log`
- **Extracción logs**: `storage/logs/auto_extract.log`
- **PID del proceso**: `storage/logs/auto_extract.pid`

## 🔧 Configuración Avanzada

### Cambiar Intervalo
```bash
php artisan lottery:auto-extract --interval=60  # Cada minuto
php artisan lottery:auto-extract --interval=120 # Cada 2 minutos
```

### Solo Durante Horarios de Lotería
```bash
# Cron job que solo ejecuta de 10:30 AM a 23:59 PM cada 30 segundos
*/30 10-23 * * * /usr/bin/php /home/tu_usuario/public_html/artisan lottery:auto-update
```

## ✅ Verificación

El sistema está funcionando correctamente si:
- ✅ Los números aparecen automáticamente en Extractos
- ✅ Los logs muestran extracciones exitosas
- ✅ No hay errores en los logs
- ✅ Los números se guardan en la BD automáticamente

## 🆘 Solución de Problemas

### El proceso no inicia
- Verifica permisos de archivos
- Revisa logs de Laravel
- Confirma que la ruta de PHP es correcta

### No se extraen números
- Verifica conexión a internet
- Revisa logs de extracción
- Confirma que el servicio web está disponible

### Proceso se detiene
- Verifica logs de errores
- Reinicia el proceso
- Revisa recursos del servidor
