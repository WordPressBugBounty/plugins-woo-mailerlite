<?php

namespace MailerLite\Includes\Classes\Settings;

use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\PlatformAPI;
use MailerLite\Includes\Classes\Data\TrackingData;
use MailerLite\Includes\Classes\Process\ProductProcess;
use MailerLite\Includes\Shared\Api\ApiType;

class MailerLiteSettings extends Singleton
{
    /**
     * Class instance
     * @var $instance
     */
    protected static $instance;

    /**
     * Check if checkout action is active
     * woo_ml_is_active
     * @return mixed
     */
    public function isActive()
    {
        $api_status = $this->getMlOption('api_status', false);

        return $api_status;
    }

    /**
     * Get settings option
     * woo_ml_get_option
     *
     * @param $key
     * @param null $default
     *
     * @return null
     */
    public function getMlOption($key, $default = null)
    {
        $settings = get_option('woocommerce_mailerlite_settings');

        return (isset($settings[$key])) ? $settings[$key] : $default;
    }

    /**
     *
     * woo_ml_sync_failed
     * @return bool
     */
    public function syncFailed()
    {
        return get_option('woo_ml_resource_sync_failed') || (get_option('woo_ml_sync_active') && ! get_transient('woo_ml_resource_sync_in_progress'));
    }

    /**
     * Get settings group options
     * woo_ml_settings_get_group_options
     * @return array
     */
    public function getGroupOptions($onlyNames = false)
    {

        if ( ! is_admin()) {
            return [];
        }

        $options = array();
        // init groups params
        $params = [];
        // groups limit
        $params['limit'] = 100;
        // pagination for new api
        if (isset($_POST['page'])) {
            if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
                $params['page'] = $_POST['page'];
                if (isset($_POST['filter'])) {
                    if ($_POST['filter'] !== "") {
                        $params['filter'] = ['name' => $_POST['filter']];
                    }
                }
            } else if((int)get_option('woo_mailerlite_platform', 1) === ApiType::CLASSIC) {
                $params['offset'] = 0;
                if ($_POST['page'] !== '1') {
                    $params['offset'] = ($_POST['page'] - 1)  * $params['limit'];
                }
                if (isset($_POST['filter'])) {
                    if ($_POST['filter'] !== "") {
                        $params['filters'] = ['name' => ['$like' => '%' . $_POST['filter'] . '%']];
                    } else {
                        $_POST['page'] = 1;
                        $params['offset'] = 0;
                    }
                }
            }
        }


        // offset for classic

