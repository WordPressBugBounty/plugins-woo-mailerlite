<?php

use MailerLite\Includes\Classes\Settings\ApiSettings;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

/**
 * Functions inside this file are being used in different MailerLite related WordPress plugins
 */

if ( ! function_exists('mailerlite_wp_get_groups')) :
    /**
     * Get groups from API
     *
     * @param $api_key
     *
     * @return array|bool
     */
    function mailerlite_wp_get_groups($params)
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        $groups = array();

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() === ApiType::INVALID) {

                return false;
            }
            $results = $mailerliteClient->getGroups($params);
            if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {
                if (count($results) >= 1) {
                    return $results;
                }
            }
            if (isset($results['data'])) {
                return $results;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_get_subscriber_by_email')) :
    /**
     * Get subscriber from API by email
     *
     * @param $email
     *
     * @return mixed
     */
    function mailerlite_wp_get_subscriber_by_email($email)
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        if (empty($email)) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $subscriber = $mailerliteClient->searchSubscriber($email);

            if (isset($subscriber->id)) {
                return $subscriber;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_update_subscriber')) :
    /**
     * Update subscriber via API
     *
     * @param $subscriber_email
     * @param array $subscriber_data
     *
     * @return mixed
     */
    function mailerlite_wp_update_subscriber($subscriber_email, $subscriber_data = array())
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        if (empty($subscriber_email)) {
            return false;
        }
        try {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $subscriber_updated = $mailerliteClient->updateSubscriber($subscriber_email,
                $subscriber_data); // returns updated subscriber
            if (isset($subscriber_updated->id)) {
                return $subscriber_updated;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_sync_ecommerce_customer')) :
    function mailerlite_wp_sync_ecommerce_customer($customer_id)
    {

        $shop = get_option('woo_ml_shop_id', false);

        if ($shop === false) {

            return false;
        }

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

            $customer = new WC_Customer($customer_id);

            $email = $customer->get_email();

            $customer_data = [
                'first_name'   => $customer->get_first_name(),
                'last_name'    => $customer->get_last_name(),
                'company'      => $customer->get_billing_company(),
                'city'         => $customer->get_billing_city(),
                'postcode'     => $customer->get_billing_postcode(),
                'state'        => $customer->get_billing_state(),
                'country'      => $customer->get_billing_country(),
                'phone'        => $customer->get_billing_phone(),
            ];

            $subscriber_fields = MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data);

            $fields = array_merge($subscriber_fields, [
                'orders_count' => $customer->get_order_count(),
                'total_spent'  => $customer->get_total_spent(),
            ]);

            $mailerliteClient->syncCustomer($shop, $customer_id, $email, $fields);
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_set_double_optin')) :
    /**
     * Set MailerLite double opt in status
     *
     * @param bool $status
     *
     * @return bool
     */
    function mailerlite_wp_set_double_optin($status)
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {

                $result = $mailerliteClient->setDoubleOptin($status);

                if (isset($result->enabled)) {
                    $double_optin = $result->enabled == true ? 'yes' : 'no';
                    update_option('double_optin', $double_optin);

                    return $result->enabled;
                } else {
                    return false;
                }
            }

            if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

                $doi = $mailerliteClient->getDoubleOptin();

                if ($doi !== $status) {
                    $result = $mailerliteClient->setDoubleOptin($status);

                    $double_optin = $result == true ? 'yes' : 'no';

                    update_option('double_optin', $double_optin);

                    return $result;
                }

                return $doi;
            }
        } catch (Exception $e) {
            return false;
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_create_custom_field')) :
    /**
     * Create custom field in MailerLite
     *
     * @param array $field_data
     *
     * @return bool
     */
    function mailerlite_wp_create_custom_field($field_data)
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {

            return false;
        }

        if ( ! isset($field_data['title']) || ! isset($field_data['type'])) {

            return false;
        }

        try {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $temp_name = false;

            if (isset($field_data['key'])) {

                $temp_name = $field_data['key'] . ' ' . $field_data['title'];
            }

            $field_added = $mailerliteClient->createField($temp_name ?: $field_data['title'], $field_data['type']);

            if (isset($field_added->id)) {

                if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

                    $mailerliteClient->updateField($field_added->id, $field_data['name'] . ' ' . $field_data['title']);
                }

                return $field_added;
            } else {
                return false;
            }

        } catch (Exception $e) {
            return false;
        }
    }
endif;

if ( ! function_exists('mailerlite_wp_get_custom_fields')) :
    /**
     * Get custom fields from MailerLite
     *
     * @param array $args
     *
     * @return mixed
     */
    function mailerlite_wp_get_custom_fields($args = array())
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            return $mailerliteClient->getFields();

        } catch (Exception $e) {

            return false;
        }
    }
endif;