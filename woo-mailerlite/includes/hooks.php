<?php

use MailerLite\Includes\Classes\Process\CartProcess;
use MailerLite\Includes\Classes\Process\OrderProcess;
use MailerLite\Includes\Classes\Process\OrderSyncProcess;
use MailerLite\Includes\Classes\Process\ProductProcess;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Settings\SynchronizeSettings;


/**
 * Shows the final purchase total at the bottom of the checkout page
 *
 * @return void
 * @since 1.5
 */
function woo_ml_checkout_label()
{
    if ( ! MailerLiteSettings::getInstance()->isActive()) {
        return;
    }

    $checkout = MailerLiteSettings::getInstance()->getMlOption('checkout', 'no');

    if ('yes' != $checkout) {
        return;
    }

    $group = MailerLiteSettings::getInstance()->getMlOption('group');

    if (empty($group)) {
        return;
    }

    $label     = MailerLiteSettings::getInstance()->getMlOption('checkout_label');
    $preselect = MailerLiteSettings::getInstance()->getMlOption('checkout_preselect', 'no');
    $hidden    = MailerLiteSettings::getInstance()->getMlOption('checkout_hide', 'no');

    if ('yes' === $hidden) {
        ?>
        <input name="woo_ml_subscribe" type="hidden" id="woo_ml_subscribe" value="1" checked="checked"/>
        <?php
    } else {

        woocommerce_form_field('woo_ml_subscribe', array(
            'type'  => 'checkbox',
            'label' => __($label, 'woo-mailerlite'),
            'checked' => (bool) ($_COOKIE['mailerlite_accepts_marketing'] ?? 'yes' === $preselect) ? 'checked' : ''
        ), (bool) ($_COOKIE['mailerlite_accepts_marketing'] ?? 'yes' === $preselect));
    }
}

$checkout_position      = MailerLiteSettings::getInstance()->getMlOption('checkout_position', 'checkout_billing');
$checkout_position_hook = 'woocommerce_' . $checkout_position;

if ($checkout_position !== 'checkout_billing_email') {
    add_action($checkout_position_hook, 'woo_ml_checkout_label', 20);
}

/**
 * Remove (optional) string for ML label
 *
 */
function remove_ml_checkout_optional_text($field, $key, $args, $value)
{

    if (is_checkout() && ! is_wc_endpoint_url() && strpos($field,
            'woo_ml_subscribe') !== false && get_option('ml_account_authenticated')) {

        $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
        $field    = str_replace($optional, '', $field);
    }

    return $field;
}

add_filter('woocommerce_form_field', 'remove_ml_checkout_optional_text', 10, 4);

/**
 * Maybe prepare signup
 *
 * @param $order_id
 */
function woo_ml_checkout_maybe_prepare_signup($order_id)
{

    if (isset($_POST['woo_ml_subscribe']) && '1' == $_POST['woo_ml_subscribe']) {
        OrderProcess::getInstance()->setOrderCustomerSubscribe($order_id);
    }
}

add_action('woocommerce_checkout_update_order_meta', 'woo_ml_checkout_maybe_prepare_signup');

/**
 * Process order completed (and finally paid)
 *
 * @param $order_id
 */
function woo_ml_process_order_completed($order_id)
{

    if ( ! woo_ml_integration_setup_completed()) {
        woo_ml_setup_integration();
    }

    if ( ! woo_ml_old_integration()) {
        OrderProcess::getInstance()->sendCompletedOrder($order_id);
    }

    OrderProcess::getInstance()->processOrderTracking($order_id);
}

add_action('woocommerce_order_status_completed', 'woo_ml_process_order_completed');

function woo_ml_proceed_to_checkout()
{

    if ( ! get_option('ml_account_authenticated')) {

        return false;
    }

    if ( ! woo_ml_old_integration()) {
        CartProcess::getInstance()->sendCart();
    }
}

add_action('woocommerce_add_to_cart', 'woo_ml_proceed_to_checkout');

function woo_ml_cart_action_updated($cart_updated)
{

    woo_ml_proceed_to_checkout();

    return $cart_updated;
}

add_filter('woocommerce_update_cart_action_cart_updated', 'woo_ml_cart_action_updated');
add_filter('woocommerce_cart_item_removed', 'woo_ml_cart_action_updated');

function woo_ml_order_status_change($order_id)
{
    if ( ! woo_ml_old_integration()) {
        OrderProcess::getInstance()->paymentStatusProcessing($order_id);
    }
}

add_action('woocommerce_order_status_changed', 'woo_ml_order_status_change');


function woo_ml_enqueue_styles()
{

    if ( ! get_option('ml_account_authenticated')) {

        return false;
    }

    wp_enqueue_style('related-styles', plugins_url('/../public/css/style.css', __FILE__));
}

add_action('wp_enqueue_scripts', 'woo_ml_enqueue_styles');

/**
 * WooCommerce product update
 *
 * @param $product_id
 */
