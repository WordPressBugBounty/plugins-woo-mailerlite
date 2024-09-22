<?php

namespace MailerLite\Includes\Classes\Settings;

use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class ApiSettings extends Singleton
{

    /**
     * Get settings api key status
     * woo_ml_settings_get_api_key_status
     * @return string
     */
    public function getApiKeyStatus()
    {

        $api_status = MailerLiteSettings::getInstance()->getMlOption('api_status', false);

        return ($api_status) ? '<span style="color: green;">' . __('Valid',
                'woo-mailerlite') . '</span>' : '<span style="color: red;">' . __('Invalid',
                'woo-mailerlite') . '</span>';
    }

    /**
     * Validate given API key
     * woo_ml_validate_api_key
     *
     * @param $api_key
     *
     * @return bool
     */
    public function validateApiKey($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        return $this->apiKeyValidation($api_key);
    }

    /**
     * Check MailerLite API connection
     * mailerlite_wp_api_key_validation
     *
     * @param $api_key
     *
     * @return bool
     */
    public function apiKeyValidation($api_key)
    {

        if (empty($api_key)) {
            return false;
        }

        try {
            $resetSettings = ResetSettings::getInstance();
            // delete cached options preventing some sites to update settings
            wp_cache_delete('alloptions', 'options');

            $mailerliteClient = new PlatformAPI($api_key);
            $result           = $mailerliteClient->validateAccount();

            if (isset($result) && ! isset($result->errors) && $mailerliteClient->responseCode() !== 401 && $result !== false) {
                $settings               = get_option('woocommerce_mailerlite_settings');
                $settings['api_key']    = $api_key;
                $settings['api_status'] = true;

                update_option('ml_account_authenticated', true, false);
                update_option('woo_ml_key', $api_key, false);
                update_option('woo_ml_wizard_setup', 1, false);
                if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {
                    $settings['double_optin'] = $result->double_optin;
                    $settings['consumer_key'] = '';
                    $settings['consumer_secret'] = '';
                    update_option('double_optin', $result->double_optin);
                }

                if ((int)get_option('woo_mailerlite_platform', 1) !== $mailerliteClient->getApiType()) {
                    $resetSettings->resetShop();
                }

                $shop_id = get_option('woo_ml_shop_id', false);

                if ($shop_id !== false) {

                    $verify_shop = $mailerliteClient->getShop($shop_id);

                    if ($verify_shop === false) {

                        $resetSettings->resetShop();

                        delete_option('woo_ml_shop_id');
                    }
                }

                update_option('woo_mailerlite_platform', $mailerliteClient->getApiType());

                if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

                    update_option('account_id', $result->id);
                    update_option('account_subdomain', '');

                    $settings['double_optin'] = $result->double_optin ? 'yes' : 'no';
                    update_option('double_optin', $result->double_optin ? 'yes' : 'no');

                    if (isset($result->name)) {
                        update_option('woo_ml_account_name', $result->name);
                    }
                } else if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {
                    $accountDetails = $mailerliteClient->getAccountDetails();
                    if(isset($accountDetails->account)) {
                        update_option('woo_ml_account_name', $accountDetails->account->name ?? '');
                    }
                }

                $initAdditionalSettings = [
                    'resubscribe' => '',
                    'checkout' => '',
                    'checkout_position' => '',
                    'checkout_preselect' => '',
                    'checkout_hide' => '',
                    'checkout_label' => '',
                    'additional_sub_fields' => '',
                    'disable_checkout_sync' => '',
                    'popups' => '',
                    'ignore_product_list' => '',
                    'auto_update_plugin' => ''
                ];
                $settings = array_merge($settings, $initAdditionalSettings);

                update_option('woocommerce_mailerlite_settings', $settings, false);

                return true;
            } else {

                switch ($mailerliteClient->responseCode()) {
                    case 401 :
                        set_transient('ml-admin-notice-invalid-key', 'Invalid API Key. Please enter a correct one and click Connect account again.', 5);
                        break;
                    case 0 :
                        set_transient('ml-admin-notice-invalid-key', $mailerliteClient->getResponseBody(), 5);
                        break;
                    default:
                        set_transient('ml-admin-notice-invalid-key', 'Error: ' . $mailerliteClient->responseCode(), 5);
                }
            }

        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Check wether API key exists or not
     * mailerlite_wp_api_key_exists
     * @return bool
     */
    public function wpApiKeyExists()
    {
        if (defined('MAILERLITE_WP_API_KEY') && ! empty(MAILERLITE_WP_API_KEY)) {
            return true;
        }

        return false;
    }

}
