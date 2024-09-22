<?php

namespace MailerLite\Includes\Shared\Api;

class PlatformAPI
{

    private $api;
    private $api_key;

    /**
     * MailerLiteAPI constructor
     *
     * @access      public
     * @return      void
     * @since       1.6.0
     */
    public function __construct($api_key)
    {

        $this->api_key = $api_key;

        if ($api_key !== '') {

            switch ($this->getApiType()) {
                case ApiType::CLASSIC:
                    $this->api = new MailerLiteClassicAPI($api_key);
                    break;
                case ApiType::CURRENT:
                    $this->api = new MailerLiteAPI($api_key);
                    break;
                default:
                    $this->api_key = '';
                    break;
            }
        }
    }

    /**
     * get API Key Type
     *
     * @access      public
     * @return      int
     * @since       1.6.0
     */
    public function getApiType()
    {

        if ($this->api_key == '') {

            return ApiType::INVALID;
        }

        if ($this->isValidClassicKey()) {

            return ApiType::CLASSIC;
        } else {

            return ApiType::CURRENT;
        }
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

        return $this->api->validateKey();
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

        return $this->api->updateSubscriber($subscriber_email, $subscriber_data);
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

        return $this->api->updateSubscriberStatus($email, $status);
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

        return $this->api->searchSubscriber($query);
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

        return $this->api->getGroups($params);
    }

    /**
     * Get group by id
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getGroupById($params = [])
    {

        return $this->api->getGroupById($params);
    }

    /**
     * Check if more groups need to be loaded
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function checkMoreGroups($limit = 100, $offset = 2)
    {

        return $this->api->checkMoreGroups($limit, $offset);
    }

    /**
     * Check if more groups need to be loaded
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getMoreGroups($limit = 100, $offset = 1)
    {

        return $this->api->getMoreGroups($limit, $offset);
    }

    /**
     * Get Double opt-in setting
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function getDoubleOptin()
    {

        return $this->api->getDoubleOptin();
    }

    /**
     * Enable/Disable Double opt-in setting
     *
     * @access      public
     * @return      mixed
     * @since       1.6.0
     */
    public function setDoubleOptin($enable)
    {
        return $this->api->setDoubleOptin($enable);
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

        return $this->api->getFields($params);
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

        return $this->api->createField($title, $type);
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

        return $this->api->updateField($id, $name);
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

        return $this->api->syncCustomerWooCommerce($customer_id, $email, $fields, $shopUrl);
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

        return $this->api->setConsumerData($consumerKey, $consumerSecret, $store, $currency, $group_id, $resubscribe,
            $ignoreList, $create_segments, $shop_name, $shop_id, $popups_enabled);
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

        return $this->api->sendSubscriberData($shop_id, $data);
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

        return $this->api->saveOrder($shop, $orderData);
    }

    /**
     * Send WooCommerce Order Processing
     *
     * @access      public
     *
     * @param       $shop_id
     * @param       $data
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function sendOrderProcessing($shop_id, $data)
    {

        return $this->api->sendOrderProcessing($shop_id, $data);
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

        return $this->api->sendCart($shop, $cartData);
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

        return $this->api->toggleShop($shop, $activeState, $shop_id, $shop_name);
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

        return $this->api->getShopSettings($shopUrl);
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

        return $this->api->validateAccount();
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
        return $this->api->getShops();
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

        return $this->api->getShop($shop_id);
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
        return $this->api->createShop($name, $url, $currency, $group_id, $enable_popups, $access_data, $enable_resubscribe);
    }

    /**
     * Delete shop
     *
     * @access      public
     *
     * @param       $shop
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function deleteShop($shop)
    {
        return $this->api->deleteShop($shop);
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

        return $this->api->fetchOrder($shop_id, $order_id);
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

        return $this->api->importOrders($shop_id, $orders);
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

        return $this->api->importCustomers($shop_id, $customers);
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

        return $this->api->syncOrder($shop_id, $order_id, $customer, $cart, $status, $total_price, $created_at);
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

        return $this->api->deleteOrder($shop_id, $order_id);
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

        return $this->api->updateOrder($shop_id, $order_id, $status, $total_price);
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

        return $this->api->syncProduct($shop_id, $product_id, $name, $price, $exclude_automation, $url, $image, $categories, $description, $shortDescription);
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

        return $this->api->replaceProductCategories($shop_id, $product_id, $categories);
    }

    /**
     * Import products
     *
     * @access public
     *
     * @param $shop_id
     * @param $products
     *
     * @return array
     * @since 1.6.0
     */
    public function importProducts($shop_id, $products)
    {

        return $this->api->importProducts($shop_id, $products);
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

        return $this->api->deleteProduct($shop_id, $product_id);
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

        return $this->api->syncCategory($shop_id, $category_id, $name);
    }

    /**
     * Import categories
     *
     * @access public
     *
     * @param $shop_id
     * @param $categories
     *
     * @return array
     * @since 1.6.0
     */
    public function importCategories($shop_id, $categories)
    {

        return $this->api->importCategories($shop_id, $categories);
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

        return $this->api->deleteCategory($shop_id, $category_id);
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

        return $this->api->syncCustomer($shop_id, $customer_id, $email, $fields);
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

        return $this->api->updateCustomer($shop_id, $customer_id, $email, $accepts_marketing, $create_subscriber);
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
        return $this->api->deleteCustomer($shop_id, $customer_id);
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

        return $this->api->fetchCustomer($shop_id, $customer_id);
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

        return $this->api->updateCart($shop_id, $cart_id, $checkout_url, $cart_total, $order_id);
    }


    /**
     * Add cart item
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

        return $this->api->addCartItem($shop_id, $cart_id, $item);
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

        return $this->api->replaceCartItems($shop_id, $cart_id, $items);
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

        return $this->api->getCartItems($shop_id, $cart_id);
    }

    /**
     * Get Account Details
     *
     * @access      public
     *
     * @return      mixed
     * @since       1.6.0
     */
    public function getAccountDetails()
    {

        return $this->api->getAccountDetails();
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

        return $this->api->deleteCartItem($shop_id, $cart_id, $item_id);
    }

    public function batch($requests)
    {
        return $this->api->batch($requests);
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
        return $this->api->getResponseBody();
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
        return $this->api->responseCode();
    }

    /**
     * Checks if token is a valid md5 string
     *
     * @access      private
     * @return      bool
     * @since       1.6.0
     */
    private function isValidClassicKey()
    {

        return (strlen($this->api_key) < 100);
    }

    public function createGroup($name)
    {
        return $this->api->createGroup($name);
    }
}