<?php

class WooMailerLiteService
{
    /**
     * @var null|static $instance
     */
    protected static $instance = null;

    protected $apiClient;

    public function __construct()
    {
        $this->apiClient = WooMailerLiteApi::client();
    }

    public static function instance()
    {
        if (!empty(static::$instance)) {
            return static::$instance;
        }
        static::$instance = new static();
        return static::$instance;
    }

    /**
     * Triggered when the cart is created/updated
     * @return void
     */
    public function handleCartUpdated()
    {
        WooMailerLiteSession::set('woo_mailerlite_cart_hash', WC()->session->get_customer_id());
        $cart = WooMailerLiteCart::where('hash', WooMailerLiteSession::getMLCartHash())->first();
        $data = WooMailerLiteSession::cart();
        $data = json_decode($data, true);
        if (!isset($data['checkout_id'])) {
            $data['checkout_id'] = floor(microtime(true) * 1000);
        }

        $data = json_encode($data);
        if (!$cart) {
            WooMailerLiteCart::create([
                'hash' => WooMailerLiteSession::getMLCartHash(),
                'email' => WooMailerLiteSession::billingEmail(),
                'subscribe' => ($_POST['signup'] ?? false) == 'true' ? 1 : 0,
                'data' => $data,
            ]);

        } else {
            $cart->update([
                'email' => WooMailerLiteSession::billingEmail(),
                'subscribe' => ($_POST['signup'] ?? false) == 'true' ? 1 : 0,
                'data' => $data,
            ]);
        }
    }

    /**
     * Triggered when the checkout form data is changed
     * @return void
     */
    public function setCartEmail()
    {
        WooMailerLiteSession::set('woo_mailerlite_checkbox', $_POST['signup'] == 'true');
        // set the customer in session
        $data = WooMailerLiteSession::cart();
        $data = json_decode($data, true);
        if (!isset($data['checkout_id'])) {
            $data['checkout_id'] = floor(microtime(true) * 1000);
        }
        if (!WooMailerLiteSession::getMLCartHash()) {
            $this->handleCartUpdated();
        }
        WooMailerLiteSession::set('woo_mailerlite_customer_data', ['customer' => $_POST, 'cart' => WC()->session->get( 'woo_mailerlite_cart_hash')]);
        // find the cart by cart id
        $cart = WooMailerLiteCart::where('hash', WooMailerLiteSession::getMLCartHash())->first();

        // update email in cart
        if ($cart) {
            $cart->update([
                'email' => $_POST['email'],
                'subscribe' => $_POST['signup'] == 'true' ? 1 : 0,
            ]);
        } else {
            // create cart if it doesn't exist
            WooMailerLiteCart::create([
                'hash' => WooMailerLiteSession::getMLCartHash(),
                'email' => $_POST['email'],
                'subscribe' => $_POST['signup'] == 'true' ? 1 : 0,
                'data' => $data,
            ]);
        }
        $this->sendCart();
    }

    public function sendCart()
    {
        if (WooMailerLiteOptions::get('settings.syncAfterCheckout')) {
            return true;
        }
        $checkoutData = WooMailerLiteCheckoutDataService::getCheckoutData();
        $customer = WooMailerLiteSession::getMLCustomer();
        $customerQuery = WooMailerLiteCustomer::where('email', $customer['customer']['email'])->first();
        try {
            if (self::instance()->apiClient->isClassic()) {
                home_url();
                self::instance()->apiClient->sendCart(home_url(), $checkoutData);
                return true;
            }

            if (self::instance()->apiClient->isRewrite()) {

                $shop = WooMailerLiteOptions::get('shopId');

                if ($shop === false) {

                    return false;
                }

                $orderCustomer = [
                    'email'             => $checkoutData['email'],
                    'accepts_marketing' => $checkoutData['subscribe'] ?? false,
                    'create_subscriber' => $checkoutData['subscribe'] ?? false,
                ];

                if (isset($checkoutData['language'])) {
                    $orderCustomer['subscriber_fields'] = [
                        'subscriber_language' => $checkoutData['language'],
                    ];
                }


                if (isset($checkoutData['subscriber_fields'])) {
                    $orderCustomer['subscriber_fields'] = array_merge($orderCustomer['subscriber_fields'] ?? [],
                        $checkoutData['subscriber_fields']);
                }
                if ($customerQuery) {
                    $customerQuery->name = $customer['customer']['name'] ?? '';
                    $orderCustomer['subscriber_fields'] = array_merge($orderCustomer['subscriber_fields'] ?? [], $customerQuery->toArray());
                } else {
                    $orderCustomer['subscriber_fields'] = array_merge($orderCustomer['subscriber_fields'] ?? [],
                        $customer['customer'] ?? []);
                }

                $orderCustomer['subscriber_fields'] = array_filter(
                    $orderCustomer['subscriber_fields'],
                    function ($v, $k) {
                        return in_array($k, WooMailerLiteOptions::get("settings.syncFields"));
                    },
                    ARRAY_FILTER_USE_BOTH
                );


                $orderCart = [
                    'resource_id'  => (string)$checkoutData['id'],
                    'checkout_url' => $checkoutData['abandoned_checkout_url'],
                    'items'        => []
                ];

                if (empty($checkoutData['line_items'])) {
                    self::$instance->apiClient->deleteOrder($checkoutData['id']);
                    return false;
                }

                foreach ($checkoutData['line_items'] as $item) {

                    $product = wc_get_product($item['product_id']);

                    $orderCart['items'][] = [
                        'product_resource_id' => (string)$item['product_id'],
                        'variant'             => $product->get_name(),
                        'quantity'            => (int)$item['quantity'],
                        'price'               => floatval($product->get_price('edit')),
                    ];
                }

                self::$instance->apiClient->syncOrder($shop, $checkoutData['id'], $orderCustomer, $orderCart, 'pending',
                    $checkoutData['total_price'], $checkoutData['created_at']);
            }
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}