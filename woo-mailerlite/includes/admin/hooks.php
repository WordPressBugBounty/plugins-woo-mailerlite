<?php

use MailerLite\Includes\Classes\Process\ProductProcess;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Data\TrackingData;
use MailerLite\Includes\Classes\Settings\ShopSettings;

/**
 * Handle admin notices
 */
function woo_ml_admin_notices()
{

    $notices = array();

    // Actions
    $admin_notice = (isset($_GET['woo_ml_admin_notice'])) ? $_GET['woo_ml_admin_notice'] : null;

    // Debug
    /*
    $notices[] = array(
        'type' => 'warning',
        'dismiss' => false,
        'force' => false,
        'message' => __('Plugin settings has been successfully reset.', 'woo-mailerlite')
    );
    */

    // Integration setup (in case setup was not yet completed via settings handling)
    if (MailerLiteSettings::getInstance()->isActive() && ! woo_ml_integration_setup_completed()) {
        $notices[] = array(
            'type'    => 'warning',
            'dismiss' => false,
            'force'   => true,
            'message' => sprintf(wp_kses(__('In order to complete our integration setup, please <a href="%s">click here</a>.',
                'woo-mailerlite'), array('a' => array('href' => array()))),
                esc_url(TrackingData::getInstance()->getCompleteIntegrationSetupUrl()))
        );
    }

    //message to be displayed for users who are only using the old functionalities just now
    if (MailerLiteSettings::getInstance()->isActive() && woo_ml_shop_not_active()) {
        $notices[] = array(
            'type'    => 'error',
            'dismiss' => false,
            'force'   => true,
            'message' => __('Your shop is currently not active. Please reconnect to MailerLite.', 'woo-mailerlite')
        );
    }

    // Integration setup completed
    if ('integration_setup_completed' === $admin_notice) {
        $notices[] = array(
            'type'    => 'success',
            'dismiss' => true,
            'force'   => false,
            'message' => __('Integration setup has been successfully completed.', 'woo-mailerlite')
        );
    }

    if (MailerLiteSettings::getInstance()->syncFailed()) {
        $notices[] = array(
            'type'    => 'error',
            'dismiss' => true,
            'force'   => true,
            'message' => __('We did not manage to sync all of your shop resources. Please try again.', 'woo-mailerlite')
        );
    }

    // Hook
    $notices = apply_filters('woo_ml_admin_notices', $notices);

    $is_plugin_area = true; // Maybe add a check here later

    // Output messages
    if (sizeof($notices) > 0) {
        foreach ($notices as $notice_id => $notice) {

            // Maybe showing the notice on plugin related admin pages only
            if (isset($notice['force']) && false === $notice['force'] && ! $is_plugin_area) {
                continue;
            }

            $classes = 'woo-ml-notice notice';

            if ( ! empty($notice['type'])) {
                $classes .= ' notice-' . $notice['type'];
            }

            if (isset($notice['dismiss']) && true === $notice['dismiss']) {
                $classes .= ' is-dismissible';
            }

            ?>
            <div id="woo-ml-notice-<?php echo ( ! empty($notice['id'])) ? $notice['id'] : $notice_id; ?>"
                 class="<?php echo $classes; ?>">
                <p><strong>WooCommerce MailerLite:</strong> <?php echo $notice['message']; ?></p>
            </div>
            <?php
        }
    }
}

add_action('admin_notices', 'woo_ml_admin_notices');

/**
 * Migration after updates
 *
 * @access      private
 * @return      void
 */
function woo_ml_migrations()
{

    // get last installed version
    $current_version = get_option('woo_ml_version', false);

    if ($current_version !== WOO_MAILERLITE_VER) {

        if (version_compare($current_version, '1.5.8', '<')) {

            /**
             * start migration to reset ml_data table data_value
             * to store ignored products instead of all products
             */

            $settings = get_option('woocommerce_mailerlite_settings', array());

            if ( ! isset($settings['ignore_product_list'])) {

                $settings['ignore_product_list'] = [];
            }

            $products = [];

            if ( ! empty($settings['ignore_product_list'])) {

                $_pf = new WC_Product_Factory();

                foreach ($settings['ignore_product_list'] as $product_id) {

                    $product = $_pf->get_product($product_id);

                    if ($product) {

                        $products[$product_id] = $product->get_title();
                    }
                }
            }

            if (TrackingData::getInstance()->updateData($products) !== false) {
                add_option('woo_ml_version', WOO_MAILERLITE_VER);
            }
        }

        if (version_compare($current_version, '2.1.4', '<')) {

            /**
             * start migration to set sync fields with all fields preselected
             */

            ShopSettings::getInstance()->initSyncFields();
        }

        update_option('woo_ml_version', WOO_MAILERLITE_VER);
    }
}

/**
 * Handle admin actions
 */
function woo_ml_admin_actions()
{

    woo_ml_migrations();

    if ( ! isset($_GET['woo_ml_action'])) {
        return;
    }

    // Handle admin actions here
    if ('setup_integration' === $_GET['woo_ml_action']) {
        // Setup integration
        woo_ml_setup_integration();
        // Afterwards redirect to settings and show success notice
        wp_redirect(add_query_arg('woo_ml_admin_notice', 'integration_setup_completed',
            TrackingData::getInstance()->getSettingsPageUrl()));
        exit;
    }
}

