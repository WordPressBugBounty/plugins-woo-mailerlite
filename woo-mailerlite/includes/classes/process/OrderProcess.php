<?php

namespace MailerLite\Includes\Classes\Process;

use MailerLite\Includes\Classes\Settings\ApiSettings;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class OrderProcess extends Singleton
{

    /**
     * Process order tracking
     * 1.) Get current data from WooCommerce
     * 2.) Update subscriber data with updated values
     * woo_ml_process_order_tracking
     *
     * @param $order_id
     */
    public function processOrderTracking($order_id)
    {

        $order = wc_get_order($order_id);

        $order_tracked = $order->get_meta('_woo_ml_order_tracking');

        if ($order_tracked) // Prevent tracking orders multiple times
        {
            return;
        }

        // Step 1: Get order tracking data from order
        $tracking_data = $this->getOrderTrackingData($order_id);

        if (!empty($tracking_data)) {

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {
                if(!get_option('mailerlite_disable_checkout_sync')) {
                    $data = [];
                    $customer_data = $this->getCustomerDataFromOrder($order_id);
                    $data['email'] = $customer_data['email'];
                    $subscribe = $order->get_meta('_woo_ml_subscribe');

                    if (isset($_POST['woo_ml_subscribe']) && '1' == $_POST['woo_ml_subscribe'] && $subscribe === "") {
                        $subscribe = true;
                    }

                    if ($subscribe === "") {
                        $subscribe = MailerLiteSettings::getInstance()->getMlOption('checkout_hide') === 'yes';
                    }

                    if ($subscribe) {
                        $order->add_meta_data('_woo_ml_subscribe', true, true);

                        // save instead of save_meta_data for pending payment orders
                        $order->save();
                    }
                    $data['checked_sub_to_mailist'] = $subscribe;
                    $data['checkout_id'] = $_COOKIE['mailerlite_checkout_token'] ?? CheckoutProcess::getInstance()->getSavedCheckoutIdByEmail($customer_data['email']);
                    $data['order_id'] = $order_id;
                    $data['payment_method'] = $order->get_payment_method();

                    $subscriber_fields = MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data);
                    if (sizeof($subscriber_fields) > 0) {
                        $data['fields'] = $subscriber_fields;
                    }
                    $this->addSubscriberSaveOrder($data, 'order_created');
                }
                $ml_subscriber_obj = mailerlite_wp_get_subscriber_by_email($tracking_data['email']);
                if ((!$ml_subscriber_obj) && ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CLASSIC)) {
                    $orderStatus = $order->get_status();
                    if ($orderStatus == 'completed' || $orderStatus == 'processing') {
                        $this->processOrderSubscription($order_id);
                    }
                }
                // Customer exists on MailerLite
                if ($ml_subscriber_obj) {

                    // Step 2: Update subscriber data via API
                    $customer_data = $this->getCustomerDataFromOrder($order_id);
                    $subscriber_data = [
                        'fields' => [
                            'woo_orders_count' => $tracking_data['orders_count'],
                            'woo_total_spent' => $tracking_data['total_spent'],
                            'woo_last_order' => $tracking_data['last_order'],
                            'woo_last_order_id' => $order_id
                        ]
                    ];
                    $orderStatus = $order->get_status();
                    if ($orderStatus == 'completed' || $orderStatus == 'processing') {
                        if (isset($tracking_data['customer_id']) && (int)$tracking_data['customer_id']) {
                            $lastSyncedCustomer = get_option('woo_ml_last_synced_customer', 0);
                            if (($lastSyncedCustomer > 0) && ($lastSyncedCustomer < $tracking_data['customer_id'])) {
                                update_option('woo_ml_last_synced_customer', $tracking_data['customer_id']);
                            }
                        }
                    }
                    $subscriber_data['fields'] = array_merge($subscriber_data['fields'], MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data));

                    $subscriber_updated = mailerlite_wp_update_subscriber($tracking_data['email'], $subscriber_data);

                    if ($subscriber_updated) {

                        $this->completeOrderDataSubmitted($order_id);
                    }
                }
            }
        }

        // Mark order data as tracked
        $this->completeOrderTracking($order_id);
    }

    /**
     * Get order tracking data from order
     * woo_ml_get_order_tracking_data
     *
     * @param int $order_id
     *
     * @return array
     * @throws Exception
     */
    public function getOrderTrackingData($order_id)
    {

        if (!is_numeric($order_id)) {
            return [];
        }

        $customer_email = '';
        $order_count = 0;
        $total_spent = 0;
        $last_order_date = '';

        $order = wc_get_order($order_id);

        $customer = \WC_Data_Store::load('report-customers')->get_data([
                    'order_before' => null,
                    'order_after'  => null,
                    'searchby'     => 'email',
                    'search'       => $order->get_billing_email(),
                ])->data;

        $customer_email = $order->get_billing_email();

        if (count($customer)) {
            foreach ($customer as $value) {
                if ( ! isset($customer['last_order_date'])) {
                    $customer['last_order_date'] = $value['date_last_order'];
                }

                if ($customer['last_order_date'] < $value['date_last_order']) {
                    $customer['last_order_date'] = $value['date_last_order'];
                }

                $order_count     += $value['orders_count'] ?? 0;
                $total_spent     += $value['total_spend'] ?? 0;
            }

            $last_order_date = $customer['last_order_date'];
            $dateCreated = $order->get_date_created();
            // sometimes when an order is placed, the last order is not included with the orders count
            if (isset($dateCreated) && ($last_order_date < $order->get_date_created()->date_i18n('Y-m-d H:i:s'))) {
                $last_order_date = $order->get_date_created()->date_i18n('Y-m-d H:i:s');

                $order_count++;
                $total_spent += $order->get_total();
            }
        } else {
            $last_order_date = $order->get_date_created()->date_i18n('Y-m-d H:i:s');

            $order_count++;
            $total_spent += $order->get_total();
        }

        return [
            'email' => $customer_email,
            'customer_id' => $customer[0]['id'] ?? 0,
            'orders_count' => $order_count,
            'total_spent' => $total_spent,
            'last_order' => $last_order_date,
            'last_order_id' => $order_id
        ];
    }

    /**
     * Process order create and subscription to newsletter
     * woo_ml_process_order_subscription
     *
     * @param $order_id
     *
     * @return void
     */
    public function processOrderSubscription($order_id)
    {
        $order = wc_get_order($order_id);
        $customer_data = $this->getCustomerDataFromOrder($order_id);

        $subscribe = $order->get_meta('_woo_ml_subscribe');
        if (isset($_POST['woo_ml_subscribe']) && '1' == $_POST['woo_ml_subscribe'] && $subscribe === "") {
            $subscribe = true;
        }
        if ($subscribe === "") {
            $subscribe = MailerLiteSettings::getInstance()->getMlOption('checkout_hide') === 'yes';

            if ($subscribe) {
                $order->add_meta_data('_woo_ml_subscribe', true, true);

                // save instead of save_meta_data for pending payment orders
                $order->save();
            }
        }


        $data = [];
        $data['email'] = $customer_data['email'];
        $data['checked_sub_to_mailist'] = $subscribe;
        $checkout_id = isset($_COOKIE['mailerlite_checkout_token']) ? $_COOKIE['mailerlite_checkout_token'] : CheckoutProcess::getInstance()->getSavedCheckoutIdByEmail($customer_data['email']);

        $data['checkout_id'] = $checkout_id;
        $data['order_id'] = $order_id;
        $data['payment_method'] = $order->get_payment_method();

        if ($data['payment_method'] == 'bacs' || $data['payment_method'] == 'cheque') {
            @setcookie('mailerlite_checkout_email', null, -1, '/');
            @setcookie('mailerlite_checkout_token', null, -1, '/');
            @setcookie('mailerlite_accepts_marketing', null, -1, '/');
        } else {
            $data['checkout_data'] = CheckoutProcess::getInstance()->getCheckoutData();
        }
        $subscriber_fields = MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data);
        if (sizeof($subscriber_fields) > 0) {
            $data['fields'] = $subscriber_fields;
        }

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {

            $subscriber_result = $this->addSubscriberSaveOrder($data, 'order_created');
            if ($subscriber_result) {
                $this->processOrderTracking($order_id);
            }
            if (isset($subscriber_result->added_to_group)) {
                if ($subscriber_result->added_to_group) {
                    $this->markOrderSubscribedViaApi($order_id);
                } else {
                    $this->completeOrderCustomerAlreadySubscribed($order_id);
                }
            }

            if (isset($subscriber_result->updated_fields) && $subscriber_result->updated_fields) {
                $this->markOrderSubscriberUpdated($order_id);
            }
        }

        if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

            $shop = get_option('woo_ml_shop_id', false);

            if ($shop === false) {

                return;
            }

            $subscribe = $this->orderCustomerSubscribe($order_id);

            $cart_details = CartProcess::getInstance()->getCartDetails($order_id);

            //rename zip key for API
            $zip = $subscriber_fields['zip'] ?? '';
            unset($subscriber_fields['zip']);
            $subscriber_fields['z_i_p'] = $zip;

            $order_customer = [
                'email' => $data['email'],
                'create_subscriber' => $subscribe,
                'accepts_marketing' => $subscribe,
                'subscriber_fields' => $subscriber_fields
            ];

            $customer_fields = $this->getOrderTrackingData($order_id);

            if ($customer_fields['customer_id'] !== 0) {
                $order_customer['resource_id'] = (string)$customer_fields['customer_id'];
            }

            $order_customer['orders_count'] = $customer_fields['orders_count'];
            $order_customer['total_spent'] = $customer_fields['total_spent'];

            $order_cart = [
                'items' => $cart_details['items']
            ];

            // Delete existing cart (abandoned checkout)
            $checkout_id = $_COOKIE['mailerlite_checkout_token'] ?? CheckoutProcess::getInstance()->getSavedCheckoutIdByEmail($data['email']);

            if ($checkout_id !== null) {

                $mailerliteClient->deleteOrder($shop, $checkout_id);

                @setcookie('mailerlite_checkout_email', null, -1, '/');
                @setcookie('mailerlite_checkout_token', null, -1, '/');
                @setcookie('mailerlite_accepts_marketing', null, -1, '/');

                CheckoutProcess::getInstance()->removeCheckout($data['email']);
            }

            // set order status completed (when processing and paid)
            if ($cart_details['status'] == 'processing') {
                $cart_details['status'] = 'completed';
            }

            // Create order
            $order_create = $mailerliteClient->syncOrder($shop, $order_id, $order_customer, $order_cart,
                $cart_details['status'], $cart_details['total_price'], $cart_details['created_at']);

            if ($order_create) {

                $this->completeOrderDataSubmitted($order_id);
            }
        }
    }

    /**
     * Get customer data from order
     * woo_ml_get_customer_data_from_order
     *
     * @param $order_id
     *
     * @return array|bool
     */
    public function getCustomerDataFromOrder($order_id)
    {

        if (empty($order_id)) {
            return false;
        }

        $order = wc_get_order($order_id);

        if (method_exists($order, 'get_billing_email')) {
            $data = array(
                'email' => $order->get_billing_email(),
                'name' => "{$order->get_billing_first_name()} {$order->get_billing_last_name()}",
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'city' => $order->get_billing_city(),
                'postcode' => $order->get_billing_postcode(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country(),
                'phone' => $order->get_billing_phone()
            );
        } else {
            // NOTE: Only for compatibility with WooCommerce < 3.0
            $data = array(
                'email' => $order->billing_email,
                'name' => "{$order->billing_first_name} {$order->billing_last_name}",
                'first_name' => $order->billing_first_name,
                'last_name' => $order->billing_last_name,
                'company' => $order->billing_company,
                'city' => $order->billing_city,
                'postcode' => $order->billing_postcode,
                'state' => $order->billing_state,
                'country' => $order->billing_country,
                'phone' => $order->billing_phone
            );
        }

        return $data;
    }

    /**
     * Check whether a customer wants to be subscribed to our mailing list or not
     * woo_ml_order_customer_subscribe
     *
     * @param $order_id
     *
     * @return bool
     */
    public function orderCustomerSubscribe($order_id)
    {

        $order = wc_get_order($order_id);

        $subscribe = $order->get_meta('_woo_ml_subscribe');

        return ('1' == $subscribe) ? true : false;
    }

    /**
     * Mark order as "wants to be subscribed to mailing our list"
     * woo_ml_set_order_customer_subscribe
     *
     * @param $order_id
     */
    public function setOrderCustomerSubscribe($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_subscribe', true);
        $order->save_meta_data();
    }

    /**
     * Check whether order data was submitted via API or not
     * woo_ml_order_data_submitted
     *
     * @param $order_id
     *
     * @return bool
     */
    public function orderDataSubmitted($order_id)
    {

        $order = wc_get_order($order_id);

        $data_submitted = $order->get_meta('_woo_ml_order_data_submitted');

        return ('1' == $data_submitted) ? true : false;
    }

    /**
     * Mark order as "order data submitted"
     * woo_ml_complete_order_data_submitted
     *
     * @param $order_id
     */
    public function completeOrderDataSubmitted($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_order_data_submitted', true);
        $order->save_meta_data();
    }

    /**
     * Gets triggered on completed order event. Fetches order data
     * and passes it along to api
     * woo_ml_send_completed_order
     *
     * @param Integer $order_id
     *
     * @return void
     */
    public function sendCompletedOrder($order_id)
    {
        $order = wc_get_order($order_id);
        $order_data['order'] = $order->get_data();
        $order_items = $order->get_items();
        $customer_email = $order->get_billing_email();

        $saved_checkout = CheckoutProcess::getInstance()->getSavedCheckoutByEmail($customer_email);
        $order_data['checkout_id'] = !empty($saved_checkout) ? $saved_checkout->checkout_id : null;

        $ignored_products = MailerLiteSettings::getInstance()->remapList(ProductProcess::getInstance()->getIgnoredProductList());

        foreach ($order_items as $key => $value) {
            $item_data = $value->get_data();
            $order_data['order']['line_items'][$key] = $item_data;
            $order_data['order']['line_items'][$key]['ignored_product'] = in_array($item_data['product_id'],
                $ignored_products) ? 1 : 0;
        }
        @setcookie('mailerlite_checkout_email', null, -1, '/');
        @setcookie('mailerlite_checkout_token', null, -1, '/');
        @setcookie('mailerlite_accepts_marketing', null, -1, '/');

        if ($order_data['order']['status'] == 'processing') {
            $order_data['order']['status'] = 'completed';
        }

        $this->wpSendOrder($order_data);

        if (!empty($saved_checkout)) {

            CheckoutProcess::getInstance()->removeCheckout($customer_email);
        }

    }

    /**
     * Check whether order was already tracked or not
     * woo_ml_order_tracking_completed
     *
     * @param $order_id
     *
     * @return bool
     */
    public function checkOrderTracking($order_id)
    {
        $order = wc_get_order($order_id);

        $order_tracked = $order->get_meta('_woo_ml_order_tracked');

        return ('1' == $order_tracked) ? true : false;
    }

    /**
     * Mark order as being tracked
     * woo_ml_complete_order_tracking
     *
     * @param $order_id
     */
    public function completeOrderTracking($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_order_tracked', true);
        $order->save_meta_data();
    }

    /**
     * On change of order status to processing send order data
     * woo_ml_payment_status_processing
     *
     * @param Integer $order_id
     *
     * @return void
     */
    public function paymentStatusProcessing($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_status() === 'processing') {
            if ((isset($_COOKIE['mailerlite_accepts_marketing']) && $_COOKIE['mailerlite_accepts_marketing']) || MailerLiteSettings::getInstance()->getMlOption('checkout_hide') === 'yes') {
                $order->add_meta_data('_woo_ml_subscribe', true);
                $order->save_meta_data();
            } elseif (isset($_COOKIE['mailerlite_accepts_marketing']) && $_COOKIE['mailerlite_accepts_marketing'] === false) {
                $order->add_meta_data('_woo_ml_subscribe', false);
                $order->save_meta_data();
            }

            $data = [];
            $customer_email = $order->get_billing_email();

            // load the checkout id from the cookie first
            // if that fails, then check the mailerlite checkouts table
            $checkoutId = null;
            if (isset($_COOKIE['mailerlite_checkout_token'])) {

                $checkoutId = $_COOKIE['mailerlite_checkout_token'];
            } else {

                $saved_checkout = CheckoutProcess::getInstance()->getSavedCheckoutByEmail($customer_email);
                $checkoutId = !empty($saved_checkout) ? $saved_checkout->checkout_id : null;
            }

            $data['checkout_id'] = $checkoutId;
            $data['order_id'] = $order_id;
            $data['payment_method'] = $order->get_payment_method();

            @setcookie('mailerlite_checkout_email', null, -1, '/');
            @setcookie('mailerlite_checkout_token', null, -1, '/');
            @setcookie('mailerlite_accepts_marketing', null, -1, '/');

            $this->addSubscriberSaveOrder($data, 'order_processing');
            CheckoutProcess::getInstance()->removeCheckout($customer_email);

            if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {

                $this->processOrderSubscription($order_id);
            }
        }
    }

    /**
     * Mark order as "subscriber was updated"
     * woo_ml_complete_order_subscriber_updated
     *
     * @param $order_id
     */
    public function markOrderSubscriberUpdated($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_subscriber_updated', true);
        $order->save_meta_data();
    }

    /**
     * Check whether a subscriber was updated from order or not
     * woo_ml_order_subscriber_updated
     *
     * @param $order_id
     *
     * @return bool
     */
    public function checkOrderSubscriberUpdated($order_id)
    {
        $order = wc_get_order($order_id);

        $subscriber_updated_from_order = $order->get_meta('_woo_ml_subscriber_updated');

        return ('1' == $subscriber_updated_from_order) ? true : false;
    }

    /**
     *
     * woo_ml_complete_order_customer_already_subscribed
     *
     * @param $order_id
     *
     * @return void
     */
    public function completeOrderCustomerAlreadySubscribed($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_already_subscribed', true);
        $order->save_meta_data();
    }

    /**
     * Mark order as "customer subscribed via API"
     * woo_ml_complete_order_customer_subscribed
     *
     * @param $order_id
     */
    public function markOrderSubscribedViaApi($order_id)
    {
        $order = wc_get_order($order_id);

        $order->add_meta_data('_woo_ml_subscribed', true);
        $order->save_meta_data();
    }

    /**
     *
     * woo_ml_order_customer_already_subscribed
     *
     * @param $order_id
     *
     * @return bool
     */
    public function orderCustomerAlreadySubscribed($order_id)
    {

        $order = wc_get_order($order_id);

        $already_subscribed = $order->get_meta('_woo_ml_already_subscribed');

        return ('1' == $already_subscribed) ? true : false;
    }

    /**
     * Check whether a customer was subscribed via API or not
     *subscribe
     * woo_ml_order_customer_subscribed
     *
     * @param $order_id
     *
     * @return bool
     */
    public function checkCustomerSubscribed($order_id)
    {

        $order = wc_get_order($order_id);

        $subscribed = $order->get_meta('_woo_ml_subscribed');

        return ('1' == $subscribed) ? true : false;
    }

    /**
     * Sending order data on creation of order and/or order status change to processing
     * mailerlite_wp_add_subscriber_and_save_order
     *
     * @param array $data
     * @param string $event
     *
     * @return bool
     */
    public function addSubscriberSaveOrder($data, $event)
    {
        if (!ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $shop_id = '';

            $shop_url = site_url();
            $data['shop_url'] = home_url();
            $data['order_url'] = $shop_url . "/wp-admin/post.php?post=" . $data['order_id'] . "&action=edit";
            //order_created case also takes care of processing sub if they have ticked the box to
            //receive newsletters

            if ($mailerliteClient->getApiType() === ApiType::CLASSIC) {
                if ($event === 'order_created') {
                    $result = $mailerliteClient->sendSubscriberData($shop_id, $data);

                    if (isset($result->added_to_group) && isset($result->updated_fields)) {
                        return $result;
                    } elseif (isset($result->deactivate) && $result->deactivate) {
                        woo_ml_deactivate_woo_ml_plugin(true);

                        return false;
                    } else {
                        return false;
                    }
                } else {

                    $result = $mailerliteClient->sendOrderProcessing($shop_id, $data);
                    if (isset($result->deactivate) && $result->deactivate) {
                        woo_ml_deactivate_woo_ml_plugin(true);
                    }

                    if ($event === 'order_processing') {
                        $this->sendCompletedOrder($data['order_id']);
                        $this->processOrderTracking($data['order_id']);
                    }

                    return true;
                }
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sends completed order data to api to be evaluated and saved and/if trigger automations
     * mailerlite_wp_send_order
     *
     * @param array $order_data
     *
     * @return bool|void
     */
    public function wpSendOrder($order_data)
    {
        if (!ApiSettings::getInstance()->wpApiKeyExists()) {
            return false;
        }

        try {
            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {

                $store = home_url();

                $shop_url = site_url();
                $order_data['order_url'] = $shop_url . "/wp-admin/post.php?post=" . $order_data['order']['id'] . "&action=edit";

                $result = $mailerliteClient->saveOrder($store, $order_data);

                if (isset($result->deactivate) && $result->deactivate) {
                    woo_ml_deactivate_woo_ml_plugin(true);
                }
            }

            if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

                $shop = get_option('woo_ml_shop_id', false);

                if ($shop === false) {

                    return false;
                }

                $subscribe = $this->orderCustomerSubscribe($order_data['order']['id']) || (MailerLiteSettings::getInstance()->getMlOption('checkout_hide') === 'yes');
                if ($subscribe) {
                    $this->setOrderCustomerSubscribe($order_data['order']['id']);
                }
                $cart_details = CartProcess::getInstance()->getCartDetails($order_data['order']['id']);

                $customer_data = $this->getCustomerDataFromOrder($order_data['order']['id']);

                $subscriber_fields = MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data);

                //rename zip key for API
                $zip = $subscriber_fields['zip'] ?? '';
                unset($subscriber_fields['zip']);
                $subscriber_fields['z_i_p'] = $zip;

                $order_customer = [
                    'email' => $order_data['order']['billing']['email'],
                    'create_subscriber' => $subscribe,
                    'accepts_marketing' => $subscribe,
                    'subscriber_fields' => $subscriber_fields
                ];

                $customer_fields = $this->getOrderTrackingData($order_data['order']['id']);

                if ($customer_fields['customer_id'] !== 0) {
                    $order_customer['resource_id'] = (string)$customer_fields['customer_id'];
                }

                $order_customer['orders_count'] = $customer_fields['orders_count'];
                $order_customer['total_spent'] = $customer_fields['total_spent'];

                $order_cart = [
                    'items' => $cart_details['items']
                ];

                if (is_numeric($order_data['order']['id'])) {
                    $order_data['order']['status'] = 'completed';
                }

                $mailerliteClient->syncOrder($shop, $order_data['order']['id'], $order_customer, $order_cart,
                    $order_data['order']['status'], $cart_details['total_price'], $cart_details['created_at']);

                if ($mailerliteClient->responseCode() === 201 || $mailerliteClient->responseCode() === 200) {

                    $this->orderDataSubmitted($order_data['order']['id']);

                    // Delete existing cart (abandoned checkout)
                    if ($order_data['checkout_id'] !== null) {

                        $mailerliteClient->deleteOrder($shop, $order_data['checkout_id']);
                    }
                }

                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete the order on cancellation
     *
     * @param $order_id
     *
     * @return bool
     */
    public function cancelOrder($order_id)
    {
        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        $order = wc_get_order($order_id);

        $shop = get_option('woo_ml_shop_id', false);

        if ($shop === false) {
            return false;
        }

        if (!$order) {
            return false;
        }

        // get subscriber orders count and total spent fields
        $customer_data = $this->getOrderTrackingData($order_id);

        if ($mailerliteClient->getApiType() == ApiType::CURRENT) {
            $mailerliteClient->deleteOrder($shop, $order_id);

            // recalculate as the values contain the current order
            $customer_data['orders_count']--;
            $customer_data['total_spent'] -= $order->get_total();

            $mailerliteClient->syncCustomer($shop, $customer_data['customer_id'], $customer_data['email'],
                $customer_data);
        }

        if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {
            $subscriber_fields = [
                'woo_orders_count'  => $customer_data['orders_count'] - 1,
                'woo_total_spent'   => $customer_data['total_spent'] - $order->get_total(),
                'woo_last_order'    => $customer_data['last_order'],
                'woo_last_order_id' => $customer_data['last_order_id']
            ];

            $store = home_url();

            $mailerliteClient->syncCustomer($store, $customer_data['customer_id'],
                $customer_data['email'], $subscriber_fields);
        }

        return true;
    }
}