        $groups = mailerlite_wp_get_groups($params);
        $namesOnly = [];
        if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CLASSIC) {
            foreach ($groups as $group) {
                $namesOnly[] = $group->name;
                $options['data'][] = [
                    'id' => $group->id,
                    'name' => $group->name,
                ];
            }
        } else if ($groups && is_array($groups['data']) && sizeof($groups['data']) > 0) {
            foreach ($groups['data'] as $group) {
                $namesOnly[] = $group['name'];
                if (isset($group['id']) && isset($group['name'])) {
                    $options['data'][] = [
                        'id' => $group['id'],
                        'name' => $group['name'],
                    ];
                }
            }
        }

        if ($onlyNames) {
            return $namesOnly;
        }
        $options['pagination'] = $groups['pagination']  ?? (isset($_POST['page'])) ? $_POST['page'] + 1 : 1;
        return $options;
    }

    /**
     * Map array in correct structure for WooCommerce Integration
     * woo_ml_remap_list
     * @return array
     */
    public function remapList($products)
    {

        return array_map('strval', array_keys($products));
    }

    /**
     * Creates the ml_data table and sets the ml_data_table option flag
     * woo_create_mailer_data_table
     */
    public function createMailerDataTable()
    {

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . 'ml_data';

        $sql = "CREATE TABLE $table (
            data_name varchar(45) NOT NULL,
            data_value text NOT NULL,
            PRIMARY KEY  (data_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('ml_data_table', 1);

    }

    /**
     * Set WooCommerce MailerLite settings options
     * woo_ml_set_option
     *
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function setOption($key, $value)
    {
        $settings = get_option('woocommerce_mailerlite_settings');

        if (isset($settings[$key])) {
            $settings[$key] = $value;
        }

        update_option('woocommerce_mailerlite_settings', $settings);

        return true;
    }

    /**
     * Remove product from ignored
     * woo_ml_remove_ignore_product
     *
     * @param $product_id
     */
    public function removeIgnoreProduct($product_id)
    {

        delete_post_meta($product_id, '_woo_ml_product_ignored');
    }

    /**
     * Mark product as ignored for automation
     * woo_ml_ignore_product
     *
     * @param $product_id
     */
    public function ignoreProduct($product_id)
    {

        add_post_meta($product_id, '_woo_ml_product_ignored', true, true);
    }

    /**
     * Mark category as being tracked
     * woo_ml_complete_category_tracking
     *
     * @param $category_id
     * @param $ecommerce_id
     */
    public function completeCategoryTracking($category_id, $ecommerce_id)
    {

        add_term_meta($category_id, '_woo_ml_category_tracked', $ecommerce_id, true);
    }

    /**
     * Mark product as being tracked
     * woo_ml_complete_product_tracking
     *
     * @param $product_id
     */
    public function completeProductTracking($product_id)
    {

        add_post_meta($product_id, '_woo_ml_product_tracked', true, true);
    }

    /**
     * Check whether product was already tracked or not
     * woo_ml_product_tracking_completed
     *
     * @param $product_id
     *
     * @return bool
     */
    public function checkProductTracking($product_id)
    {

        $product_tracked = get_post_meta($product_id, '_woo_ml_product_tracked', true);

        return ('1' == $product_tracked) ? true : false;
    }

    /**
     * Get subscriber fields from customer data
     * woo_ml_get_subscriber_fields_from_customer_data
     *
     * @param $customer_data
     *
     * @return array
     */
    public function getSubscriberFieldsFromCustomerData($customer_data)
    {

        $settings = get_option('woocommerce_mailerlite_settings', []);

        $sync_fields = [];

        if ( isset($settings['sync_fields']) && is_array($settings['sync_fields'])) {

            $sync_fields = $settings['sync_fields'];
        }

        if (empty($sync_fields)) {
            $sync_fields = ShopSettings::getInstance()->initSyncFields();
        }

        $subscriber_fields = array();

        if ( ! empty($customer_data['first_name'])) {
            $subscriber_fields['name'] = $customer_data['first_name'];
        }

        if ( ! empty($customer_data['last_name'])) {
            $subscriber_fields['last_name'] = $customer_data['last_name'];
        }

        if ( ! empty($customer_data['company']) && in_array('company', $sync_fields)) {
            $subscriber_fields['company'] = $customer_data['company'];
        }

        if ( ! empty($customer_data['city']) && in_array('city', $sync_fields)) {
            $subscriber_fields['city'] = $customer_data['city'];
        }

        if ( ! empty($customer_data['postcode']) && in_array('zip', $sync_fields)) {
            if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
                $subscriber_fields['z_i_p'] = $customer_data['postcode'];
            } else {
                $subscriber_fields['zip'] = $customer_data['postcode'];
            }
        }

        if ( ! empty($customer_data['state']) && in_array('state', $sync_fields)) {
            $subscriber_fields['state'] = $customer_data['state'];
        }

        if ( ! empty($customer_data['country']) && in_array('country', $sync_fields)) {
            $subscriber_fields['country'] = $customer_data['country'];
        }

        if ( ! empty($customer_data['phone']) && in_array('phone', $sync_fields)) {
            $subscriber_fields['phone'] = $customer_data['phone'];
        }

        return $subscriber_fields;
    }

    public function createGroup($name)
    {
        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);
        $response         = $mailerliteClient->createGroup($name);
        if ($mailerliteClient->responseCode() === 200 || $mailerliteClient->responseCode() === 201) {
            set_transient('ml-admin-group-created', 'Group created successfully', 5);
        } else {
            set_transient('ml-admin-notice-invalid-key', 'Error: ' . $mailerliteClient->responseCode(), 5);
        }
        return $response;
    }

    public function updateSettings($data)
    {
        $data['resubscribe']           = $data['resubscribe'] ?? 'no';
        $data['additional_sub_fields'] = $data['additional_sub_fields'] ?? 'no';
        $data['checkout_preselect']    = $data['checkout_preselect'] ?? 'no';
        $data['disable_checkout_sync'] = $data['disable_checkout_sync'] ?? 'no';
        $data['popups']                = $data['popups'] ?? 'no';
        $data['auto_update_plugin']    = $data['auto_update_plugin'] ?? 'no';
        $data['double_optin']          = $data['double_optin'] ?? 'no';
        $data['checkout_hide']         = $data['checkout_hide'] ?? 'no';
        $data['checkout']              = $data['checkout'] ?? 'no';
        $data['checkout_label']        = (isset($data['checkout_label']) && ($data['checkout_label'] != '')) ? $data['checkout_label'] : 'Yes, I want to receive your newsletter.';
        $settings           = get_option('woocommerce_mailerlite_settings', null);
        $data['api_key']    = $settings['api_key'] ?? null;
        $data['api_status'] = $settings['api_status'] ?? false;

        if ( ! isset($data['group'])) {
            $data['group'] = $settings['group'] ?? '';
        }

        if ( ! isset($data['ignore_product_list'])) {
            $data['ignore_product_list'] = $settings['ignore_product_list'] ?? '';
        }

        $setup_integration        = false;
        $revoke_integration_setup = false;

        if (isset($data['api_key'])) {

            $api_status = $settings['api_status'];
            $api_key    = $settings['api_key'];

            if (empty($data['api_key'])) {

                $api_status               = false;
                $revoke_integration_setup = true;
                delete_option('woo_ml_key');
                delete_option('ml_account_authenticated');
            } elseif ( ! empty($data['api_key']) && $data['api_key'] != $api_key) {
                $validation = ApiSettings::getInstance()->validateApiKey(esc_html($data['api_key']));
                $api_status = ($validation);

                if ($api_status) {
                    $setup_integration = true;
                    update_option('woo_ml_key', $data['api_key']);
                }
                $data['api_key'] = "...." . substr($data['api_key'], -4);
            }

            // Store API validation
            $data['api_status'] = $api_status;

        }

        // Handle Double Opt-In
        if (isset($data['double_optin'])) {

            if ($data['double_optin'] != $settings['double_optin']) {

                $double_optin = ('yes' === $data['double_optin']) ? true : false;

                $data['double_optin'] = mailerlite_wp_set_double_optin($double_optin) === true ? 'yes' : 'no';
            }
        }

        // Handle Additional Subscriber
        if (isset($data['additional_sub_fields'])) {
            $sub_fields_enabled = $data['additional_sub_fields'] === 'yes' ? 1 : 0;
            update_option('mailerlite_additional_sub_fields', $sub_fields_enabled);

            if ($sub_fields_enabled) {
                woo_ml_setup_additional_sub_fields();
            }
        }

        // Handle Do not add subscribers on checkout
        if (isset($data['disable_checkout_sync'])) {
            $disable_checkout_sync = $data['disable_checkout_sync'] === 'yes' ? 1 : 0;
            update_option('mailerlite_disable_checkout_sync', $disable_checkout_sync);
        }

        if (isset($data['popups']) && ($data['popups'] !== $settings['popups'])) {
            $popups_disabled = $data['popups'] === 'yes' ? 0 : 1;
            update_option('mailerlite_popups_disabled', $popups_disabled);
        }

        if (isset($data['auto_update_plugin'])) {
            $auto_update_enabled = $data['auto_update_plugin'] === 'yes' ? 1 : 0;
            update_option('woo_ml_auto_update', $auto_update_enabled);
        }

        // save shop to our db for ecommerce tracking
        // hiding the ck and cs values once save performed as we don't need to have them saved here anyway
        // we only need them for backwards connection from classic api to plugin to get products and categories.
        if (( ! empty($data['consumer_key']) && ! empty($data['consumer_secret'])) || (int)get_option('woo_mailerlite_platform',
                1) === ApiType::CURRENT) {

            if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
                $data['consumer_key']    = '';
                $data['consumer_secret'] = '';
            }

            $resubscribe = isset($data['resubscribe']) && ($data['resubscribe'] === 'yes') ? 1 : 0;

            $result = ShopSettings::getInstance()->wpSetConsumerData(
                $data['consumer_key'],
                $data['consumer_secret'],
                $data['group'],
                $resubscribe,
                $data['ignore_product_list']);
            if (isset($result['errors']) || $result === false) {
                $data['consumer_key']    = '';
                $data['consumer_secret'] = '';
                do_action('mailerlite_alerts', $result);
                set_transient('invalid_consumer_keys', $result['errors'], 10);
            } else {
                $data['consumer_key']    = '....' . substr($data['consumer_key'], -4);
                $data['consumer_secret'] = '....' . substr($data['consumer_secret'], -4);

                //update product ignore list in ml_data
                $ignore_list = ProductProcess::getInstance()->getIgnoredProductList();

                $products = array_filter($ignore_list, function ($k) use ($data) {
                    return in_array($k, $data['ignore_product_list']);
                }, ARRAY_FILTER_USE_KEY);

                TrackingData::getInstance()->updateData($products);
            }
        }

        // Handle integration setup
        if ($revoke_integration_setup) {
            woo_ml_revoke_integration_setup();
        }

        if ($setup_integration) {
            woo_ml_setup_integration();
        }
        do_action('mailerlite_alerts', [
            'success' => 'Your settings have been saved.'
        ]);

        return $data;
    }

    /*
     * Reset plugin with resources
     */
    public function softReset()
    {
        do {
            $finished = \MailerLite\Includes\Classes\Settings\ResetSettings::getInstance()->resetTrackedResources(true);
        } while (!$finished);
        ShopSettings::getInstance()->toggleShopConnection(0);
        header('Location: ' . admin_url('admin.php?page=mailerlite'));
    }

    /*
     * Reset plugin without resetting resources
     */
    public function softResetPlugin()
    {
        ShopSettings::getInstance()->toggleShopConnection(0);
        header('Location: ' . admin_url('admin.php?page=mailerlite'));
    }

    /**
     * Verify custom fields
     * @return void
     */
    public function verify_custom_fields()
    {
        if (is_admin() && get_option('ml_account_authenticated')) {
            woo_ml_setup_integration_custom_fields();
        }
    }

    public function getCurrentSelectedGroup($reset = false)
    {
        if (!$reset) {
            $cachedGroup = wp_cache_get('woo_mailerlite_selected_group');
            if ($cachedGroup) {
                return $cachedGroup;
            }
        } else {
            wp_cache_delete('woo_mailerlite_selected_group');
            return true;
        }
        if (!MAILERLITE_WP_API_KEY) {
            return false;
        }
        $apiClient = new \MailerLite\Includes\Shared\Api\PlatformAPI(MAILERLITE_WP_API_KEY);
        if (!$this->getMlOption('group')) {
            MailerLiteSettings::getInstance()->softResetPlugin();
        }
        $group = $apiClient->getGroupById($this->getMlOption('group'));
        if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
            $group = $group->data;
        }
        $selectedGroup = ['id' => $group->id, 'name'=> $group->name];
        wp_cache_add('woo_mailerlite_selected_group', $selectedGroup, '', 600);
        return $selectedGroup;
    }

    public function pluginSettingsUpdate()
    {
        if (isset($_POST['group'])) {
            if (defined('DOING_AJAX') && DOING_AJAX) {
                if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
                    wp_send_json_error( 'Invalid security token sent.' );
                    wp_die();
                }
            } else if (!wp_verify_nonce($_POST['_wpnonce'], 'ml_save_settings_nonce')) {
                exit;
            }
            if(!isset($_POST['checkout'])) {
                $_POST = array_diff_key($_POST, array_flip(['checkout_position', 'checkout_preselect', 'checkout_hide', 'checkout_label', 'disable_checkout_sync']));
            }
            $_POST['resubscribe']           = $_POST['resubscribe'] ?? 'no';
            $_POST['additional_sub_fields'] = $_POST['additional_sub_fields'] ?? 'no';
            $_POST['checkout_preselect']    = $_POST['checkout_preselect'] ?? 'no';
            $_POST['disable_checkout_sync'] = $_POST['disable_checkout_sync'] ?? 'no';
            $_POST['popups']                = $_POST['popups'] ?? 'no';
            $_POST['auto_update_plugin']    = $_POST['auto_update_plugin'] ?? 'no';
            $_POST['double_optin']          = $_POST['double_optin'] ?? 'no';
            $_POST['checkout_hide']         = $_POST['checkout_hide'] ?? 'no';
            $_POST['checkout']              = $_POST['checkout'] ?? 'no';
            $_POST['checkout_label']        = (isset($_POST['checkout_label']) && ($_POST['checkout_label'] != '')) ? $_POST['checkout_label'] : 'Yes, I want to receive your newsletter.';
            $_POST['sync_fields']           = $_POST['sync_fields'] ?? [];

            update_option('woocommerce_mailerlite_settings',
                apply_filters('woocommerce_settings_api_sanitized_fields_mailerlite', array_merge(get_option('woocommerce_mailerlite_settings', []), $_POST)), 'yes');
        }
    }
}
