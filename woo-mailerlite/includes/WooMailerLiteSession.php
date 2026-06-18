<?php

class WooMailerLiteSession {

    public static function get()
    {
        return WC()->session;
    }

    public static function set($key, $value)
    {
        WC()->session->set($key, $value);
    }

    public static function cart()
    {
        return json_encode(WC()->cart->get_cart());
    }

    public static function customer()
    {
        return WC()->get_customer();
    }

    public static function billingEmail()
    {
        $customer = WC()->cart->get_customer();
        return $customer->get_billing_email() ?: $customer->get_email();
    }

    public static function getMLCustomer()
    {
        $customer = WC()->session->get('woo_mailerlite_customer_data');
        if (empty($customer) || !isset($customer['customer'])) {
           return ['customer' => ['email' => null]];
        }

        return $customer;
    }

    public static function getMLCartHash()
    {
        if (WC()->session) {
            return WC()->session->get('woo_mailerlite_cart_hash');
        }
        return null;
    }

    public static function getMlCheckbox()
    {
        return WC()->session->get('woo_mailerlite_checkbox') ?? false;
    }

    public static function getMLCartStageCacheKey()
    {
        return sprintf('woo_ml_cart_stage:%s', self::getMLCartHash());
    }
}
