# Sistema Automático de Pagos - Loterías

## Descripción General

El sistema automático de pagos detecta **automáticamente** cuando se insertan números ganadores y calcula los pagos **al instante**, sin necesidad de comandos manuales.

## 🎯 Funcionamiento Automático

### **Detección Inteligente de Turnos (DINÁMICO)**
- El sistema **monitorea constantemente** la inserción de números ganadores
- **Detecta automáticamente** qué turnos tienen jugadas
- **Procesa solo los turnos jugados** (no desperdicia recursos)
- **Funciona con TODAS las loterías configuradas** dinámicamente desde la base de datos

### **Cálculo Instantáneo**
- Cuando se inserta un número ganador → **Cálculo automático inmediato**
- Verifica todas las jugadas del turno → **Pagos automáticos**
- Inserta resultados en la tabla `results` → **Sin intervención manual**

## 🚀 Componentes del Sistema

### 1. **NumberObserver** (Detección Automática)
- **Se activa automáticamente** cuando se inserta/actualiza un número
- **Detecta turnos jugados** en tiempo real
- **Calcula pagos instantáneamente**

### 2. **AutoPaymentSystem** (Comando de Respaldo)
- **Comando independiente** que verifica cada 5 segundos
- **Procesa turnos completos** que puedan haberse perdido
- **Sistema de respaldo** para máxima confiabilidad

## 📋 Flujo de Funcionamiento

### **Escenario: Usuario apuesta en PREVIA**

1. **Usuario hace apuesta**:
   - Crea jugada en `apus` table
   - Lotería: `NAC1015` (Previa)
   - Número: `1234`, Posición: `3`

2. **Sistema detecta inserción**:
   - Se inserta número ganador: `Pos 3 = 1234`
   - **NumberObserver se activa automáticamente**

3. **Cálculo automático**:
   - Busca jugadas en `NAC1015` posición `3`
   - Encuentra la jugada del usuario
   - Verifica si `1234` coincide con `1234` ✅
   - Calcula premio según tabla de pagos

4. **Inserción automática**:
   - Inserta resultado en tabla `results`
   - **Usuario ve su premio automáticamente**

## 🎯 Ejemplo Práctico

### **Turno: PREVIA (10:15 AM)**

**Jugadas existentes:**
- Usuario A: `1234` en posición `3` - $100
- Usuario B: `5678` en posición `5` - $50

**Números ganadores insertados:**
- Posición 1: `9876`
- Posición 2: `5432`
- Posición 3: `1234` ← **¡ACCIÓN AUTOMÁTICA!**
- Posición 4: `1111`
- Posición 5: `5678` ← **¡ACCIÓN AUTOMÁTICA!**

**Resultado automático:**
- Usuario A: Premio calculado e insertado automáticamente
- Usuario B: Premio calculado e insertado automáticamente
- **Sin comandos manuales necesarios**

## 🔧 Configuración del Sistema

### **Iniciar Sistema Automático**

**Windows:**
```bash
start_auto_payment_system.bat
```

**Linux/Mac:**
```bash
chmod +x start_auto_payment_system.sh
./start_auto_payment_system.sh
```

**Comando directo:**
```bash
php artisan lottery:auto-payment --interval=5
```

### **Verificar Loterías Configuradas**

**Listar todas las loterías:**
```bash
php artisan lottery:list-all
```

**Probar sistema automático:**
```bash
php artisan lottery:test-auto-payment
```

### **Parámetros Configurables**

- `--interval=5`: Verificación cada 5 segundos (ajustable)
- Horario de funcionamiento: 10:25 AM - 12:00 AM
- Detección automática: Activada por defecto

## 📊 Monitoreo del Sistema

### **Logs Automáticos**

El sistema registra automáticamente:

```
NumberObserver - Nuevo número insertado: NAC1015 - Pos 3 - Valor 1234
NumberObserver - Procesando 2 jugadas para NAC1015 - Pos 3
NumberObserver - Resultado automático insertado: Ticket 12345 - Premio: 7000
NumberObserver - Procesamiento completado: 2 resultados insertados - Total: $7,000.00
```

### **Ubicación de Logs**
- `storage/logs/laravel.log`
- Buscar: "NumberObserver" o "AutoPaymentSystem"

## 🎯 Ventajas del Sistema Automático

### ✅ **Completamente Automático**
- No requiere comandos manuales
- Se activa automáticamente al insertar números
- Procesamiento instantáneo

### ✅ **Inteligente y Eficiente**
- Solo procesa turnos con jugadas
- No desperdicia recursos en turnos vacíos
- Detección en tiempo real