function woo_ml_product_update($product_id)
{

    ProductProcess::getInstance()->wpSyncProduct($product_id);
}

add_action('woocommerce_update_product', 'woo_ml_product_update', 10, 1);

/**
 * WooCommerce order update
 *
 * @param $order_id
 */
function woo_ml_order_update($order_id, $order)
{
    $mailerliteClient = new \MailerLite\Includes\Shared\Api\PlatformAPI(MAILERLITE_WP_API_KEY);
    if ( $mailerliteClient->getApiType() == \MailerLite\Includes\Shared\Api\ApiType::CLASSIC ) {
        return OrderProcess::getInstance()->processOrderTracking($order_id);
    }
    OrderSyncProcess::getInstance()->syncOrder($order_id, $order);
}

add_action('woocommerce_saved_order_items', 'woo_ml_order_update', 10, 2);

/**
 * WooCommerce bulk order update
 *
 * @param $redirect_to
 * @param $action
 * @param $order_ids
 */
function woo_ml_bulk_order_update($redirect_to, $action, $order_ids)
{

    OrderSyncProcess::getInstance()->syncBulkOrder($action, $order_ids);

    return $redirect_to;
}

add_filter('handle_bulk_actions-edit-shop_order', 'woo_ml_bulk_order_update', 10, 3);

/**
 * WooCommerce order cancel
 *
 * @param $order_id
 */
function woo_ml_order_cancel($order_id)
{

    OrderProcess::getInstance()->cancelOrder($order_id);
}

add_action( 'woocommerce_order_status_cancelled', 'woo_ml_order_cancel', 21, 1 );

/**
 * WP Post delete
 *
 * @param $post_id
 */
function woo_ml_post_delete($post_id)
{

    SynchronizeSettings::getInstance()->syncPostDelete($post_id);
}

add_action('delete_post', 'woo_ml_post_delete', 10);

function woo_ml_customer_update($customer_id, $customer_data = '')
{

    mailerlite_wp_sync_ecommerce_customer($customer_id);
}

add_action('woocommerce_update_customer', 'woo_ml_customer_update', 99, 2);
add_action('user_register', 'woo_ml_customer_update', 10, 1);
add_action('profile_update', 'woo_ml_customer_update', 10, 2);

/**
 * WP Product Category create/update
 *
 * @param $category_id
 */
function woo_ml_product_category_sync($category_id)
{

    ProductProcess::getInstance()->syncProductCategory($category_id);
}

add_action('created_product_cat', 'woo_ml_product_category_sync', 10, 2);
add_action('edited_product_cat', 'woo_ml_product_category_sync', 10, 2);

function woo_ml_product_category_delete($term, $tt_id, $deleted_term, $object_ids)
{

    // Avoid auto save from calling API
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    ProductProcess::getInstance()->deleteProductCategory($term);
}

add_action('delete_product_cat', 'woo_ml_product_category_delete', 10, 4);

function woo_ml_billing_checkout_fields($fields) {

    if ( ! MailerLiteSettings::getInstance()->isActive()) {
        return $fields;
    }

    $checkout = MailerLiteSettings::getInstance()->getMlOption('checkout', 'no');

    if ('yes' != $checkout) {
        return $fields;
    }

    $group = MailerLiteSettings::getInstance()->getMlOption('group');

    if (empty($group)) {
        return $fields;
    }

    $checkout_position = MailerLiteSettings::getInstance()->getMlOption('checkout_position', 'checkout_billing');

    if ($checkout_position !== 'checkout_billing_email') {
        return $fields;
    }

    $label     = MailerLiteSettings::getInstance()->getMlOption('checkout_label');
    $preselect = MailerLiteSettings::getInstance()->getMlOption('checkout_preselect', 'no');
    $hidden    = MailerLiteSettings::getInstance()->getMlOption('checkout_hide', 'no');

    $new_billing_fields = [];

    foreach ($fields['billing'] as $key => $field) {
        $new_billing_fields[$key] = $field;

        // Add the custom checkbox field right after 'billing_email'.
        if ($key === 'billing_email') {

            $new_billing_fields['woo_ml_subscribe'] = [
                'type' => ($hidden === 'no') ? 'checkbox' : 'hidden',
                'default' => (bool) ($_COOKIE['mailerlite_accepts_marketing'] ?? 'yes' === $preselect),
                'required' => false,
            ];
            $new_billing_fields['woo_ml_preselect_enabled'] = [
                'type' => 'hidden',
                'default' => $preselect
            ];
            if ($hidden === 'no') {
                $new_billing_fields['woo_ml_subscribe']['label'] = __($label, 'woo-mailerlite');
            } else {
                $new_billing_fields['woo_ml_subscribe']['custom_attributes'] = [
                    'checked' => 'checked',
                ];
            }
        }
    }

    // Replace the 'billing' fields with the modified field order.
    $fields['billing'] = $new_billing_fields;

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'woo_ml_billing_checkout_fields', PHP_INT_MAX);