<?php
/**
 * Sistema de logs independiente con rotación automática
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Logger {

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
        $this->log_dir = $upload_dir['basedir'] . '/imagina-updater-server-logs';
        $this->log_file = $this->log_dir . '/imagina-updater-server.log';

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
        $config = get_option('imagina_updater_server_config', array());
        return isset($config['enable_logging']) && $config['enable_logging'];
    }

    /**
     * Obtener nivel de log configurado
     */
    private function get_log_level() {
        $config = get_option('imagina_updater_server_config', array());
        return isset($config['log_level']) ? $config['log_level'] : self::LEVEL_INFO;
    }

    /**
     * Verificar si un nivel de log debe registrarse
     */
    private function should_log($level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        );

        $configured_level = $this->get_log_level();
        $configured_priority = isset($levels[$configured_level]) ? $levels[$configured_level] : 1;
        $current_priority = isset($levels[$level]) ? $levels[$level] : 1;

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
    private function format_message($message, $level, $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );

        // Agregar contexto si existe
        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return $formatted . PHP_EOL;
    }

    /**
     * Rotar archivos de log si es necesario
     */
    private function maybe_rotate_logs() {
        if (!file_exists($this->log_file)) {
            return;
        }

        $file_size = filesize($this->log_file);

        if ($file_size < $this->max_file_size) {
            return;
        }

        // Inicializar WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Rotar archivos existentes
        for ($i = $this->max_files - 1; $i > 0; $i--) {
            $old_file = $this->log_file . '.' . $i;
            $new_file = $this->log_file . '.' . ($i + 1);

            if (file_exists($old_file)) {
                if ($i === $this->max_files - 1) {
                    // Eliminar el archivo más antiguo
                    wp_delete_file($old_file);
                } else {
                    // Renombrar archivo
                    $wp_filesystem->move($old_file, $new_file);
                }
            }
        }

        // Rotar archivo actual
        $wp_filesystem->move($this->log_file, $this->log_file . '.1');
    }

    /**
     * Métodos de conveniencia para diferentes niveles
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
     * Obtener ruta del archivo de log
     */
    public function get_log_file() {
        return $this->log_file;
    }

    /**
     * Obtener directorio de logs
     */
    public function get_log_dir() {
        return $this->log_dir;
    }

    /**
     * Leer logs
     */
    public function read_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;

        $start_line = max(0, $total_lines - $lines);

        $file->seek($start_line);
        $log_lines = array();

        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $log_lines[] = $line;
            }
        }

        return array_reverse($log_lines);
    }

    /**
     * Limpiar logs
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            wp_delete_file($this->log_file);
        }

        // También limpiar archivos rotados
        for ($i = 1; $i <= $this->max_files; $i++) {
            $rotated_file = $this->log_file . '.' . $i;
            if (file_exists($rotated_file)) {
                wp_delete_file($rotated_file);
            }
        }
    }
}
