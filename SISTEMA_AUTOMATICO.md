# 🤖 Sistema de Actualización Automática de Números Ganadores

## 📋 Descripción
Sistema completamente automático que detecta y inserta números ganadores de lotería en tiempo real, sin intervención manual.

## 🔄 Componentes del Sistema

### 1. **Comando Artisan Automático**
- **Archivo**: `app/Console/Commands/AutoUpdateLotteryNumbers.php`
- **Comando**: `php artisan lottery:auto-update`
- **Frecuencia**: Cada 5 minutos (configurado en `app/Console/Kernel.php`)
- **Función**: Extrae números de vivitusuerte.com e inserta en BD

### 2. **API de Verificación**
- **Archivo**: `app/Http/Controllers/Api/AutoUpdateController.php`
- **Endpoint**: `POST /api/check-new-numbers`
- **Función**: Verifica nuevos números desde el frontend

### 3. **Actualización en Tiempo Real**
- **JavaScript**: Actualización automática cada 2 minutos
- **Indicador visual**: Muestra cuando se está actualizando
- **Recarga automática**: Si detecta nuevos números

## 🎯 Flujo de Funcionamiento

### **Automático (Principal)**
1. **Cada 5 minutos**: El scheduler ejecuta `lottery:auto-update`
2. **Extracción**: Obtiene números de las 13 ciudades desde vivitusuerte.com
3. **Inserción**: Guarda números en BD con fecha actual
4. **Logging**: Registra todas las operaciones

### **Tiempo Real (Frontend)**
1. **Cada 2 minutos**: JavaScript verifica nuevos números via API
2. **Indicador**: Muestra "Actualizando automáticamente..."
3. **Recarga**: Si hay nuevos números, recarga la página
4. **Visualización**: Muestra números en "Ver solo cabeza" y "Ver extracto completo"

### **Automático con Refuerzo**
- **🤖 Automático**: Sistema funciona solo cada 5 minutos
- **🔵 Botón "Buscar"**: Refuerzo para casos excepcionales
- **🔄 Botón "Reiniciar"**: Para volver a la fecha actual
- **📊 Mensajes informativos**: Indica estado de los números

## 🏙️ Ciudades Soportadas
- Ciudad (NAC)
- Santa Fé (SFE)
- Provincia (PRO)
- Entre Ríos (RIO)
- Córdoba (COR)
- Corrientes (CTE)
- Chaco (CHA)
- Neuquén (NQN)
- Misiones (MIS)
- Mendoza (MZA)
- Río Negro (Rio)
- Tucumán (Tucu)
- Santiago (San)

## ⏰ Turnos de Lotería
1. **La Previa** (extract_id: 1)
2. **Primera** (extract_id: 2)
3. **Matutina** (extract_id: 3)
4. **Vespertina** (extract_id: 4)
5. **Nocturna** (extract_id: 5)

## 🚀 Comandos Útiles

### **Ejecutar manualmente**
```bash
php artisan lottery:auto-update
```

### **Forzar actualización**
```bash
php artisan lottery:auto-update --force
```

### **Ver logs**
```bash
tail -f storage/logs/laravel.log
```

### **Verificar scheduler**
```bash
php artisan schedule:list
```

## 📊 Indicadores Visuales

### **En la Interfaz**
- 🤖 **"Auto-actualización activa"**: Indica que el sistema está funcionando
- 🟢 **Punto verde pulsante**: Sistema activo
- 🔄 **"Actualizando automáticamente..."**: Cuando se está actualizando
- 🟠 **Botón "Buscar Manual"**: Para uso excepcional

### **En Logs**
- ✅ **Números insertados**: Cantidad de números nuevos
- 🔄 **Números actualizados**: Cantidad de números modificados
- ❌ **Errores**: Problemas en la extracción o inserción

## 🔧 Configuración

### **Frecuencias**
- **Scheduler**: 5 minutos (`everyFiveMinutes()`)
- **Frontend**: 2 minutos (120000ms)
- **Sin solapamiento**: `withoutOverlapping()`
- **En segundo plano**: `runInBackground()`

### **URLs de Extracción**
- **Base**: `https://vivitusuerte.com/pizarra/`
- **Ciudades con espacios**: Usan `+` (ej: `santa+fe`)
- **Mapeo específico**: Ciudad tiene estructura diferente

## 🎉 Beneficios

1. **🔄 Completamente Automático**: Sin intervención manual
2. **⚡ Tiempo Real**: Actualización cada 2-5 minutos
3. **🎯 Preciso**: Extrae exactamente 20 números por turno
4. **📱 Responsive**: Funciona en todos los dispositivos
5. **🛡️ Robusto**: Manejo de errores y logging completo
6. **👥 Multi-usuario**: Todos ven los datos actualizados

## 🎯 Sistema Automático con Refuerzo

- **🤖 Automático principal**: Sistema funciona solo cada 5 minutos
- **🔵 Botón "Buscar"**: Refuerzo para casos excepcionales
- **🔄 Botón "Reiniciar"**: Para volver a la fecha actual
- **📊 Mensajes informativos**: Indica estado de los números
- **🎯 Robot inteligente**: Detecta e inserta números automáticamente

---

**¡El sistema funciona como un robot! 🤖** Detecta automáticamente nuevos números y los inserta en la base de datos, mostrándolos inmediatamente en la interfaz.
