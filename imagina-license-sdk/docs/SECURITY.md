# 🔒 Documento de Seguridad - Imagina License SDK

Este documento explica las **7 capas de seguridad** implementadas en el sistema de licencias y cómo protegen contra bypass y hackeo.

## 🎯 Objetivo de Seguridad

**Hacer que sea más fácil pagar la licencia que hackearla.**

El sistema implementa múltiples capas de validación que:
- ✅ Son difíciles de bypassear todas al mismo tiempo
- ✅ Requieren modificación del código en múltiples archivos
- ✅ Se rompen con cada actualización del plugin
- ✅ Validan constantemente con el servidor
- ✅ Detectan modificaciones del código de validación

## 🛡️ Las 7 Capas de Seguridad

### Capa #1: Validación Remota Obligatoria

**¿Qué hace?**
- El servidor es la **única fuente de verdad**
- El cliente DEBE comunicarse con el servidor para validar
- No hay forma de validar offline (excepto durante el grace period)

**Implementación:**
```php
private function verify_with_server() {
    $manager = Imagina_Updater_License_Manager::get_instance();
    $result = $manager->verify_plugin_license( $this->plugin_slug );

    // Si el servidor dice NO, es NO
    return $result;
}
```

**¿Cómo protege?**
- Un usuario no puede simplemente cambiar `return true;` en el código
- Debe falsificar toda la comunicación con el servidor
- Las respuestas del servidor están firmadas criptográficamente

**¿Se puede bypassear?**
- ❌ Muy difícil: Requeriría modificar el cliente API, el gestor de licencias, y falsificar firmas digitales

---

### Capa #2: Heartbeat Constante (WP-Cron)

**¿Qué hace?**
- Verifica automáticamente todas las licencias cada 12 horas
- Se ejecuta en background usando WP-Cron
- Detecta licencias desactivadas remotamente

**Implementación:**
```php
// Programar verificación cada 12 horas
wp_schedule_event( time(), 'imagina_license_12hours', 'imagina_license_heartbeat' );

// En cada ejecución
public function run_heartbeat() {
    foreach ( $this->registered_plugins as $slug => $validator ) {
        $validator->force_check(); // Verificación remota forzada
    }
}
```

**¿Cómo protege?**
- Aunque un usuario bypass la verificación inicial, el heartbeat la revalidará
- Si desactivas una licencia en el servidor, se detecta en máximo 12 horas
- Envía emails al admin si detecta licencia inválida

**¿Se puede bypassear?**
- ⚠️ Parcialmente: El usuario puede desactivar el cron
- ✅ Pero la validación en `admin_init` seguirá funcionando
- ✅ Y la validación al activar el plugin seguirá funcionando

---

### Capa #3: Firma Digital Criptográfica (HMAC-SHA256)

**¿Qué hace?**
- Todas las respuestas del servidor están firmadas con HMAC-SHA256
- El cliente verifica la firma antes de aceptar la respuesta
- Usa el `activation_token` como secreto (único por sitio)

**Implementación:**
```php
// En el servidor
$signature = hash_hmac( 'sha256', wp_json_encode( $response ), $activation_token );
$response['signature'] = $signature;

// En el cliente
$expected = hash_hmac( 'sha256', wp_json_encode( $data ), $activation_token );
if ( ! hash_equals( $expected, $received_signature ) ) {
    return false; // Firma inválida
}
```

**¿Cómo protege?**
- Imposible falsificar respuestas del servidor sin conocer el `activation_token`
- El token es único por sitio y no se puede predecir
- Usa `hash_equals()` para prevenir timing attacks

**¿Se puede bypassear?**
- ❌ Casi imposible: Requeriría extraer el `activation_token` de la base de datos
- ❌ Y modificar el código para generar firmas falsas
- ❌ Y conocer el algoritmo exacto de firma

---

### Capa #4: License Tokens de Corta Duración (24h)

**¿Qué hace?**
- El servidor genera tokens JWT que expiran cada 24 horas
- El cliente debe renovarlos constantemente
- Los tokens incluyen: plugin_slug, site_domain, timestamps

