<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use MailerLite\Includes\Classes\Process\OrderProcess;

/**
 * Register meta boxes
 */
function woo_ml_register_meta_boxes()
{

    $screen = 'shop_order';
    if (class_exists('CustomOrdersTableController') && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()) {
        $screen = wc_get_page_screen_id( 'shop-order' );
    }
    // if hpos is enabled
    if (get_current_screen()->id == 'woocommerce_page_wc-orders') {
        $screen = 'woocommerce_page_wc-orders';
    }

    add_meta_box(
        'woo-ml-order',
        '<span class="woo-ml-icon"></span>&nbsp;&nbsp;' . __('MailerLite', 'woo-mailerlite'),
        'woo_ml_order_meta_box_output',
        $screen,
        'side',
        'low'
    );
}

add_action('add_meta_boxes', 'woo_ml_register_meta_boxes');

/**
 * Outputs the content of the meta box
 */
function woo_ml_order_meta_box_output($post)
{

    wp_nonce_field(basename(__FILE__), 'woo_ml_order_meta_box_nonce');

    $order = ($post instanceof WP_Post) ? wc_get_order($post->ID) : $post;

    $order_id = $order->get_id();

    $icon_yes = '<span class="dashicons dashicons-yes" style="color: #00A153;right: 7px;position: absolute;"></span>';
    $icon_no  = '<span class="dashicons dashicons-no-alt" style="color: #a00;right: 7px;position: absolute;"></span>';
    ?>
    <?php $subscribe = OrderProcess::getInstance()->orderCustomerSubscribe($order_id); ?>
    <p>
        <?php echo _e('Signed up for emails',
                'woo-mailerlite') . wc_help_tip("Customer ticked the Subscribe box at the checkout stage to receive newsletters."); ?><?php echo ($subscribe) ? $icon_yes : $icon_no; ?>
    </p>
    <?php $subscribed = OrderProcess::getInstance()->checkCustomerSubscribed($order_id); ?>
    <p>
        <?php echo _e('Subscribed to email list',
                'woo-mailerlite') . wc_help_tip("Customer was successfully added to the subscriber list in MailerLite."); ?><?php echo ($subscribed) ? $icon_yes : $icon_no; ?>
    </p>
    <?php $already_subscribed = OrderProcess::getInstance()->orderCustomerAlreadySubscribed($order_id); ?>
    <p>
        <?php echo _e('Previously subscribed',
                'woo-mailerlite') . wc_help_tip("Customer was already an existing subscriber."); ?><?php echo ($already_subscribed) ? $icon_yes : $icon_no; ?>
    </p>
    <?php $subscriber_updated = OrderProcess::getInstance()->checkOrderSubscriberUpdated($order_id); ?>
    <p>
        <?php echo _e('Updated subscriber data',
                'woo-mailerlite') . wc_help_tip("Checkout data including purchases and contact information was successfully updated in MailerLite."); ?><?php echo ($subscriber_updated) ? $icon_yes : $icon_no; ?>
    </p>
    <?php $order_data_submitted = OrderProcess::getInstance()->orderDataSubmitted($order_id); ?>
    <p>
        <?php echo _e('Order data submitted',
                'woo-mailerlite') . wc_help_tip("Order data is uploaded to MailerLite once the payment is processed."); ?><?php echo ($order_data_submitted) ? $icon_yes : $icon_no; ?>
    </p>
    <?php $order_tracking_completed = OrderProcess::getInstance()->checkOrderTracking($order_id); ?>
    <p>
        <?php echo _e('Order tracking completed',
                'woo-mailerlite') . wc_help_tip("All stages of the tracking process on this order have been completed."); ?><?php echo ($order_tracking_completed) ? $icon_yes : $icon_no; ?>
    </p>
    <?php
}

/**
 * Saves the custom meta input
 */
function woo_ml_order_meta_box_save($post_id)
{

    // Checks save status
    $is_autosave    = wp_is_post_autosave($post_id);
    $is_revision    = wp_is_post_revision($post_id);
    $is_valid_nonce = (isset($_POST['woo_ml_order_meta_box_nonce']) && wp_verify_nonce($_POST['woo_ml_order_meta_box_nonce'],
            basename(__FILE__))) ? 'true' : 'false';

    // Exits script depending on save status
    if ($is_autosave || $is_revision || ! $is_valid_nonce) {
        return;
    }
}

add_action('save_post', 'woo_ml_order_meta_box_save');