add_action('admin_init', 'woo_ml_admin_actions');

/**
 * Add checkbox element to bulk and quick edit menu on a product
 */
function woo_ml_bulk_edit_quick_edit()
{

    echo '<div class="inline-edit-group">';
    woocommerce_wp_checkbox(array(
        'id'          => 'ml_ignore_product',
        'label'       => __('MailerLite e-commerce automations', 'woo-mailerlite'),
        'description' => __('Ignore product', 'woo-mailerlite'),
        'value'       => '',
        'desc_tip'    => false
    ));
    echo '</div>';
}

add_action('woocommerce_product_quick_edit_end', 'woo_ml_bulk_edit_quick_edit', 99);
add_action('woocommerce_product_bulk_edit_end', 'woo_ml_bulk_edit_quick_edit', 99);

/**
 * Populate MailerLite ignore product checkbox
 */
function woo_ml_product_custom_column($column, $post_id)
{

    $ignore_list = ProductProcess::getInstance()->getIgnoredProductList();

    switch ($column) {
        case 'name' :
            ?>
            <div class="hidden ml_ignore_product_inline" id="ml_ignore_product_inline_<?php echo $post_id; ?>">
                <div id="_ml_ignore_product"><?php echo array_key_exists($post_id, $ignore_list) ? 'yes' : 'no' ?></div>
            </div>
            <?php

            break;
    }
}

add_action('manage_product_posts_custom_column', 'woo_ml_product_custom_column', 99, 2);

/**
 * Handle save bulk and quick edit of a product
 */
function woo_ml_bulk_edit_quick_edit_save($post_id, $post)
{

    // If this is an autosave, skip because form was not submitted
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    if ('product' !== $post->post_type) {
        return $post_id;
    }

    $ignore_list = ProductProcess::getInstance()->getIgnoredProductList();

    if (isset($_REQUEST['ml_ignore_product'])) {

        $ignore_list[$post_id] = $post->post_title;

        MailerLiteSettings::getInstance()->ignoreProduct($post_id);
    } else {

        MailerLiteSettings::getInstance()->removeIgnoreProduct($post_id);

        if (array_key_exists($post_id, $ignore_list)) {

            $ignore_list = TrackingData::getInstance()->removeProductFromList($ignore_list, array($post_id));
        }
    }

    TrackingData::getInstance()->saveLocalIgnoreProducts($ignore_list);
}

add_action('woocommerce_product_bulk_and_quick_edit', 'woo_ml_bulk_edit_quick_edit_save', 10, 2);

/**
 * Add a product tab for MailerLite options
 */
function woo_ml_product_data_tab($product_data_tabs)
{

    $product_data_tabs['woo-ml'] = array(
        'label'    => __('MailerLite', 'woo-mailerlite'),
        'target'   => 'woo-ml-product-data',
        'priority' => 99
    );

    return $product_data_tabs;
}

add_filter('woocommerce_product_data_tabs', 'woo_ml_product_data_tab');

/**
 * Add options to MailerLite product tab
 */
function woo_ml_product_data_fields()
{

    global $post;
    ?>
    <div id="woo-ml-product-data" class="panel woocommerce_options_panel">
        <?php

        $ignore_list = ProductProcess::getInstance()->getIgnoredProductList();

        woocommerce_wp_checkbox(array(
            'id'          => 'ml_ignore_product',
            'label'       => __('Ignore Product', 'woo-mailerlite'),
            'description' => __('Select if you do not wish to trigger any e-commerce automations for this product',
                'woo-mailerlite'),
            'value'       => array_key_exists($post->ID, $ignore_list) ? 'yes' : 'no',
            'desc_tip'    => true,
        ));
        ?>
    </div>
    <?php
}

add_action('woocommerce_product_data_panels', 'woo_ml_product_data_fields');

/**
 * Handle save options in MailerLite product tab
 */
function woo_ml_product_data_save($post_id)
{

    $title = $_POST['post_title'];

    $ignore_list = ProductProcess::getInstance()->getIgnoredProductList();

    if (isset($_POST['ml_ignore_product'])) {

        $ignore_list[$post_id] = $title;

        MailerLiteSettings::getInstance()->ignoreProduct($post_id);
    } else {

        MailerLiteSettings::getInstance()->removeIgnoreProduct($post_id);

        if (array_key_exists($post_id, $ignore_list)) {

            $ignore_list = TrackingData::getInstance()->removeProductFromList($ignore_list, array($post_id));
        }
    }

    TrackingData::getInstance()->saveLocalIgnoreProducts($ignore_list);
}

add_action('woocommerce_process_product_meta', 'woo_ml_product_data_save');

/**
 * Add additional script when editing a product
 */
function woo_ml_edit_product_enqueue_admin_scripts()
{

    wp_enqueue_script('woo-ml-bulk-quick-edit', WOO_MAILERLITE_URL . 'assets/js/bq_edit.js',
        array('jquery', 'inline-edit-post'), WOO_MAILERLITE_VER, true);
}

add_action('admin_print_scripts-edit.php', 'woo_ml_edit_product_enqueue_admin_scripts');
