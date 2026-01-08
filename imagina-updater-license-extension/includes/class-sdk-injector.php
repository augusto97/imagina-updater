<?php
/**
 * Inyector de Protección de Licencias v4.0
 *
 * Inyecta automáticamente el código de protección en plugins premium.
 * Ya no requiere SDK externo - el código de protección es autónomo.
 *
 * @package Imagina_License_Extension
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_SDK_Injector {

    /**
     * Marcador de protección para detectar si ya está inyectado
     */
    const PROTECTION_MARKER = 'IMAGINA LICENSE PROTECTION';

    /**
     * Inyectar protección en un plugin si es premium y no la tiene
     *
     * @param string $plugin_zip_path Ruta al archivo ZIP del plugin
     * @param bool   $is_premium      Si el plugin es premium
     * @return array Array con 'success' y 'message'
     */
    public static function inject_sdk_if_needed($plugin_zip_path, $is_premium) {
        // Si no es premium, no hacer nada
        if (!$is_premium) {
            return array(
                'success' => true,
                'message' => 'Plugin no es premium, no se requiere protección'
            );
        }

        // Verificar que el archivo existe
        if (!file_exists($plugin_zip_path)) {
            return array(
                'success' => false,
                'message' => 'Archivo ZIP no encontrado: ' . $plugin_zip_path
            );
        }

        // Verificar que ZipArchive está disponible
        if (!class_exists('ZipArchive')) {
            return array(
                'success' => false,
                'message' => 'Extensión ZipArchive no disponible'
            );
        }

        // Crear directorio temporal
        $temp_dir = self::get_temp_dir();
        $extract_dir = $temp_dir . '/' . uniqid('plugin_');

        // Extraer ZIP
        $zip = new ZipArchive();
        if ($zip->open($plugin_zip_path) !== true) {
            return array(
                'success' => false,
                'message' => 'No se pudo abrir el archivo ZIP'
            );
        }

        $zip->extractTo($extract_dir);
        $zip->close();

        // Buscar el directorio del plugin (primer nivel)
        $plugin_dir = self::find_plugin_directory($extract_dir);
        if (!$plugin_dir) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => false,
                'message' => 'No se encontró el directorio del plugin en el ZIP'
            );
        }

        // Buscar archivo principal del plugin
        $main_file = self::find_main_plugin_file($plugin_dir);
        if (!$main_file) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => false,
                'message' => 'No se encontró el archivo principal del plugin'
            );
        }

        // Verificar si ya tiene protección
        if (self::has_protection($main_file)) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => true,
                'message' => 'El plugin ya tiene protección de licencias'
            );
        }

        // Extraer metadatos del plugin
        $plugin_data = self::extract_plugin_metadata($main_file);
        if (!$plugin_data) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => false,
                'message' => 'No se pudieron extraer los metadatos del plugin'
            );
        }

        // Obtener URL del servidor
        $server_url = self::get_server_url();

        // Inyectar código de protección
        $inject_result = self::inject_protection_code($main_file, $plugin_data, $server_url);
        if (!$inject_result['success']) {
            self::cleanup_directory($extract_dir);
            return $inject_result;
        }

        // Re-empaquetar el plugin
        $rezip_result = self::rezip_plugin($extract_dir, $plugin_zip_path);
        self::cleanup_directory($extract_dir);

        if (!$rezip_result['success']) {
            return $rezip_result;
        }

        // Registrar el checksum del código de protección
        self::register_protection_checksum($plugin_data['slug']);

        // Actualizar slug_override en la base de datos para que coincida
        self::update_plugin_slug_override($plugin_data['name'], $plugin_data['slug']);

        return array(
            'success' => true,
            'message' => 'Protección de licencias inyectada correctamente',
            'plugin_slug' => $plugin_data['slug']
        );
    }

    /**
     * Verificar si el archivo ya tiene protección
     *
     * @param string $file_path
     * @return bool
     */
    private static function has_protection($file_path) {
        $content = file_get_contents($file_path);
        return strpos($content, self::PROTECTION_MARKER) !== false;
    }

    /**
     * Inyectar el código de protección en el archivo principal
     *
     * @param string $main_file   Ruta del archivo principal
     * @param array  $plugin_data Datos del plugin
     * @param string $server_url  URL del servidor
     * @return array
     */
    private static function inject_protection_code($main_file, $plugin_data, $server_url) {
        // Cargar el generador de protección
        require_once dirname(__FILE__) . '/class-protection-generator.php';

        // Generar el código de protección
        $protection_code = Imagina_License_Protection_Generator::generate(
            $plugin_data['name'],
            $plugin_data['slug'],
            $server_url
        );

        // Leer el contenido actual
        $content = file_get_contents($main_file);

        if ($content === false) {
            return array(
                'success' => false,
                'message' => 'No se pudo leer el archivo principal del plugin'
            );
        }

        // Buscar el lugar adecuado para inyectar (después de la verificación de ABSPATH)
        $injection_point = self::find_injection_point($content);

        if ($injection_point === false) {
            return array(
                'success' => false,
                'message' => 'No se encontró un punto de inyección adecuado'
            );
        }

        // Inyectar el código
        $new_content = substr($content, 0, $injection_point) .
                       "\n" . $protection_code . "\n" .
                       substr($content, $injection_point);

        // Guardar el archivo modificado
        if (file_put_contents($main_file, $new_content) === false) {
            return array(
                'success' => false,
                'message' => 'No se pudo escribir el archivo principal del plugin'
            );
        }

        return array(
            'success' => true,
            'message' => 'Código de protección inyectado'
        );
    }

    /**
     * Encontrar el punto de inyección en el código
     *
     * @param string $content
     * @return int|false
     */
    private static function find_injection_point($content) {
        // Patrones ordenados por preferencia
        $patterns = array(
            // Después de verificación ABSPATH con die
            '/if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*\{\s*(die|exit)[^}]*\}\s*\n/i',
            // Después de verificación ABSPATH con exit simple
            '/if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*(die|exit)\s*;?\s*\n/i',
            // Después de verificación WPINC
            '/if\s*\(\s*!\s*defined\s*\(\s*[\'"]WPINC[\'"]\s*\)\s*\)\s*\{\s*(die|exit)[^}]*\}\s*\n/i',
            // Después del bloque de comentarios del plugin header
            '/\*\/\s*\n/',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                // Retornar la posición después del match
                return $matches[0][1] + strlen($matches[0][0]);
            }
        }

        // Fallback: después del tag <?php
        $php_tag_pos = strpos($content, '<?php');
        if ($php_tag_pos !== false) {
            // Encontrar el final de la primera línea o bloque de comentarios
            $next_newline = strpos($content, "\n", $php_tag_pos);
            if ($next_newline !== false) {
                return $next_newline + 1;
            }
        }

        return false;
    }

    /**
     * Extraer metadatos del plugin
     *
     * @param string $plugin_file Archivo principal del plugin
     * @return array|false
     */
    private static function extract_plugin_metadata($plugin_file) {
        $content = file_get_contents($plugin_file);

        if (!$content) {
            return false;
        }

        // Extraer el Plugin Name
        if (!preg_match('/Plugin Name:\s*(.+)$/mi', $content, $matches)) {
            return false;
        }

        $plugin_name = trim($matches[1]);

        // Generar slug del nombre del directorio
        $dir_name = basename(dirname($plugin_file));
        $plugin_slug = sanitize_title($dir_name);

        // Si el directorio es temporal, usar el nombre del archivo
        if (strpos($dir_name, 'plugin_') === 0) {
            $plugin_slug = sanitize_title(basename($plugin_file, '.php'));
        }

        // Intentar extraer Text Domain como slug alternativo
        if (preg_match('/Text Domain:\s*(.+)$/mi', $content, $td_matches)) {
            $text_domain = trim($td_matches[1]);
            if (!empty($text_domain)) {
                $plugin_slug = sanitize_key($text_domain);
            }
        }

        return array(
            'name' => $plugin_name,
            'slug' => $plugin_slug
        );
    }

    /**
     * Buscar el archivo principal del plugin
     *
     * @param string $plugin_dir Directorio del plugin
     * @return string|false Ruta del archivo principal o false
     */
    private static function find_main_plugin_file($plugin_dir) {
        $files = glob($plugin_dir . '/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Plugin Name:') !== false) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Buscar el directorio del plugin en el directorio de extracción
     *
     * @param string $extract_dir Directorio de extracción
     * @return string|false Ruta del directorio del plugin o false
     */
    private static function find_plugin_directory($extract_dir) {
        $items = scandir($extract_dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $extract_dir . '/' . $item;

            if (is_dir($full_path)) {
                // Verificar si contiene un archivo PHP con Plugin Name:
                $php_files = glob($full_path . '/*.php');
                foreach ($php_files as $php_file) {
                    $content = file_get_contents($php_file);
                    if (strpos($content, 'Plugin Name:') !== false) {
                        return $full_path;
                    }
                }
            }
        }

        // Si no hay directorio, verificar si los archivos están en la raíz
        $php_files = glob($extract_dir . '/*.php');
        foreach ($php_files as $php_file) {
            $content = file_get_contents($php_file);
            if (strpos($content, 'Plugin Name:') !== false) {
                return $extract_dir;
            }
        }

        return false;
    }

    /**
     * Re-empaquetar el plugin en ZIP
     *
     * @param string $extract_dir Directorio raíz de extracción
     * @param string $output_zip  Ruta del ZIP de salida
     * @return array
     */
    private static function rezip_plugin($extract_dir, $output_zip) {
        // Crear backup del ZIP original
        $backup_path = $output_zip . '.backup';
        if (file_exists($output_zip)) {
            copy($output_zip, $backup_path);
            unlink($output_zip);
        }

        $zip = new ZipArchive();
        if ($zip->open($output_zip, ZipArchive::CREATE) !== true) {
            // Restaurar backup
            if (file_exists($backup_path)) {
                rename($backup_path, $output_zip);
            }
            return array(
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP'
            );
        }

        // Agregar todos los archivos del directorio
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extract_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($extract_dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }

        $zip->close();

        // Eliminar backup
        if (file_exists($backup_path)) {
            unlink($backup_path);
        }

        return array(
            'success' => true,
            'message' => 'Plugin re-empaquetado correctamente'
        );
    }

    /**
     * Obtener URL del servidor desde la configuración
     *
     * @return string
     */
    private static function get_server_url() {
        // Intentar obtener de la configuración del servidor
        $server_url = get_option('imagina_updater_server_url', '');

        if (empty($server_url)) {
            // Usar la URL del sitio actual como fallback
            $server_url = home_url();
        }

        return $server_url;
    }

    /**
     * Registrar el checksum del código de protección
     *
     * @param string $plugin_slug
     */
    private static function register_protection_checksum($plugin_slug) {
        require_once dirname(__FILE__) . '/class-protection-generator.php';

        $checksums = get_option('imagina_license_protection_checksums', array());
        $checksums[$plugin_slug] = array(
            'version' => Imagina_License_Protection_Generator::PROTECTION_VERSION,
            'updated_at' => current_time('mysql'),
        );

        update_option('imagina_license_protection_checksums', $checksums);

        // Log
        if (function_exists('imagina_license_log')) {
            imagina_license_log(sprintf(
                'Protección inyectada en plugin: %s (versión %s)',
                $plugin_slug,
                Imagina_License_Protection_Generator::PROTECTION_VERSION
            ), 'info');
        }
    }

    /**
     * Actualizar slug_override en la base de datos del plugin
     *
     * Esto asegura que el slug usado por la protección coincida con lo que
     * el servidor espera al validar licencias.
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $generated_slug Slug generado por el injector
     */
    private static function update_plugin_slug_override($plugin_name, $generated_slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Buscar el plugin por nombre exacto
        $plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT id, slug, slug_override FROM $table WHERE name = %s",
            $plugin_name
        ));

        if (!$plugin) {
            // Intentar búsqueda más flexible (LIKE con escape)
            $plugin = $wpdb->get_row($wpdb->prepare(
                "SELECT id, slug, slug_override FROM $table WHERE name LIKE %s",
                '%' . $wpdb->esc_like($plugin_name) . '%'
            ));
        }

        if ($plugin) {
            // Solo actualizar si el slug_override es diferente o está vacío
            if (empty($plugin->slug_override) || $plugin->slug_override !== $generated_slug) {
                $wpdb->update(
                    $table,
                    array('slug_override' => $generated_slug),
                    array('id' => $plugin->id),
                    array('%s'),
                    array('%d')
                );

                if (function_exists('imagina_license_log')) {
                    imagina_license_log(sprintf(
                        'Actualizado slug_override para plugin %s: %s → %s',
                        $plugin_name,
                        $plugin->slug_override ?: '(vacío)',
                        $generated_slug
                    ), 'info');
                }
            }
        } else {
            if (function_exists('imagina_license_log')) {
                imagina_license_log(sprintf(
                    'No se encontró plugin "%s" en la base de datos para actualizar slug_override',
                    $plugin_name
                ), 'warning');
            }
        }
    }

    /**
     * Obtener directorio temporal
     *
     * @return string
     */
    private static function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/imagina-license-temp';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);

            // Crear .htaccess para protección
            file_put_contents($temp_dir . '/.htaccess', 'Deny from all');
        }

        return $temp_dir;
    }

    /**
     * Limpiar directorio temporal
     *
     * @param string $dir Directorio a limpiar
     */
    private static function cleanup_directory($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Remover protección de un plugin (para testing/desarrollo)
     *
     * @param string $plugin_zip_path
     * @return array
     */
    public static function remove_protection($plugin_zip_path) {
        if (!file_exists($plugin_zip_path)) {
            return array(
                'success' => false,
                'message' => 'Archivo ZIP no encontrado'
            );
        }

        $temp_dir = self::get_temp_dir();
        $extract_dir = $temp_dir . '/' . uniqid('plugin_');

        $zip = new ZipArchive();
        if ($zip->open($plugin_zip_path) !== true) {
            return array(
                'success' => false,
                'message' => 'No se pudo abrir el archivo ZIP'
            );
        }

        $zip->extractTo($extract_dir);
        $zip->close();

        $plugin_dir = self::find_plugin_directory($extract_dir);
        if (!$plugin_dir) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => false,
                'message' => 'No se encontró el directorio del plugin'
            );
        }

        $main_file = self::find_main_plugin_file($plugin_dir);
        if (!$main_file) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => false,
                'message' => 'No se encontró el archivo principal'
            );
        }

        $content = file_get_contents($main_file);

        // Remover el bloque de protección
        $pattern = '/\n?\/\/ ={10,}\n\/\/ IMAGINA LICENSE PROTECTION.*?\/\/ END IMAGINA LICENSE PROTECTION\n\/\/ ={10,}\n?/s';
        $new_content = preg_replace($pattern, '', $content);

        if ($new_content !== $content) {
            file_put_contents($main_file, $new_content);

            $rezip_result = self::rezip_plugin($extract_dir, $plugin_zip_path);
            self::cleanup_directory($extract_dir);

            if (!$rezip_result['success']) {
                return $rezip_result;
            }

            return array(
                'success' => true,
                'message' => 'Protección removida correctamente'
            );
        }

        self::cleanup_directory($extract_dir);
        return array(
            'success' => true,
            'message' => 'El plugin no tenía protección'
        );
    }

    /**
     * Verificar si un plugin tiene protección actualizada
     *
     * @param string $plugin_slug
     * @return array
     */
    public static function check_protection_status($plugin_slug) {
        require_once dirname(__FILE__) . '/class-protection-generator.php';

        $checksums = get_option('imagina_license_protection_checksums', array());

        if (!isset($checksums[$plugin_slug])) {
            return array(
                'has_protection' => false,
                'needs_update' => true,
                'message' => 'Plugin no tiene protección registrada'
            );
        }

        $current_version = Imagina_License_Protection_Generator::PROTECTION_VERSION;
        $installed_version = $checksums[$plugin_slug]['version'];

        $needs_update = version_compare($installed_version, $current_version, '<');

        return array(
            'has_protection' => true,
            'needs_update' => $needs_update,
            'installed_version' => $installed_version,
            'current_version' => $current_version,
            'updated_at' => $checksums[$plugin_slug]['updated_at']
        );
    }
}
