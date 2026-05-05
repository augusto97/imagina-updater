# Documento de seguridad — Imagina Updater License Extension

Este documento describe las **7 capas de seguridad** que protegen los plugins premium distribuidos por el sistema Imagina Updater.

> **Nota sobre el origen**: este contenido se rescató del antiguo `imagina-license-sdk/` (que se eliminó en la Fase 0) y se adaptó al modelo actual del sistema, en el que la extensión de licencias **inyecta automáticamente** el código de protección al subir un plugin marcado como Premium. Ya no existe un SDK manual que el desarrollador del plugin tenga que integrar.

## Objetivo de seguridad

**Hacer que sea más fácil pagar la licencia que romperla.**

El sistema implementa múltiples capas de validación que:

- Son difíciles de bypassear todas al mismo tiempo.
- Requieren modificación del código en múltiples archivos.
- Se rompen con cada actualización del plugin (re-inyección).
- Validan constantemente con el servidor.
- Detectan modificaciones del código de validación.

## Modelo actual: auto-inyección (no integración manual)

A diferencia del modelo del antiguo SDK (en el que el desarrollador del plugin premium incluía manualmente el SDK como `vendor/`), en el modelo actual:

1. El admin sube un plugin desde `imagina-updater-server` y lo marca como Premium.
2. La extensión de licencias engancha el hook `imagina_updater_after_move_plugin_file`.
3. El componente `Imagina_License_SDK_Injector` extrae el ZIP, inyecta el código de protección y vuelve a empaquetar el ZIP.
4. El código inyectado es **autónomo**: no depende de un SDK externo. Cada plugin recibe una clase con nombre único `ILP_<hash>` (ofuscado).

Esto significa que el desarrollador no tiene que integrar nada. La protección es transparente al subir el ZIP.

---

## Las 7 capas de seguridad

### Capa 1 — Validación remota obligatoria

**Qué hace:**

- El servidor es la **única fuente de verdad**.
- El cliente DEBE comunicarse con el servidor para validar.
- No hay forma de validar offline (excepto durante el grace period).

**Implementación (cliente):**

```php
private function verify_with_server() {
    $manager = Imagina_Updater_License_Manager::get_instance();
    $result  = $manager->verify_plugin_license( $this->plugin_slug );

    // Si el servidor dice NO, es NO
    return $result;
}
```

**Cómo protege:**

- El usuario no puede simplemente cambiar `return true;` en el código.
- Debe falsificar toda la comunicación con el servidor.
- Las respuestas del servidor están firmadas criptográficamente (capa 3).

**Bypass:** muy difícil. Requeriría modificar cliente API + gestor de licencias + falsificar firmas digitales.

---

### Capa 2 — Heartbeat constante

**Qué hace:**

- Verifica automáticamente todas las licencias cada 12 horas.
- Se ejecuta en background usando WP-Cron (en una fase futura migrará a Action Scheduler).
- Detecta licencias desactivadas remotamente.

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

**Cómo protege:**

- Aunque un usuario bypaseé la verificación inicial, el heartbeat la revalidará.
- Si desactivas una licencia en el servidor, se detecta en máximo 12 horas.

**Bypass parcial:** el usuario puede desactivar el cron, pero la validación en `admin_init` y al activar el plugin sigue funcionando.

---

### Capa 3 — Firma digital criptográfica (HMAC-SHA256)

**Qué hace:**

- Todas las respuestas del servidor están firmadas con HMAC-SHA256.
- El cliente verifica la firma antes de aceptar la respuesta.
- Usa el `activation_token` del sitio como secreto (único por sitio).

**Implementación (servidor):**

```php
$signature           = hash_hmac( 'sha256', wp_json_encode( $response ), $activation_token );
$response['signature'] = $signature;
```

**Implementación (cliente):**

```php
$expected = hash_hmac( 'sha256', wp_json_encode( $data ), $activation_token );
if ( ! hash_equals( $expected, $received_signature ) ) {
    return false; // Firma inválida
}
```

**Cómo protege:**

- Imposible falsificar respuestas del servidor sin conocer el `activation_token`.
- El token es único por sitio y no se puede predecir.
- Usa `hash_equals()` para prevenir timing attacks.

**Bypass:** casi imposible sin extraer el `activation_token` de la base de datos del sitio.

---

### Capa 4 — License tokens de corta duración (24 h)

**Qué hace:**

- El servidor genera tokens (formato JWT-like) que expiran cada 24 horas.
- El cliente debe renovarlos constantemente.
- Los tokens incluyen `plugin_slug`, `site_domain` y timestamps.

**Implementación:**

```php
$payload = array(
    'plugin_slug' => $plugin_slug,
    'site_domain' => $site_domain,
    'iat'         => time(),                          // Issued at
    'exp'         => time() + 86400,                  // Expira en 24h
    'jti'         => bin2hex( random_bytes( 16 ) ),   // ID único
);

$token = base64url_encode( wp_json_encode( $payload ) ) . '.' . $signature;
```

