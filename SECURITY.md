# 🔒 Seguridad - Imagina Updater

## Protecciones Implementadas

### 1. Protección de Archivos ZIP

Los archivos de plugins se almacenan en `wp-content/uploads/imagina-updater-plugins/` con protección multinivel:

#### ✅ Apache (.htaccess)
Ya configurado automáticamente. Bloquea acceso directo a archivos `.zip`.

#### ⚠️ Nginx (requiere configuración manual)
Agregar al bloque `server {}` de tu sitio:

```nginx
# Imagina Updater - Bloquear acceso directo a archivos
location ~ ^/wp-content/uploads/imagina-updater-plugins/.*\.zip$ {
    deny all;
    return 403;
}
```

**Ubicación del archivo**: Ver `wp-content/uploads/imagina-updater-plugins/nginx.conf.example`

#### ⚠️ OpenLiteSpeed (requiere configuración manual)
1. Ir a WebAdmin → Virtual Hosts → [tu-sitio] → Rewrite
2. Agregar regla:

```
RewriteRule ^wp-content/uploads/imagina-updater-plugins/.*\.zip$ - [F,L]
```

#### ✅ IIS (web.config)
Ya configurado automáticamente mediante `web.config`.

---

### 2. Rate Limiting

Sistema de protección multinivel contra abuso y ataques DDoS:

**Límites:**
- **60 peticiones/minuto** por API key
- **100 peticiones/minuto** por IP (más permisivo para hosting compartido)
- **Ban temporal de 15 minutos** después de 5 violaciones en 1 hora

**Compatibilidad:**
- ✅ Detecta IP real detrás de proxies (Cloudflare, Nginx, load balancers)
- ✅ Compatible con CDN y reverse proxies
- ✅ Usa transients de WordPress (funciona con caché de objetos)

---

### 3. Sistema de Permisos

Control granular de acceso a plugins por API key:

**Niveles de acceso:**
1. **Todos los plugins** - Acceso completo
2. **Plugins específicos** - Solo plugins seleccionados
3. **Grupos de plugins** - Por grupos creados previamente

**Protección:**
- ✅ Verificación en TODOS los endpoints (`/plugins`, `/plugin/{slug}`, `/download/{slug}`)
- ✅ Error 403 si intenta acceder a plugin no permitido
- ✅ Filtrado automático en listados

---

### 4. Autenticación API Key

**Métodos soportados:**
```bash
# Header Authorization Bearer
Authorization: Bearer ius_xxxxxxxxxxxxx

# Header X-API-Key
X-API-Key: ius_xxxxxxxxxxxxx

# Query parameter (menos seguro, solo para testing)
?api_key=ius_xxxxxxxxxxxxx
```

**Protección:**
- ✅ API keys únicas de 64 caracteres
- ✅ Prefijo `ius_` para identificación
- ✅ Activación/desactivación sin eliminar
- ✅ Registro de último uso

---

### 5. Validaciones y Sanitización

**Inputs:**
- ✅ Todas las queries SQL usan `$wpdb->prepare()`
- ✅ Validación de tipos en REST API
- ✅ Sanitización con funciones WordPress (`sanitize_text_field`, `esc_url_raw`, etc.)
- ✅ Validación de MIME types en uploads (solo ZIP)
- ✅ Verificación de archivos subidos con `is_uploaded_file()`

**Outputs:**
- ✅ Escape de datos en admin con `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ Nonces en todos los formularios admin

---

## 🚨 Configuración Recomendada

### Para Servidores Nginx

**1. Copiar archivo de configuración:**
```bash
cp wp-content/uploads/imagina-updater-plugins/nginx.conf.example /etc/nginx/snippets/imagina-updater.conf
```

**2. Incluir en tu virtual host:**
```nginx
server {
    # ... tu configuración ...

    include /etc/nginx/snippets/imagina-updater.conf;

    # ... resto de configuración ...
}
```

**3. Probar y recargar:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

**4. Verificar protección:**
```bash
# Debe devolver 403 Forbidden
curl -I https://tu-servidor.com/wp-content/uploads/imagina-updater-plugins/plugin-1.0.0.zip
```

---

### Para OpenLiteSpeed

**1. Acceder a WebAdmin Console**
- URL: `https://tu-servidor:7080`
- Usuario: admin

**2. Navegar a:**
WebAdmin → Virtual Hosts → [tu-sitio] → Rewrite → Rewrite Rules

**3. Agregar regla:**
```
RewriteRule ^wp-content/uploads/imagina-updater-plugins/.*\.zip$ - [F,L]
```

**4. Graceful Restart:**
Actions → Graceful Restart

**5. Verificar:**
```bash
curl -I https://tu-servidor.com/wp-content/uploads/imagina-updater-plugins/plugin-1.0.0.zip
```

---

## 🔍 Monitoreo y Alertas

