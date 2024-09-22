<?php

namespace MailerLite\Includes\Classes\Settings;


use MailerLite\Includes\Classes\Process\CheckoutProcess;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class ShopSettings extends Singleton
{
    /**
     * Get triggered on deactivate plugin event. Sends store name to api
     * to toggle its active status
     * mailerlite_wp_toggle_shop_connection
     *
     * @param bool $active_state
     *
     * @return bool|void
     */
    public function wpToggleShopConnection($active_state)
    {
        if (!ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $store = home_url();

            $shop_id = get_option('woo_ml_shop_id', false);

            if ($mailerliteClient->getApiType() === ApiType::CURRENT && $shop_id === false) {

                return false;
            }

            $shop_name = get_bloginfo('name');;

            return $mailerliteClient->toggleShop($store, $active_state, $shop_id, $shop_name);

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clears ml specific options from the database,
     * Drops mailerlite_checkouts table,
     * Sends api request
     * woo_ml_toggle_shop_connection
     *
     * @param Bool $active_status
     *
     * @return void
     */
    public function toggleShopConnection($active_status)
    {
        if (!$active_status) {
            delete_option('woocommerce_mailerlite_settings');
            delete_option('ml_account_authenticated');
            delete_option('double_optin');
            delete_option('woo_ml_version');
            delete_option('woo_ml_wizard_setup');
            delete_option('woo_ml_guests_sync_count');
            delete_option('woo_ml_shop_id');
            delete_option('woo_ml_account_name');
            delete_option('woo_ml_integration_setup');
            delete_option('woo_ml_last_synced_customer');
            MailerLiteSettings::getInstance()->getCurrentSelectedGroup(true);
            CheckoutProcess::getInstance()->dropMailerliteCheckoutsTable();
            if (!function_exists('WC')) {
                return false;
            }
            $this->wpToggleShopConnection($active_status);
            delete_option('woo_ml_key');
        } else {
            CheckoutProcess::getInstance()->createMailerliteCheckoutsTable();
            update_option('ml_account_authenticated', false);
        }
    }

    /**
     * API call to get all shop settings from the MailerLite side
     * mailerlite_wp_get_shop_settings_from_db
     * @return array|bool
     */
    public function getShopSettingsFromDb()
    {
        if (!ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {
                $result = $mailerliteClient->getShopSettings(home_url());

                if ($result) {

                    if (isset($result->deactivate) && $result->deactivate) {
                        $warning_msg = __('Your shop appears to be deactivated, please save the configuration to re-activate.',
                            'woo-mailerlite');

                        add_action('admin_notices', function () use ($warning_msg) {

                            printf('<div class="%1$s"><p>%2$s</p></div>',
                                esc_attr('notice notice-warning is-dismissible'), esc_html($warning_msg));
                        });
                    } else {
                        return $result;
                    }
                } else {
                    return false;
                }
            }

            if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update ignore product list
     * woo_ml_update_ignore_product_list
     * @return boolean
     */
    public function updateIgnoreProductList($products)
    {

        $settings = $this->getShopSettingsFromDb();

        if ($settings !== false) {

            $resubscribe = (MailerLiteSettings::getInstance()->getMlOption('resubscribe', 'no') == 'yes') ? 1 : 0;

            if (isset($settings->settings->resubscribe)) {

                $resubscribe = $settings->settings->resubscribe;
            }
            $mailerLiteSettings = MailerLiteSettings::getInstance();
            $results = $this->wpSetConsumerData(
                $mailerLiteSettings->getMlOption('consumer_key'),
                $mailerLiteSettings->getMlOption('consumer_secret'),
                $mailerLiteSettings->getMlOption('group'),
                $resubscribe,
                $products);

            return $results;
        }

        return false;
    }

    /**
     * Sends to api shop data needed to make back and forth connection with woo commerce
     * Api returns account id and subdomain used to for universal script
     * mailerlite_wp_set_consumer_data
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $apiKey
     *
     * @return array|bool
     */
    public function wpSetConsumerData(
        $consumerKey,
        $consumerSecret,
        $group,
        $resubscribe,
        $ignoreList = [],
        $create_segments = false
    )
    {
        if (!ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $store = home_url();
            $currency = get_option('woocommerce_currency');

            if (empty($group)) {
                return ['errors' => 'MailerLite - WooCommerce integration: Please select a group.'];
            }

            if (strpos($store, 'https://') !== false ) {

                $shop_name = get_bloginfo('name');
                $shop_id = get_option('woo_ml_shop_id', false);
                $popups_enabled = !get_option('mailerlite_popups_disabled');

                if (empty($shop_name)) {
                    $shop_name = $store;
                }

                if ($mailerliteClient->getApiType() === ApiType::CURRENT && $shop_id === false) {

                    $shops = $mailerliteClient->getShops();

                    foreach ($shops as $shop) {

                        if ($shop->url == $store) {

                            $shop_id = $shop->id;
                            update_option('woo_ml_shop_id', $shop->id);
                            break;
                        }
                    }
                }

                $result = $mailerliteClient->setConsumerData($consumerKey, $consumerSecret, $store, $currency, $group,
                    $resubscribe, $ignoreList, $create_segments, $shop_name, $shop_id, $popups_enabled);

                if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {

                    if (isset($result->account_id) && (isset($result->account_subdomain))) {

                        update_option('account_id', $result->account_id);
                        update_option('account_subdomain', $result->account_subdomain);
                        update_option('new_plugin_enabled', true);
                        update_option('ml_shop_not_active', false);
                        MailerLiteSettings::getInstance()->getCurrentSelectedGroup(true);
                    } elseif (isset($result->errors)) {
                        return ['errors' => $result->errors];
                    }
                }
                if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

                    if (isset($result->id) && ($mailerliteClient->responseCode() === 200 || $mailerliteClient->responseCode() === 201)) {

                        update_option('woo_ml_shop_id', $result->id);
                        update_option('new_plugin_enabled', true);
                        update_option('ml_shop_not_active', false);
                        update_option('mailerlite_popups_disabled', $result->enable_popups ? 0 : 1);
                    } elseif (isset($result->errors)) {

                        return ['errors' => $result->errors];
                    } elseif ($result === false) {

                        $response = json_decode($mailerliteClient->getResponseBody());

                        $message = $response->message ?? 'Unknown error.';

                        return ['errors' => $message];
                    }
                }

                return true;
            } else {
                return ['errors' => 'MailerLite - WooCommerce integration: Your shop url does not have the right security protocol'];
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Initialize sync field settings with all fields preselected
     */
    public function initSyncFields()
    {
        $settings = get_option('woocommerce_mailerlite_settings');

        if ($settings !== false) {

            $settings['sync_fields'] = [
                'name',
                'email',
                'company',
                'city',
                'zip',
                'state',
                'country',
                'phone'
            ];

            update_option( 'woocommerce_mailerlite_settings', $settings, 'yes' );
        }

        return $settings['sync_fields'] ?? [];
    }
}
