# 👁️ Modal de Detalles del Cliente - Documentación

## 📋 Descripción
Se ha implementado un modal emergente que permite a los administradores ver información detallada de cada cliente, incluyendo:

- **Jugadas Enviadas**: Lista de todas las jugadas enviadas por el cliente
- **Extractos**: Extractos de lotería disponibles por fecha
- **Resultados**: Tabla de resultados del cliente
- **Liquidaciones**: Cálculos de liquidación diaria del cliente

## 🚀 Funcionalidades Implementadas

### 1. Botón de "Ojo" en la Lista de Clientes
- **Ubicación**: Columna "Acciones" en la tabla de clientes
- **Permiso requerido**: `ver clientes`
- **Funcionalidad**: Al hacer clic, abre el modal con los detalles del cliente

### 2. Modal de Detalles del Cliente
- **Componente**: `ClientDetailsModal`
- **Archivo**: `app/Livewire/Admin/Clients/ClientDetailsModal.php`
- **Vista**: `resources/views/livewire/admin/clients/client-details-modal.blade.php`

### 3. Cuatro Pestañas de Información

#### 📤 Jugadas Enviadas
- **Filtros disponibles**:
  - Fecha
  - Tipo (Jugadas/Redoblonas/Todos)
  - Elementos por página (5, 10, 25)
- **Información mostrada**:
  - Hora de envío
  - Número de ticket
  - Tipo de jugada
  - APU
  - Cantidad de loterías
  - Importe
  - Estado (Activo/Inactivo)
- **Total**: Suma de importes de jugadas activas

#### 📋 Extractos
- **Filtros disponibles**:
  - Fecha
- **Información mostrada**:
  - Nombre del extracto
  - Ciudades con sus códigos
  - Números por posición (1-20)
  - Valores actuales de los números

#### 🏆 Resultados
- **Filtros disponibles**:
  - Fecha
  - Elementos por página (5, 10, 25)
- **Información mostrada**:
  - Número de ticket
  - Loterías
  - Número jugado
  - Posición
  - Número ganador (NumR)
  - Posición ganadora (PosR)
  - Importe apostado
  - Aciertos obtenidos
- **Total**: Suma de aciertos del cliente

#### 💰 Liquidaciones
- **Filtros disponibles**:
  - Fecha (máximo hasta ayer)
- **Diseño**: 
  - **Formato de boleta**: Idéntico al diseño de la vista normal de liquidaciones
  - **Ancho fijo**: 80mm (tamaño de boleta estándar)
  - **Fondo blanco**: Con texto negro para simular una boleta real
  - **Centrado**: La boleta se muestra centrada en el modal
- **Información mostrada**:
  - **Header**: ID del cliente y fecha de liquidación
  - **Detalle de resultados**: Tabla con loterías, números, posiciones, apuestas y ganancias
  - **Totales por horarios**: Previa, Mañana, Matutina, Tarde, Noche
  - **Cálculos principales**:
    - JUGADAS / TOTAL PASE
    - COMIS. J. 20% (comisión)
    - TOT.ACIERT (total aciertos)
    - DEJA PASE / TOTAL DEJA
    - GENER. DEJA
    - ANTERI (arrastre anterior)
    - UD DEJA (lo que deja el cliente)
    - ARRASTRE (para el siguiente día)
  - **Comisión de fin de semana**: Se aplica el 30% los sábados (COMI DEJA SEM)
- **Características especiales**:
  - Cálculo automático de arrastre basado en datos históricos del cliente
  - Manejo especial para días sábado (comisión semanal)
  - Filtro de fecha limitado a días anteriores
  - Diseño idéntico a la vista normal para consistencia

## 🛠️ Archivos Modificados/Creados

### Nuevos Archivos:
1. `app/Livewire/Admin/Clients/ClientDetailsModal.php` - Componente Livewire
2. `resources/views/livewire/admin/clients/client-details-modal.blade.php` - Vista del modal

### Archivos Modificados:
1. `resources/views/livewire/admin/clients/show-clients.blade.php` - Agregado botón de ojo y modal

## 🔧 Configuración Técnica

### Permisos
- Utiliza el permiso existente `ver clientes`
- Solo usuarios con este permiso pueden ver el botón de ojo

### Relaciones de Base de Datos
- **Client** → **User** (por email)
- **User** → **PlaysSentModel** (por user_id)
- **User** → **Result** (por user_id)
- **Extract** → **City** → **Number** (para extractos)

### Características del Modal
- **Responsive**: Se adapta a diferentes tamaños de pantalla
- **Paginación**: Para jugadas enviadas y resultados
- **Filtros en tiempo real**: Los datos se actualizan al cambiar filtros
- **Diseño consistente**: Sigue el tema oscuro del sistema
- **Cierre**: Click en overlay o botón X

## 🎨 Estilos y Diseño
- **Tema**: Oscuro consistente con el sistema
- **Colores**: 
  - Fondo principal: `#1b1f22`
  - Fondo secundario: `#22272b`
  - Acentos: `#yellow-200`
- **Iconos**: FontAwesome
- **Transiciones**: Suaves para mejor UX

## 📱 Responsive Design
- **Desktop**: Modal de ancho completo (max-w-7xl)
- **Mobile**: Se adapta al ancho de pantalla
- **Tablas**: Scroll horizontal en pantallas pequeñas

## 🔍 Casos de Uso
1. **Administrador revisa jugadas de un cliente**: Filtra por fecha y tipo
2. **Verificar extractos del día**: Consulta números ganadores
3. **Revisar resultados de un cliente**: Ve aciertos y premios
4. **Análisis de liquidación**: Calcula ganancias y arrastre del cliente
5. **Análisis de actividad**: Compara jugadas vs resultados vs liquidaciones
6. **Control de comisiones**: Verifica cálculos de comisión y arrastre
7. **Seguimiento histórico**: Revisa evolución de un cliente en el tiempo

## ⚠️ Consideraciones
- El modal solo muestra datos del usuario asociado al cliente
- Si un cliente no tiene usuario asociado, no se mostrarán datos
- Los filtros se mantienen por sesión del modal
- La paginación se resetea al cambiar de pestaña
- Las liquidaciones solo se pueden consultar para fechas anteriores (hasta ayer)
- Los cálculos de arrastre se basan en datos históricos del cliente específico

## 🚀 Próximas Mejoras Posibles
- Exportar datos a PDF/Excel
- Gráficos de actividad del cliente
- Historial de cambios
- Notas del administrador
- Filtros avanzados (rango de fechas, importes, etc.)
