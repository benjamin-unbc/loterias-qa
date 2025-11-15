# Instrucciones para Medir Latencia de Base de Datos

Este documento explica cómo medir la latencia de red y el tiempo de conexión a MySQL para diagnosticar por qué el gestor de jugadas es más lento en QA que en local.

## 📋 Métodos de Medición

### Método 1: Ping al Servidor (Más Simple)

**En PowerShell (Windows):**
```powershell
# Ejecutar el script de PowerShell
.\test_ping.ps1
```

**O manualmente desde CMD/PowerShell:**
```cmd
# Primero, necesitas saber el DB_HOST de tu .env
# Luego ejecuta:
ping DB_HOST -n 10

# Ejemplo si tu DB_HOST es "mysql.tusuerte22.store":
ping mysql.tusuerte22.store -n 10
```

**En Linux/Mac:**
```bash
# Si tu DB_HOST está en .env, primero obtén el valor:
grep DB_HOST .env

# Luego haz ping:
ping -c 10 TU_DB_HOST
```

### Método 2: Test de Conexión MySQL Simple (Sin Laravel)

Este script NO requiere Laravel, solo PHP con PDO:

```bash
php test_mysql_connection.php
```

**Ventajas:**
- Mide el tiempo real de conexión a MySQL
- No requiere Laravel cargado
- Más rápido de ejecutar

### Método 3: Test Completo con Laravel (Más Detallado)

Este script mide conexión, consultas, validación de FK e inserción:

```bash
php test_db_latency.php
```

**Ventajas:**
- Mide tiempos reales de operaciones que usa tu aplicación
- Incluye validación de foreign key
- Mide inserción real de jugadas

## 🔍 Interpretación de Resultados

### Ping (Latencia de Red)
- **< 10 ms**: Excelente - Servidor local o misma red
- **10-50 ms**: Bueno - Misma región, impacto mínimo
- **50-100 ms**: Regular - Diferente región, puede notarse
- **> 100 ms**: Alta - Puede causar retrasos significativos

### Conexión MySQL
- **< 5 ms**: Excelente - Base de datos local
- **5-20 ms**: Bueno - Servidor en misma red
- **20-50 ms**: Regular - Servidor remoto, latencia notable
- **50-100 ms**: Moderado - Retrasos de 50-200ms por operación
- **> 100 ms**: Alta latencia - Afecta significativamente el rendimiento

### Tiempo de Inserción de Jugada
El tiempo total de inserción incluye:
1. Conexión a BD: ~X ms
2. Validación de FK (consulta a users): ~X * 1.5 ms
3. Inserción en plays: ~X * 1.2 ms
4. **Total estimado: ~X * 3.7 ms**

Si la conexión tarda 30ms, la inserción completa puede tardar ~110ms.

## 🎯 Comparación Local vs QA

Para diagnosticar el problema:

1. **Ejecuta los tests en LOCAL:**
   ```bash
   php test_mysql_connection.php
   php test_db_latency.php
   ```

2. **Ejecuta los tests en QA:**
   ```bash
   php test_mysql_connection.php
   php test_db_latency.php
   ```

3. **Compara los resultados:**
   - Si en local es < 5ms y en QA es > 50ms, **la latencia de red es el problema**
   - Si ambos son similares, el problema puede ser otro (carga del servidor, configuración, etc.)

## 📝 Notas Importantes

- **Ping puede fallar** si el servidor bloquea ICMP (ping). En ese caso, usa los scripts PHP.
- **Los scripts PHP** miden la latencia real de MySQL, que es lo que realmente importa.
- **En producción/QA**, el servidor de BD suele estar en un servidor diferente, por eso hay más latencia.

## 🛠️ Soluciones Posibles (si la latencia es alta)

1. **Optimizar conexiones**: Usar connection pooling
2. **Cache**: Cachear más datos para reducir consultas
3. **Índices**: Asegurar que las tablas tengan índices adecuados
4. **Servidor más cercano**: Mover la BD a un servidor más cercano geográficamente
5. **Conexión persistente**: Usar conexiones persistentes de MySQL

## ⚠️ Importante

Después de ejecutar los tests, puedes eliminar estos archivos:
- `test_db_latency.php`
- `test_mysql_connection.php`
- `test_ping.ps1`
- `INSTRUCCIONES_TEST_LATENCIA.md`

O mantenerlos para futuras pruebas de rendimiento.