**Implementación:**
```php
$payload = array(
    'plugin_slug' => $plugin_slug,
    'site_domain' => $site_domain,
    'iat'         => time(),              // Issued at
    'exp'         => time() + 86400,      // Expira en 24h
    'jti'         => bin2hex( random_bytes( 16 ) ), // ID único
);

$token = base64url_encode( json_encode( $payload ) ) . '.' . $signature;
```

**¿Cómo protege?**
- Aunque un usuario extraiga un token válido, expira en 24h
- No puede reutilizar tokens antiguos
- No puede usar el token de otro sitio (verificación de dominio)

**¿Se puede bypassear?**
- ⚠️ Temporalmente: Durante las 24h del token
- ✅ Pero debe renovarse constantemente
- ✅ Y cada renovación verifica con el servidor

---

### Capa #5: Verificación de Integridad del SDK

**¿Qué hace?**
- El SDK calcula su propio checksum (SHA-256)
- Lo compara con un checksum esperado almacenado
- Si detecta modificación, se auto-desactiva

**Implementación:**
```php
private function verify_sdk_integrity() {
    $current_checksum = hash_file( 'sha256', __FILE__ );
    $expected_checksum = $this->get_expected_checksum();

    if ( ! hash_equals( $expected_checksum, $current_checksum ) ) {
        $this->trigger_integrity_failure( 'modified_code' );
        deactivate_plugins( plugin_basename( $this->plugin_file ) );
        wp_die( 'El código de licenciamiento ha sido modificado.' );
    }
}
```

**¿Cómo protege?**
- Detecta si el usuario modifica el código del SDK
- Por ejemplo, si cambia `return false;` a `return true;`
- Se desactiva automáticamente al detectar modificación

**¿Se puede bypassear?**
- ⚠️ Sí: El usuario puede modificar también la verificación de integridad
- ✅ Pero requiere entender el código y modificar múltiples funciones
- ✅ Y se rompe con cada actualización del plugin

---

### Capa #6: Ofuscación de Código Crítico

**¿Qué hace?**
- El código del SDK usa nombres de variables ofuscados
- La lógica crítica está distribuida en múltiples funciones
- Dificulta la lectura y modificación del código

**Ejemplo:**
```php
// En lugar de:
if ( $is_valid ) {
    return true;
}

// Se usa:
private $__x9f2a;
private function __v7k3m() {
    return $this->__x9f2a && $this->__c2h8l() && $this->__n5p1q();
}
```

**¿Cómo protege?**
- Hace más difícil entender qué hace el código
- Dificulta encontrar dónde modificar para bypassear
- Requiere tiempo y habilidad para reverse-engineering

**¿Se puede bypassear?**
- ⚠️ Sí: Con suficiente tiempo y habilidad técnica
- ✅ Pero es tedioso y se pierde con cada actualización
- ✅ Más fácil pagar la licencia

**Nota:** La ofuscación actual es básica. Para mayor seguridad, se puede usar un ofuscador PHP comercial.

---

### Capa #7: Múltiples Puntos de Verificación

**¿Qué hace?**
- No verifica solo una vez al activar
- Verifica en múltiples momentos:
  - Al cargar el plugin (`plugins_loaded`)
  - En admin init (`admin_init`)
  - Antes de AJAX (`wp_ajax_*`)
  - Antes de REST API (`rest_pre_dispatch`)
  - En el heartbeat (cada 12h)

**Implementación:**
```php
// Verificación al cargar
add_action( 'plugins_loaded', array( $this, 'verify_license' ) );

// Verificación en admin
add_action( 'admin_init', array( $this, 'validate_on_admin_init' ) );

// Verificación en AJAX
add_action( 'wp_ajax_*', array( $this, 'validate_before_ajax' ), 0 );

// Verificación en REST API
add_filter( 'rest_pre_dispatch', array( $this, 'validate_before_rest' ), 10, 3 );
```

