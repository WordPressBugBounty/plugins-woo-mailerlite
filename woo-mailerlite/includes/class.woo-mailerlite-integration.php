<?php

use MailerLite\Includes\Classes\Data\TrackingData;
use MailerLite\Includes\Classes\Process\ProductProcess;
use MailerLite\Includes\Classes\Settings\ApiSettings;
use MailerLite\Includes\Classes\Settings\ShopSettings;
use MailerLite\Includes\Shared\Api\ApiType;

/**
 * Integration Demo Integration.
 *
 * @package  Woo_Mailerlite_Integration
 * @category Integration
 */

if ( ! class_exists('Woo_Mailerlite_Integration')) :

    class Woo_Mailerlite_Integration
    {
        /**
         * MailerLite Settings.
         *
         * @var string
         */
        private $settings;

        /**
         * MailerLite alerts.
         *
         * @var array
         */
        private $alerts;

        /**
         * Init and hook in the integration.
         */
        public function __construct()
        {
            $this->settings = get_option('woocommerce_mailerlite_settings');
            $request = $_REQUEST;
            //making a request only on load of the integrations page
            if (isset($request['page']) && $request['page'] == 'mailerlite') {
                $this->getShopSettingsFromDb();
                add_action('admin_head', [$this, 'hide_wordpress_thankyou']);
                add_action('mailerlite_alerts', [$this, 'showAlerts'], 10, 1);
            }
            if ((isset($request['page']) && $request['page'] == 'wc-settings') && (isset($request['tab'])  && $request['tab'] == 'integration') && (isset($request['section']) && $request['section'] == 'mailerlite')) {
                wp_redirect(admin_url('admin.php?page=mailerlite'));
                exit;
            }
            // Load the settings.
            $this->create_new_initial_segments();


            if (get_option('ml_account_authenticated') || $this->get_option('api_status')) {
                if ((get_option('woo_ml_shop_id', false) !== false) && ( ! in_array(get_option('woo_ml_wizard_setup',
                        0), [1, 2]))) {
                    update_option('woo_ml_wizard_setup', 2);
                }
            }

            add_action('admin_menu', [$this, 'register_mailerlite_submenu_page'], 99);
            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_mailerlite',
                [\MailerLite\Includes\Classes\Settings\MailerLiteSettings::getInstance(), 'updateSettings']);
        }

        /**
         * Show alerts.
         * @param $alerts
         *
         * @return void
         */
        public function showAlerts($alerts)
        {
            if(isset($alerts['errors'])) {
                $this->alerts['error'] = $alerts['errors'];
            }
            if(isset($alerts['success'])) {
                $this->alerts['success'] = $alerts['success'];
            }
        }

        /**
         * Register MailerLite settings page
         * @return void
         */
        public function register_mailerlite_submenu_page()
        {
            add_submenu_page('woocommerce', 'MailerLite', 'MailerLite', 'manage_options', 'mailerlite',
                [$this, 'mailerlite_settings_page_callback']);
        }

        /**
         * MailerLite settings page render
         * @return void
         */
        public function mailerlite_settings_page_callback()
        {
            if (get_transient('ml-admin-notice-invalid-key')) {
                $this->showAlerts([
                    'errors' => get_transient('ml-admin-notice-invalid-key')
                ]);
            }

            if (get_option('woo_ml_wizard_setup', 0) >= 1) {

                $untracked_categories_count = 0;
                $untracked_products_count   = 0;

                if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
                    $untracked_categories_count = TrackingData::getInstance()->getUntrackedCategoriesCount();
                    $untracked_products_count   = TrackingData::getInstance()->getUntrackedProductsCount();
                }

                $untracked_customers_count = TrackingData::getInstance()->getUntrackedCustomersCount() + count(get_option('woo_ml_non_synced_customer', []));

                $tracked_categories_count = 0;
                $tracked_products_count   = 0;

                if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
                    $tracked_categories_count = TrackingData::getInstance()->getTrackedCategoriesCount();
                    $tracked_products_count   = TrackingData::getInstance()->getTrackedProductCount();
                }
                $total_tracked = TrackingData::getInstance()->getCustomersCount() - $untracked_customers_count;

                $total_untracked_resources = $untracked_categories_count + $untracked_products_count + $untracked_customers_count;
                $total_tracked_resources = $tracked_categories_count + $tracked_products_count + $total_tracked;
                $this->settings            = get_option('woocommerce_mailerlite_settings');

                $totalResourcesToTrack = $total_untracked_resources ?? 0;
                $total_untracked_resources = max($total_untracked_resources, 0);
            }
            echo "<div id='woo_ml_loader'></div>";
            if (get_option('woo_ml_wizard_setup', 0) == 2) {
                if ( ! woo_ml_integration_setup_completed()) {
                    woo_ml_setup_integration();
                }
                require_once WOO_MAILERLITE_DIR . 'includes/views/app.php';
            } else {
                $currentStep = get_option('woo_ml_wizard_setup', 0);
                require_once WOO_MAILERLITE_DIR . 'includes/views/wizard.php';
            }
        }

        /**
         * Hide Wordpress Thank you (footer)
         * @return void
         */
        public function hide_wordpress_thankyou()
        {
            echo '<style type="text/css">#wpfooter {display:none;}</style>';
            echo '<style type="text/css">.notice {display:none;}</style>';
        }


        public function getAllCustomers()
        {
            global $wpdb;
            $query = "SELECT count(DISTINCT email) FROM {$wpdb->prefix}wc_customer_lookup where email IS NOT NULL AND email != '';";
            return $wpdb->get_var($query);
        }


        /**
         * Create new initial segments
         * @return void
         */
        public function create_new_initial_segments()
        {
            if ( ! get_option('ml_new_group_segments')) {
                ShopSettings::getInstance()->wpSetConsumerData("....", "....", $this->get_option('group'),
                    $this->get_option('resubscribe'), [], true);
                update_option('ml_new_group_segments', true);
            }
        }

        /**
         * Getting groups, selected group, double opt-in and popups
         * settings from MailerLite, only on load of the integrations page.
         */
        public function getShopSettingsFromDb()
        {
            $result = ShopSettings::getInstance()->getShopSettingsFromDb();

            $api_key = get_option('woo_ml_key');
            if ( ! $api_key) {
                if ( ! empty($this->get_option('api_key'))) {
                    update_option('woo_ml_key', $this->get_option('api_key'));

                    $temp_key = '';
                    if ( ! empty($api_key)) {
                        $temp_key = "...." . substr($api_key, -4);
                    }
                    $this->update_option('api_key', $temp_key);
                }
            } else {
                $temp_key = '';
                if ( ! empty($api_key)) {
                    $temp_key = "...." . substr($api_key, -4);
                }
                $this->update_option('api_key', $temp_key);
            }

            if ( ! empty($result) && isset($result->settings)) {

                $settings = $result->settings;

                $doi = (get_option('double_optin') == true || get_option('double_optin') == 'yes') ? 'yes' : 'no';

                if ((int)get_option('woo_mailerlite_platform',
                        1) === ApiType::CLASSIC && $doi !== $settings->double_optin) {

                    $doi = $settings->double_optin;
                    update_option('double_optin', $doi);
                }

                $this->update_option('double_optin', $doi);

                $additional_sub_fields = (get_option('mailerlite_additional_sub_fields') == true) ? 'yes' : 'no';
                $this->update_option('additional_sub_fields', $additional_sub_fields);

                $disable_checkout_sync = (get_option('mailerlite_disable_checkout_sync') == true) ? 'yes' : 'no';
                $this->update_option('disable_checkout_sync', $disable_checkout_sync);

                $resubscribe = $settings->resubscribe ? 'yes' : 'no';
                $this->update_option('resubscribe', $resubscribe);
                $this->update_option('group', $settings->group_id);
                update_option('woo_ml_last_manually_tracked_order_id', $settings->last_tracked_order_id);
                $popups_disabled = get_option('mailerlite_popups_disabled');
                $this->update_option('popups', $popups_disabled ? 'no' : 'yes');
                $auto_update_enabled = get_option('woo_ml_auto_update');
                $this->update_option('auto_update_plugin', $auto_update_enabled ? 'yes' : 'no');
            } elseif (isset($result->active_state)) {
                update_option('ml_shop_not_active', true);
            }

        }

        /**
         * Update option
         * @param $key
         * @param $value
         *
         * @return bool
         */
        public function update_option($key, $value)
        {
            $this->settings[ $key ] = $value;
            return update_option( 'woocommerce_mailerlite_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_mailerlite', $this->settings ), 'yes' );
        }

        /**
         * Get option
         * @param $key
         *
         * @return mixed|string|null
         */
        public function get_option($key)
        {
            return $this->settings[$key] ?? null;
        }
    }

endif;