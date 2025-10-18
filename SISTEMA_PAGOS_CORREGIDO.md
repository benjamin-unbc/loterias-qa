# Sistema de Pagos Corregido - Loterías

## Descripción General

El sistema de pagos ha sido corregido para implementar correctamente todas las tablas de premios según las especificaciones del negocio.

## Tablas de Pagos Implementadas

### 1. Tabla Quiniela (Apuestas de 1 dígito)
- **Formato de apuesta**: `***X` (ejemplo: `***6`)
- **Cálculo**: Solo usa la tabla Quiniela
- **Multiplicador**: `cobra_1_cifra` de la tabla `quiniela`

**Ejemplo**: Si apuesto `***6` en posición 1 y sale `6336` en posición 1, el cálculo es: `100 x 7 = 700`

### 2. Tabla Prizes (Apuestas de 2 dígitos)
- **Formato de apuesta**: `**XX` (ejemplo: `**22`)
- **Cálculo**: Basado en la posición donde sale el número ganador
- **Multiplicadores**:
  - Posiciones 1-5: `cobra_5` de la tabla `prizes`
  - Posiciones 6-10: `cobra_10` de la tabla `prizes`
  - Posiciones 11-20: `cobra_20` de la tabla `prizes`

**Ejemplo**: Si apuesto `**22` en posición 3 y sale `4222` en posición 5, el cálculo es: `100 x 14 = 1400`

### 3. Tabla FigureOne (Apuestas de 3 dígitos)
- **Formato de apuesta**: `*XXX` (ejemplo: `*123`)
- **Cálculo**: Basado en la posición donde sale el número ganador
- **Multiplicadores**:
  - Posiciones 1-5: `cobra_5` de la tabla `figureone`
  - Posiciones 6-10: `cobra_10` de la tabla `figureone`
  - Posiciones 11-20: `cobra_20` de la tabla `figureone`

**Ejemplo**: Si apuesto `*123` en posición 4 y sale `4123` en posición 5, el cálculo es: `100 x 120 = 12000`

### 4. Tabla FigureTwo (Apuestas de 4 dígitos)
- **Formato de apuesta**: `XXXX` (ejemplo: `4545`)
- **Cálculo**: Basado en la posición donde sale el número ganador
- **Multiplicadores**:
  - Posiciones 1-5: `cobra_5` de la tabla `figuretwo`
  - Posiciones 6-10: `cobra_10` de la tabla `figuretwo`
  - Posiciones 11-20: `cobra_20` de la tabla `figuretwo`

**Ejemplo**: Si apuesto `4545` en posición 3 y sale `4545` en posición 5, el cálculo es: `100 x 700 = 70000`

## Lógica de Cálculo

### Algoritmo Principal

1. **Identificación del tipo de apuesta**: Se determina por la cantidad de dígitos en el número apostado (sin asteriscos)
2. **Verificación de acierto**: Se compara si los últimos N dígitos del número ganador coinciden con la apuesta
3. **Cálculo del premio**: 
   - **SI SALE EN POSICIÓN 1 (A LA CABEZA)**: SIEMPRE usar tabla Quiniela
   - **SI SALE EN OTRAS POSICIONES (2-20)**: Usar tabla específica según número de dígitos
4. **Aplicación de multiplicador**: Se multiplica el importe apostado por el multiplicador correspondiente

### 🎯 **REGLA FUNDAMENTAL:**
**POSICIÓN 1 = A LA CABEZA = SIEMPRE TABLA QUINIELA**

### Método `calculatePositionBasedPrize()`

```php
private function calculatePositionBasedPrize(int $position, $payoutTable): float
{
    if ($position <= 5) {
        return (float) ($payoutTable->cobra_5 ?? 0);
    } elseif ($position <= 10) {
        return (float) ($payoutTable->cobra_10 ?? 0);
    } elseif ($position <= 20) {
        return (float) ($payoutTable->cobra_20 ?? 0);
    }
    
    return 0.0; // No hay premio si sale después de la posición 20
}
```

## Campos de la Tabla de Resultados

Los resultados se guardan en la tabla `results` con los siguientes campos:

- `user_id`: ID del usuario que hizo la apuesta
- `ticket`: Número de ticket
- `lottery`: Lotería donde se apostó
- `number`: Número apostado
- `position`: Posición apostada
- `numR`: Número de redoblona (si aplica)
- `posR`: Posición de redoblona (si aplica)
- `XA`: Campo estático (valor 'X')
- `import`: Importe apostado
- `aciert`: Monto del premio calculado
- `date`: Fecha del sorteo
- `time`: Hora del sorteo
- `created_at`: Fecha de creación del registro
- `updated_at`: Fecha de última actualización

## Logging

El sistema incluye logging detallado para facilitar el debugging:

- Log de aciertos por tipo de apuesta (1, 2, 3, 4 dígitos)
- Log del cálculo final (importe x multiplicador = premio)
- Log de inserción de resultados en la base de datos

## Archivos Modificados

- `app/Jobs/CalculateLotteryResults.php`: Lógica principal de cálculo de premios
- Imports agregados para los modelos: `PrizesModel`, `FigureOneModel`, `FigureTwoModel`

## Notas Importantes

1. **Redoblona**: No se modificó la lógica de redoblona, solo se corrigieron las apuestas simples
2. **Posiciones**: El sistema considera que las posiciones van del 1 al 20
3. **Validación**: Se valida que existan todas las tablas de pagos antes de procesar
4. **Eliminación de duplicados**: Se eliminan resultados existentes antes de calcular nuevos para evitar duplicados

## Ejemplos de Uso

### Ejemplo 1: Apuesta de 4 dígitos EN POSICIÓN 1 (A LA CABEZA)
- **Apuesta**: `9984` en posición 1, importe 100
- **Número ganador**: `9984` en posición 1
- **Cálculo**: 100 x 3500 = 350000 (tabla Quiniela, 4 cifras - POSICIÓN 1)

### Ejemplo 2: Apuesta de 3 dígitos EN POSICIÓN 1 (A LA CABEZA)
- **Apuesta**: `*676` en posición 1, importe 100
- **Número ganador**: `8676` en posición 1
- **Cálculo**: 100 x 600 = 60000 (tabla Quiniela, 3 cifras - POSICIÓN 1)

### Ejemplo 3: Apuesta de 2 dígitos EN OTRA POSICIÓN
- **Apuesta**: `**22` en posición 3, importe 100
- **Número ganador**: `4222` en posición 5
- **Cálculo**: 100 x 14 = 1400 (tabla Prizes, posición 5)

### Ejemplo 4: Apuesta de 3 dígitos EN OTRA POSICIÓN
- **Apuesta**: `*123` en posición 4, importe 100
- **Número ganador**: `4123` en posición 10
- **Cálculo**: 100 x 60 = 6000 (tabla FigureOne, posición 10)