**¿Cómo protege?**
- Aunque bypassees una verificación, hay más
- Dificulta bypassear todo el sistema
- Cada punto usa el mismo sistema de validación robusto

**¿Se puede bypassear?**
- ⚠️ Sí: Modificando todos los puntos de verificación
- ✅ Pero es muy tedioso y propenso a errores
- ✅ Más fácil pagar la licencia

---

## 🔥 Grace Period: Balance entre Seguridad y UX

### ¿Qué es el Grace Period?

Un período de tiempo (por defecto 3 días) durante el cual el plugin sigue funcionando aunque la verificación remota falle.

### ¿Por qué existe?

**Problemas que soluciona:**
- 🌐 Problemas temporales de conectividad
- 🔧 Mantenimiento del servidor
- 🐛 Errores temporales de API
- 🏖️ Admin de vacaciones sin acceso

**Sin grace period:**
- Si el servidor cae 1 hora, todos los sitios se desactivan
- Mala experiencia de usuario
- Soporte técnico saturado

### ¿Es una vulnerabilidad?

**No**, porque:
1. **Solo se activa si falla la verificación remota**
   - No se puede activar manualmente
   - No se puede extender

2. **Es temporal (3 días por defecto)**
   - Después de 3 días sin verificación exitosa, se desactiva
   - Configurable: `'grace_period' => 7 * DAY_IN_SECONDS` (7 días)

3. **Se resetea al verificar exitosamente**
   - En cuanto el servidor responde OK, se resetea a 0

### Implementación:

```php
private function handle_verification_failure() {
    // Primera vez que falla
    if ( empty( $this->license_state['grace_period_start'] ) ) {
        $this->license_state['grace_period_start'] = time();
    }

    $time_in_grace = time() - $this->license_state['grace_period_start'];

    // Aún en grace period
    if ( $time_in_grace < $this->grace_period ) {
        return true; // Permitir funcionamiento
    }

    // Grace period expirado
    $this->invalidate_license( 'grace_period_expired' );
    return false;
}
```

### Configurar Grace Period:

```php
// Sin grace period (estricto)
$license = Imagina_License_SDK::init( array(
    'grace_period' => 0,
) );

// 1 día de gracia
$license = Imagina_License_SDK::init( array(
    'grace_period' => DAY_IN_SECONDS,
) );

// 7 días de gracia (recomendado para producción)
$license = Imagina_License_SDK::init( array(
    'grace_period' => 7 * DAY_IN_SECONDS,
) );
```

---

## ⚠️ Limitaciones Conocidas

### PHP no es 100% Seguro

**Realidad:**
- PHP es interpretado, no compilado
- El código fuente está disponible
- Con suficiente tiempo, cualquier sistema PHP puede ser reverse-engineered

**Mitigación:**
- Múltiples capas de validación
- Verificación constante con el servidor
- Ofuscación del código
- Detección de modificaciones

### ¿Qué puede hacer un usuario muy técnico?

1. **Modificar el SDK**
   - ✅ Detectado por verificación de integridad
   - ✅ Se rompe con cada actualización

2. **Modificar también la verificación de integridad**
   - ⚠️ Posible
   - ✅ Pero requiere modificar múltiples archivos
   - ✅ Se rompe con cada actualización

3. **Bloquear las peticiones al servidor**
   - ⚠️ Posible (con firewall/hosts)
   - ✅ Pero solo funciona durante el grace period
   - ✅ Después se desactiva

4. **Modificar el plugin cliente**
   - ⚠️ Posible
   - ✅ Pero afecta TODOS los plugins premium
   - ✅ Y requiere habilidades técnicas avanzadas

### ¿Vale la pena el esfuerzo?

**Para el usuario:** NO
- Requiere habilidades técnicas avanzadas
- Debe modificar código en cada actualización
- Pierde soporte oficial
- Más fácil pagar la licencia

**Para ti como desarrollador:** SÍ
- El 99% de los usuarios no intentará hackear
- El 1% restante probablemente no pagaría de todas formas
- Proteges contra "piratería casual"
- Control total sobre licencias activas

