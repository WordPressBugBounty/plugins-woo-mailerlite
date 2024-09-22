<?php

namespace MailerLite\Includes\Classes\Settings;

use Automattic\WooCommerce\Utilities\OrderUtil;
use MailerLite\Includes\Classes\Data\TrackingData;
use MailerLite\Includes\Classes\Process\OrderProcess;
use MailerLite\Includes\Classes\Process\ProductProcess;
use MailerLite\Includes\Classes\Singleton;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;

class SynchronizeSettings extends Singleton
{

    /**
     * Class instance
     * @var $instance
     */
    protected static $instance;


    /**
     * Bulk synchronize untracked products
     * woo_ml_sync_untracked_products
     * @return array
     */
    public function syncUntrackedProducts()
    {

        set_time_limit(600);

        $message = 'Oops, we did not manage to sync all of your products, please try again.';

        try {

            $checkProducts = TrackingData::getInstance()->getUntrackedProducts();

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if (is_array($checkProducts) && sizeof($checkProducts) > 0) {

                $shop = get_option('woo_ml_shop_id', false);

                if ($shop === false) {

                    return [
                        'error' => true,
                        'message' => 'Shop is not activated.'
                    ];
                }

                $syncProducts = [];

                foreach ($checkProducts as $post) {

                    if (!isset($post->ID)) {
                        continue;
                    }

                    $product = wc_get_product($post->ID);

                    $productID = $product->get_id();
                    $productName = $product->get_name() ?: 'Untitled product';
                    $productPrice = floatval($product->get_price('edit'));
                    $productImage = ProductProcess::getInstance()->productImage($product);
                    $productDescription = $product->get_description();
                    $productShortDescription = $product->get_short_description();

                    $productURL = $product->get_permalink();

                    $categories = get_the_terms($productID, 'product_cat');

                    if (!$categories || is_wp_error($categories)) {
                        $categories = [];
                    }

                    $productCategories = [];
                    foreach ($categories as $category) {

                        if (isset($category->term_id) && is_numeric($category->term_id)) {
                            if(!in_array((string) $category->term_id, $productCategories)) {
                                $productCategories[] = (string)$category->term_id;
                            }
                        }
                    }

                    $exclude_automation = get_post_meta($productID, '_woo_ml_product_ignored', true) === "1";

                    $syncProduct = [
                        'resource_id' => (string)$productID,
                        'name' => $productName,
                        'price' => $productPrice,
                        'url' => $productURL,
                        'exclude_from_automations' => $exclude_automation,
                        'categories' => $productCategories,
                    ];
                    if (empty($productURL) || !is_string($productURL)) {
                        MailerLiteSettings::getInstance()->completeProductTracking((string)$productID);
                        continue;
                    }
                    if (!empty($productImage)) {

                        $syncProduct['image'] = (string)$productImage;
                    }

                    if (!empty($productDescription)) {
                        $syncProduct['description'] = $productDescription;
                    }

                    if (!empty($productShortDescription)) {
                        $syncProduct['short_description'] = $productShortDescription;
                    }

                    $syncProducts[] = $syncProduct;
                }

                $syncCount = 0;

                if (count($syncProducts) > 0) {

                    $result = $mailerliteClient->importProducts($shop, $syncProducts);

                    if (empty($result) || $mailerliteClient->responseCode() == 422 || $mailerliteClient->responseCode() == 500) {

                        $errorMsg = json_decode($mailerliteClient->getResponseBody());
                        $message = 'Oops, we did not manage to sync all of your products, please try again. (' . $mailerliteClient->responseCode() . ')';

                        if ($mailerliteClient->responseCode() == 422 && isset($errorMsg->message)) {

                            $message = $errorMsg->message;
                        }

                        return [
                            'error' => true,
                            'code' => $mailerliteClient->responseCode(),
                            'message' => $message
                        ];
                    }

                    if ($mailerliteClient->responseCode() == 201 || $mailerliteClient->responseCode() == 200) {

                        foreach ($syncProducts as $product) {

                            MailerLiteSettings::getInstance()->completeProductTracking($product['resource_id']);
                            $syncCount++;
                        }
                    }
                }

                return [
                    'completed' => $syncCount,
                    'code' => $mailerliteClient->responseCode()
                ];

            }

            return [];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $message
            ];
        }
    }

    /**
     * Bulk synchronize untracked resources
     * woo_ml_sync_untracked_resources
     * @return bool
     * @throws Exception
     */
    public function syncUntrackedResources()
    {

        if(!get_option('woo_ml_shop_id', false) && (get_option('woo_ml_wizard_setup', 0) == 1)) {
            MailerLiteSettings::getInstance()->updateSettings([]);
        }

        $trackingData = TrackingData::getInstance();
        if ((int)get_option('woo_mailerlite_platform', 1) === ApiType::CURRENT) {
            $untracked_categories_count = $trackingData->getUntrackedCategoriesCount();

            if ($untracked_categories_count > 0) {

                return json_encode(array_merge([
                    'allDone' => false,
                ], $this->syncUntrackedCategories()));
            }

            $untracked_products_count = $trackingData->getUntrackedProductsCount();

            if ($untracked_products_count > 0) {

                return json_encode(array_merge([
                    'allDone' => false,
                ], $this->syncUntrackedProducts()));
            }
        }


        $untracked_customers_count = TrackingData::getInstance()->getUntrackedCustomersCount() + count(get_option('woo_ml_non_synced_customer', []));

        if ($untracked_customers_count > 0) {
            return json_encode(array_merge([
                'allDone' => false,
            ], $this->syncUntrackedCustomers()));
        }
        update_option('woo_ml_new_sync', 1);
        return json_encode([
            'allDone' => true,
            'completed' => 0,
        ]);
    }

    /**
     * Bulk synchronize untracked customers
     * woo_ml_sync_untracked_customers
     * @return array
     * @throws Exception
     */
    public function syncUntrackedCustomers()
    {

        set_time_limit(600);

        $message = 'Oops, we did not manage to sync all of your customers, please try again.';

        try {
            $fieldsVerified = get_option('woo_ml_verify_fields', false);
            if (!$fieldsVerified) {
                MailerLiteSettings::getInstance()->verify_custom_fields();
                update_option('woo_ml_verify_fields', true);
            }
            $trackingData = TrackingData::getInstance();

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            $shop = get_option('woo_ml_shop_id', false);

            if ($shop === false && $mailerliteClient->getApiType() == ApiType::CURRENT) {

                return [
                    'error' => true,
                    'message' => 'Shop is not activated.'
                ];
            }
            $lastSyncedCustomer = get_option('woo_ml_last_synced_customer', 0);
            $customersToSync = $trackingData->getCustomersToSync($lastSyncedCustomer);
            $syncCount = 0;
            $syncCustomers = [];
            $syncFilter = [];
            if(count($customersToSync)) {
                foreach($customersToSync as $customer) {

                    $order = wc_get_order((int) $customer['last_order_id'] );


                    if ( is_a( $order, 'WC_Order_Refund' ) ) {
                        $order = wc_get_order( $order->get_parent_id() );
                    }
                    if ($order) {
                        $customer['phone'] = $order->get_billing_phone();
                        $customer['company'] = $order->get_billing_company();
                    }



                    if (($customer['email'] == null || $customer['email'] == '') || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
                        update_option('woo_ml_last_synced_customer', $customer['resource_id']);
                        continue;
                    }
                    $syncFilter[$customer['resource_id']] = $customer['create_subscriber'];
                    $syncCustomer = [
                        'resource_id' => $customer['resource_id'],
                        'email' => $customer['email'],
                        'create_subscriber' => (bool)$customer['create_subscriber'],
                        'accepts_marketing' => (bool)$customer['create_subscriber'],
                        'orders_count' => $customer['orders_count'],
                        'total_spent' => $customer['total_spent'],
                        'last_order_id' => $customer['last_order_id'],
                        'last_order' => $customer['last_order'],
                    ];

                    $syncCustomer['subscriber_fields'] = MailerLiteSettings::getInstance()->getSubscriberFieldsFromCustomerData($customer);

                    update_option('woo_ml_last_synced_customer', $customer['resource_id']);
                    $syncCustomers[] = $syncCustomer;
                }
            }

            if (count($syncCustomers) > 0) {

                if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {

                    foreach ($syncCustomers as $syncCustomer) {

                        $subscriber_fields = $syncCustomer['subscriber_fields'];

                        $subscriber_fields['woo_orders_count'] = $syncCustomer['orders_count'];
                        $subscriber_fields['woo_total_spent'] = $syncCustomer['total_spent'];
                        $subscriber_fields['woo_last_order'] = $syncCustomer['last_order'];
                        $subscriber_fields['woo_last_order_id'] = $syncCustomer['last_order_id'];

                        $store = home_url();

                        $subscriber_updated = $mailerliteClient->syncCustomer($store, $syncCustomer['resource_id'],
                            $syncCustomer['email'], $subscriber_fields);

                        if (isset($subscriber_updated->updated_subscriber)) {
                            // used for updating order meta
                        }

                        $syncCount++;
                        update_option('woo_ml_last_synced_customer', $syncCustomer['resource_id']);

                    }
                }

                if ($mailerliteClient->getApiType() == ApiType::CURRENT) {

                    $result = $mailerliteClient->importCustomers($shop, $syncCustomers);
                    if ($mailerliteClient->responseCode() !== 200) {

                        delete_option('woo_ml_verify_fields');
                        $errorMsg = json_decode($mailerliteClient->getResponseBody());
                        $message = 'Oops, we did not manage to sync all of your customers, please try again. (' . $mailerliteClient->responseCode() . ')';

                        if ($mailerliteClient->responseCode() == 422 && isset($errorMsg->message)) {

                            $message = $errorMsg->message;
                        }

                        if ($mailerliteClient->responseCode() == 413 && isset($errorMsg->message)) {
                            if (get_option('woo_ml_wizard_setup', 0) == 1) {
                                return [
                                    'allDone' => true,
                                ];
                            }
                            $message = $errorMsg->message;
                        }

                        return [
                            'error' => true,
                            'code' => $mailerliteClient->responseCode(),
                            'message' => $mailerliteClient->getResponseBody()
                        ];
                    } else {
                        update_option('woo_ml_last_synced_customer', end($result)->resource_id ?? get_option('woo_ml_last_synced_customer', 0));

                        if (count($result) < count($syncCustomers)) {
                            $nonSyncedCustomers = [];
                            $result = array_diff(array_keys($syncFilter), array_column($result, 'resource_id'));
                            foreach ($result as $key) {
                                if ($syncFilter[$key] == 1) {
                                    $nonSyncedCustomers[] = $key;
                                }
                            }
                            if (!empty($nonSyncedCustomers)) {
                                update_option('woo_ml_non_synced_customer', $nonSyncedCustomers);
                            }
                            if (get_option('woo_ml_wizard_setup', 0) == 1) {
                                return [
                                    'allDone' => true,
                                ];
                            }
                            return [
                                'error' => true,
                                'code' => 413,
                                'message' => true
                            ];
                        }
                    }

                    $syncCount += count($result);
                }

            }
            delete_option('woo_ml_verify_fields');
            return [
                'completed' => $syncCount,
                'data' => $syncCustomers
            ];
        } catch (\Exception $e) {
            delete_option('woo_ml_verify_fields');
            return [
                'error' => true,
                'message' => $message
            ];
        }
    }

    /**
     * Bulk synchronize untracked categories
     * woo_ml_sync_untracked_categories
     * @return array
     */
    public function syncUntrackedCategories()
    {

        set_time_limit(600);

        try {

            $syncCount = 0;

            $checkCategories = TrackingData::getInstance()->getUntrackedCategories();

            $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

            if (is_array($checkCategories) && sizeof($checkCategories) > 0) {

                $shop = get_option('woo_ml_shop_id', false);

                if ($shop === false) {

                    return [
                        'error' => true,
                        'message' => 'Shop is not activated.'
                    ];
                }

                $importCategories = [];

                foreach ($checkCategories as $category) {

                    if (!isset($category->term_id)) {
                        continue;
                    }

                    $importCategories[] = [
                        'name' => $category->name,
                        'resource_id' => (string)$category->term_id
                    ];
                }

                if (count($importCategories) > 0) {

                    $result = $mailerliteClient->importCategories($shop, $importCategories);

                    if ($mailerliteClient->responseCode() !== 200) {

                        return [
                            'error' => true,
                        ];
                    }

                    foreach ($result as $category) {

                        MailerLiteSettings::getInstance()->completeCategoryTracking($category->resource_id,
                            $category->id);
                        $syncCount++;
                    }
                }
            }

            return [
                'completed' => $syncCount,
            ];
        } catch (\Exception $e) {

            return [
                'error' => true,
            ];
        }
    }

    /**
     * Call to handle product, order and customer delete event
     * mailerlite_wp_sync_post_delete
     */
    public function syncPostDelete($post_id)
    {

        $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

        if ($mailerliteClient->getApiType() === ApiType::CURRENT) {

            $shop = get_option('woo_ml_shop_id', false);

            if ($shop === false) {

                return false;
            }

            if (get_post_type($post_id) === 'product') {
                $mailerliteClient->deleteProduct($shop, $post_id);
            }
        }

        if (class_exists('OrderUtil') && OrderUtil::get_order_type($post_id) === 'shop_order') {
            OrderProcess::getInstance()->cancelOrder($post_id);
        }
    }
}