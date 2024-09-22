<?php

namespace MailerLite\Includes\Classes\Process;

use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class OrderSyncProcess extends Singleton
{
    /**
     * Call to handle bulk order update event
     * mailerlite_wp_sync_bulk_order
     */
    public function syncBulkOrder($action, $order_ids)
    {
        $orderProcess     = OrderProcess::getInstance();
        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

            foreach ($order_ids as $order_id) {

                $order = wc_get_order($order_id);

                $shop = get_option('woo_ml_shop_id', false);

                if ($shop === false) {

                    return false;
                }

                // we check the action, the new order status is not yet available
                if ($action === 'mark_cancelled') {

                    // woocommerce_order_status_cancelled action will delete the order
                    return false;
                }

                $subscribe = $orderProcess->orderCustomerSubscribe($order_id);

                $cart_details = CartProcess::getInstance()->getCartDetails($order_id);

                $customer_data     = $orderProcess->getCustomerDataFromOrder($order_id);
                $subscriber_fields = $this->getSubscriberFieldsFromCustomerData($customer_data);

                // rename zip key for API
                $zip = $subscriber_fields['zip'] ?? '';
                unset($subscriber_fields['zip']);
                $subscriber_fields['z_i_p'] = $zip;

                $order_customer = [
                    'email'             => $order->get_billing_email(),
                    'create_subscriber' => $subscribe,
                    'accepts_marketing' => $subscribe,
                    'subscriber_fields' => $subscriber_fields
                ];

                $customer_fields = OrderProcess::getInstance()->getOrderTrackingData($order_id);

                if ($customer_fields['customer_id'] !== 0) {
                    $order_customer['resource_id'] = (string)$customer_fields['customer_id'];
                }

                $order_customer['orders_count'] = $customer_fields['orders_count'];
                $order_customer['total_spent'] = $customer_fields['total_spent'];

                $order_cart = [
                    'items' => $cart_details['items']
                ];

                $order_status = $order->get_status();

                if (is_numeric($order_id)) {
                    $order_status = 'completed';
                }

                $mailerliteClient->syncOrder(
                    $shop,
                    $order_id,
                    $order_customer,
                    $order_cart,
                    $order_status,
                    $order->get_total(),
                    date('Y-m-d h:m:s', strtotime($order->get_date_created()))
                );
            }
        }
    }

    /**
     * Call to handle order update event
     * mailerlite_wp_sync_order
     */
    public function syncOrder($order_id, $post)
    {
        $orderProcess = OrderProcess::getInstance();
        $order        = wc_get_order($order_id);

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

            $shop = get_option('woo_ml_shop_id', false);

            if ($shop === false) {

                return false;
            }

            $subscribe = $orderProcess->orderCustomerSubscribe($order_id);

            $cart_details = CartProcess::getInstance()->getCartDetails($order_id);

            $customer_data     = $orderProcess->getCustomerDataFromOrder($order_id);
            $subscriber_fields = $this->getSubscriberFieldsFromCustomerData($customer_data);

            //rename zip key for API
            $zip = $subscriber_fields['zip'] ?? '';
            unset($subscriber_fields['zip']);
            $subscriber_fields['z_i_p'] = $zip;

            $order_customer = [
                'email'             => $order->get_billing_email(),
                'create_subscriber' => $subscribe,
                'accepts_marketing' => $subscribe,
                'subscriber_fields' => $subscriber_fields
            ];

            $customer_fields = OrderProcess::getInstance()->getOrderTrackingData($order_id);

            if ($customer_fields['customer_id'] !== 0) {
                $order_customer['resource_id'] = (string)$customer_fields['customer_id'];
            }

            $order_customer['orders_count'] = $customer_fields['orders_count'];
            $order_customer['total_spent'] = $customer_fields['total_spent'];

            $order_cart = [
                'items' => $cart_details['items']
            ];

            $order_status = $order->get_status();

            if (is_numeric($order_id)) {
                $order_status = 'completed';
            }

            // check for latest order status
            if (isset($post['order_status'])) {

                $order_status = $post['order_status'];
            }

            // check if order is cancelled
            if ($order_status === 'wc-cancelled') {

                // woocommerce_order_status_cancelled action will delete the order
                return true;
            }

            $mailerliteClient->syncOrder($shop, $order_id, $order_customer, $order_cart, $order_status,
                $order->get_total(), date('Y-m-d h:m:s', strtotime($order->get_date_created())));

            if ($mailerliteClient->responseCode() === 201) {

                $orderProcess->completeOrderDataSubmitted($order_id);

                return true;
            }
        }
    }

    private function getSubscriberFieldsFromCustomerData($customer_data)
    {
        return MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer_data);
    }

}