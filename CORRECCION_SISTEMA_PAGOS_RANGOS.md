# Corrección del Sistema de Pagos por Rangos de Posiciones

## Problema Identificado

El sistema de pagos estaba funcionando incorrectamente. Solo pagaba cuando el número ganador salía **exactamente en la posición apostada**, pero según las reglas del negocio, debería pagar cuando el número sale en **cualquier posición dentro del rango de la tabla apostada**.

### Ejemplos del Problema:
- Si apostaste a posición 20 y tu número salió en posición 17 → **NO PAGABA** (debería pagar)
- Si apostaste a posición 5 y tu número salió en posición 2 → **NO PAGABA** (debería pagar)
- Si apostaste a posición 10 y tu número salió en posición 8 → **NO PAGABA** (debería pagar)

## Solución Implementada

### Reglas de Pago Corregidas:

1. **Quiniela (Posición 1)**: Mantiene el comportamiento actual (solo paga si sale exactamente en posición 1)
2. **Otras Tablas (Posiciones 2-20)**: Ahora paga por rango de posiciones:
   - Si apostaste a posición 5 → Busca en posiciones 1-5
   - Si apostaste a posición 10 → Busca en posiciones 1-10  
   - Si apostaste a posición 20 → Busca en posiciones 1-20

### Tablas de Pago:
- **Prizes/FigureOne/FigureTwo**:
  - `cobra_5`: Si el número sale en posiciones 1-5
  - `cobra_10`: Si el número sale en posiciones 6-10
  - `cobra_20`: Si el número sale en posiciones 11-20

## Archivos Modificados

### 1. `app/Jobs/CalculateLotteryResults.php`
- **Método modificado**: Lógica principal de cálculo de resultados
- **Cambios**:
  - Agregado método `getSearchRangeForPosition()` para determinar rangos de búsqueda
  - Modificada la lógica para buscar números ganadores en rangos apropiados
  - Mantenida la lógica especial para posición 1 (quiniela)

### 2. `app/Observers/NumberObserver.php`
- **Método modificado**: `processAutoPaymentsForNumber()`
- **Cambios**:
  - Agregado método `getMatchingPlaysForNumber()` para buscar jugadas en rangos
  - Corregida la búsqueda de jugadas para incluir rangos apropiados
  - Mantenida la lógica especial para posición 1

### 3. `app/Console/Commands/AutoPaymentSystem.php`
- **Método modificado**: `calculatePlayResult()`
- **Cambios**:
  - Agregado método `findWinningNumberInRange()` para buscar números en rangos
  - Agregado método `getSearchRangeForPosition()` para determinar rangos
  - Corregida la búsqueda de números ganadores

## Ejemplos de Funcionamiento Corregido

### Ejemplo 1: Apuesta a Posición 20
- **Apuesta**: Número `1234` en posición 20
- **Resultado**: Número `1234` sale en posición 17
- **Antes**: ❌ No pagaba (buscaba solo en posición 20)
- **Ahora**: ✅ Paga con multiplicador `cobra_20` (busca en posiciones 1-20)

### Ejemplo 2: Apuesta a Posición 5
- **Apuesta**: Número `22` en posición 5
- **Resultado**: Número `22` sale en posición 2
- **Antes**: ❌ No pagaba (buscaba solo en posición 5)
- **Ahora**: ✅ Paga con multiplicador `cobra_5` (busca en posiciones 1-5)

### Ejemplo 3: Apuesta a Posición 10
- **Apuesta**: Número `567` en posición 10
- **Resultado**: Número `567` sale en posición 8
- **Antes**: ❌ No pagaba (buscaba solo en posición 10)
- **Ahora**: ✅ Paga con multiplicador `cobra_10` (busca en posiciones 1-10)

### Ejemplo 4: Quiniela (Sin Cambios)
- **Apuesta**: Número `6` en posición 1
- **Resultado**: Número `6` sale en posición 1
- **Antes**: ✅ Pagaba con tabla quiniela
- **Ahora**: ✅ Sigue pagando con tabla quiniela (sin cambios)

## Logging Mejorado

Se agregó logging detallado para facilitar el debugging:
- Indica la posición apostada vs la posición donde realmente salió el número
- Muestra qué tabla de pagos se está usando
- Registra el cálculo final del premio

## Compatibilidad

- ✅ **Quiniela**: Sin cambios (solo posición exacta)
- ✅ **Redoblonas**: Sin cambios (lógica independiente)
- ✅ **Tablas Prizes/FigureOne/FigureTwo**: Corregidas para pagar por rangos
- ✅ **Sistema automático**: Funciona con la nueva lógica
- ✅ **Jobs manuales**: Funcionan con la nueva lógica

## Pruebas Recomendadas

1. **Probar apuestas a posición 5** con números que salgan en posiciones 1-5
2. **Probar apuestas a posición 10** con números que salgan en posiciones 1-10
3. **Probar apuestas a posición 20** con números que salgan en posiciones 1-20
4. **Verificar que quiniela** sigue funcionando igual (solo posición 1)
5. **Verificar redoblonas** siguen funcionando igual

## Fecha de Implementación
**Fecha**: $(date)
**Desarrollador**: AI Assistant
**Estado**: ✅ Implementado y listo para pruebas
