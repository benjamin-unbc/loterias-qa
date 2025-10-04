# 📋 DOCUMENTACIÓN TÉCNICA - SISTEMA AUTOMÁTICO DE NÚMEROS GANADORES

## 📖 ÍNDICE
1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Componentes Implementados](#componentes-implementados)
4. [Base de Datos](#base-de-datos)
5. [Servicios](#servicios)
6. [Comandos Artisan](#comandos-artisan)
7. [Sistema de Notificaciones](#sistema-de-notificaciones)
8. [Interfaz de Usuario](#interfaz-de-usuario)
9. [Configuración y Deployment](#configuración-y-deployment)
10. [Mantenimiento](#mantenimiento)

---

## 🎯 RESUMEN EJECUTIVO

### Objetivo
Implementar un sistema completamente automático que extraiga, procese e inserte números ganadores de lotería desde `vivitusuerte.com` cada 5 minutos, con notificaciones en tiempo real para administradores.

### Funcionalidades Principales
- ✅ **Extracción automática** cada 5 minutos
- ✅ **Inserción automática** en base de datos
- ✅ **Notificaciones en tiempo real** para administradores
- ✅ **Interfaz diferenciada** por roles de usuario
- ✅ **Sistema de refuerzo** manual para administradores
- ✅ **Soporte para 15 ciudades** con turnos específicos

---

## 🏗️ ARQUITECTURA DEL SISTEMA

### Diagrama de Flujo
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Scheduler     │───▶│  WinningNumbers  │───▶│  Base de Datos  │
│   (Cada 5 min)  │    │     Service      │    │   (Numbers)     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ System Notif.   │    │  Livewire UI     │    │  Auto-Refresh   │
│   (BD + UI)     │    │  (Extracts)      │    │  (JavaScript)   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Tecnologías Utilizadas
- **Backend**: Laravel 10, PHP 8.1+
- **Frontend**: Livewire, Tailwind CSS, JavaScript
- **Base de Datos**: MySQL
- **Scheduler**: Laravel Task Scheduler
- **Web Scraping**: cURL, DOMDocument, DOMXPath

---

## 🔧 COMPONENTES IMPLEMENTADOS

### 1. Servicio Principal: `WinningNumbersService`
**Ubicación**: `app/Services/WinningNumbersService.php`

#### Funcionalidades:
- Extracción de números desde `vivitusuerte.com/pizarra/{ciudad}`
- Parsing HTML con DOMDocument y DOMXPath
- Mapeo específico de ciudades y turnos
- Logging personalizado para debugging

#### Métodos Principales:
```php
public function extractWinningNumbers(string $city): ?array
public function getAvailableCities(): array
private function getCityUrl(string $city): ?string
private function parseWinningNumbers(string $html, string $city): ?array
private function extractNumbersFromTurn(\DOMXPath $xpath, string $turn, $tables, string $city): array
```

#### Ciudades Soportadas:
```php
[
    'Ciudad', 'Santa Fé', 'Provincia', 'Entre Ríos', 'Córdoba',
    'Corrientes', 'Chaco', 'Neuquén', 'Misiones', 'Mendoza',
    'Río Negro', 'Tucumán', 'Santiago', 'Jujuy', 'Salta'
]
```

#### Configuración Especial:
- **Jujuy y Salta**: Solo extraen Matutina, Vespertina, Nocturna
- **Otras ciudades**: Extraen todos los turnos (La Previa, Primera, Matutina, Vespertina, Nocturna)

### 2. Comando Artisan: `AutoUpdateLotteryNumbers`
**Ubicación**: `app/Console/Commands/AutoUpdateLotteryNumbers.php`

#### Funcionalidades:
- Ejecución automática cada 5 minutos
- Extracción de números para todas las ciudades
- Inserción en base de datos
- Creación de notificaciones del sistema
- Logging detallado de operaciones

#### Uso:
```bash
# Ejecución manual
php artisan lottery:auto-update

# Ejecución forzada
php artisan lottery:auto-update --force
```

#### Configuración en Scheduler:
```php
// app/Console/Kernel.php
$schedule->command('lottery:auto-update')
         ->everyFiveMinutes()
         ->withoutOverlapping()
         ->runInBackground();
```

### 3. Sistema de Notificaciones
**Ubicación**: `app/Models/SystemNotification.php`, `app/Livewire/SystemNotifications.php`

#### Base de Datos:
```sql
CREATE TABLE system_notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,           -- 'success', 'info', 'warning', 'error'
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,                      -- Datos adicionales
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Funcionalidades:
- Notificaciones automáticas cada 5 minutos
- Solo visibles para administradores
- Interfaz en tiempo real con auto-refresh
- Marcado de notificaciones como leídas

### 4. Componente Livewire: `Extracts`
**Ubicación**: `app/Livewire/Admin/Extracts.php`

#### Funcionalidades Diferenciadas por Rol:

##### Administrador:
- Búsqueda real y refuerzo automático
- Extracción de números desde vivitusuerte.com
- Mensajes detallados de resultados

##### Otros Roles:
- Solo recarga de página
- Visualización de datos existentes
- Mensaje simple de recarga

#### Métodos Principales:
```php
public function searchDate()                    // Búsqueda diferenciada por rol
public function reinforceAutomaticUpdate()      // Refuerzo para administradores
private function insertCityNumbersToDatabase()  // Inserción en BD
```

---

## 🗄️ BASE DE DATOS

### Tablas Principales

#### `numbers`
```sql
CREATE TABLE numbers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    city_id BIGINT NOT NULL,
    extract_id BIGINT NOT NULL,
    index INT NOT NULL,                    -- Posición (1-20)
    value VARCHAR(4) NOT NULL,             -- Número de 4 dígitos
    date DATE NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(id),
    FOREIGN KEY (extract_id) REFERENCES extracts(id)
);
```

#### `cities`
```sql
CREATE TABLE cities (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    extract_id BIGINT NOT NULL,            -- 1=La Previa, 2=Primera, 3=Matutina, 4=Vespertina, 5=Nocturna
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,             -- Código único (NAC, SFE, PRO, etc.)
    time TIME NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `extracts`
```sql
CREATE TABLE extracts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,            -- 'PREVIA', 'PRIMERA', 'MATUTINA', 'VESPERTINA', 'NOCTURNA'
    date DATE,
    time TIME,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Mapeo de Ciudades
```php
$cityMapping = [
    'Ciudad' => 'NAC',      'Santa Fé' => 'SFE',     'Provincia' => 'PRO',
    'Entre Ríos' => 'RIO',  'Córdoba' => 'COR',      'Corrientes' => 'CTE',
    'Chaco' => 'CHA',       'Neuquén' => 'NQN',      'Misiones' => 'MIS',
    'Mendoza' => 'MZA',     'Río Negro' => 'Rio',    'Tucumán' => 'Tucu',
    'Santiago' => 'San',    'Jujuy' => 'JUJ',        'Salta' => 'Salt'
];
```

### Mapeo de Turnos
```php
$turnMapping = [
    'La Previa' => 1,       'Primera' => 2,          'Matutina' => 3,
    'Vespertina' => 4,      'Nocturna' => 5
];
```

---

## 🌐 SERVICIOS

### 1. WinningNumbersService
**Propósito**: Extracción y procesamiento de números ganadores

#### Configuración de URLs:
```php
private function getCityUrl(string $city): ?string
{
    $cityUrls = [
        'Ciudad' => '/pizarra/ciudad',
        'Santa Fé' => '/pizarra/santa+fe',      // Espacios como +
        'Provincia' => '/pizarra/provincia',
        'Entre Ríos' => '/pizarra/entre+rios',
        'Córdoba' => '/pizarra/cordoba',
        'Corrientes' => '/pizarra/corrientes',
        'Chaco' => '/pizarra/chaco',
        'Neuquén' => '/pizarra/neuquen',
        'Misiones' => '/pizarra/misiones',
        'Mendoza' => '/pizarra/mendoza',
        'Río Negro' => '/pizarra/rio+negro',
        'Tucumán' => '/pizarra/tucuman',
        'Santiago' => '/pizarra/santiago',
        'Jujuy' => '/pizarra/jujuy',
        'Salta' => '/pizarra/salta'
    ];
    
    return $cityUrls[$city] ?? null;
}
```

#### Parsing HTML:
```php
private function parseWinningNumbers(string $html, string $city): ?array
{
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    
    // Buscar tablas por turno
    $tables = $xpath->query('//table');
    
    // Extraer números por turno
    foreach ($turns as $turn) {
        $numbers = $this->extractNumbersFromTurn($xpath, $turn, $tables, $city);
        $turnsData[$turn] = $numbers;
    }
    
    return $turnsData;
}
```

### 2. Sistema de Logging Personalizado
```php
private function log(string $message, string $level = 'info'): void
{
    if (class_exists('\Illuminate\Support\Facades\Log')) {
        \Illuminate\Support\Facades\Log::$level($message);
    } else {
        $timestamp = $this->getCurrentTime();
        echo "[$level] $message\n";
    }
}
```

---

## ⚙️ COMANDOS ARTISAN

### 1. AutoUpdateLotteryNumbers
**Comando**: `php artisan lottery:auto-update`

#### Parámetros:
- `--force`: Fuerza actualización aunque ya existan números

#### Flujo de Ejecución:
1. Verificar si ya hay números para hoy (a menos que sea forzado)
2. Obtener lista de ciudades disponibles
3. Para cada ciudad:
   - Extraer números desde vivitusuerte.com
   - Procesar cada turno
   - Insertar en base de datos
4. Crear notificación del sistema
5. Mostrar resumen de resultados

#### Salida de Ejemplo:
```
🔄 Iniciando actualización automática de números ganadores...
🏙️  Procesando 15 ciudades...
📍 Procesando: Ciudad
📍 Procesando: Santa Fé
...
📍 Procesando: Jujuy
  ✅ Matutina: 20 números procesados
📍 Procesando: Salta
  ✅ Matutina: 20 números procesados
🎉 Actualización completada exitosamente!
📊 Números nuevos: 40
🔄 Números actualizados: 0
📅 Fecha: 2025-10-02
```

### 2. Configuración del Scheduler
**Archivo**: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('playssent:update-status')->everyMinute();
    $schedule->command('fetch:plays-sent')->everyMinute();
    
    // Actualización automática de números ganadores cada 5 minutos
    $schedule->command('lottery:auto-update')
             ->everyFiveMinutes()
             ->withoutOverlapping()
             ->runInBackground();
}
```

#### Iniciar Scheduler:
```bash
# Desarrollo
php artisan schedule:work

# Producción (cron job)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔔 SISTEMA DE NOTIFICACIONES

### 1. Modelo SystemNotification
**Ubicación**: `app/Models/SystemNotification.php`

#### Métodos Principales:
```php
public static function createNotification($type, $title, $message, $data = null)
public function markAsRead()
public static function getUnread()
public static function getRecent()
```

### 2. Componente Livewire
**Ubicación**: `app/Livewire/SystemNotifications.php`

#### Funcionalidades:
- Carga automática de notificaciones
- Marcado como leídas
- Auto-refresh cada 30 segundos
- Solo visible para administradores

### 3. Interfaz de Usuario
**Ubicación**: `resources/views/livewire/system-notifications.blade.php`

#### Características:
- Botón flotante en esquina superior izquierda
- Contador de notificaciones no leídas
- Panel desplegable con historial
- Indicadores visuales por tipo de notificación

#### Tipos de Notificaciones:
- **success**: Números encontrados e insertados
- **info**: Búsqueda realizada sin resultados
- **warning**: Errores en ciudades específicas
- **error**: Errores críticos del sistema

---

## 🎨 INTERFAZ DE USUARIO

### 1. Componente Extracts
**Ubicación**: `resources/views/livewire/admin/extracts.blade.php`

#### Características:
- Filtro por fecha
- Botón "Buscar" con funcionalidad diferenciada
- Botón "Reiniciar" para volver a fecha actual
- Toggle entre "Ver solo cabeza" y "Ver extracto completo"

#### Funcionalidad Diferenciada por Rol:

##### Administrador:
```html
<button title="Reforzar búsqueda automática">
    <span>Buscar</span>
    <span wire:loading>Buscando...</span>
</button>
```

##### Otros Roles:
```html
<button title="Recargar página para ver datos actuales">
    <span>Buscar</span>
    <span wire:loading>Recargando...</span>
</button>
```

### 2. Sistema de Notificaciones
**Ubicación**: `resources/views/layouts/app.blade.php`

```html
@livewire('system-notifications')
```

#### JavaScript para Auto-refresh:
```javascript
// Auto-refresh cada 30 segundos
setInterval(() => {
    @this.call('loadNotifications');
}, 30000);
```

### 3. Sidebar Oculto
**Ubicación**: `resources/views/livewire/admin/sidebar.blade.php`

#### Elementos Ocultados:
```html
{{-- OCULTADO: Extractor de Artículos - Solo accesible por ruta directa --}}
{{-- OCULTADO: Cabezas de Lotería - Solo accesible por ruta directa --}}
{{-- OCULTADO: 20 Ganadores - Solo accesible por ruta directa --}}
```

#### Rutas Directas:
- `/extractor-interface`
- `/heads-interface`
- `/20-ganadores`

---

## 🚀 CONFIGURACIÓN Y DEPLOYMENT

### 1. Requisitos del Sistema
- **PHP**: 8.1 o superior
- **Laravel**: 10.x
- **MySQL**: 5.7 o superior
- **cURL**: Habilitado
- **DOMDocument**: Habilitado

### 2. Instalación
```bash
# Clonar repositorio
git clone [repository-url]

# Instalar dependencias
composer install
npm install

# Configurar base de datos
cp .env.example .env
# Editar .env con configuración de BD

# Ejecutar migraciones
php artisan migrate

# Compilar assets
npm run build
```

### 3. Configuración del Scheduler
```bash
# Agregar al crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

# O ejecutar en desarrollo
php artisan schedule:work
```

### 4. Variables de Entorno
```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=loterias_extract
DB_USERNAME=root
DB_PASSWORD=

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

---

## 🔧 MANTENIMIENTO

### 1. Monitoreo del Sistema
```bash
# Verificar scheduler
php artisan schedule:list

# Ver logs
tail -f storage/logs/laravel.log

# Verificar notificaciones
php artisan tinker
>>> App\Models\SystemNotification::count()
```

### 2. Comandos de Mantenimiento
```bash
# Limpiar notificaciones antiguas (opcional)
php artisan tinker
>>> App\Models\SystemNotification::where('created_at', '<', now()->subDays(30))->delete()

# Verificar números insertados hoy
php artisan tinker
>>> App\Models\Number::where('date', today())->count()

# Forzar actualización
php artisan lottery:auto-update --force
```

### 3. Troubleshooting

#### Problema: Scheduler no ejecuta
```bash
# Verificar que el cron esté configurado
crontab -l

# Verificar permisos
chmod +x artisan

# Ejecutar manualmente
php artisan schedule:run
```

#### Problema: No se extraen números
```bash
# Verificar conectividad
curl -I https://vivitusuerte.com/pizarra/ciudad

# Verificar logs
tail -f storage/logs/laravel.log

# Probar comando manualmente
php artisan lottery:auto-update --force
```

#### Problema: Notificaciones no aparecen
```bash
# Verificar que el usuario sea administrador
php artisan tinker
>>> App\Models\User::find(1)->hasRole('Administrador')

# Verificar notificaciones en BD
php artisan tinker
>>> App\Models\SystemNotification::latest()->first()
```

### 4. Backup y Recuperación
```bash
# Backup de base de datos
mysqldump -u root -p loterias_extract > backup_$(date +%Y%m%d).sql

# Backup de archivos
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path-to-project
```

---

## 📊 MÉTRICAS Y MONITOREO

### 1. Métricas del Sistema
- **Frecuencia de ejecución**: Cada 5 minutos
- **Ciudades procesadas**: 15
- **Números por ciudad/turno**: 20
- **Total máximo por ejecución**: 1,500 números
- **Tiempo promedio de ejecución**: 2-5 segundos

### 2. Logs Importantes
```bash
# Logs de extracción
grep "Auto-update completado" storage/logs/laravel.log

# Logs de errores
grep "ERROR" storage/logs/laravel.log

# Logs de notificaciones
grep "SystemNotification" storage/logs/laravel.log
```

### 3. Alertas Recomendadas
- **Scheduler no ejecuta** por más de 10 minutos
- **Errores de conectividad** con vivitusuerte.com
- **Fallos en inserción** de números
- **Notificaciones no creadas** después de ejecución

---

## 🔒 SEGURIDAD

### 1. Permisos y Roles
- **Administrador**: Acceso completo a búsqueda y refuerzo
- **Otros roles**: Solo visualización y recarga
- **Notificaciones**: Solo visibles para administradores

### 2. Validaciones
- **Números**: Validación de formato (4 dígitos)
- **Fechas**: Validación de formato y rango
- **Ciudades**: Validación contra lista permitida
- **Turnos**: Validación contra mapeo definido

### 3. Rate Limiting
- **Scheduler**: Sin solapamiento (`withoutOverlapping()`)
- **API**: Protección CSRF en endpoints
- **Web Scraping**: Delays entre requests (implementar si es necesario)

---

## 📈 ESCALABILIDAD

### 1. Optimizaciones Implementadas
- **Ejecución en background**: `runInBackground()`
- **Sin solapamiento**: `withoutOverlapping()`
- **Logging eficiente**: Solo errores y resultados importantes
- **Consultas optimizadas**: Uso de índices en BD

### 2. Mejoras Futuras Sugeridas
- **Cache de resultados**: Redis para evitar re-extracciones
- **Queue system**: Para procesamiento asíncrono
- **WebSockets**: Para notificaciones en tiempo real
- **API rate limiting**: Para proteger el scraping
- **Monitoring**: Prometheus/Grafana para métricas

---

## 📝 CHANGELOG

### Versión 1.0.0 (2025-10-02)
- ✅ Implementación inicial del sistema automático
- ✅ Soporte para 15 ciudades
- ✅ Sistema de notificaciones
- ✅ Interfaz diferenciada por roles
- ✅ Scheduler cada 5 minutos
- ✅ Auto-refresh cada 30 segundos
- ✅ Ocultación de elementos del sidebar
- ✅ Sistema de refuerzo para administradores

---

## 👥 CONTACTO Y SOPORTE

### Desarrollador
- **Nombre**: Asistente AI
- **Fecha**: 2025-10-02
- **Versión**: 1.0.0

### Documentación Adicional
- **README.md**: Instrucciones básicas de instalación
- **SISTEMA_AUTOMATICO.md**: Documentación de usuario
- **Logs**: `storage/logs/laravel.log`

---

*Documentación generada automáticamente el 2025-10-02*
