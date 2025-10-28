<?php
/**
 * Gestión de plugins y versiones
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Plugin_Manager {

    /**
     * Asegurar que el esquema de BD esté actualizado
     */
    private static function ensure_schema_updated() {
        global $wpdb;
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Verificar si slug_override existe
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_plugins LIKE 'slug_override'");
        if (empty($columns)) {
            error_log('IMAGINA UPDATER SERVER: Esquema desactualizado, ejecutando migraciones');
            Imagina_Updater_Server_Database::run_migrations();
        }
    }

    /**
     * Obtener el directorio de uploads para plugins
     */
    public static function get_upload_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/imagina-updater-plugins';
    }

    /**
     * Validar formato de versión semántica
     */
    private static function is_valid_version($version) {
        // Regex para versión semántica básica: X.Y.Z o X.Y.Z-suffix
        $pattern = '/^(\d+)\.(\d+)(\.(\d+))?(-[a-zA-Z0-9\-\.]+)?$/';
        return preg_match($pattern, $version);
    }

    /**
     * Subir un nuevo plugin o nueva versión
     *
     * @param array $file Archivo ZIP del plugin ($_FILES)
     * @param string|null $changelog Notas de la versión
     * @return array|WP_Error
     */
    public static function upload_plugin($file, $changelog = null) {
        // Validar archivo
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_file', __('Archivo no válido', 'imagina-updater-server'));
        }

        if (!file_exists($file['tmp_name'])) {
            return new WP_Error('file_not_found', __('Archivo temporal no encontrado', 'imagina-updater-server'));
        }

        // Validar que sea un ZIP
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, array('application/zip', 'application/x-zip-compressed'))) {
            return new WP_Error('invalid_type', __('El archivo debe ser un ZIP', 'imagina-updater-server'));
        }

        // Extraer información del plugin
        $plugin_data = self::extract_plugin_info($file['tmp_name']);

        if (is_wp_error($plugin_data)) {
            return $plugin_data;
        }

        // Validar versión
        if (!self::is_valid_version($plugin_data['version'])) {
            return new WP_Error('invalid_version', __('Formato de versión inválido. Use formato semántico: X.Y.Z', 'imagina-updater-server'));
        }

        // Mover archivo a directorio seguro
        $upload_dir = self::get_upload_dir();
        $filename = sanitize_file_name($plugin_data['slug'] . '-' . $plugin_data['version'] . '.zip');
        $file_path = $upload_dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            $error_msg = error_get_last();
            return new WP_Error('upload_failed', __('Error al mover el archivo', 'imagina-updater-server') . ': ' . ($error_msg['message'] ?? 'Desconocido'));
        }

        // Calcular checksum
        $checksum = hash_file('sha256', $file_path);
        $file_size = filesize($file_path);

        // Asegurar que el esquema esté actualizado
        self::ensure_schema_updated();

        // Guardar en base de datos
        global $wpdb;
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
        $table_versions = $wpdb->prefix . 'imagina_updater_versions';

        // Verificar si el plugin ya existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plugins WHERE slug = %s",
            $plugin_data['slug']
        ));

        // Usar transacciones para garantizar consistencia
        $wpdb->query('START TRANSACTION');

        try {
            if ($existing) {
                // Verificar si la versión es más nueva
                if (version_compare($plugin_data['version'], $existing->current_version, '<=')) {
                    if (!unlink($file_path)) {
                        error_log('IMAGINA UPDATER SERVER: No se pudo eliminar archivo: ' . $file_path);
                    }
                    throw new Exception(__('La versión debe ser mayor a la actual', 'imagina-updater-server'));
                }

                // Guardar versión anterior en historial
                $result = $wpdb->insert(
                    $table_versions,
                    array(
                        'plugin_id' => $existing->id,
                        'version' => $existing->current_version,
                        'file_path' => $existing->file_path,
                        'file_size' => $existing->file_size,
                        'checksum' => $existing->checksum,
                        'uploaded_at' => $existing->uploaded_at
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s')
                );

                if ($result === false) {
                    throw new Exception(__('Error al guardar historial de versión', 'imagina-updater-server'));
                }

                // Actualizar plugin con nueva versión
                $result = $wpdb->update(
                    $table_plugins,
                    array(
                        'current_version' => $plugin_data['version'],
                        'file_path' => $file_path,
                        'file_size' => $file_size,
                        'checksum' => $checksum,
                        'uploaded_at' => current_time('mysql'),
                        'name' => $plugin_data['name'],
                        'description' => $plugin_data['description'],
                        'author' => $plugin_data['author'],
                        'homepage' => $plugin_data['homepage']
                    ),
                    array('id' => $existing->id),
                    array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );

                if ($result === false) {
                    throw new Exception(__('Error al actualizar plugin', 'imagina-updater-server'));
                }

                $plugin_id = $existing->id;
            } else {
                // Crear nuevo plugin
                $result = $wpdb->insert(
                    $table_plugins,
                    array(
                        'slug' => $plugin_data['slug'],
                        'slug_override' => null,
                        'name' => $plugin_data['name'],
                        'description' => $plugin_data['description'],
                        'author' => $plugin_data['author'],
                        'homepage' => $plugin_data['homepage'],
                        'current_version' => $plugin_data['version'],
                        'file_path' => $file_path,
                        'file_size' => $file_size,
                        'checksum' => $checksum,
                        'uploaded_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
                );

                if ($result === false) {
                    throw new Exception(__('Error al crear plugin', 'imagina-updater-server'));
                }

                $plugin_id = $wpdb->insert_id;
            }

            // Guardar changelog si se proporcionó
            if (!empty($changelog)) {
                $result = $wpdb->insert(
                    $table_versions,
                    array(
                        'plugin_id' => $plugin_id,
                        'version' => $plugin_data['version'],
                        'file_path' => $file_path,
                        'file_size' => $file_size,
                        'checksum' => $checksum,
                        'changelog' => sanitize_textarea_field($changelog),
                        'uploaded_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
                );

                if ($result === false) {
                    throw new Exception(__('Error al guardar changelog', 'imagina-updater-server'));
                }
            }

            // Commit de la transacción
            $wpdb->query('COMMIT');

            return array(
                'id' => $plugin_id,
                'slug' => $plugin_data['slug'],
                'name' => $plugin_data['name'],
                'version' => $plugin_data['version']
            );

        } catch (Exception $e) {
            // Rollback en caso de error
            $wpdb->query('ROLLBACK');

            // Limpiar archivo si existe
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    error_log('IMAGINA UPDATER SERVER: No se pudo eliminar archivo tras error: ' . $file_path);
                }
            }

            return new WP_Error('transaction_failed', $e->getMessage());
        }
    }

    /**
     * Extraer información del plugin desde el ZIP
     *
     * @param string $zip_path Ruta al archivo ZIP
     * @return array|WP_Error
     */
    private static function extract_plugin_info($zip_path) {
        $zip = new ZipArchive();

        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_error', __('No se pudo abrir el archivo ZIP', 'imagina-updater-server'));
        }

        // Buscar archivo principal del plugin y extraer carpeta raíz
        $plugin_file = null;
        $plugin_folder = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Buscar archivos .php en el raíz o dentro de una carpeta (primer nivel)
            // Acepta: plugin.php o carpeta/plugin.php
            if (preg_match('/^([^\/]+\.php|[^\/]+\/[^\/]+\.php)$/', $filename)) {
                $content = $zip->getFromIndex($i);

                // Verificar si tiene los headers de plugin
                if (preg_match('/Plugin Name:/i', $content)) {
                    $plugin_file = $content;

                    // Extraer nombre de la carpeta desde la ruta del archivo
                    // Si es "carpeta/archivo.php", extraer "carpeta"
                    // Si es "archivo.php", usar el nombre del archivo sin extensión
                    if (strpos($filename, '/') !== false) {
                        $plugin_folder = substr($filename, 0, strpos($filename, '/'));
                    } else {
                        // Plugin en raíz del ZIP (sin carpeta)
                        $plugin_folder = basename($filename, '.php');
                    }

                    break;
                }
            }
        }

        $zip->close();

        if (!$plugin_file || !$plugin_folder) {
            return new WP_Error('no_plugin_header', __('No se encontró archivo de plugin válido en el ZIP', 'imagina-updater-server'));
        }

        // Extraer información usando función de WordPress
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_data = array();

        // Extraer headers del plugin
        if (preg_match('/Plugin Name:\s*(.+)/i', $plugin_file, $matches)) {
            $plugin_data['name'] = trim($matches[1]);
        }

        if (preg_match('/Version:\s*(.+)/i', $plugin_file, $matches)) {
            $plugin_data['version'] = trim($matches[1]);
        }

        if (preg_match('/Description:\s*(.+)/i', $plugin_file, $matches)) {
            $plugin_data['description'] = trim($matches[1]);
        }

        if (preg_match('/Author:\s*(.+)/i', $plugin_file, $matches)) {
            $plugin_data['author'] = trim($matches[1]);
        }

        if (preg_match('/Plugin URI:\s*(.+)/i', $plugin_file, $matches)) {
            $plugin_data['homepage'] = trim($matches[1]);
        }

        // Validar datos mínimos
        if (empty($plugin_data['name']) || empty($plugin_data['version'])) {
            return new WP_Error('invalid_plugin', __('El plugin no tiene nombre o versión definidos', 'imagina-updater-server'));
        }

        // USAR EL NOMBRE DE LA CARPETA COMO SLUG (no el nombre del plugin)
        // Esto asegura que el slug coincida con la estructura real del plugin
        $plugin_data['slug'] = sanitize_file_name($plugin_folder);

        return $plugin_data;
    }

    /**
     * Obtener todos los plugins
     *
     * @return array
     */
    public static function get_all_plugins() {
        self::ensure_schema_updated();

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    /**
     * Obtener un plugin por slug (verifica tanto slug como slug_override)
     *
     * @param string $slug Slug del plugin
     * @return object|null
     */
    public static function get_plugin_by_slug($slug) {
        self::ensure_schema_updated();

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Primero buscar por slug_override, luego por slug normal
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug_override = %s OR (slug_override IS NULL AND slug = %s) LIMIT 1",
            $slug,
            $slug
        ));
    }

    /**
     * Actualizar el slug personalizado de un plugin
     *
     * @param int $plugin_id ID del plugin
     * @param string|null $new_slug Nuevo slug (null para usar el auto-generado)
     * @return bool|WP_Error
     */
    public static function update_plugin_slug($plugin_id, $new_slug) {
        self::ensure_schema_updated();

        global $wpdb;
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Validar que el plugin existe
        $plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plugins WHERE id = %d",
            $plugin_id
        ));

        if (!$plugin) {
            error_log('IMAGINA UPDATER SERVER: Plugin no encontrado con ID: ' . $plugin_id);
            return new WP_Error('not_found', __('Plugin no encontrado', 'imagina-updater-server'));
        }

        // Si el nuevo slug es igual al slug auto-generado, usar NULL
        if ($new_slug === $plugin->slug) {
            $new_slug = null;
        }

        // Si se proporciona un slug, validarlo
        if ($new_slug !== null && $new_slug !== '') {
            // Sanitizar
            $new_slug = sanitize_title($new_slug);

            if (empty($new_slug)) {
                error_log('IMAGINA UPDATER SERVER: Slug inválido después de sanitizar');
                return new WP_Error('invalid_slug', __('Slug inválido', 'imagina-updater-server'));
            }

            // Verificar que no exista otro plugin con ese slug
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_plugins
                WHERE id != %d AND (slug = %s OR slug_override = %s)",
                $plugin_id,
                $new_slug,
                $new_slug
            ));

            if ($exists > 0) {
                error_log('IMAGINA UPDATER SERVER: Ya existe un plugin con slug: ' . $new_slug);
                return new WP_Error('slug_exists', __('Ya existe un plugin con ese slug', 'imagina-updater-server'));
            }
        } else {
            // Si está vacío, usar NULL para restablecer al auto-generado
            $new_slug = null;
        }

        error_log('IMAGINA UPDATER SERVER: Actualizando slug_override a: ' . var_export($new_slug, true) . ' para plugin ID: ' . $plugin_id);

        // Actualizar slug_override
        $result = $wpdb->update(
            $table_plugins,
            array('slug_override' => $new_slug),
            array('id' => $plugin_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            error_log('IMAGINA UPDATER SERVER: Error de BD al actualizar slug. Error: ' . $wpdb->last_error);
            return new WP_Error('update_failed', __('Error al actualizar el slug en la base de datos: ', 'imagina-updater-server') . $wpdb->last_error);
        }

        error_log('IMAGINA UPDATER SERVER: Slug actualizado exitosamente. Filas afectadas: ' . $result);

        return true;
    }

    /**
     * Obtener historial de versiones de un plugin
     *
     * @param int $plugin_id ID del plugin
     * @return array
     */
    public static function get_version_history($plugin_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_versions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE plugin_id = %d ORDER BY uploaded_at DESC",
            $plugin_id
        ));
    }

    /**
     * Eliminar un plugin y todas sus versiones
     *
     * @param int $plugin_id ID del plugin
     * @return bool|WP_Error
     */
    public static function delete_plugin($plugin_id) {
        global $wpdb;

        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
        $table_versions = $wpdb->prefix . 'imagina_updater_versions';

        // Obtener plugin
        $plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plugins WHERE id = %d",
            $plugin_id
        ));

        if (!$plugin) {
            return new WP_Error('not_found', __('Plugin no encontrado', 'imagina-updater-server'));
        }

        // Eliminar archivo actual
        if (file_exists($plugin->file_path)) {
            if (!unlink($plugin->file_path)) {
                error_log('IMAGINA UPDATER SERVER: No se pudo eliminar archivo actual: ' . $plugin->file_path);
            }
        }

        // Obtener y eliminar archivos de versiones antiguas
        $versions = self::get_version_history($plugin_id);
        foreach ($versions as $version) {
            if (file_exists($version->file_path)) {
                if (!unlink($version->file_path)) {
                    error_log('IMAGINA UPDATER SERVER: No se pudo eliminar versión antigua: ' . $version->file_path);
                }
            }
        }

        // Eliminar de base de datos
        $wpdb->delete($table_versions, array('plugin_id' => $plugin_id), array('%d'));
        $wpdb->delete($table_plugins, array('id' => $plugin_id), array('%d'));

        return true;
    }

    /**
     * Registrar descarga de plugin
     *
     * @param int $api_key_id ID de la API key
     * @param int $plugin_id ID del plugin
     * @param string $version Versión descargada
     */
    public static function log_download($api_key_id, $plugin_id, $version) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_downloads';

        $wpdb->insert(
            $table,
            array(
                'api_key_id' => $api_key_id,
                'plugin_id' => $plugin_id,
                'version' => $version,
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
                'downloaded_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Obtener IP del cliente de forma segura
     */
    private static function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validar y sanitizar IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        return $ip ? $ip : '';
    }
}
