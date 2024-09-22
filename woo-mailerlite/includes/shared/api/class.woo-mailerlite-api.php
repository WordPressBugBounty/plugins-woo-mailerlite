<?php

namespace MailerLite\Includes\Shared\Api;

class MailerLiteAPI
{

    private $url = 'https://connect.mailerlite.com/api';
    private $client;
    private $response;
    private $response_code;

    /**
     * MailerLiteAPI constructor
     *
     * @access      public
     * @return      void
     * @since       1.6.0
     */
    public function __construct($api_key)
    {
        $this->client = new MailerLiteClient($this->url, [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-Version'     => '2022-11-21',
        ]);
    }

    /**
     * Validate API Key
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function validateKey()
    {
        $response = $this->client->remote_get('/account');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Update subscriber
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function updateSubscriber($subscriber_email, $subscriber_data)
    {

        // TODO - implement update subscriber
    }

    /**
     * Update subscriber status
     *
     * @access      public
     * @return      mixed
     * @since       1.7.4
     */
    public function updateSubscriberStatus($email, $status)
    {

        $response = $this->client->remote_post('/subscribers', [
            'email'  => $email,
            'status' => $status
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Search for a subscriber
     *
     * @access      public
     * @return      mixed
     * @since       1.8.5
     */
    public function searchSubscriber($query)
    {
        $response = $this->client->remote_get('/subscribers', $query);
        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Get groups
     *
     * @access      public
     * @return      array[]
     * @since       1.6.0
     */
    public function getGroups($params)
    {
        $response = $this->client->remote_get('/groups', $params);
        $response = self::parseResponse($response);

        $groups = [];

        if (isset($response->data)) {

            foreach ($response->data as $record) {

                $group                 = [];
                $group['id']           = $record->id;
                $group['name']         = $record->name;
                $group['total']        = $record->active_count;
                $group['opened']       = $record->open_rate->float;
                $group['clicked']      = $record->click_rate->float;
                $group['date_created'] = $record->created_at;

                $groups['data'][] = $group;
            }
        }
        if ($response->links) {
            $groups['pagination'] = $response->links;
        }
        return $groups;
    }

    /**
     * Get group by id
     *
     * @access      public
     * @return      array[]
     * @since       1.6.0
     */
    public function getGroupById($group)
    {
        $response = $this->client->remote_get('/groups/'.$group);
        return self::parseResponse($response);
    }

    /**
     * Check if more groups need to be loaded
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function checkMoreGroups($limit, $offset)
    {

        $response = $this->client->remote_get('/groups', [
            'limit' => $limit,
            'page'  => $offset
        ]);

        $response = self::parseResponse($response);

        return count($response->data ?? []) > 0;
    }

    /**
     * Get more groups
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getMoreGroups($limit, $offset)
    {

        $response = $this->client->remote_get('/groups', [
            'limit' => $limit,
            'page'  => $offset
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }

    /**
     * Get Double opt-in setting
     *
     * @access      public
     * @return      bool
     * @since       1.6.0
     */
    public function getDoubleOptin()
    {
        $response = $this->client->remote_get('/subscribe-settings/double-optin');

        if (isset($response)) {
            $response = self::parseResponse($response);

            if (isset($response->double_optin) && $response->double_optin) {

                return true;
            }
        }

        return false;
    }

    /**
     * Toggle Double opt-in setting
     *
     * @access      public
     * @return      bool
     * @since       1.6.0
     */
    public function setDoubleOptin()
    {
        $this->client->remote_post('/subscribe-settings/double-optin/toggle');

        return $this->getDoubleOptin();
    }

    /**
     * Get Fields
     *
     * @access      public
     *
     * @param       $params
     *
     * @return      array[]
     * @since       1.6.0
     */
    public function getFields($params): array
    {
        $response = $this->client->remote_get('/fields', $params);
        $response = self::parseResponse($response);

        $fields = [];

        if (isset($response->data)) {
            foreach ($response->data as $field) {
                $ml_field          = [];
                $ml_field['id']    = $field->id;
                $ml_field['title'] = $field->name;
                $ml_field['key']   = $field->key;
                $ml_field['type']  = $field->type;

                $fields[] = $ml_field;
            }
        }

        return $fields;
    }

    /**
     * Create Custom Field
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function createField($title, $type)
    {

        $response = $this->client->remote_post('/fields', [
            'name' => $title,
            'type' => $type
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Update Custom Field
     *
     * @access      public
     * @return      mixed
     * @since       1.7.11
     */
    public function updateField($id, $name)
    {

        $response = $this->client->remote_put('/fields/' . $id, [
            'name' => $name
        ]);

        return self::parseResponse($response);
    }

    /**
     * Sync WooCommerce Customer
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function syncCustomerWooCommerce($customer_id, $email, $fields, $shop_id)
    {

        if ($customer_id !== '0') {

            $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/customers', [
                'email'             => $email,
                'accepts_marketing' => true, // TODO
                'total_spent'       => 0,
                'create_subscriber' => true // TODO
            ]);
        } else {

            $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/customers/' . $customer_id, [
                'email'             => $email,
                'accepts_marketing' => true, // TODO
                'total_spent'       => 0,
                'create_subscriber' => true // TODO
            ]);
        }

        $response = self::parseResponse($response);

        return $response->data;
    }

    /**
     * Set WooCommerce Consumer Data
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function setConsumerData(
        $consumerKey,
        $consumerSecret,
        $store,
        $currency,
        $group_id,
        $resubscribe,
        $ignoreList,
        $create_segments,
        $shop_name,
        $shop_id,
        $popups_enabled
    ) {

        if ($shop_id !== false) {

            $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id, [
                'name'               => $shop_name,
                'url'                => $store,
                'currency'           => $currency,
                'platform'           => 'woocommerce',
                'group_id'           => $group_id,
                'enable_popups'      => $popups_enabled,
                'enable_resubscribe' => $resubscribe,
                'enabled'            => true,
                'access_data'        => '-'
            ]);
        } else {

            $response = $this->client->remote_post('/ecommerce/shops', [
                'name'               => $shop_name,
                'url'                => $store,
                'currency'           => $currency,
                'platform'           => 'woocommerce',
                'group_id'           => $group_id,
                'enable_popups'      => $popups_enabled,
                'enable_resubscribe' => $resubscribe,
                'enabled'            => true,
                'access_data'        => '-'
            ]);
        }

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Send WooCommerce Subscriber Data
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function sendSubscriberData($shop_id, $data)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/customers', [
            'email'             => $data['email'],
            'accepts_marketing' => $data['checked_sub_to_mailist'],
            'total_spent'       => 0,
            'create_subscriber' => $data['checked_sub_to_mailist']
        ]);

        $response = self::parseResponse($response);

        return $response->data;
    }

    /**
     * Save WooCommerce Order
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function saveOrder($shop_id, $orderData)
    {

        return true;
    }

    /**
     * Send WooCommerce Order Processing
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function sendOrderProcessing($shop_id, $data)
    {

        $order_id = $data['order_id'];

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/orders/' . $order_id, [
            'status' => 'processing'
        ]);

        $response = self::parseResponse($response);

        return $response->data;
    }

    /**
     * Update WooCommerce Cart
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function sendCart($shop_id, $cartData)
    {

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/carts/' . $cartData['cart_id'], [
            'checkout_url' => $cartData['abandoned_checkout_url'],
            'cart_total'   => 0
        ]);

        $response = self::parseResponse($response);

        return $response->data;
    }

    /**
     * Toggle WooCommerce Shop
     *
     * @access      public
     *
     * @param       $shop
     * @param       $activeState
     * @param       $shop_id
     * @param       $shop_name
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function toggleShop($shop, $activeState, $shop_id, $shop_name)
    {

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id, [
            'name'    => $shop_name,
            'url'     => $shop,
            'enabled' => $activeState
        ]);

        $response = self::parseResponse($response);

        return $response->data;
    }

    /**
     * Get WooCommerce Shop Settings
     *
     * @access      public
     *
     * @param       $shop_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function getShopSettings($shop_id)
    {

        $response = $this->client->remote_get('/ecommerce/shops/' . $shop_id);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Validate WooCommerce Account
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function validateAccount()
    {

        $response = $this->client->remote_get('/account');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Get list of shops
     *
     * @access      public
     * @return      array
     * @since       1.6.0
     */
    public function getShops()
    {

        $response = $this->client->remote_get('/ecommerce/shops');

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }

    /**
     * Get a shop
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getShop($shop_id)
    {

        $response = $this->client->remote_get('/ecommerce/shops/' . $shop_id);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Create shop
     *
     * @access      public
     *
     * @param       $name
     * @param       $url
     * @param       $currency
     * @param       $group_id
     * @param       $enable_popups
     * @param       $access_data
     * @param       $enable_resubscribe
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function createShop($name, $url, $currency, $group_id, $enable_popups, $access_data, $enable_resubscribe)
    {
        $response = $this->client->remote_post('/ecommerce/shops', [
            'name'               => $name,
            'url'                => $url,
            'currency'           => $currency,
            'platform'           => 'woocommerce',
            'group_id'           => $group_id,
            'enable_popups'      => $enable_popups,
            'enable_resubscribe' => $enable_resubscribe,
            'enabled'            => true,
            'access_data'        => $access_data
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Delete shop
     *
     * @access      public
     *
     * @param       $shop
     *
     * @return      bool
     * @since       1.6.0
     */
    public function deleteshop($shop)
    {
        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Fetch order
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $order_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function fetchOrder($shop_id, $order_id)
    {

        $response = $this->client->remote_get('/ecommerce/shops/' . $shop_id . '/orders/' . $order_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Import orders
     *
     * @access public
     *
     * @param $shop_id
     * @param $orders
     *
     * @return array
     * @since 1.6.0
     */
    public function importOrders($shop_id, $orders)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/orders/import?with_resource_id', [
            'orders' => $orders
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }

    /**
     * Import customers
     *
     * @access public
     *
     * @param $shop_id
     * @param $customers
     *
     * @return array
     * @since 1.8.5
     */
    public function importCustomers($shop_id, $customers)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/customers/import?with_resource_id',
            $customers);

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }

    /**
     * Sync order
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $order_id
     * @param       $customer
     * @param       $cart
     * @param       $status
     * @param       $total_price
     * @param       $created_at
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function syncOrder($shop_id, $order_id, $customer, $cart, $status, $total_price, $created_at)
    {

        $order_status = 'pending';
        // if order status from woocommerce is wc-completed or wc-processing, set order status to completed
        if (in_array($status, ['wc-completed', 'wc-processing'])) {
            $status = 'completed';
        }
        if ($status == 'completed' || $status == 'processing') {
            if (isset($customer['resource_id']) && (int)$customer['resource_id']) {
                $lastSyncedCustomer = get_option('woo_ml_last_synced_customer', 0);
                if (($lastSyncedCustomer > 0) && ($lastSyncedCustomer < $customer['resource_id'])) {
                    update_option('woo_ml_last_synced_customer', $customer['resource_id']);
                }
            }
            $order_status = 'complete';
        }

        if ( ! isset($cart['resource_id'])) {
            $cart['resource_id'] = (string)$order_id;
        }

        if ( ! isset($cart['cart_total'])) {
            $cart['cart_total'] = $total_price;
        }

        $parameters = [
            'resource_id' => (string)$order_id,
            'customer'    => $customer,
            'cart'        => $cart,
            'status'      => $order_status,
            'total_price' => $total_price,
            'created_at'  => $created_at
        ];
        // check if wpml is enabled and current language is not english
        if ( defined( 'ICL_SITEPRESS_VERSION' )) {
            foreach($parameters['cart']['items'] as &$product) {

                // send english product id
                $args = array('element_id' => $product['product_resource_id'], 'element_type' => 'post' );
                $productLang = apply_filters( 'wpml_element_language_details', null, $args );

                if ($productLang->source_language_code) {
                    $product['product_resource_id'] = (string)apply_filters( 'wpml_object_id', $product['product_resource_id'], 'post', FALSE, $productLang->source_language_code ) ?? $product['product_resource_id'];
                }
            }
        }
        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/orders/queue-order-sync?with_resource_id',
            $parameters);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Delete order
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $order_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function deleteOrder($shop_id, $order_id)
    {

        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop_id . '/orders/' . $order_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Update order
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $order_id
     * @param       $status
     * @param       $total_price
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function updateOrder($shop_id, $order_id, $status, $total_price)
    {

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/orders/' . $order_id . '?with_resource_id',
            [
                'status'      => $status,
                'total_price' => (float)$total_price
            ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Sync product
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $product_id
     * @param       $name
     * @param       $price
     * @param       $exclude_automation
     * @param       $url
     * @param       $image
     * @param       $categories
     * @param       $description
     * @param       $shortDescription
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function syncProduct($shop_id, $product_id, $name, $price, $exclude_automation, $url, $image, $categories, $description, $shortDescription)
    {

        $parameters = [
            'resource_id'              => (string)$product_id,
            'name'                     => $name,
            'price'                    => $price,
            'exclude_from_automations' => $exclude_automation,
            'url'                      => $url,
            'categories'               => $categories,
        ];

        if ( ! empty($image)) {
            $parameters['image'] = (string)$image;
        }

        if (!empty($description)) {
            $parameters['description'] = $description;
        }

        if (!empty($shortDescription)) {
            $parameters['short_description'] = $shortDescription;
        }

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/products?with_resource_id',
            $parameters);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Replace product categories
     *
     * @access public
     *
     * @param $shop_id
     * @param $product_id
     * @param $categories
     *
     * @return mixed
     * @since 1.6.0
     */
    public function replaceProductCategories($shop_id, $product_id, $categories)
    {

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/products/' . $product_id . '/categories/multiple?with_resource_id',
            [
                'replace'    => true,
                'categories' => $categories
            ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Import products
     *
     * @access  public
     *
     * @param   @shop_id
     * @param   @products
     *
     * @return  array
     * @since   1.6.0
     */
    public function importProducts($shop_id, $products)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/products/import?with_resource_id&replace_categories',
            $products);

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }

    /**
     * Delete product
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $product_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function deleteProduct($shop_id, $product_id)
    {

        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop_id . '/products/' . $product_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Create product category
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $category_id
     * @param       $name
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function syncCategory($shop_id, $category_id, $name)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/categories?with_resource_id', [
            "resource_id" => (string)$category_id,
            "name"        => $name
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Import categories
     *
     * @access  public
     *
     * @param   @shop_id
     * @param   @categories
     *
     * @return  array
     * @since   1.6.0
     */
    public function importCategories($shop_id, $categories)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/categories/import?with_resource_id',
            $categories);

        $response = self::parseResponse($response);

        return $response->data ?? [];
    }


    /**
     * Delete product category
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $category_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function deleteCategory($shop_id, $category_id)
    {

        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop_id . '/categories/' . $category_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Sync customer
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $customer_id
     * @param       $email
     * @param       $fields
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function syncCustomer($shop_id, $customer_id, $email, $fields)
    {

        if (strval($customer_id) === "0") {
            return false;
        }

        $data = [
            'resource_id' => (string)$customer_id,
            'email'       => $email,
        ];

        if (isset($fields['orders_count'])) {
            $data['orders_count'] = $fields['orders_count'];

            unset($fields['orders_count']);
        }

        if (isset($fields['total_spent'])) {
            $data['total_spent'] = $fields['total_spent'];

            unset($fields['total_spent']);
        }

        $data['subscriber_fields'] = $fields;

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/customers' . '?with_resource_id',
            $data);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Update customer
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $customer_id
     * @param       $email
     * @param       $accepts_marketing
     * @param       $create_subscriber
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function updateCustomer($shop_id, $customer_id, $email, $accepts_marketing, $create_subscriber)
    {

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/customers/' . $customer_id, [
            'email'             => $email,
            'accepts_marketing' => $accepts_marketing,
            'create_subscriber' => $create_subscriber
        ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Delete customer
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $customer_id
     *
     * @return      bool
     * @since       1.6.0
     */
    public function deleteCustomer($shop_id, $customer_id)
    {
        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop_id . '/customers/' . $customer_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Fetch order
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $customer_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function fetchCustomer($shop_id, $customer_id)
    {

        $response = $this->client->remote_get('/ecommerce/shops/' . $shop_id . '/customers/' . $customer_id . '?with_resource_id');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Update cart
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $cart_id
     * @param       $checkout_url
     * @param       $cart_total
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function updateCart($shop_id, $cart_id, $checkout_url, $cart_total, $order_id)
    {

        $parameters = [
            'resource_id' => (string)$order_id,
            'cart_total'  => $cart_total
        ];

        if ( ! empty($checkout_url)) {
            $parameters['checkout_url'] = (string)$checkout_url;
        }

        $response = $this->client->remote_put('/ecommerce/shops/' . $shop_id . '/carts/' . $cart_id, $parameters);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Add cart items
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $cart_id
     * @param       $item
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function addCartItem($shop_id, $cart_id, $item)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/carts/' . $cart_id . '/items', $item);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Replace cart items
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $cart_id
     * @param       $items
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function replaceCartItems($shop_id, $cart_id, $items)
    {

        $response = $this->client->remote_post('/ecommerce/shops/' . $shop_id . '/carts/' . $cart_id . '/items/replace?with_resource_id',
            [
                'items' => $items
            ]);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Get cart items
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $cart_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function getCartItems($shop_id, $cart_id)
    {

        $response = $this->client->remote_get('/ecommerce/shops/' . $shop_id . '/carts/' . $cart_id . '/items');

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Delete cart items
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $cart_id
     * @param       $item_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function deleteCartItem($shop_id, $cart_id, $item_id)
    {

        $response = $this->client->remote_delete('/ecommerce/shops/' . $shop_id . '/carts/' . $cart_id . '/items/' . $item_id);

        $response = self::parseResponse($response);

        return $response->data ?? false;
    }

    /**
     * Batch process requests
     *
     * @access      public
     *
     * @param       $requests
     *
     * @return      mixed
     * @since       1.6.6
     */
    public function batch($requests)
    {
        $response = $this->client->remote_post('/batch', $requests);

        $response = self::parseResponse($response);

        return $response->responses ?? false;
    }

    /**
     * Get cart items
     *
     * @access      public
     *
     * @return      boolean
     * @since       1.6.0
     */
    public function getAccountDetails()
    {

        return false;
    }

    /**
     * Create Group
     *
     * @access      public
     *
     * @param       $name
     *
     * @return      mixed
     * @since       1.6.6
     */
    public function createGroup($name)
    {
        $response = $this->client->remote_post('/groups', [
            'name' => $name
        ]);
        return self::parseResponse($response);
    }

    /**
     * Get raw response body
     *
     * @access      public
     * @return      string
     * @since       1.6.0
     */
    public function getResponseBody()
    {
        return $this->response;
    }

    /**
     * Get response code
     *
     * @access      public
     * @return      int
     * @since       1.6.0
     */
    public function responseCode()
    {
        return $this->response_code;
    }

    /**
     * Get response code and body
     *
     * @access      private
     * @return      mixed
     * @since       1.6.0
     */
    private function parseResponse($response)
    {
        if ( ! is_wp_error($response)) {

            $this->response      = wp_remote_retrieve_body($response);
            $this->response_code = wp_remote_retrieve_response_code($response);

            if ( ! is_wp_error($this->response)) {
                $response = json_decode($this->response);

                if (json_last_error() == JSON_ERROR_NONE) {

                    if ( ! isset($response->error)) {
                        return $response;
                    }
                }
            }
        } else {
            $this->response      = $response->get_error_message();
            $this->response_code = $response->get_error_code();
        }

        return false;
    }
}