### Ver Logs de Seguridad

**Activar logging:**
1. Ir a: Imagina Updater → Configuración
2. Activar "Habilitar Logging"
3. Nivel: WARNING o ERROR

**Ver logs:**
- Admin: Imagina Updater → Logs
- Buscar: "Rate limit", "bloqueada", "violaciones"

### Alertas Importantes

El sistema registra automáticamente:
- ⚠️ Intentos de rate limit excedido
- 🚫 IPs baneadas temporalmente
- ❌ Intentos de acceso a plugins no permitidos
- 🔓 Accesos denegados por API key inválida

---

## ✅ Checklist de Seguridad

### Configuración Inicial
- [ ] **Ejecutar migraciones de BD** (Configuración → Mantenimiento)
- [ ] **Verificar permisos del directorio uploads** (755 recomendado)
- [ ] **Activar logging** para monitoreo
- [ ] **Configurar Nginx/OLS** si no usas Apache

### Gestión de API Keys
- [ ] **Usar HTTPS** siempre (obligatorio para producción)
- [ ] **Crear API keys con permisos mínimos** necesarios
- [ ] **Revisar periódicamente** API keys activas
- [ ] **Desactivar API keys** no utilizadas (no eliminar para mantener estadísticas)

### Monitoreo Regular
- [ ] **Revisar logs** semanalmente
- [ ] **Verificar estadísticas** de descargas por API key
- [ ] **Detectar patrones** sospechosos de uso

### Servidor Web
- [ ] **SSL/TLS habilitado** (certificado válido)
- [ ] **Firewall configurado** (solo puertos necesarios)
- [ ] **WordPress actualizado** a última versión
- [ ] **PHP 7.4+** (recomendado 8.0+)

---

## 🛡️ Respuesta a Incidentes

### Si detectas uso no autorizado:

**1. Identificar API key:**
```sql
SELECT * FROM wp_imagina_updater_downloads
WHERE api_key_id = [ID_SOSPECHOSA]
ORDER BY downloaded_at DESC;
```

**2. Desactivar inmediatamente:**
- Admin → API Keys → Desactivar

**3. Revisar logs:**
- Buscar IPs asociadas
- Identificar plugins descargados
- Ver patrones de tiempo/frecuencia

**4. Crear nueva API key:**
- Generar nueva con permisos actualizados
- Compartir con cliente legítimo
- Documentar incidente

### Si servidor está bajo ataque DDoS:

**1. Verificar bans automáticos:**
```bash
# Ver transients de bans
wp transient list | grep imagina_updater_ip_ban
```

**2. Opciones de mitigación:**
- Cloudflare (protección DDoS automática)
- Rate limiting a nivel de servidor
- Fail2ban con reglas personalizadas

---

## 📊 Mejores Prácticas

### Para Administradores del Servidor

1. **Grupos de Plugins Lógicos**
   - Crear grupos por: cliente, tipo de licencia, categoría
   - Asignar permisos por grupos (más fácil de gestionar)

2. **Nombrar API Keys Descriptivamente**
   - Formato: `Cliente - Sitio - Tipo`
   - Ejemplo: "Empresa XYZ - Producción - Premium"

3. **Revisar Estadísticas**
   - Descargas inusuales pueden indicar uso compartido
   - Múltiples IPs con misma API key = señal de alerta

### Para Clientes (Sites que consumen)

1. **Proteger API Key**
   - Nunca commitear en Git
   - Usar variables de entorno si es posible
   - No compartir entre sitios

2. **Usar HTTPS**
   - Obligatorio para evitar intercepción
   - Headers no encriptados = API key expuesta

3. **Reportar Problemas**
   - Si ves errores 403, revisar permisos
   - Si ves 429, revisar automations que puedan estar consultando mucho

---

## 🔐 Niveles de Seguridad por Servidor

### Apache
✅ **ALTA** - .htaccess automático, sin configuración manual

### Nginx
⚠️ **MEDIA** - Requiere configuración manual (ver arriba)

### OpenLiteSpeed
⚠️ **MEDIA** - Requiere configuración manual (ver arriba)

### IIS
✅ **ALTA** - web.config automático

### Otros (LiteSpeed, Caddy, etc.)
⚠️ **BAJA** - Requiere configuración custom

**RECOMENDACIÓN:** Probar acceso directo después de instalar:
```bash
curl -I https://tu-servidor.com/wp-content/uploads/imagina-updater-plugins/[plugin-real].zip
```

**Resultado esperado:** `403 Forbidden` o `404 Not Found`
**Resultado peligroso:** `200 OK` (CONFIGURAR PROTECCIÓN INMEDIATAMENTE)

---

## 📞 Soporte

Para reportar vulnerabilidades de seguridad: **[email de seguridad]**

**NO publicar vulnerabilidades en GitHub Issues.**
