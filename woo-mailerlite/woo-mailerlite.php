<?php

use MailerLite\Includes\Classes\Process\CheckoutProcess;
use MailerLite\Includes\Classes\Settings\ShopSettings;

/**
 * Plugin Name:     MailerLite - WooCommerce integration
 * Plugin URI:      https://wordpress.org/plugins/woo-mailerlite/
 * Description:     Official MailerLite integration for WooCommerce. Track sales and campaign ROI, import products details, automate emails based on purchases and seamlessly add your customers to your email marketing lists via WooCommerce's checkout process.
 * Version:         2.1.21
 * Author:          MailerLite
 * Author URI:      https://mailerlite.com
 * Text Domain:     woo-mailerlite
 * WC tested up to: 8.8.5
 * WC requires at least: 3.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin path
define('WOO_MAILERLITE_DIR', plugin_dir_path(__FILE__));

if (!class_exists('Woo_Mailerlite')) {

    /**
     * Main Woo_Mailerlite class
     *
     * @since       1.0.0
     */
    class Woo_Mailerlite
    {

        /**
         * @var         Woo_Mailerlite $instance The one true Woo_Mailerlite
         * @since       1.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @return      object self::$instance The one true Woo_Mailerlite
         * @since       1.0.0
         */
        public static function instance()
        {
            if (!self::$instance) {
                self::$instance = new Woo_Mailerlite();
                self::$instance->setup_constants();
                self::$instance->includes();
                self::$instance->hooks();
                self::$instance->load_textdomain();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @return      void
         * @since       1.0.0
         */
        private function setup_constants()
        {
            // Plugin name
            define('WOO_MAILERLITE_NAME', 'MailerLite - WooCommerce integration');

            // Plugin version
            define('WOO_MAILERLITE_VER', '2.1.21');

            // Plugin URL
            define('WOO_MAILERLITE_URL', plugin_dir_url(__FILE__));

            // Plugin prefix
            define('WOO_MAILERLITE_PREFIX', 'woo_ml_');

            // API Key
            if (!defined('MAILERLITE_WP_API_KEY')) {
                $option_value = get_option('woo_ml_key');

                $api_key = $option_value ?: '';
                define('MAILERLITE_WP_API_KEY', $api_key);

            }
            // Other
            define('WOO_MAILERLITE_MIN_PHP_VERSION', '7.2.5');
        }

        /**
         * Include necessary files
         *
         * @access      private
         * @return      void
         * @since       1.0.0
         */
        private function includes()
        {

            // Get out if WooCommerce is not active
            if (!class_exists('WC_Integration')) {
                return;
            }

            if (!$this->check_server_requirements()) {
                return;
            }

            $this->check_plugin_version();

            // Classes
            initClasses();
            // Dependencies
            require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-api-type.php';
            require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-api-client.php';
            require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-platform-api.php';
            require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-classic-api.php';
            require_once WOO_MAILERLITE_DIR . 'includes/shared/api/class.woo-mailerlite-api.php';
            require_once WOO_MAILERLITE_DIR . 'includes/shared/mailerlite-wp-functions.php';

            // Core functions and hooks
            require_once WOO_MAILERLITE_DIR . 'includes/functions.php';
            require_once WOO_MAILERLITE_DIR . 'includes/integration-setup-functions.php';
            require_once WOO_MAILERLITE_DIR . 'includes/hooks.php';
            require_once WOO_MAILERLITE_DIR . 'includes/scripts.php';

            // Admin functions and hooks
            if (is_admin()) {
                require_once WOO_MAILERLITE_DIR . 'includes/admin/hooks.php';
                require_once WOO_MAILERLITE_DIR . 'includes/admin/ajax.php';
                require_once WOO_MAILERLITE_DIR . 'includes/admin/meta-boxes.php';
            }

            // Include our integration class.
            include_once WOO_MAILERLITE_DIR . 'includes/class.woo-mailerlite-integration.php';

            // Register the integration.
            add_action('plugins_loaded', function() {
                new Woo_MailerLite_Integration();
            }, 12);
        }

        /**
         * Fire some hooks
         */
        private function hooks()
        {
            add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

            $auto_update = get_option('woo_ml_auto_update');

            if ($auto_update != false) {
                add_filter('auto_update_plugin', array($this, 'plugin_update'), PHP_INT_MAX, 2);
            }
        }

        /**
         * Add plugin action links
         *
         * @param $links
         * @param $file
         *
         * @return mixed
         */
        public function plugin_action_links($links, $file)
        {

            if ($file !== 'woo-mailerlite/woo-mailerlite.php') {
                return $links;
            }

            if (!$this->check_server_requirements()) {
                $info = '<span style="color: #ff0000; font-weight: bold;">' . sprintf(esc_html__('PHP Version %1$s or newer required',
                        'woo-mailerlite'), WOO_MAILERLITE_MIN_PHP_VERSION) . '</span>';
                array_unshift($links, $info);

                return $links;
            }

            if (class_exists('WC_Integration')) {
                $settings_link = '<a href="' . admin_url('admin.php?page=mailerlite') . '">' . esc_html__('Settings',
                        'woo-mailerlite') . '</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }

        /**
         * Enable always update for MailerLite plugin
         *
         * @param $update
         * @param $item
         *
         * @return bool
         */
        public function plugin_update($update, $item)
        {

            $plugins = [
                'woo-mailerlite'
            ];

            if (isset($item->slug)) {

                if (in_array($item->slug, $plugins)) {
                    // Always update plugins
                    return true;
                }
            }

            return $update;
        }


        /**
         * Internationalization
         *
         * @access      public
         * @return      void
         * @since       1.0.0
         */
        public function load_textdomain()
        {
            // Set filter for language directory
            $lang_dir = WOO_MAILERLITE_DIR . '/languages/';
            $lang_dir = apply_filters('woo_mailerlite_languages_directory', $lang_dir);

            // Traditional WordPress plugin locale filter
            $locale = apply_filters('plugin_locale', get_locale(), 'woo-mailerlite');
            $mofile = sprintf('%1$s-%2$s.mo', 'woo-mailerlite', $locale);

            // Setup paths to current locale file
            $mofile_local = $lang_dir . $mofile;
            $mofile_global = WP_LANG_DIR . '/woo-mailerlite/' . $mofile;

            if (file_exists($mofile_global)) {
                // Look in global /wp-content/languages/woo-mailerlite/ folder
                load_textdomain('woo-mailerlite', $mofile_global);
            } elseif (file_exists($mofile_local)) {
                // Look in local /wp-content/plugins/woo-mailerlite/languages/ folder
                load_textdomain('woo-mailerlite', $mofile_local);
            } else {
                // Load the default language files
                load_plugin_textdomain('woo-mailerlite', false, $lang_dir);
            }
        }

        /**
         * Check web server requirements
         *
         * @return bool
         */
        private function check_server_requirements()
        {

            if (version_compare(phpversion(), WOO_MAILERLITE_MIN_PHP_VERSION, '<')) {
                return false;
            }

            return true;
        }

        /**
         * Check plugin is updated
         *
         * @return bool
         */

        private function check_plugin_version()
        {

            if (!is_admin()) {
                return false;
            }

            return true;
        }
    }
} // End if class_exists check

/**
 * The main function responsible for returning the one true Woo_Mailerlite
 * instance to functions everywhere
 * @return      \Woo_Mailerlite The one true Woo_Mailerlite
 *
 * @since       1.0.0
 */
function woo_ml_load()
{
    return Woo_Mailerlite::instance();
}

add_action('plugins_loaded', 'woo_ml_load');

function woo_ml_deactivate()
{
    require_once WOO_MAILERLITE_DIR . 'includes/functions.php';
    ShopSettings::getInstance()->toggleShopConnection(0);
}

register_deactivation_hook(__FILE__, 'woo_ml_deactivate');

function woo_ml_activate()
{
    require_once WOO_MAILERLITE_DIR . 'includes/functions.php';
    initClasses();
    ShopSettings::getInstance()->toggleShopConnection(1);
}

register_activation_hook(__FILE__, 'woo_ml_activate');

function woo_mlb_reload_checkout()
{
    require_once WOO_MAILERLITE_DIR . 'includes/functions.php';
    initClasses();
    CheckoutProcess::getInstance()->reloadCheckout();
}

add_action('init', 'woo_mlb_reload_checkout');

function woo_ml_deactivate_woo_ml_plugin($deactivate = false)
{
    if ($deactivate) {
        woo_mlb_reload_checkout();

        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(plugin_basename(__FILE__), true);
    }
}

add_action('woocommerce_blocks_loaded', function () {

    if (class_exists('\Automattic\WooCommerce\Blocks\Package') &&
        interface_exists('\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {

        require_once __DIR__ . '/includes/woo-mailerlite-blocks-integration.php';

        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function ($integration_registry) {
                $integration_registry->register(new WooMlBlock_Integration());
            }
        );

        add_filter(
            '__experimental_woocommerce_blocks_add_data_attributes_to_block',
            function ($allowed_blocks) {
                $allowed_blocks[] = 'mailerlite-block/woo-mailerlite';

                return $allowed_blocks;
            },
            10,
            1
        );
    }
});

function register_WooML_block_category($categories)
{

    return array_merge(
        $categories,
        [
            [
                'slug' => 'woo-mailerlite',
                'title' => __('MailerLite - WooCommerce integration block', 'woo-mailerlite'),
            ],
        ]
    );
}

function initClasses()
{
    // Classes
    require_once WOO_MAILERLITE_DIR . 'includes/classes/Singleton.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/data/TrackingData.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/settings/MailerLiteSettings.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/settings/ShopSettings.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/settings/ApiSettings.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/settings/SynchronizeSettings.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/settings/ResetSettings.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/process/OrderProcess.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/process/ProductProcess.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/process/CartProcess.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/process/CheckoutProcess.php';
    require_once WOO_MAILERLITE_DIR . 'includes/classes/process/OrderSyncProcess.php';


}

add_action('block_categories_all', 'register_WooML_block_category', 10, 2);


/**
 * Declaring MailerLite HPOS compatibility
 */
function woo_ml_hpos_compatibility()
{
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

add_action( 'before_woocommerce_init', 'woo_ml_hpos_compatibility');