---

## 🎯 Control Remoto de Licencias

### Desactivar una Licencia

**Desde el servidor:**

1. Ve a `API Keys`
2. Desactiva la API Key del cliente

**Efecto:**
- En la próxima verificación (máximo 12 horas), el plugin se desactiva
- El cliente ve un aviso de licencia inválida
- Las funcionalidades premium dejan de funcionar

### Limitar Sitios por Licencia

```php
// En el servidor, al crear/editar API Key
'max_activations' => 5  // Máximo 5 sitios
```

**Efecto:**
- El cliente puede activar hasta 5 sitios con esta API Key
- Al intentar activar el 6º sitio, recibe un error
- Puedes desactivar sitios específicos para liberar slots

### Revocar Acceso a Plugins Específicos

```php
// En el servidor, cambiar access_type
'access_type' => 'specific',
'allowed_plugins' => '[1, 2, 3]'  // Solo plugins 1, 2, 3
```

**Efecto:**
- El cliente pierde acceso a otros plugins
- En la próxima verificación, esos plugins se desactivan
- Útil para downgrades de licencia

---

## 📊 Comparación con Otros Sistemas

| Feature | Imagina SDK | Freemius | WooCommerce | EDD |
|---------|-------------|----------|-------------|-----|
| Verificación remota | ✅ Sí | ✅ Sí | ✅ Sí | ✅ Sí |
| Firma digital | ✅ HMAC-SHA256 | ✅ Sí | ❌ No | ⚠️ Opcional |
| Verificación de integridad | ✅ Checksum | ❌ No | ❌ No | ❌ No |
| Grace period | ✅ Configurable | ✅ Fijo | ✅ Fijo | ✅ Fijo |
| Heartbeat | ✅ 12h | ✅ 24h | ❌ No | ⚠️ Depende |
| Control total servidor | ✅ 100% | ❌ Depende API | ⚠️ Parcial | ⚠️ Parcial |
| Código abierto | ✅ Sí | ❌ No | ✅ Sí | ✅ Sí |
| Costes externos | ❌ No | ✅ Sí ($) | ❌ No | ❌ No |

---

## 🔧 Mejoras Futuras de Seguridad

### 1. Ofuscación Avanzada

Usar un ofuscador PHP comercial:
- ionCube
- Zend Guard
- SourceGuardian

**Ventaja:** Código prácticamente ilegible
**Desventaja:** Requiere extensión PHP adicional

### 2. Encriptación de Código

Encriptar partes críticas del SDK:
- Solo se desencriptan en runtime
- Usa claves derivadas del servidor

**Ventaja:** Muy difícil de reverse-engineer
**Desventaja:** Impacto en rendimiento

### 3. Code Signing

Firmar el plugin con certificado digital:
- Verifica autenticidad del código
- Detecta modificaciones no autorizadas

**Ventaja:** Nivel enterprise de seguridad
**Desventaja:** Requiere infraestructura PKI

### 4. Hardware Fingerprinting

Identificar el servidor por hardware:
- CPU, disco, MAC address
- Detecta clonación de sitios

**Ventaja:** Previene duplicación
**Desventaja:** Problemas con cloud/VPS

---

## 📚 Conclusión

El sistema de licencias Imagina implementa **7 capas de seguridad** que hacen que:

✅ **Sea muy difícil** bypassear el sistema completamente
✅ **Requiera conocimientos técnicos** avanzados para intentarlo
✅ **Se rompa con cada actualización**, requiriendo trabajo constante
✅ **Sea más fácil pagar** la licencia que hackearla
✅ **Tengas control total** sobre las licencias desde el servidor

**Es 100% seguro:** NO, ningún sistema PHP lo es.
**Es suficientemente seguro:** SÍ, para el 99% de los casos de uso.

El objetivo no es ser inquebrantable, sino hacer que el coste (tiempo, habilidad) de hackear sea mayor que el coste de la licencia.
