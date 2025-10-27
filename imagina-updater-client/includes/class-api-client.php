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
    private function request($endpoint, $method = 'GET', $body = null) {
        $url = $this->server_url . 'wp-json/imagina-updater/v1/' . ltrim($endpoint, '/');

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            )
        );

        if ($body !== null && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
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
     * Obtener URL de descarga para un plugin
     */
    public function get_download_url($slug) {
        return $this->server_url . 'wp-json/imagina-updater/v1/download/' . $slug . '?api_key=' . urlencode($this->api_key);
    }
}
