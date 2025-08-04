<?php

class WooMailerLiteCheckoutDataService
{
    public static function getCheckoutData()
    {
        if (empty(WC()->cart)) {
            WC()->frontend_includes();

            if ( ! did_action('woocommerce_load_cart_from_session') && function_exists('wc_load_cart')) {
                wc_load_cart();
            }
        }
        $cart = WC()->cart;
        $cartItems = $cart->get_cart();
        $customer = $cart->get_customer();
        $cartFromDb = WooMailerLiteCart::where('hash', WooMailerLiteSession::getMLCartHash())->first();
        $cartData = $cartFromDb->data;
        $customerEmail = $customer->get_email();

        if ( !$customerEmail) {
            $customerEmail = $cartFromDb->email ?? "";
        }

        // check if email was updated recently in checkout
        if (filter_var($cartFromDb->email, FILTER_VALIDATE_EMAIL) && $customerEmail !== $cartFromDb->email) {
            $customerEmail = $cartFromDb->email;
        }
        $checkoutData = [];
        if (!empty($customerEmail)) {
            $lineItems = [];
            $total = 0;

            foreach ($cartItems as $key => $item) {
                $subtotal = intval($item['quantity']) * floatval($item['data']->get_price('edit'));

                $lineItems[] = [
                    'key'          => $key,
                    'line_subtotal' => $subtotal,
                    'line_total'    => $subtotal,
                    'product_id'    => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'variation'    => $item['variation'],
                    'variation_id'  => $item['variation_id'],
                ];

                $total += $subtotal;
            }

            $shopCheckoutUrl = wc_get_checkout_url();
            $checkoutUrl = $shopCheckoutUrl . '?ml_checkout=' . $cartData['checkout_id'];

            $checkoutData = [
                'id'                     => $cartData['checkout_id'],
                'email'                  => $customerEmail,
                'line_items'              => $lineItems,
                'abandoned_checkout_url'   => $checkoutUrl,
                'total_price'             => $total,
                'created_at'              => date('Y-m-d H:i:s'),
            ];

            if ($cartFromDb->subscribe) {
                $checkoutData['subscribe'] = true;
            }
            if (isset($_POST['language'])) {
                $checkoutData['language'] = $_POST['language'];
            }

            if (!empty($subscriberFields)) {
                $checkoutData['subscriber_fields'] = $subscriberFields;
            }
        }
        return $checkoutData;
    }
}