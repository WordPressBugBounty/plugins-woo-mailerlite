<?php

namespace MailerLite\Includes\Classes\Data;

use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Settings\ShopSettings;
use MailerLite\Includes\Classes\Singleton;
use Automattic\WooCommerce\Utilities\OrderUtil;

class TrackingData extends Singleton
{
    private $wpdb;

    private $hposEnabled = false;
    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        if (class_exists('OrderUtil') && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $this->hposEnabled = true;
        }

    }


    /**
     * Get untracked product categories
     * woo_ml_get_untracked_categories
     * @return array
     */
    public function getUntrackedCategories()
    {

        $term_args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'none',
            'meta_key' => '_woo_ml_category_tracked',
            'meta_compare' => 'NOT EXISTS'
        );

        return get_terms($term_args);
    }

    /**
     * Get untracked products
     * woo_ml_get_untracked_products
     *
     * @param array $args
     *
     * @return array
     */
    public function getUntrackedProducts($args = array())
    {

        $defaults = array(
            'post_type' => 'product',
            'posts_per_page' => 100,
            'meta_key' => '_woo_ml_product_tracked',
            'meta_compare' => 'NOT EXISTS'
        );

        $args = wp_parse_args($args, $defaults);
        $product_posts_query = new \WP_Query($args);

        $products = [];

        if ($product_posts_query->have_posts()) {
            $products = $product_posts_query->posts;
        }

        return $products;
    }

    /**
     * Get tracked product categories count
     * woo_ml_get_tracked_categories_count
     * @return int
     */
    public function getTrackedCategoriesCount()
    {

        $term_args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'none',
            'meta_key' => '_woo_ml_category_tracked',
            'meta_compare' => 'EXISTS'
        );

        $categories = get_terms($term_args);

        return count($categories);
    }

    /**
     *
     * woo_ml_count_untracked_products_count
     * @return mixed
     */
    public function getUntrackedProductsCount()
    {
        $defaults = array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'meta_key' => '_woo_ml_product_tracked',
            'meta_compare' => 'NOT EXISTS'
        );

        $args = wp_parse_args($defaults);
        $products_query = new \WP_Query($args);

        return $products_query->found_posts;
    }

    /**
     *
     * woo_ml_get_untracked_customers_count
     * @return mixed
     */
    public function getUntrackedCustomersCount()
    {
        $lastSyncedCustomer = get_option('woo_ml_last_synced_customer', 0);
        $query = "SELECT
                    count(DISTINCT wcl.customer_id)
                FROM
                    {$this->wpdb->prefix}wc_customer_lookup wcl
                    INNER JOIN {$this->wpdb->prefix}wc_order_stats wcos ON wcos.customer_id = wcl.customer_id
                WHERE
                    wcos.status IN('wc-processing', 'wc-completed')
                    AND wcl.email != ''
                    AND wcl.customer_id > {$lastSyncedCustomer};";

        return $this->wpdb->get_var($query);
    }

    /**
     *
     * woo_ml_get_tracked_products_count
     * @return int
     */
    public function getTrackedProductCount()
    {
        $defaults = array(
            'post_type' => 'product',
            'posts_per_page' => 100,
            'meta_key' => '_woo_ml_product_tracked',
            'meta_compare' => 'EXISTS'
        );

        $args = wp_parse_args($defaults);
        $product_posts_query = new \WP_Query($args);

        $product_posts = [];

        if ($product_posts_query->have_posts()) {
            $product_posts = $product_posts_query->get_posts();
        }

        return count($product_posts);
    }

    /**
     *
     * woo_ml_count_untracked_categories_count
     * @return int
     */
    public function getUntrackedCategoriesCount()
    {
        return count($this->getUntrackedCategories());
    }

    /**
     * Get settings page url
     * woo_ml_get_settings_page_url
     * @return string
     */
    public function getSettingsPageUrl()
    {
        return admin_url('admin.php?page=wc-settings&tab=integration&section=mailerlite');
    }

    /**
     * Get complete integration setup url
     * woo_ml_get_complete_integration_setup_url
     * @return string
     */
    public function getCompleteIntegrationSetupUrl()
    {
        return add_query_arg('woo_ml_action', 'setup_integration', $this->getSettingsPageUrl());
    }

    /**
     * Update ignore product list in ml_data table
     * woo_ml_update_data
     * @return mixed
     */
    public function updateData($products)
    {

        $table = $this->wpdb->prefix . 'ml_data';

        $tableCreated = get_option('ml_data_table');

        if ($tableCreated != 1) {

            MailerLiteSettings::getInstance()->createMailerDataTable();
        }

        $updateQuery = $this->wpdb->prepare("
                INSERT INTO $table (data_name, data_value) VALUES ('products', %s) ON DUPLICATE KEY UPDATE data_value = %s
                ", json_encode($products), json_encode($products));

        return $this->wpdb->query($updateQuery);
    }

    /**
     * Save ignored products to WooCommerce Integration and ml_data table
     * woo_ml_save_local_ignore_products
     */
    public function saveLocalIgnoreProducts($products)
    {

        $ignore_map = MailerLiteSettings::getInstance()->remapList($products);

        if (ShopSettings::getInstance()->updateIgnoreProductList($ignore_map) === true) {

            // save updated ignore product list to WooCommerce Integration
            $settings = get_option('woocommerce_mailerlite_settings');

            if (!isset($settings['ignore_product_list'])) {
                $settings['ignore_product_list'] = array();
            }

            $settings['ignore_product_list'] = $ignore_map;

            update_option('woocommerce_mailerlite_settings', $settings);

            //save product ignore list to ml_data
            $this->updateData($products);
        }
    }

    /**
     * Remove product from product ignore list for ml_data
     * woo_ml_remove_product_from_list
     * @return array
     */
    public function removeProductFromList($products, $remove_list)
    {

        return array_filter($products, function ($k) use ($remove_list) {
            return !in_array($k, $remove_list);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     *
     * woo_ml_get_customers_count
     * @return mixed
     */
    public function getCustomersCount()
    {

        $query = "SELECT
            count(DISTINCT wcl.customer_id)
        FROM
            {$this->wpdb->prefix}wc_customer_lookup wcl
            INNER JOIN {$this->wpdb->prefix}wc_order_stats wcos on wcos.customer_id = wcl.customer_id
            WHERE wcl.email  != ''
            AND wcos.status IN ('wc-processing', 'wc-completed');";
        return $this->wpdb->get_var($query);
    }

    public function getCustomersToSync($lastTrackedCustomer = 0, $limit = 100)
    {

        if($this->hposEnabled) {
            return $this->getCustomersToSyncToSyncForHpos($lastTrackedCustomer, $limit);
        }
        $query = "SELECT
                    wcos.customer_id AS resource_id,
                    wcl.email,
                    CASE WHEN (
                        SELECT
                            wpm.meta_value
                        FROM
                            {$this->wpdb->prefix}postmeta wpm
                        WHERE
                            wpm.meta_key = '_woo_ml_subscribe'
                            AND wpm.post_id = max(wcos.order_id)
                        LIMIT 1) THEN
                        TRUE
                    ELSE
                        FALSE
                    END AS create_subscriber,
                    count(DISTINCT (wcos.order_id)) AS orders_count,
                    sum(DISTINCT (wcos.total_sales)) AS total_spent,
                    wcl.first_name AS name,
                    wcl.last_name AS last_name,
                    wcl.city AS city,
                    wcl.state AS state,
                    wcl.country AS country,
                    wcl.postcode AS postcode,
                    max(wcos.order_id) AS last_order_id,
                    max(wcos.date_created) AS last_order
                FROM
                    {$this->wpdb->prefix}wc_customer_lookup wcl
                    INNER JOIN {$this->wpdb->prefix}wc_order_stats wcos ON wcos.customer_id = wcl.customer_id
                WHERE
                    wcos.status IN('wc-processing', 'wc-completed')
                    AND wcl.email != ''
                    AND wcos.customer_id > {$lastTrackedCustomer}
                    {$this->prepareNonSyncedCustomerQuery()}      
                GROUP BY
                    wcos.customer_id,
                    email
                ORDER BY
                    resource_id
                LIMIT {$limit};";

        return $this->wpdb->get_results($query, 'ARRAY_A');
    }


    public function getCustomersToSyncToSyncForHpos($lastTrackedCustomer, $limit)
    {
        $query = "SELECT
                    wcos.customer_id AS resource_id,
                    wcl.email,
                    CASE WHEN (
                        SELECT
                            wca.meta_value
                        FROM
                            {$this->wpdb->prefix}wc_orders_meta wca
                        WHERE
                            wca.meta_key = '_woo_ml_subscribe'
                            AND wca.order_id = max(wcos.order_id)
                        LIMIT 1) THEN
                        TRUE
                    ELSE
                        FALSE
                    END AS create_subscriber,
                    count(DISTINCT (wcos.order_id)) AS orders_count,
                    sum(DISTINCT (wcos.total_sales)) AS total_spent,
                    wcl.first_name AS name,
                    wcl.last_name AS last_name,
                    min(wca.company) AS company,
                    wcl.city AS city,
                    wcl.state AS state,
                    wcl.country AS country,
                    min(wca.phone) AS phone,
                    wcl.postcode AS postcode,
                    max(wcos.order_id) AS last_order_id,
                    max(wcos.date_created) AS last_order
                FROM
                    {$this->wpdb->prefix}wc_orders_meta AS wpm
                    INNER JOIN {$this->wpdb->prefix}wc_order_stats wcos ON wcos.order_id = wpm.order_id
                    INNER JOIN {$this->wpdb->prefix}wc_customer_lookup wcl ON wcl.customer_id = wcos.customer_id
                    INNER JOIN {$this->wpdb->prefix}wc_order_addresses wca ON wca.order_id = wpm.order_id
                WHERE
                    wcos.status IN('wc-processing', 'wc-completed')
                    AND wca.address_type = 'billing'
                    AND wcos.customer_id > {$lastTrackedCustomer}
                    {$this->prepareNonSyncedCustomerQuery()}
                GROUP BY
                    wcos.customer_id,
                    email
                ORDER BY
                    resource_id
                LIMIT {$limit};";

        return $this->wpdb->get_results($query, 'ARRAY_A');
    }

    private function prepareNonSyncedCustomerQuery()
    {
        $nonSyncedCustomers = get_option('woo_ml_non_synced_customer', false);
        return $nonSyncedCustomers ? "OR wcos.customer_id IN(" . implode(",", $nonSyncedCustomers). ")": "";
    }
}