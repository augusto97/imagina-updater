<?php
/**
 * Inyector automático de SDK de licencias en plugins premium
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_SDK_Injector {

    /**
     * Inyectar SDK en un plugin si es premium y no lo tiene
     *
     * @param string $plugin_zip_path Ruta al archivo ZIP del plugin
     * @param bool $is_premium Si el plugin es premium
     * @return array Array con 'success' y 'message'
     */
    public static function inject_sdk_if_needed($plugin_zip_path, $is_premium) {
        // Si no es premium, no hacer nada
        if (!$is_premium) {
            return array(
                'success' => true,
                'message' => 'Plugin no es premium, no se requiere SDK'
            );
        }

        // Verificar que el archivo existe
        if (!file_exists($plugin_zip_path)) {
            return array(
                'success' => false,
                'message' => 'Archivo ZIP no encontrado: ' . $plugin_zip_path
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

        // Verificar si ya tiene el SDK
        if (self::plugin_has_sdk($plugin_dir)) {
            self::cleanup_directory($extract_dir);
            return array(
                'success' => true,
                'message' => 'SDK ya existe en el plugin'
            );
        }

        // Inyectar SDK
        $inject_result = self::inject_sdk_to_plugin($plugin_dir);
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

        return array(
            'success' => true,
            'message' => 'SDK inyectado correctamente'
        );
    }

    /**
     * Verificar si el plugin ya tiene el SDK
     *
     * @param string $plugin_dir Directorio del plugin
     * @return bool
     */
    private static function plugin_has_sdk($plugin_dir) {
        $sdk_loader = $plugin_dir . '/imagina-license-sdk/loader.php';
        return file_exists($sdk_loader);
    }

    /**
     * Inyectar SDK en el plugin
     *
     * @param string $plugin_dir Directorio del plugin
     * @return array
     */
    private static function inject_sdk_to_plugin($plugin_dir) {
        // Crear directorio del SDK
        $sdk_dir = $plugin_dir . '/imagina-license-sdk';
        if (!wp_mkdir_p($sdk_dir)) {
            return array(
                'success' => false,
                'message' => 'No se pudo crear el directorio del SDK'
            );
        }

        // Copiar archivos del SDK
        $source_sdk_dir = IMAGINA_LICENSE_PLUGIN_DIR . 'includes/license-sdk';
        $files_to_copy = array(
            'loader.php',
            'class-crypto.php',
            'class-license-validator.php',
            'class-heartbeat.php'
        );

        foreach ($files_to_copy as $file) {
            $source = $source_sdk_dir . '/' . $file;
            $dest = $sdk_dir . '/' . $file;

            if (!file_exists($source)) {
                return array(
                    'success' => false,
                    'message' => 'Archivo SDK no encontrado: ' . $file
                );
            }

            if (!copy($source, $dest)) {
                return array(
                    'success' => false,
                    'message' => 'No se pudo copiar el archivo: ' . $file
                );
            }
        }

        // Agregar código de inicialización al archivo principal del plugin
        $main_file = self::find_main_plugin_file($plugin_dir);
        if (!$main_file) {
            return array(
                'success' => false,
                'message' => 'No se encontró el archivo principal del plugin'
            );
        }

        $inject_code_result = self::inject_initialization_code($main_file);
        if (!$inject_code_result['success']) {
            return $inject_code_result;
        }

        return array(
            'success' => true,
            'message' => 'SDK inyectado correctamente'
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
     * Inyectar código de inicialización en el archivo principal
     *
     * @param string $main_file Ruta del archivo principal
     * @return array
     */
    private static function inject_initialization_code($main_file) {
        $content = file_get_contents($main_file);

        // Verificar si ya tiene el código de inicialización
        if (strpos($content, 'imagina-license-sdk/loader.php') !== false) {
            return array(
                'success' => true,
                'message' => 'Código de inicialización ya existe'
            );
        }

        // Extraer metadatos del plugin
        $plugin_data = self::extract_plugin_metadata($main_file);

        if (!$plugin_data) {
            return array(
                'success' => false,
                'message' => 'No se pudieron extraer los metadatos del plugin'
            );
        }

        // Cargar el generador de código seguro
        require_once dirname(__FILE__) . '/class-sdk-injector-secure.php';

        // Generar código de protección multicapa
        $init_code = "\n" . Imagina_License_SDK_Injector_Secure::generate_secure_code(
            $plugin_data['name'],
            $plugin_data['slug']
        ) . "\n";

        // Buscar un lugar adecuado para inyectar (después de la verificación de ABSPATH)
        $patterns = array(
            "/if\s*\(\s*!\s*defined\s*\(\s*['\"]ABSPATH['\"]\s*\)\s*\)\s*\{[^}]*\}\s*\n/",
            "/if\s*\(\s*!\s*defined\s*\(\s*['\"]WPINC['\"]\s*\)\s*\)\s*\{[^}]*\}\s*\n/",
            "/<\?php\s*\n/"
        );

        $injected = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $insert_position = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, $init_code, $insert_position, 0);
                $injected = true;
                break;
            }
        }

        // Si no encontramos ningún patrón, inyectar después de <?php
        if (!$injected) {
            $content = preg_replace('/<\?php/', "<?php\n" . $init_code, $content, 1);
        }

        // Guardar el archivo modificado
        if (!file_put_contents($main_file, $content)) {
            return array(
                'success' => false,
                'message' => 'No se pudo escribir el archivo principal del plugin'
            );
        }

        return array(
            'success' => true,
            'message' => 'Código de inicialización inyectado'
        );
    }

    /**
     * Extraer metadatos del plugin
     *
     * @param string $plugin_file Archivo principal del plugin
     * @return array|false Array con 'name' y 'slug' o false en error
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

        // Generar slug del nombre del archivo
        $basename = basename($plugin_file, '.php');
        $plugin_slug = sanitize_title($basename);

        return array(
            'name' => $plugin_name,
            'slug' => $plugin_slug
        );
    }

    /**
     * Re-empaquetar el plugin en ZIP
     *
     * @param string $extract_dir Directorio raíz de extracción
     * @param string $output_zip Ruta del ZIP de salida
     * @return array
     */
    private static function rezip_plugin($extract_dir, $output_zip) {
        // Eliminar el ZIP anterior
        if (file_exists($output_zip)) {
            unlink($output_zip);
        }

        $zip = new ZipArchive();
        if ($zip->open($output_zip, ZipArchive::CREATE) !== true) {
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

        return array(
            'success' => true,
            'message' => 'Plugin re-empaquetado correctamente'
        );
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

        return false;
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
}
