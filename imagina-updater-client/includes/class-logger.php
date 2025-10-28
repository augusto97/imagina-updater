<?php
/**
 * Sistema de logs independiente con rotación automática
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Client_Logger {

    /**
     * Instancia única
     */
    private static $instance = null;

    /**
     * Directorio de logs
     */
    private $log_dir;

    /**
     * Archivo de log actual
     */
    private $log_file;

    /**
     * Tamaño máximo del archivo de log (5MB por defecto)
     */
    private $max_file_size = 5242880; // 5MB

    /**
     * Número máximo de archivos de rotación a mantener
     */
    private $max_files = 5;

    /**
     * Niveles de log
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/imagina-updater-logs';
        $this->log_file = $this->log_dir . '/imagina-updater.log';

        // Crear directorio si no existe
        $this->ensure_log_directory();
    }

    /**
     * Asegurar que el directorio de logs existe y está protegido
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);

            // Crear .htaccess para proteger el directorio
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($this->log_dir . '/.htaccess', $htaccess_content);

            // Crear index.php vacío
            file_put_contents($this->log_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Verificar si el logging está habilitado
     */
    public function is_enabled() {
        $config = get_option('imagina_updater_client_config', array());
        return isset($config['enable_logging']) && $config['enable_logging'];
    }

    /**
     * Obtener nivel de log configurado
     */
    private function get_log_level() {
        $config = get_option('imagina_updater_client_config', array());
        return isset($config['log_level']) ? $config['log_level'] : self::LEVEL_INFO;
    }

    /**
     * Verificar si un nivel de log debe registrarse
     */
    private function should_log($level) {
        $configured_level = $this->get_log_level();

        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        );

        $current_priority = isset($levels[$level]) ? $levels[$level] : 0;
        $configured_priority = isset($levels[$configured_level]) ? $levels[$configured_level] : 1;

        return $current_priority >= $configured_priority;
    }

    /**
     * Escribir log
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        // Verificar si está habilitado
        if (!$this->is_enabled()) {
            return;
        }

        // Verificar nivel de log
        if (!$this->should_log($level)) {
            return;
        }

        // Verificar rotación de archivos
        $this->maybe_rotate_logs();

        // Formatear mensaje
        $formatted_message = $this->format_message($message, $level, $context);

        // Escribir al archivo
        file_put_contents($this->log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Formatear mensaje de log
     */
    private function format_message($message, $level, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );

        // Agregar contexto si existe
        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }

        $formatted .= PHP_EOL;

        return $formatted;
    }

    /**
     * Rotar archivos de log si es necesario
     */
    private function maybe_rotate_logs() {
        if (!file_exists($this->log_file)) {
            return;
        }

        if (filesize($this->log_file) < $this->max_file_size) {
            return;
        }

        // Rotar archivos existentes
        for ($i = $this->max_files - 1; $i >= 1; $i--) {
            $old_file = $this->log_file . '.' . $i;
            $new_file = $this->log_file . '.' . ($i + 1);

            if (file_exists($old_file)) {
                if ($i === $this->max_files - 1) {
                    // Eliminar el archivo más antiguo
                    unlink($old_file);
                } else {
                    // Renombrar al siguiente número
                    rename($old_file, $new_file);
                }
            }
        }

        // Renombrar el archivo actual
        rename($this->log_file, $this->log_file . '.1');
    }

    /**
     * Métodos de conveniencia para cada nivel
     */
    public function debug($message, $context = array()) {
        $this->log($message, self::LEVEL_DEBUG, $context);
    }

    public function info($message, $context = array()) {
        $this->log($message, self::LEVEL_INFO, $context);
    }

    public function warning($message, $context = array()) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }

    public function error($message, $context = array()) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * Obtener contenido del log actual
     */
    public function get_log_content($lines = 500) {
        if (!file_exists($this->log_file)) {
            return '';
        }

        // Leer las últimas N líneas del archivo
        $content = file($this->log_file);

        if ($content === false) {
            return '';
        }

        // Tomar las últimas líneas
        $content = array_slice($content, -$lines);

        return implode('', $content);
    }

    /**
     * Obtener tamaño del archivo de log
     */
    public function get_log_size() {
        if (!file_exists($this->log_file)) {
            return 0;
        }

        return filesize($this->log_file);
    }

    /**
     * Limpiar todos los logs
     */
    public function clear_logs() {
        // Eliminar archivo principal
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }

        // Eliminar archivos rotados
        for ($i = 1; $i <= $this->max_files; $i++) {
            $rotated_file = $this->log_file . '.' . $i;
            if (file_exists($rotated_file)) {
                unlink($rotated_file);
            }
        }
    }

    /**
     * Obtener ruta del archivo de log
     */
    public function get_log_file_path() {
        return $this->log_file;
    }

    /**
     * Obtener lista de archivos de log
     */
    public function get_log_files() {
        $files = array();

        // Archivo principal
        if (file_exists($this->log_file)) {
            $files[] = array(
                'name' => 'imagina-updater.log',
                'path' => $this->log_file,
                'size' => filesize($this->log_file),
                'modified' => filemtime($this->log_file)
            );
        }

        // Archivos rotados
        for ($i = 1; $i <= $this->max_files; $i++) {
            $rotated_file = $this->log_file . '.' . $i;
            if (file_exists($rotated_file)) {
                $files[] = array(
                    'name' => 'imagina-updater.log.' . $i,
                    'path' => $rotated_file,
                    'size' => filesize($rotated_file),
                    'modified' => filemtime($rotated_file)
                );
            }
        }

        return $files;
    }
}
