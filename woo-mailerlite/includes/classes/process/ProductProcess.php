<?php

namespace MailerLite\Includes\Classes\Process;

use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class ProductProcess extends Singleton
{
    /**
     * Retrieves the url of the image of the given product
     * woo_ml_product_image
     *
     * @param $product
     * @param string $size
     *
     * @return mixed|null
     */
    public function productImage($product, $size = 'large')
    {
        if ($product->get_image_id()) {

            $image = wp_get_attachment_image_src($product->get_image_id(), $size, false);

            list($src, $width, $height) = $image;

            return $src;
        } elseif ($product->get_parent_id()) {

            $parentProduct = wc_get_product($product->get_parent_id());
            if ($parentProduct) {

                return $this->productImage($parentProduct, $size);
            }
        }

        return '';
    }

    /**
     * Get Product List
     * woo_ml_get_product_list
     * @return array|mixed
     */
    public function getIgnoredProductList()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ml_data';

        $tableCreated = get_option('ml_data_table');

        if ($tableCreated != 1) {

            MailerLiteSettings::getInstance()->createMailerDataTable();
        }

        $data = $wpdb->get_row("SELECT * FROM $table WHERE data_name = 'products'");

        if (!empty($data)) {

            $products = json_decode($data->data_value, true);

            if ($products !== false) {

                return $products;
            } else {

                return [];
            }
        } else {

            return [];
        }

    }

    /**
     *
     * mailerlite_wp_delete_product_category
     *
     * @param $category_id
     *
     * @return false|void
     */
    public function deleteProductCategory($category_id)
    {

        $shop = get_option('woo_ml_shop_id', false);

        if ($shop === false) {

            return false;
        }

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() !== ApiType::CURRENT) {

            return false;
        }

        $mailerliteClient->deleteCategory($shop, $category_id);
    }

    /**
     *
     * mailerlite_wp_sync_product_category
     *
     * @param $category_id
     *
     * @return void
     */
    public function syncProductCategory($category_id)
    {

        $category = get_term($category_id);

        if (!isset($category->term_id)) {
            return;
        }

        $shop = get_option('woo_ml_shop_id', false);

        if ($shop === false) {

            return;
        }

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() !== ApiType::CURRENT) {

            return;
        }

        $result = $mailerliteClient->syncCategory($shop, $category->term_id, $category->name);

        if ($mailerliteClient->responseCode() === 200 || $mailerliteClient->responseCode() === 201) {

            MailerLiteSettings::getInstance()->completeCategoryTracking($result->resource_id, $result->id);
        }
    }

    /**
     *
     * mailerlite_sync_product
     *
     * @param $product_id
     *
     * @return bool
     */
    public function syncProduct($product_id)
    {

        $shop = get_option('woo_ml_shop_id', false);

        if ($shop === false) {

            return false;
        }

        $product = wc_get_product($product_id);

        $productID = $product->get_id();
        $productName = $product->get_name() ?: 'Untitled product';
        $productPrice = floatval($product->get_price('edit'));
        $productImage = $this->productImage($product);
        $productURL = $product->get_permalink();
        $productDescription = $product->get_description();
        $productShortDescription = $product->get_short_description();

        $categories = get_the_terms($productID, 'product_cat');

        if (!$categories || is_wp_error($categories)) {
            $categories = [];
        }

        $productCategories = [];
        foreach ($categories as $category) {

            if (isset($category->term_id) && is_numeric($category->term_id)) {

                $productCategories[] = (string)$category->term_id;
            }
        }

        $exclude_automation = get_post_meta($productID, '_woo_ml_product_ignored', true) === "1";

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() !== ApiType::CURRENT) {

            return false;
        }

        $result = $mailerliteClient->syncProduct(
            $shop,
            $productID,
            $productName,
            $productPrice,
            $exclude_automation,
            $productURL,
            $productImage,
            [],
            $productDescription,
            $productShortDescription
        );

        if ($result !== false && ($mailerliteClient->responseCode() == 201 || $mailerliteClient->responseCode() == 200)) {
            $mailerLiteSettings = MailerLiteSettings::getInstance();
            if (!$mailerLiteSettings->checkProductTracking($productID)) {
                $mailerLiteSettings->completeProductTracking($productID);
            }

            $mailerliteClient->replaceProductCategories($shop, $productID, $productCategories);

            return true;
        }

        return false;
    }

    /**
     *
     * mailerlite_wp_sync_product
     *
     * @param $product_id
     *
     * @return void
     */
    public function wpSyncProduct($product_id)
    {
        if (did_action('woocommerce_update_product') === 1) {

            $this->syncProduct($product_id);
        }
    }

}