**Cómo protege:**

- Aunque un usuario extraiga un token válido, expira en 24 h.
- No puede reutilizar tokens antiguos.
- No puede usar el token de otro sitio (verificación de dominio + `X-Site-Domain`).

**Bypass temporal:** durante las 24 h del token, pero se rompe en cada renovación.

---

### Capa 5 — Verificación de integridad del código inyectado

**Qué hace:**

- El código inyectado calcula su propio checksum (SHA-256).
- Lo compara con un checksum esperado almacenado al inyectar.
- Si detecta modificación, se auto-desactiva.

**Implementación:**

```php
private function verify_integrity() {
    $current_checksum  = hash_file( 'sha256', __FILE__ );
    $expected_checksum = $this->get_expected_checksum();

    if ( ! hash_equals( $expected_checksum, $current_checksum ) ) {
        $this->trigger_integrity_failure( 'modified_code' );
        deactivate_plugins( plugin_basename( $this->plugin_file ) );
        wp_die( esc_html__( 'El código de licenciamiento ha sido modificado.', 'imagina-updater-license-extension' ) );
    }
}
```

**Cómo protege:**

- Detecta si el usuario modifica el código de protección inyectado.
- Por ejemplo, si cambia `return false;` a `return true;`.
- Se desactiva automáticamente al detectar modificación.

**Bypass parcial:** el usuario puede modificar también la verificación de integridad, pero requiere entender el código y modificar múltiples funciones, y se rompe con cada actualización (porque la re-inyección regenera todo).

> **Regla crítica (sección 4 de CLAUDE.md):** no desactivar las verificaciones de integridad bajo ninguna circunstancia.

---

### Capa 6 — Ofuscación de código crítico

**Qué hace:**

- El código inyectado usa nombres de clases y métodos ofuscados, generados por plugin a partir del slug.
- La clase principal recibe un nombre único `ILP_<8-char-md5-hash>` y los métodos críticos se renombran a tokens cortos (`_chk`, `_vld`, `_st`, `_vrf`, `_blk`, `_hk`, etc., derivados del slug).
- La lógica crítica está distribuida en múltiples funciones.

**Cómo protege:**

- Hace más difícil entender qué hace el código.
- Dificulta encontrar dónde modificar para bypassear.
- Requiere tiempo y habilidad para reverse-engineering.
- Cada plugin tiene nombres distintos: un fix para uno no aplica al siguiente.

**Bypass posible:** sí, con suficiente tiempo y habilidad técnica, pero es tedioso y se pierde con cada actualización.

> **Nota:** la ofuscación actual es intencionalmente básica (PHP plano). Si el negocio lo justifica, se puede combinar con un ofuscador comercial (ionCube, SourceGuardian) — ver "Mejoras futuras".

---

### Capa 7 — Múltiples puntos de verificación

**Qué hace:**

- No verifica solo una vez al activar.
- Verifica en múltiples momentos:
  - Al cargar el plugin (`plugins_loaded`).
  - En admin (`admin_init`).
  - Antes de AJAX (`wp_ajax_*`).
  - Antes de REST API (`rest_pre_dispatch`).
  - En el heartbeat (cada 12 h).

**Implementación:**

```php
add_action( 'plugins_loaded', array( $this, 'verify_license' ) );
add_action( 'admin_init', array( $this, 'validate_on_admin_init' ) );
add_action( 'wp_ajax_*', array( $this, 'validate_before_ajax' ), 0 );
add_filter( 'rest_pre_dispatch', array( $this, 'validate_before_rest' ), 10, 3 );
```

**Cómo protege:**

- Aunque bypaseés una verificación, hay más.
- Dificulta bypassear todo el sistema.
- Cada punto usa el mismo sistema de validación robusto.

**Bypass:** tedioso. Hay que modificar TODOS los puntos, y se rompe con cada actualización.

---

## Grace period: balance entre seguridad y UX

### Qué es

Un período de tiempo (por defecto **7 días** en este sistema) durante el cual el plugin sigue funcionando aunque la verificación remota falle.

### Por qué existe

Soluciona problemas reales:

- Problemas temporales de conectividad.
- Mantenimiento del servidor.
- Errores temporales de API.
- Admin sin acceso temporal a la red.

Sin grace period, si el servidor cae 1 hora todos los sitios se desactivarían — mala UX y soporte saturado.

### Por qué no es una vulnerabilidad

1. **Solo se activa si falla la verificación remota.** No se puede activar manualmente ni extender desde el cliente.
2. **Es temporal.** Tras `grace_period` segundos sin verificación exitosa, la licencia se invalida.
3. **Se resetea al verificar exitosamente.** En cuanto el servidor responde OK, el contador vuelve a 0.

### Implementación

