# Hooks reference — Imagina Updater Server

> Catálogo completo de los hooks (actions y filters) que expone el plugin servidor para que la extensión de licencias y futuras extensiones de terceros se enganchen sin modificar el core.

> **Regla crítica de CLAUDE.md §4 (no romper)**: estos hooks tienen consumidores activos (la license-extension) y posibles consumidores futuros. **No eliminar ni renombrar** ninguno sin aprobación explícita y un plan de migración.

## Índice

- [Actions](#actions)
  - [`imagina_updater_after_upload_form`](#imagina_updater_after_upload_form)
  - [`imagina_updater_after_move_plugin_file`](#imagina_updater_after_move_plugin_file)
  - [`imagina_updater_after_upload_plugin`](#imagina_updater_after_upload_plugin)
  - [`imagina_updater_plugins_column_toggles`](#imagina_updater_plugins_column_toggles)
  - [`imagina_updater_plugins_table_header`](#imagina_updater_plugins_table_header)
  - [`imagina_updater_plugins_table_row`](#imagina_updater_plugins_table_row)
- [Filters](#filters)
- [Convenciones generales](#convenciones-generales)

---

## Actions

### `imagina_updater_after_upload_form`

**Cuándo se dispara**: justo después de cerrar el formulario HTML de subida de plugin en la pantalla *Plugins*. Permite a las extensiones añadir campos extra (checkboxes, descripciones) que el handler PHP del upload pueda leer en `$_POST`.

**Parámetros**: ninguno.

**Origen**: `imagina-updater-server/admin/views/plugins.php` línea ~101.

**Consumidores actuales**:

- `Imagina_License_Admin::add_premium_field_to_form` — inyecta el checkbox "Es Premium" y la descripción de las funcionalidades de protección.

**Ejemplo de uso**:

```php
add_action( 'imagina_updater_after_upload_form', function () {
    ?>
    <p>
        <label>
            <input type="checkbox" name="mi_extension_flag" value="1" />
            <?php esc_html_e( 'Aplicar mi tratamiento personalizado al subir', 'mi-extension' ); ?>
        </label>
    </p>
    <?php
} );
```

---

### `imagina_updater_after_move_plugin_file`

**Cuándo se dispara**: después de copiar el ZIP subido a `wp-content/uploads/imagina-updater-plugins/` y **antes** de calcular el checksum o de insertar/actualizar la fila del plugin en la BD. Es el punto de extensión idóneo para *modificar el ZIP* (la license-extension lo usa para inyectar protección) o para validaciones adicionales que puedan abortar el proceso.

> Esta inyección es la base del modelo de seguridad de plugins premium. Consulta `imagina-updater-license-extension/docs/SECURITY.md`.

**Parámetros**:

| Nombre | Tipo | Descripción |
|---|---|---|
| `$file_path` | `string` | Ruta absoluta al ZIP recién copiado. **Mutable**: si la modificas (re-empaquetas), el plugin manager calculará el checksum del archivo modificado. |
| `$plugin_data` | `array` | Metadatos extraídos del header del plugin: `slug`, `name`, `version`, `author`, `description`, `homepage`. |

**Origen**: `imagina-updater-server/includes/class-plugin-manager.php` línea ~185, dentro de `upload_plugin()`.

**Consumidores actuales**:

- `Imagina_License_Admin::process_uploaded_file` — si `$_POST['is_premium']` está marcado, llama a `Imagina_License_SDK_Injector::inject_sdk_if_needed()` para inyectar el código de protección en el archivo principal del ZIP.

**Ejemplo de uso**:

```php
add_action( 'imagina_updater_after_move_plugin_file', function ( $file_path, $plugin_data ) {
    if ( ! file_exists( $file_path ) ) {
        return;
    }
    error_log( sprintf( '[Mi extensión] ZIP recibido: %s (%s v%s)', $file_path, $plugin_data['slug'], $plugin_data['version'] ) );
    // Aquí podrías abrir el ZIP, modificarlo, re-empaquetarlo, etc.
}, 10, 2 );
```

> **Buena práctica**: si vas a modificar el ZIP, usa el patrón two-phase commit (extraer → modificar → empaquetar a `.new` → `rename()` atómico) para no dejar el archivo original corrupto si algo falla a mitad. El injector v4 sigue este patrón a partir de Fase 4.3.

---

### `imagina_updater_after_upload_plugin`

**Cuándo se dispara**: después de que la fila del plugin haya sido insertada o actualizada en la BD con su nueva versión, dentro de la transacción ya comprometida (`COMMIT`). Útil para flujos *post-commit* como notificaciones, telemetría, o actualizar tablas auxiliares (la license-extension la usa para guardar el flag `is_premium` y registrar el checksum de la protección).

**Parámetros**:

| Nombre | Tipo | Descripción |
|---|---|---|
| `$result_data` | `array` | Subset estable de los datos guardados: `id` (BD), `slug`, `name`, `version`. |
| `$file_path` | `string` | Ruta absoluta al ZIP final ya almacenado. |

**Origen**: `imagina-updater-server/includes/class-plugin-manager.php` línea ~333.

**Consumidores actuales**:

- `Imagina_License_Admin::inject_protection_after_upload` — actualiza el campo `is_premium` en `wp_imagina_updater_plugins` y, si el plugin es premium, recalcula `checksum` y `file_size` después de la inyección.

**Ejemplo de uso**:

```php
add_action( 'imagina_updater_after_upload_plugin', function ( $result_data, $file_path ) {
    // Notificar a un canal interno cada vez que se sube una versión nueva
    wp_remote_post( 'https://hooks.example.com/internal', array(
        'body' => array(
            'plugin'  => $result_data['slug'],
            'version' => $result_data['version'],
        ),
    ) );
}, 10, 2 );
```

---

### `imagina_updater_plugins_column_toggles`

**Cuándo se dispara**: dentro del dropdown "Mostrar/ocultar columnas" de la tabla de plugins, después de los toggles nativos. Permite a las extensiones añadir checkboxes para columnas que ellas mismas inyectan vía `imagina_updater_plugins_table_header` / `imagina_updater_plugins_table_row`.

**Parámetros**: ninguno.

**Origen**: `imagina-updater-server/admin/views/plugins.php` línea ~157.

**Consumidores actuales**: ninguno conocido. Disponible para extensiones que añadan columnas custom.

---

### `imagina_updater_plugins_table_header`

**Cuándo se dispara**: dentro del `<tr>` de cabecera de la tabla `#plugins-table`, después de las columnas estándar y antes de la columna *Acciones*. Permite añadir columnas custom.

**Parámetros**: ninguno (la cabecera es estática).

**Origen**: `imagina-updater-server/admin/views/plugins.php` línea ~174.

**Consumidores actuales**:

- `Imagina_License_Admin::add_license_column_header` — añade la columna *Licencia* (con badge Premium/Free).

> **Importante**: si añades una columna aquí, debes añadir la celda correspondiente en `imagina_updater_plugins_table_row` para mantener el alineamiento.

**Ejemplo de uso**:

```php
add_action( 'imagina_updater_plugins_table_header', function () {
    echo '<th>' . esc_html__( 'Mi columna', 'mi-extension' ) . '</th>';
} );
```

---

### `imagina_updater_plugins_table_row`

**Cuándo se dispara**: dentro de cada `<tr>` de la tabla `#plugins-table`, en la misma posición relativa que `imagina_updater_plugins_table_header`. Recibe el plugin de la fila actual.

**Parámetros**:

| Nombre | Tipo | Descripción |
|---|---|---|
| `$plugin` | `object` | Fila completa del plugin desde `wp_imagina_updater_plugins` (incluye `id`, `slug`, `slug_override`, `name`, `current_version`, `file_path`, `is_premium`, etc.). |

**Origen**: `imagina-updater-server/admin/views/plugins.php` línea ~237.

**Consumidores actuales**:

- `Imagina_License_Admin::add_license_column_row` — renderiza el badge Premium/Free, el toggle, y el indicador de estado de la protección por plugin.

**Ejemplo de uso**:

```php
add_action( 'imagina_updater_plugins_table_row', function ( $plugin ) {
    echo '<td>' . esc_html( $plugin->slug ) . '</td>';
} );
```

---

## Filters

Actualmente no hay `apply_filters()` expuestos por el plugin servidor. Si tu extensión necesita modificar comportamiento (no solo añadir), abre un issue para añadir el filter correspondiente con la firma adecuada.

---

## Convenciones generales

- **Prefijo**: todos los hooks empiezan por `imagina_updater_` (sin scope adicional). Las extensiones deberían usar su propio prefijo (`imagina_license_`, etc.) para no colisionar.
- **Compatibilidad hacia atrás**: cualquier cambio en la firma de un hook (parámetros, tipos) requiere aprobación explícita y un plan de migración (CLAUDE.md §4 regla crítica nº 2). Lo correcto es introducir un hook nuevo (`*_v2`) y mantener el viejo.
- **Capabilities**: los hooks que se disparan en pantallas admin asumen que el contexto ya está protegido por `manage_options` (la propia página verifica antes). No añadas `current_user_can()` redundante a tus callbacks salvo que el hook se exponga también fuera del admin.
- **Side effects**: `imagina_updater_after_move_plugin_file` permite modificar el ZIP en disco. Trata el archivo con cuidado: si tu callback lanza una excepción, asegúrate de no dejar el ZIP corrupto (patrón two-phase commit recomendado, ver Fase 4.3).
- **Performance**: los hooks de la tabla de plugins (`*_table_header`, `*_table_row`) se disparan por cada fila renderizada. Evita queries pesadas en esos callbacks o cachéalas a nivel de request.