### ✅ **Confiabilidad Máxima**
- Doble sistema: Observer + Comando de respaldo
- Verificación cada 5 segundos
- Prevención de duplicados

### ✅ **Escalable**
- Funciona con cualquier cantidad de usuarios
- Procesa múltiples turnos simultáneamente
- Optimizado para alto rendimiento

## 🔍 Casos de Uso

### **Caso 1: Previa (10:15 AM)**
- Usuario apuesta en `NAC1015`
- Sistema detecta inserción de números
- Calcula pagos automáticamente
- Usuario ve premios al instante

### **Caso 2: Matutina (12:00 PM)**
- Usuario apuesta en `CHA1200`
- Sistema detecta inserción de números
- Calcula pagos automáticamente
- Usuario ve premios al instante

### **Caso 3: Vespertina (3:00 PM)**
- Usuario apuesta en `PRO1500`
- Sistema detecta inserción de números
- Calcula pagos automáticamente
- Usuario ve premios al instante

## 🚨 Solución de Problemas

### **Si no se calculan pagos automáticamente:**

1. **Verificar logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "NumberObserver"
   ```

2. **Verificar sistema activo:**
   ```bash
   php artisan lottery:auto-payment --interval=5
   ```

3. **Verificar tablas de pagos:**
   - Quiniela, Prizes, FigureOne, FigureTwo
   - Redoblona tables

### **Si hay duplicados:**
- El sistema previene duplicados automáticamente
- Verifica existencia antes de insertar
- Logs detallados para debugging

## 📁 Archivos del Sistema

### **Archivos Principales:**
- `app/Console/Commands/AutoPaymentSystem.php` - Comando principal
- `app/Observers/NumberObserver.php` - Detección automática
- `app/Models/Number.php` - Modelo con Observer

### **Archivos de Inicio:**
- `start_auto_payment_system.bat` - Windows
- `start_auto_payment_system.sh` - Linux/Mac

### **Documentación:**
- `SISTEMA_AUTOMATICO_PAGOS.md` - Esta documentación

## 🚀 Sistema Dinámico de Loterías

### **Funciona con TODAS las Loterías Configuradas**

El sistema es **completamente dinámico** y funciona con cualquier lotería que esté configurada en la base de datos:

#### **Generación Automática de Códigos**
```php
// El sistema genera códigos automáticamente:
$cityCode = $number->city->code;        // Ej: NAC, CHA, PRO
$extractTime = $number->extract->time;  // Ej: 10:15, 12:00, 15:00
$timeFormatted = str_replace(':', '', $extractTime); // Ej: 1015, 1200, 1500
$lotteryCode = $cityCode . $timeFormatted; // Ej: NAC1015, CHA1200, PRO1500
```

#### **Ejemplos de Loterías Soportadas**
- **NAC1015** (Previa Ciudad de Buenos Aires)
- **CHA1200** (Primera Matutina Chaco)
- **PRO1500** (Vespertina Provincia)
- **MZA1800** (Nocturna Mendoza)
- **CTE2100** (Nocturna Corrientes)
- **SFE1015** (Previa Santa Fe)
- **COR1200** (Primera Matutina Córdoba)
- **RIO1500** (Vespertina Entre Ríos)
- **ORO1800** (Montevideo 18:00)
- **Y CUALQUIER OTRA** que agregues a la base de datos

### **Agregar Nuevas Loterías**

**Para agregar una nueva lotería:**

1. **Agregar ciudad y horario** en la base de datos
2. **El sistema automáticamente** la detectará
3. **No necesitas modificar código** - funciona automáticamente

**Ejemplo:**
```sql
-- Agregar nueva ciudad
INSERT INTO cities (name, code) VALUES ('NUEVA_CIUDAD', 'NUE');

-- Agregar nuevo horario
INSERT INTO extracts (name, time) VALUES ('Nuevo Turno', '14:30');

-- El sistema automáticamente generará: NUE1430
```

## 🎉 Resultado Final

**El sistema ahora es completamente automático y dinámico:**

1. ✅ **Usuario apuesta** → Jugada guardada
2. ✅ **Números se insertan** → Detección automática
3. ✅ **Pagos calculados** → Automáticamente
4. ✅ **Resultados insertados** → Sin comandos manuales
5. ✅ **Usuario ve premios** → Al instante
6. ✅ **Funciona con TODAS las loterías** → Dinámicamente

**¡No más comandos manuales necesarios!** 🚀
