# 📚 Documentación - Flujo de Trabajo con Git

## 🎯 **Resumen del Flujo Completo**

1. **Desarrollo Local** → 2. **Push a GitHub** → 3. **Aprobar en GitHub** → 4. **Pull en cPanel** → 5. **Desplegar**

---

## 🖥️ **PASO 1: DESARROLLO LOCAL**

### **Verificar estado del repositorio:**
```bash
git status
```

### **Agregar archivos modificados:**
```bash
git add app/Services/WinningNumbersService.php
# O para agregar todos los cambios:
git add .
```

### **Hacer commit con mensaje descriptivo:**
```bash
git commit -m "Descripción clara del cambio realizado

- Detalle específico 1
- Detalle específico 2
- Detalle específico 3"
```

### **Subir cambios a GitHub:**
```bash
git push origin qa_loterias
```

---

## 🌐 **PASO 2: APROBAR EN GITHUB**

1. **Ir a GitHub**: https://github.com/benjamin-unbc/loterias-qa
2. **Buscar Pull Request pendiente** (si existe)
3. **Revisar los cambios** propuestos
4. **Aprobar y hacer merge** del Pull Request
5. **Confirmar** que los cambios están en la rama `qa_loterias`

---

## 🖥️ **PASO 3: DESPLEGAR EN CPANEL**

### **3.1. Conectarse a la terminal de cPanel:**
- Ir a cPanel → Terminal
- Navegar al directorio del proyecto:
```bash
cd /home16/unbcoll1/public_html/qa.tusuerte22.store
```

### **3.2. Verificar estado actual:**
```bash
git status
```

### **3.3. Ver los cambios disponibles en la rama remota:**
```bash
git fetch origin
git log HEAD..origin/qa_loterias --oneline
```

### **3.4. Hacer pull de los cambios:**
```bash
git pull origin qa_loterias
```

### **3.5. Verificar que los cambios se aplicaron:**
```bash
git log --oneline -5
```

---

## 🔧 **PASO 4: CONFIGURAR Y PROBAR**

### **4.1. Ejecutar migraciones (si las hay):**
```bash
php artisan migrate
```

### **4.2. Limpiar caché de Laravel:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### **4.3. Probar el sistema:**
```bash
php artisan lottery:auto-update
```

### **4.4. Ver logs:**
```bash
tail -20 storage/logs/laravel.log
```

---

## 🚨 **COMANDOS DE EMERGENCIA**

### **Si hay conflictos durante el pull:**
```bash
# Guardar cambios locales temporalmente
git stash

# Hacer pull
git pull origin qa_loterias

# Recuperar cambios locales
git stash pop
```

### **Si hay archivos no rastreados que interfieren:**
```bash
# Ver archivos no rastreados
git status

# Limpiar archivos no rastreados
git clean -fd

# Hacer pull
git pull origin qa_loterias
```

### **Si necesitas revertir cambios:**
```bash
# Ver historial
git log --oneline -10

# Revertir a un commit específico
git reset --hard [COMMIT_HASH]

# Forzar push (¡CUIDADO!)
git push origin qa_loterias --force
```

---

## 📋 **COMANDOS ÚTILES ADICIONALES**

### **Ver diferencias entre commits:**
```bash
git diff HEAD~1 HEAD
```

### **Ver qué archivos cambiaron:**
```bash
git diff --name-only HEAD~1 HEAD
```

### **Ver el contenido de un commit específico:**
```bash
git show [COMMIT_HASH]
```

### **Ver ramas disponibles:**
```bash
git branch -a
```

### **Cambiar de rama:**
```bash
git checkout [NOMBRE_RAMA]
```

---

## ⚠️ **CONSIDERACIONES IMPORTANTES**

### **Antes de hacer push:**
- ✅ Verificar que el código funciona localmente
- ✅ Hacer commit con mensaje descriptivo
- ✅ No incluir archivos sensibles (.env, logs, etc.)

### **Antes de hacer pull en cPanel:**
- ✅ Hacer backup de la base de datos
- ✅ Verificar que no hay cambios locales importantes
- ✅ Tener acceso a la terminal de cPanel

### **Después del pull:**
- ✅ Ejecutar migraciones si es necesario
- ✅ Limpiar caché
- ✅ Probar funcionalidades críticas
- ✅ Verificar logs de errores

---

## 🎯 **FLUJO COMPLETO RESUMIDO**

```bash
# 1. LOCAL - Desarrollo
git add .
git commit -m "Descripción del cambio"
git push origin qa_loterias

# 2. GITHUB - Aprobar Pull Request (manual)

# 3. CPANEL - Desplegar
cd /home16/unbcoll1/public_html/qa.tusuerte22.store
git status
git pull origin qa_loterias
php artisan migrate
php artisan config:clear
php artisan lottery:auto-update
```

---

## 📞 **SOPORTE**

Si tienes problemas con el flujo de Git:

1. **Verificar estado**: `git status`
2. **Ver logs**: `git log --oneline -5`
3. **Verificar rama**: `git branch -a`
4. **Verificar remoto**: `git remote -v`

**¡Recuerda siempre hacer backup antes de cambios importantes!** 🛡️