```php
private function handle_verification_failure() {
    if ( empty( $this->license_state['grace_period_start'] ) ) {
        $this->license_state['grace_period_start'] = time();
    }

    $time_in_grace = time() - $this->license_state['grace_period_start'];

    if ( $time_in_grace < $this->grace_period ) {
        return true; // Permitir funcionamiento durante grace period
    }

    $this->invalidate_license( 'grace_period_expired' );
    return false;
}
```

### Cómo se configura

El grace period del sistema actual se define al generar el código de protección (capa 5/6). El valor recomendado para producción es **7 días** (`7 * DAY_IN_SECONDS`) — es el default actual.

---

## Limitaciones conocidas

### PHP no es 100 % seguro

**Realidad:**

- PHP es interpretado, no compilado.
- El código fuente está disponible en disco.
- Con suficiente tiempo, cualquier sistema PHP puede ser reverse-engineered.

**Mitigación:**

- Múltiples capas de validación (las 7 descritas).
- Verificación constante con el servidor.
- Ofuscación del código.
- Detección de modificaciones.

### Qué puede hacer un usuario muy técnico

| Ataque | Defensa |
|---|---|
| Modificar el código inyectado | Detectado por verificación de integridad (capa 5) y se rompe con cada actualización (re-inyección regenera todo) |
| Modificar también la verificación de integridad | Posible pero requiere modificar múltiples archivos. Re-inyección lo borra. |
| Bloquear peticiones al servidor (firewall/hosts) | Solo funciona durante el grace period. Después se desactiva. |
| Modificar el plugin cliente | Afecta TODOS los plugins premium. Requiere habilidades avanzadas. |

### Conclusión práctica

El sistema no busca ser inquebrantable, sino **hacer que el coste (tiempo, habilidad) de hackear sea mayor que el coste de la licencia**. Para el 99 % de los casos esto es suficiente.

---

## Control remoto de licencias

### Desactivar una licencia

Desde el admin del servidor (página API Keys):

1. Localiza la API Key del cliente.
2. Pulsa "Desactivar".

**Efecto:**

- En la próxima verificación (máximo 12 h por el heartbeat) el plugin se desactiva en el sitio.
- El cliente ve un aviso de licencia inválida.
- Las funcionalidades premium dejan de funcionar.

### Limitar sitios por licencia

```php
// Al crear/editar API Key
'max_activations' => 5  // Máximo 5 sitios
```

**Efecto:**

- El cliente puede activar hasta 5 sitios con esta API Key.
- Al intentar activar el 6º, recibe error.
- El admin puede desactivar sitios específicos para liberar slots.

### Revocar acceso a plugins específicos

```php
// En el servidor, cambiar access_type
'access_type'      => 'specific',
'allowed_plugins'  => '[1, 2, 3]'  // Solo plugins 1, 2, 3
```

**Efecto:**

- El cliente pierde acceso a otros plugins.
- En la próxima verificación esos plugins se desactivan.
- Útil para downgrades de licencia o cambios de plan.

---

## Comparación con otros sistemas

| Feature | Imagina | Freemius | WC Memberships | EDD Software Licensing |
|---|---|---|---|---|
| Verificación remota | Sí | Sí | Sí | Sí |
| Firma HMAC-SHA256 | Sí | Sí | No | Opcional |
| Verificación de integridad | Sí (checksum) | No | No | No |
| Grace period configurable | Sí | Fijo | Fijo | Fijo |
| Heartbeat | 12 h | 24 h | No | Depende |
| Control total servidor | 100 % | Depende API | Parcial | Parcial |
| Código abierto | Sí | No | Sí | Sí |
| Costes externos | No | Sí ($) | No | No |

---

## Mejoras futuras (no implementadas)

### Ofuscación avanzada

Usar un ofuscador PHP comercial:

- ionCube
- Zend Guard
- SourceGuardian

**Pros:** código prácticamente ilegible. **Contras:** requiere extensión PHP en el servidor del cliente — incompatible con muchos hostings compartidos.

### Encriptación de código

Encriptar partes críticas del código inyectado, desencriptado solo en runtime con claves derivadas del servidor.

**Pros:** muy difícil de reverse-engineer. **Contras:** impacto en rendimiento.

### Code signing

Firmar el plugin con certificado digital.

**Pros:** nivel enterprise. **Contras:** requiere infraestructura PKI.

### Hardware fingerprinting

Identificar el servidor por hardware (CPU, disco, MAC).

**Pros:** previene clonación literal de sitios. **Contras:** problemas con cloud/VPS donde el hardware cambia.

---

## Conclusión

El sistema implementa **7 capas de seguridad** que combinadas hacen que romper la licencia sea más caro que pagarla, lo cual es el objetivo.

- ¿Es 100 % seguro? **No** — ningún sistema PHP lo es.
- ¿Es suficientemente seguro? **Sí** — para el 99 % de los casos de uso.

Para detalles operativos (endpoints REST de la extensión, hooks expuestos, métodos públicos) consulta `API.md` en este mismo directorio.
