# 📦 ARCHIVOS A SUBIR AL SERVIDOR

## ✅ Archivos modificados que debes subir:

### 1. Sistema de Autenticación Persistente
```
/auth-check.js          ⚠️ IMPORTANTE - Rutas absolutas corregidas
/auth-styles.css        ✅ NUEVO - Estilos del menú de usuario
```

### 2. Páginas HTML actualizadas
```
/index.html            ⚠️ IMPORTANTE - Botón login y scripts agregados
/blog.html             ⚠️ IMPORTANTE - Scripts de auth agregados
/post.html             ⚠️ IMPORTANTE - Rutas absolutas + scripts auth
/vixy.html             ⚠️ IMPORTANTE - Scripts de auth agregados
/vixymastery.html      ⚠️ IMPORTANTE - Botón login + scripts auth
/profile.html          ✅ Scripts de auth agregados
/settings.html         ✅ Scripts de auth agregados
/jorise.html           ✅ Scripts de auth agregados
```

### 3. JavaScript corregido
```
/admin.js              ⚠️ IMPORTANTE - Scripts/Tools en edit modal
```

### 4. Scripts de diagnóstico (opcional)
```
/test-newsletter.php           ✅ NUEVO - Diagnóstico de newsletter corregido
/verificar-suscriptores.php    ✅ NUEVO - Ver todos los suscriptores
```

---

## 🚨 ARCHIVOS CRÍTICOS (Subir primero)

Estos archivos solucionan el error 404:

1. **auth-check.js** - Cambios críticos en líneas 15 y 137:
   - `fetch('auth.php')` → `fetch('/auth.php')`
   - Todos los enlaces del dropdown ahora usan rutas absolutas

2. **post.html** - Mobile menu corregido con `/login.html`

3. **index.html**, **blog.html**, **vixy.html** - Incluyen sistema de autenticación

---

## 📋 CHECKLIST DE SUBIDA

### Paso 1: Archivos JavaScript (MUY IMPORTANTE)
- [ ] Subir `/auth-check.js` (corrige 404)
- [ ] Subir `/admin.js` (agrega Scripts/Tools en edit)

### Paso 2: Archivos CSS
- [ ] Subir `/auth-styles.css` (estilos nuevos del menú)

### Paso 3: Páginas HTML principales
- [ ] Subir `/index.html`
- [ ] Subir `/blog.html`
- [ ] Subir `/post.html` ⚠️ MUY IMPORTANTE
- [ ] Subir `/vixy.html`
- [ ] Subir `/vixymastery.html`

### Paso 4: Páginas HTML secundarias
- [ ] Subir `/profile.html`
- [ ] Subir `/settings.html`
- [ ] Subir `/jorise.html`

### Paso 5: Scripts de diagnóstico (opcional)
- [ ] Subir `/test-newsletter.php`
- [ ] Subir `/verificar-suscriptores.php`

---

## 🔍 CÓMO VERIFICAR QUE TODO FUNCIONA

### 1. Prueba de 404 corregido:
1. Ve a cualquier artículo: `https://vineksec.online/post/titulo-123`
2. Haz clic en cualquier enlace del navbar
3. ✅ Debería funcionar correctamente (sin 404)

### 2. Prueba de Login Persistente:
1. Haz login en `https://vineksec.online/login.html`
2. Navega a cualquier página
3. ✅ Deberías ver tu foto de perfil en lugar de "Login"
4. Cierra el navegador y vuelve a abrir
5. ✅ Deberías seguir logueado (30 días)

### 3. Prueba de Newsletter:
1. Abre `https://vineksec.online/verificar-suscriptores.php`
2. ✅ Deberías ver todos tus correos confirmados
3. Abre `https://vineksec.online/test-newsletter.php`
4. ✅ Envía un email de prueba a tu correo
5. Ve a admin panel y aprueba un post
6. ✅ Todos los suscriptores deberían recibir email

### 4. Prueba de Admin Edit:
1. Ve al admin panel
2. Edita cualquier post
3. ✅ En el dropdown de categoría debería aparecer "Scripts / Tools"

---

## ⚡ SUBIDA RÁPIDA (SOLO LO CRÍTICO)

Si tienes prisa, sube solo estos 3 archivos para solucionar el 404:

```
auth-check.js     ← Crítico (rutas absolutas)
post.html         ← Crítico (mobile menu)
index.html        ← Incluye auth-check.js
blog.html         ← Incluye auth-check.js
vixy.html         ← Incluye auth-check.js
```

Y luego sube el CSS:
```
auth-styles.css   ← Para que se vea bien el menú
```

---

## 🐛 DIAGNÓSTICO POST-SUBIDA

Después de subir los archivos:

### Verificar Newsletter:
```
https://vineksec.online/verificar-suscriptores.php
```
Este archivo te mostrará:
- ✅ Estructura de la tabla
- ✅ TODOS los suscriptores (sin filtros)
- ✅ Estadísticas de confirmados/activos
- ✅ Si falta alguna columna en la BD

### Verificar 404:
1. Abre consola de desarrollo (F12)
2. Ve a cualquier artículo
3. Verifica que NO haya errores 404 al cargar:
   - `/auth.php` ✅
   - `/auth-check.js` ✅
   - `/auth-styles.css` ✅

---

## 📝 NOTAS IMPORTANTES

1. **Caché del navegador**: Después de subir, presiona Ctrl+F5 para limpiar caché
2. **Permisos**: Asegúrate de que los archivos .php tengan permisos 644
3. **Rutas**: TODOS los enlaces ahora usan rutas absolutas (`/archivo.html`)
4. **Newsletter**: Si no aparecen suscriptores, usa `verificar-suscriptores.php`

---

## ❓ SI ALGO NO FUNCIONA

1. Abre `verificar-suscriptores.php` primero
2. Verifica que las columnas `confirmed` y `active` existan
3. Si faltan columnas, ejecuta el SQL que te muestra
4. Vuelve a revisar `test-newsletter.php`
