<?php

namespace MailerLite\Includes\Shared\Api;

use MailerLite\Includes\Classes\Settings\MailerLiteSettings;

class MailerLiteClient
{

    private $url;
    private $headers;
    private $timeout = 90;

    /**
     * Client constructor
     *
     * @access      public
     * @return      void
     * @since       1.6.0
     */
    public function __construct($url, $headers)
    {

        $this->url     = $url;
        $this->headers = $headers;
    }

    /**
     * Client for GET requests
     *
     * @access      public
     * @since       1.6.0
     */
    public function remote_get($endpoint, $args = [])
    {

        $args['body']       = $args;
        $args['headers']    = $this->headers;
        $args['timeout']    = $this->timeout;
        $args['user-agent'] = $this->userAgent();
        $this->log($endpoint, $args);
        return wp_remote_get($this->url . $endpoint, $args);
    }

    /**
     * Client for POST requests
     *
     * @access      public
     * @since       1.6.0
     */
    public function remote_post($endpoint, $args = [])
    {
        $params               = [];
        $params['headers']    = $this->headers;
        $params['body']       = json_encode($args);
        $params['timeout']    = $this->timeout;
        $params['user-agent'] = $this->userAgent();
        $this->log($endpoint, $params);
        return wp_remote_post($this->url . $endpoint, $params);
    }

    /**
     * Client for PUT requests
     *
     * @access      public
     * @since       1.6.0
     */
    public function remote_put($endpoint, $args = [])
    {
        $params               = [];
        $params['method']     = 'PUT';
        $params['headers']    = $this->headers;
        $params['body']       = json_encode($args);
        $params['timeout']    = $this->timeout;
        $params['user-agent'] = $this->userAgent();
        $this->log($endpoint, $params);
        return wp_remote_post($this->url . $endpoint, $params);
    }

    /**
     * Client for DELETE requests
     *
     * @access      public
     * @since       1.6.0
     */
    public function remote_delete($endpoint, $args = [])
    {

        $params               = [];
        $params['method']     = 'DELETE';
        $params['headers']    = $this->headers;
        $params['body']       = json_encode($args);
        $params['timeout']    = $this->timeout;
        $params['user-agent'] = $this->userAgent();
        $this->log($endpoint, $params);
        return wp_remote_post($this->url . $endpoint, $params);
    }

    private function userAgent()
    {
        global $wp_version;

        return 'MailerLite WooCommerce/' . WOO_MAILERLITE_VER . ' (WP/' . $wp_version . ' WOO/' . get_option('woocommerce_version',
                -1) . ')';
    }

    protected function log($endpoint, $args)
    {
        $allowedApis = [
            'https://connect.mailerlite.com/api'
        ];
        $settings = get_option('woo_ml_debug_funcions', []);

        if ((in_array($this->url, $allowedApis)) && (get_option('woo_ml_wizard_setup', 0) < 2 || MailerLiteSettings::getInstance()->getMlOption('woo_ml_debug_mode_enabled', false))) {
            if (isset($settings[$endpoint]) && $settings[$endpoint] == 5) {
                return true;
            }
            $body = $args['body'];
            unset($args['body']);
            $payload = $args;

            $payload['body']['data'] = is_array($body) ? $body : json_decode($body, true);
            $payload['body']['endpoint'] = $endpoint;


            if (!isset($settings[$endpoint])) {
                $settings[$endpoint] = 1;
            } else {
                $settings[$endpoint] += 1;
            }
            update_option('woo_ml_debug_funcions', $settings);


            $payload['body']['settings'] = [
                'plugin_settings' => get_option('woocommerce_mailerlite_settings', []),
                'ml_account_authenticated' => get_option('ml_account_authenticated', false),
                'double_optin' => get_option('double_optin', false),
                'woo_ml_version' => get_option('woo_ml_version', false),
                'woo_ml_wizard_setup' => get_option('woo_ml_wizard_setup', false),
                'woo_ml_guests_sync_count' => get_option('woo_ml_guests_sync_count', false),
                'woo_ml_shop_id' => get_option('woo_ml_shop_id', false),
                'woo_ml_account_name' => get_option('woo_ml_account_name', false),
                'woo_ml_integration_setup' => get_option('woo_ml_integration_setup', false),
                'woo_ml_last_synced_customer' => get_option('woo_ml_last_synced_customer', false),
            ];
            unset($payload['method']);
            $payload['body'] = json_encode($payload['body']);

            wp_remote_post($this->url . '/integrations/woocommerce/log', $payload);
        }
        return true;
    }
}