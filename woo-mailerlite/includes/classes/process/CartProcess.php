<?php

namespace MailerLite\Includes\Classes\Process;

use MailerLite\Includes\Classes\Settings\ApiSettings;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class CartProcess extends Singleton
{
    /**
     * Get cart details from order for the API
     * woo_ml_get_cart_details
     *
     * @param   $order_id
     *
     * @return  array
     */
    public function getCartDetails($order_id)
    {

        $order = wc_get_order($order_id);

        $items = [];

        foreach ($order->get_items() as $item_key => $item) {

            if ($item->get_product_id() !== 0) {

                $items[] = [
                    'product_resource_id' => (string)$item->get_product_id(),
                    'variant'             => $item->get_name(),
                    'quantity'            => $item->get_quantity(),
                    'price'               => (float)$item->get_total()
                ];
            }
        }

        return [
            'customer_id' => $order->get_customer_id(),
            'items'       => $items,
            'status'      => $order->get_status(),
            'total_price' => $order->get_total(),
            'created_at'  => date('Y-m-d h:m:s', strtotime($order->get_date_created()))
        ];
    }

    /**
     * Sending cart data on updated cart contents event (add or remove from cart)
     * woo_ml_send_cart
     *
     * @param $cookie_email
     *
     * @return void
     */
    public function sendCart($cookie_email = null, $subscribe = null, $language = null, $subscriber_fields = null)
    {
        $checkout_data = CheckoutProcess::getInstance()->getCheckoutData($cookie_email, $subscribe, $language,
            $subscriber_fields);
        if ( ! empty($checkout_data)) {

            $this->wpSendCart($checkout_data);
        }

    }

    /**
     * Sending cart data on cart update
     * mailerlite_wp_send_cart
     *
     * @param array $cart_data
     *
     * @return bool|void
     */
    public function wpSendCart($cart_data)
    {

        if ( ! ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {

                $shop = home_url();

                $result = $mailerliteClient->sendCart($shop, $cart_data);

                if (isset($result->deactivate) && $result->deactivate) {
                    woo_ml_deactivate_woo_ml_plugin(true);
                }
            }

            if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

                $shop = get_option('woo_ml_shop_id', false);

                if ($shop === false) {

                    return false;
                }

                $order_customer = [
                    'email'             => $cart_data['email'],
                    'accepts_marketing' => $cart_data['subscribe'] ?? false,
                    'create_subscriber' => $cart_data['subscribe'] ?? false,
                ];

                if (isset($cart_data['language'])) {
                    $order_customer['subscriber_fields'] = [
                        'subscriber_language' => $cart_data['language'],
                    ];
                }

                if (isset($cart_data['subscriber_fields'])) {
                    $order_customer['subscriber_fields'] = array_merge($order_customer['subscriber_fields'] ?? [],
                        $cart_data['subscriber_fields']);
                }

                $order_cart = [
                    'resource_id'  => (string)$cart_data['id'],
                    'checkout_url' => $cart_data['abandoned_checkout_url'],
                    'items'        => []
                ];

                if (empty($cart_data['line_items'])) {
                    $mailerliteClient->deleteOrder($shop, $cart_data['id']);
                    return false;
                }

                foreach ($cart_data['line_items'] as $item) {

                    $product = wc_get_product($item['product_id']);

                    $order_cart['items'][] = [
                        'product_resource_id' => (string)$item['product_id'],
                        'variant'             => $product->get_name(),
                        'quantity'            => (int)$item['quantity'],
                        'price'               => floatval($product->get_price('edit')),
                    ];
                }

                $mailerliteClient->syncOrder($shop, $cart_data['id'], $order_customer, $order_cart, 'pending',
                    $cart_data['total_price'], $cart_data['created_at']);
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}