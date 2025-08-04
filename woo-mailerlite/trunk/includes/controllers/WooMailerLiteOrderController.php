<?php

class WooMailerLiteOrderController extends WooMailerLiteController
{

    public function handleOrderStatusChanged($orderId)
    {
        try {
            if (WooMailerLiteCache::get('order_sent:'.$orderId)) {
                return true;
            }
            $order = wc_get_order($orderId);

            $customer = WooMailerLiteCustomer::selectAll(false)->where('email', $order->get_billing_email())->first();
            if (!$customer) {
                $customer = WooMailerLiteCustomer::selectAll(false)
                    ->getFromOrder()
                    ->where("email", $order->get_billing_email())
                    ->whereIn("status", ["wc-completed", "wc-processing", "wc-pending"])
                    ->first();
            }
            $cart = WooMailerLiteCart::where('email', $order->get_billing_email())->first();

            if (!$cart || !$customer) {
                return true;
            }

            if ($cart->subscribe) {
                $order->add_meta_data('_woo_ml_subscribe', true);
            }

            $syncFields = WooMailerLiteOptions::get('syncFields', []);
            if (empty($syncFields)) {
                $syncFields = [
                    'name',
                    'email',
                    'company',
                    'city',
                    'zip',
                    'state',
                    'country',
                    'phone'
                ];
                WooMailerLiteOptions::update('syncFields', $syncFields);
            }
            $filteredCustomerData = array_filter($customer->toArray() ?? [], function($value) {
                return !is_null($value) && trim($value) !== '';
            });

            $syncFields[] = 'last_name';
            if (!in_array('name', $syncFields)) {
                $syncFields[] = 'name';
            }

            $customerFields = array_intersect_key($filteredCustomerData, array_flip($syncFields));

            $orderCustomer = [
                'email' => $customer->email ?? $order->get_billing_email(),
                'create_subscriber' => (bool)$cart->subscribe,
                'accepts_marketing' => (bool)$cart->subscribe,
                'subscriber_fields' => $customerFields,
                'total_spent' => ($customer->total_spent ?? $order->get_total()),
                'orders_count' => ($customer->orders_count ?? 1),
                'last_order_id' => $customer->last_order_id ?? null,
                'last_order' => $customer->last_order ?? null
            ];
            $items = [];

            foreach ($order->get_items() as $item) {

                if ($item->get_product_id() !== 0) {

                    $items[] = [
                        'product_resource_id' => (string)$item->get_product_id(),
                        'variant'             => $item->get_name(),
                        'quantity'            => $item->get_quantity(),
                        'price'               => (float)$item->get_total()
                    ];
                }
            }
            $cartData = [
                'items' => $items
            ];
            if ($this->apiClient()->isClassic()) {
                $orderData['order'] = $order->get_data();
                $orderData['checkout_id'] = $cart->data['checkout_id'];
                $orderData['order_url'] = home_url() . "/wp-admin/post.php?post=" . $orderId . "&action=edit";
                $customerFields['woo_total_spent'] = ($customer->total_spent ?? $order->get_total());
                $customerFields['woo_orders_count'] = ($customer->orders_count ?? 1);
                $customerFields['woo_last_order_id'] = $customer->last_order_id ?? null;
                $customerFields['woo_last_order'] = $customer->last_order ?? nulL;
                $data = [
                    'email' => $customer->email,
                    'checked_sub_to_mailist' => (bool)$cart->subscribe,
                    'checkout_id' => $cart->data['checkout_id'],
                    'order_id' => $orderId,
                    'payment_method' => $order->get_payment_method(),
                    'fields' => $customerFields,
                    'shop_url' => home_url(),
                    'order_url' => home_url() . "/wp-admin/post.php?post=" . $orderId . "&action=edit",
                    'checkout_data' => WooMailerLiteCheckoutDataService::getCheckoutData()
                ];
                $this->apiClient()->sendOrderProcessing($data);
                if (in_array($order->get_status(), ['completed', 'processing']) && $order->get_items()) {
                    $order_items = $order->get_items();
                    foreach ($order_items as $key => $value) {
                        $item_data = $value->get_data();
                        $orderData['order']['line_items'][$key] = $item_data;
                        $orderData['order']['line_items'][$key]['ignored_product'] = in_array($item_data['product_id'],
                            array_map('strval', array_keys(WooMailerLiteOptions::get('ignored_products', [])))) ? 1 : 0;
                    }
                    $orderData['order']['status'] = 'completed';
                    WooMailerLiteCache::set('order_sent:'.$orderId, true, 20);
                    $response = $this->apiClient()->syncOrder(home_url(), $orderData);
                    $this->apiClient()->sendSubscriberData($data);
                }

            } else {
                $response = $this->apiClient()->syncOrder(WooMailerLiteOptions::get('shopId'), $orderId, $orderCustomer, $cartData, $order->get_status(), $order->get_total(), $order->get_date_created()->format('Y-m-d H:i:s'));
            }
            if (isset($response) && $response->success) {
                $order->add_meta_data('_woo_ml_order_data_submitted', true);
                if (in_array($order->get_status(), ['wc-completed', 'wc-processing','completed','processing'])) {
                    $cart->delete();
                }
                if ($this->apiClient()->isClassic()) {
                    $response = $this->apiClient()->searchSubscriber('gaurang+testabandonedcart40@mailerlite.com');
                    if ($response->success) {
                        $response->data->customer->subscriber->created_at = $response->data->date_created;
                        $response->data->customer->subscriber->updated_at = $response->data->date_updated;
                    }
                }
                if ($response->data->customer->subscriber->created_at == $response->data->customer->subscriber->updated_at) {
                    $order->add_meta_data('_woo_ml_subscribed', true);
                } else {
                    $order->add_meta_data('_woo_ml_already_subscribed', true);
                    $order->add_meta_data('_woo_ml_subscriber_updated', true);
                }
                $order->add_meta_data('_woo_ml_order_tracked', true);
            }
            $order->save();
        } catch(\Throwable $e) {
            WooMailerLiteLog()->error($e->getMessage(), ['order' => $orderId]);
            return true;
        }
    }
}