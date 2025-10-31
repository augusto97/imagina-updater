<?php
/**
 * Cliente API para conectarse al servidor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Client_API {

    /**
     * URL del servidor
     */
    private $server_url;

    /**
     * API Key
     */
    private $api_key;

    /**
     * Constructor
     */
    public function __construct($server_url, $api_key) {
        $this->server_url = trailingslashit($server_url);
        $this->api_key = $api_key;
    }

    /**
     * Realizar petición al servidor
     */
    private function request($endpoint, $method = 'GET', $body = null, $timeout = null) {
        $url = $this->server_url . 'wp-json/imagina-updater/v1/' . ltrim($endpoint, '/');

        // Timeout según tipo de operación
        if ($timeout === null) {
            // Downloads necesitan más tiempo
            if (strpos($endpoint, '/download/') !== false) {
                $timeout = 120; // 2 minutos para descargas
            } else {
                $timeout = 15; // 15 segundos para API calls normales
            }
        }

        // Obtener dominio del sitio para validación
        $site_domain = parse_url(home_url(), PHP_URL_HOST);

        $args = array(
            'method' => $method,
            'timeout' => $timeout,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'X-Site-Domain' => $site_domain // Enviar dominio para validación de activation token
            )
        );

        if ($body !== null && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        // Manejo mejorado de errores HTTP
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();

            // Identificar tipo específico de error
            if ($error_code === 'http_request_failed' && strpos($error_message, 'timed out') !== false) {
                return new WP_Error(
                    'timeout',
                    __('Tiempo de espera agotado al conectar con el servidor. Por favor, intenta nuevamente.', 'imagina-updater-client'),
                    array('original_error' => $error_message)
                );
            } elseif ($error_code === 'http_request_failed' && strpos($error_message, 'resolve host') !== false) {
                return new WP_Error(
                    'dns_error',
                    __('No se pudo resolver el dominio del servidor. Verifica la URL configurada.', 'imagina-updater-client'),
                    array('original_error' => $error_message)
                );
            } elseif ($error_code === 'http_request_failed') {
                return new WP_Error(
                    'connection_error',
                    __('Error de conexión con el servidor. Verifica tu conexión a internet.', 'imagina-updater-client'),
                    array('original_error' => $error_message)
                );
            }

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : __('Error en la petición al servidor', 'imagina-updater-client');

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        return json_decode($body, true);
    }

    /**
     * Validar conexión con el servidor
     */
    public function validate() {
        return $this->request('validate');
    }

    /**
     * Obtener lista de plugins disponibles
     */
    public function get_plugins() {
        return $this->request('plugins');
    }

    /**
     * Obtener información de un plugin específico
     */
    public function get_plugin_info($slug) {
        return $this->request('plugin/' . $slug);
    }

    /**
     * Verificar actualizaciones para múltiples plugins
     *
     * @param array $plugins Array asociativo con slug => versión instalada
     * @return array|WP_Error Array de actualizaciones disponibles o WP_Error
     */
    public function check_updates($plugins) {
        return $this->request('check-updates', 'POST', array('plugins' => $plugins));
    }

    /**
     * Obtener URL de descarga para un plugin (sin API key en URL por seguridad)
     */
    public function get_download_url($slug) {
        return $this->server_url . 'wp-json/imagina-updater/v1/download/' . $slug;
    }

    /**
     * Obtener API key (para inyectar en headers durante descarga)
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Obtener server URL
     */
    public function get_server_url() {
        return $this->server_url;
    }

    /**
     * Activar sitio (usar API key para obtener activation token)
     *
     * @return array|WP_Error Array con activation_token o WP_Error
     */
    public function activate_site() {
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        if (empty($site_domain)) {
            return new WP_Error('invalid_domain', __('No se pudo determinar el dominio del sitio', 'imagina-updater-client'));
        }

        return $this->request('activate', 'POST', array(
            'api_key' => $this->api_key,
            'site_domain' => $site_domain
        ));
    }

    /**
     * Desactivar licencia (eliminar activación en el servidor)
     *
     * @return array|WP_Error
     */
    public function deactivate_license() {
        return $this->request('deactivate-self', 'POST');
    }
}
