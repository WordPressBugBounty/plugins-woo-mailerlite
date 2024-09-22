<?php

/**
 * Scripts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Load admin scripts
 *
 * @since       1.0.0
 * @global      string $post_type The type of post that we are editing
 * @return      void
 */
function woo_ml_admin_scripts($hook)
{
    if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'mailerlite') {
        wp_enqueue_script('woo-ml-admin-messages-script', WOO_MAILERLITE_URL . 'public/js/messages.js', WOO_MAILERLITE_VER);
        wp_enqueue_script('woo-ml-admin-script', WOO_MAILERLITE_URL . 'public/js/admin.js', ['jquery'], WOO_MAILERLITE_VER);
        wp_enqueue_script('style2-script', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
        wp_enqueue_style('woo-ml-admin-style', WOO_MAILERLITE_URL . 'public/css/admin.css', false, WOO_MAILERLITE_VER);
        wp_enqueue_style('style2-style', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_style('style2-mailerlite-style', WOO_MAILERLITE_URL . 'public/css/mailerlite-select2.css', false, WOO_MAILERLITE_VER);
    }

    // Ajax
    wp_localize_script('woo-ml-admin-script', 'woo_ml_post', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce'  => wp_create_nonce( 'woo_ml_post_nonce' )]);
}
add_action('admin_enqueue_scripts', 'woo_ml_admin_scripts', 100);

function woo_ml_public_scripts()
{

    if ( ! get_option('ml_account_authenticated') ) {

        return false;
    }

    wp_enqueue_script('woo-ml-public-script', WOO_MAILERLITE_URL . 'public/js/public.js', ['jquery'], WOO_MAILERLITE_VER);
    wp_localize_script('woo-ml-public-script', 'woo_ml_public_post', ['ajax_url' => admin_url('admin-ajax.php'), 'language' => get_locale(), 'checkbox_settings' => [
        'enabled' => MailerLite\Includes\Classes\Settings\MailerLiteSettings::getInstance()->getMlOption('checkout', 'no'),
        'label' => MailerLite\Includes\Classes\Settings\MailerLiteSettings::getInstance()->getMlOption('checkout_label'),
        'preselect' => MailerLite\Includes\Classes\Settings\MailerLiteSettings::getInstance()->getMlOption('checkout_preselect', 'no'),
        'hidden' => MailerLite\Includes\Classes\Settings\MailerLiteSettings::getInstance()->getMlOption('checkout_hide', 'no'),
    ]]);
}
add_action('wp_enqueue_scripts', 'woo_ml_public_scripts', 100);