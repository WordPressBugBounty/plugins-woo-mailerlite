<?php

namespace MailerLite\Includes\Shared\Api;

class MailerLiteClassicAPI
{

    private $url = 'https://api.mailerlite.com/api/v2';
    private $client;
    private $response;
    private $response_code;

    /**
     * MailerLiteClassicAPI constructor
     *
     * @access      public
     * @return      void
     * @since       1.6.0
     */
    public function __construct($api_key)
    {
        $this->client = new MailerLiteClient($this->url, [
            'X-MailerLite-ApiKey' => $api_key,
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json'
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

        $response = $this->client->remote_get('/');
        $response = self::parseResponse($response);

        $account = false;

        if ($this->response_code === 200) {

            $account              = [];
            $account['id']        = $response->account->id;
            $account['subdomain'] = $response->account->subdomain;
        }


        return $account;
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

        $response = $this->client->remote_put('/subscribers/' . $subscriber_email, $subscriber_data);

        return self::parseResponse($response);
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

        return true;
    }

    /**
     * Search for a subscriber
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function searchSubscriber($query)
    {

        $response = $this->client->remote_get('/subscribers/' . $query);

        return self::parseResponse($response);
    }

    /**
     * Get groups
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getGroups($params = [])
    {
        $response = $this->client->remote_get('/groups', $params);

        return self::parseResponse($response);
    }

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
            'limit'  => $limit,
            'offset' => ($offset - 1) * $limit
        ]);

        return count(self::parseResponse($response)) > 0;
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
            'limit'  => $limit,
            'offset' => ($offset - 1) * $limit
        ]);

        return self::parseResponse($response);
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
        $response = $this->client->remote_get('/settings/double_optin');
        $response = self::parseResponse($response);

        if (isset($response->enabled) && $response->enabled) {

            return true;
        }

        return false;
    }

    /**
     * Enable/Disable Double opt-in setting
     *
     * @access      public
     * @return      bool
     * @since       1.6.0
     */
    public function setDoubleOptin($enable)
    {

        $response = $this->client->remote_post('/settings/double_optin', ['enable' => $enable]);
        $response = self::parseResponse($response);

        if (isset($response->enabled) && $response->enabled) {

            return true;
        }

        return false;
    }

    /**
     * Get Fields
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getFields($params = [])
    {

        $response = $this->client->remote_get('/fields', $params);

        return self::parseResponse($response);
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
            'title' => $title,
            'type'  => $type
        ]);

        return self::parseResponse($response);
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
            'title' => $name,
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
    public function syncCustomerWooCommerce($customer_id, $email, $fields, $shopUrl)
    {

        $response = $this->client->remote_post('/woocommerce/sync_customer', [
            'email'             => $email,
            'subscriber_fields' => $fields,
            'shop'              => $shopUrl
        ]);

        return self::parseResponse($response);
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

        $response = $this->client->remote_post('/woocommerce/consumer_data', [
            'consumer_key'    => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'store'           => $store,
            'currency'        => $currency,
            'group_id'        => $group_id,
            'resubscribe'     => $resubscribe,
            'ignore_list'     => $ignoreList,
            'create_segments' => $create_segments
        ]);

        return self::parseResponse($response);
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

        $response = $this->client->remote_post('/woocommerce/save_subscriber', ['data' => $data]);

        return self::parseResponse($response);
    }

    /**
     * Save WooCommerce Order
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function saveOrder($shop, $orderData)
    {

        $response = $this->client->remote_post('/woocommerce/alternative_save_order', [
            'order_data' => $orderData,
            'shop'       => $shop
        ]);

        return self::parseResponse($response);
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

        $response = $this->client->remote_post('/woocommerce/order_processing', ['data' => $data]);

        return self::parseResponse($response);
    }

    /**
     * Send WooCommerce Cart
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function sendCart($shop, $cartData)
    {

        $response = $this->client->remote_post('/woocommerce/save_cart', [
            'cart_data' => $cartData,
            'shop'      => $shop
        ]);

        return self::parseResponse($response);
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

        $shopName = parse_url($shop, PHP_URL_HOST);

        $response = $this->client->remote_post('/woocommerce/toggle_shop_connection', [
            'active_state' => $activeState,
            'shop'         => $shopName
        ]);

        return self::parseResponse($response);
    }

    /**
     * Get WooCommerce Shop Settings
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getShopSettings($shopUrl)
    {

        $shopName = parse_url($shopUrl, PHP_URL_HOST);

        $response = $this->client->remote_get('/woocommerce/settings/' . $shopName);

        return self::parseResponse($response);
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

        $response = $this->client->remote_get('/woocommerce/initial_account_settings');

        return self::parseResponse($response);
    }

    /**
     * Get Account Details
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getAccountDetails()
    {

        $response = $this->client->remote_get('/me');

        return self::parseResponse($response);
    }

    /**
     * Get list of shops
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getShops()
    {

        return [];
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

        return false;
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
     * @return      bool
     * @since       1.6.0
     */
    public function createShop($name, $url, $currency, $group_id, $enable_popups, $access_data, $enable_resubscribe)
    {
        return true;
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
    public function deleteShop($shop)
    {
        return true;
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

        return false;
    }

    /**
     * Import orders
     *
     * @access public
     *
     * @param $shop_id
     * @param $orders
     *
     * @return bool
     * @since 1.6.0
     */
    public function importOrders($shop_id, $orders)
    {

        return true;
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

        return [];
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

        return true;
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

        return true;
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

        return true;
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
     * @return      bool
     * @since       1.6.0
     */
    public function syncProduct($shop_id, $product_id, $name, $price, $exclude_automation, $url, $image, $categories, $description, $shortDescription)
    {

        return true;
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
     * @return bool
     * @since 1.6.0
     */
    public function replaceProductCategories($shop_id, $product_id, $categories)
    {

        return true;
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

        return [];
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

        return true;
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

        return [];
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

        return [];
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

        return true;
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

        return $this->syncCustomerWooCommerce($customer_id, $email, $fields, $shop_id);
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

        return true;
    }

    /**
     * Delete customer
     *
     * @access      public
     *
     * @param       $shop
     * @param       $customer_id
     *
     * @return      bool
     * @since       1.6.0
     */
    public function deleteCustomer($shop, $customer_id)
    {
        return true;
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

        return true;
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
     * @param       $order_id
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function updateCart($shop_id, $cart_id, $checkout_url, $cart_total, $order_id)
    {

        return true;
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

        return true;
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

        return true;
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

        return true;
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

        return true;
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
        return true;
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