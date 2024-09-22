<?php

use MailerLite\Includes\Classes\Process\CartProcess;
use MailerLite\Includes\Classes\Settings\ApiSettings;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;
use MailerLite\Includes\Classes\Settings\ResetSettings;
use MailerLite\Includes\Classes\Settings\ShopSettings;
use MailerLite\Includes\Classes\Settings\SynchronizeSettings;
use MailerLite\Includes\Shared\Api\ApiType;
use MailerLite\Includes\Shared\Api\PlatformAPI;
/**
 * Ajax
 */

// Exit if accessed directly
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Refresh groups
 */
function woo_ml_admin_ajax_refresh_groups()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $mailerLiteSettings = MailerLiteSettings::getInstance();
        wp_send_json([
            'groups'  => $mailerLiteSettings->getGroupOptions()
        ]);
    }
}

add_action('wp_ajax_nopriv_post_woo_ml_refresh_groups', 'woo_ml_admin_ajax_refresh_groups');
add_action('wp_ajax_post_woo_ml_refresh_groups', 'woo_ml_admin_ajax_refresh_groups');

/**
 * Sync Customers
 */
function woo_ml_admin_ajax_sync_untracked_resources()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {

        $response = false;

        try {
            $shop_synced = SynchronizeSettings::getInstance()->syncUntrackedResources();
            if (is_bool($shop_synced)) {
                $response = true;
            } else {
                $response = $shop_synced;
            }
            $responseObj = json_decode($response);
            if(isset($responseObj->allDone) && $responseObj->allDone) {
                if((int)get_option('woo_ml_wizard_setup', 0) !== 2) {
                    update_option('woo_ml_wizard_setup', 2);
                    woo_ml_setup_integration();
                }
            }
            echo $response;
        } catch (\Exception $e) {
            return true;
        }

    }
    exit;
}

add_action('wp_ajax_post_woo_ml_sync_untracked_resources', 'woo_ml_admin_ajax_sync_untracked_resources');

/**
 * Is called when the user presses the Reset resources sync button in the plugin admin settings
 */
function woo_ml_reset_resources_sync()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if (isset($_POST['cancelSync'])) {

        update_option('woo_ml_wizard_setup', 1);
        $settings = get_option('woocommerce_mailerlite_settings', []);
        if(isset($settings['consumer_key'])) {
            unset($settings['consumer_key']);
        }
        if(isset($settings['consumer_secret'])) {
            unset($settings['consumer_secret']);
        }
        update_option('woocommerce_mailerlite_settings',
            apply_filters('woocommerce_settings_api_sanitized_fields_mailerlite', $settings), 'yes');
    }
    ResetSettings::getInstance()->resetTrackedResources();
}

add_action('wp_ajax_post_woo_ml_reset_resources_sync', 'woo_ml_reset_resources_sync');

function woo_ml_email_cookie()
{

    if (defined('DOING_AJAX') && DOING_AJAX) {
        try {
            $email     = $_POST['email'] ?? null;
            $subscribe = isset($_POST['signup']) && 'true' == $_POST['signup'];
            $language  = $_POST['language'] ?? '';

            $subscriber_fields = [];

            $first_name = $_POST['first_name'] ?? '';
            $last_name  = $_POST['last_name'] ?? '';

            if ( ! empty($first_name)) {
                $subscriber_fields['name'] = $first_name;
            }

            if ( ! empty($last_name)) {
                $subscriber_fields['last_name'] = $last_name;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // set accepts marketing cookie only when on checkout
                if (isset($_POST['signup'])) {
                    @setcookie('mailerlite_accepts_marketing', $subscribe, time() + 172800, '/');
                }

                //setting email cookie and cart token for two days
                @setcookie('mailerlite_checkout_email', $email, time() + 172800, '/');
                // get token cookie from request so it remains consistent

                if (isset($_POST['cookie_mailerlite_checkout_token']) && ($_POST['cookie_mailerlite_checkout_token'] !== '')) {
                    $_COOKIE['mailerlite_checkout_token'] = $_POST['cookie_mailerlite_checkout_token'];
                } else {
                    if (!isset($_SESSION['mailerlite_checkout_token'])) {
                        $_SESSION['mailerlite_checkout_token'] = floor(microtime(true) * 1000);
                    }
                    @setcookie('mailerlite_checkout_token', $_SESSION['mailerlite_checkout_token'], time() + 172800, '/');
                    $_COOKIE['mailerlite_checkout_token'] = $_SESSION['mailerlite_checkout_token'];
                }

                if (get_option('mailerlite_disable_checkout_sync') == false) {
                    CartProcess::getInstance()->sendCart($email, $subscribe, $language, $subscriber_fields);
                }
            }
        } catch (\Exception $e) {
            return true;
        }
    }
    exit;
}

add_action('wp_ajax_nopriv_post_woo_ml_email_cookie', 'woo_ml_email_cookie');
add_action('wp_ajax_post_woo_ml_email_cookie', 'woo_ml_email_cookie');

function woo_ml_validate_key()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false ) ) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        if ( ! empty($_POST['key'])) {
            ApiSettings::getInstance()->validateApiKey($_POST['key']);
        }
    }
    exit;
}

add_action('wp_ajax_nopriv_post_woo_ml_validate_key', 'woo_ml_validate_key');
add_action('wp_ajax_post_woo_ml_validate_key', 'woo_ml_validate_key');

/**
 * Update hide checkbox setting
 */
function woo_ml_admin_ajax_update_hide_checkbox()
{

    if ( ! is_admin()) {
        die();
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {

        if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'ml-block-settings-update')) {

            if (isset($_POST['hidden'])) {

                $isHidden = filter_var($_POST['hidden'], FILTER_VALIDATE_BOOLEAN);

                MailerLiteSettings::getInstance()->setOption('checkout_hide', $isHidden ? 'yes' : 'no');

                wp_send_json_success([
                    'hidden' => $isHidden,
                ], 200);
            }
        }

        die();
    }
}

add_action('wp_ajax_woo_ml_admin_ajax_update_hide_checkbox', 'woo_ml_admin_ajax_update_hide_checkbox');

/**
 * Update preselect checkbox setting
 */
function woo_ml_admin_ajax_update_preselect_checkbox()
{

    if ( ! is_admin()) {
        die();
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {

        if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'ml-block-settings-update')) {

            if (isset($_POST['preselect'])) {

                $isPreselect = filter_var($_POST['preselect'], FILTER_VALIDATE_BOOLEAN);

                MailerLiteSettings::getInstance()->setOption('checkout_preselect', $isPreselect ? 'yes' : 'no');

                wp_send_json_success([
                    'preselect' => $isPreselect,
                ], 200);
            }
        }

        die();
    }
}

/**
 * Refresh groups
 */
function woo_ml_admin_create_group()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        if(!isset($_POST['createGroup'])) {
            return false;
        }
        $mailerLiteSettings = MailerLiteSettings::getInstance();
        if(in_array($_POST['createGroup'], $mailerLiteSettings->getGroupOptions())) {
            wp_send_json([
                'error' => 'Group name already exists'
            ]);
        }
        wp_send_json(MailerLiteSettings::getInstance()->createGroup($_POST['createGroup']));
    }
}

function woo_ml_admin_get_debug_log()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    $errorPath = ini_get('error_log');
    $lines = '';
    if(!empty($errorPath)) {
        $lines = `tail -500 {$errorPath}`;
    }
    if(!empty($lines)) {
        wp_send_json(['success' => true, 'log' => nl2br($lines)]);
    } else {
        wp_send_json(['success' => false, 'log' => [], 'message' => "Couldn't fetch error log"]);

    }
}

function woo_ml_reset_integration()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    delete_option('woo_ml_shop_id');
    delete_option('woo_ml_wizard_setup');
    delete_option('woo_ml_key');
    delete_option('ml_shop_not_active');

    ShopSettings::getInstance()->initSyncFields();
}

function woo_ml_admin_save_group()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    $mailerliteClient = new PlatformAPI(MAILERLITE_WP_API_KEY);

    $data = [
        'group' => $_POST['group']
    ];
    if ($mailerliteClient->getApiType() == ApiType::CLASSIC) {

        if (!empty($_POST['consumer_key']) && !empty($_POST['consumer_secret'])) {
            $data['consumer_key'] = $_POST['consumer_key'];
            $data['consumer_secret'] = $_POST['consumer_secret'];
        } else {
            wp_send_json([
                'error' => true,
                'message' => 'Consumer key or secret missing!'
            ]);
        }
    }

    $settings = array_merge(get_option('woocommerce_mailerlite_settings'), $data);
    update_option('woocommerce_mailerlite_settings',
        apply_filters('woocommerce_settings_api_sanitized_fields_mailerlite', $settings), 'yes');
    if(get_transient('invalid_consumer_keys')) {
        wp_send_json([
            'error' => true,
            'message' => get_transient('invalid_consumer_keys')
        ]);
        delete_transient('invalid_consumer_keys');
    }
    wp_send_json([
        'error' => false,
        'message' => 'Group Saved successfully'
    ]);
}

function woo_ml_skip_resource_sync()
{
    if (!check_ajax_referer( 'woo_ml_post_nonce', 'nonce', false)) {
        wp_send_json_error( 'Invalid security token sent.' );
        wp_die();
    }
    if((int)get_option('woo_ml_wizard_setup', 0) !== 2) {
        MailerLiteSettings::getInstance()->pluginSettingsUpdate();
        update_option('woo_ml_wizard_setup', 2);
        woo_ml_setup_integration();
    }
}

add_action('wp_ajax_woo_ml_admin_ajax_update_preselect_checkbox', 'woo_ml_admin_ajax_update_preselect_checkbox');

add_action('wp_ajax_woo_ml_create_group', 'woo_ml_admin_create_group');
add_action('wp_ajax_woo_ml_get_debug_log', 'woo_ml_admin_get_debug_log');
add_action('wp_ajax_woo_ml_reset_integration', 'woo_ml_reset_integration');
add_action('wp_ajax_woo_ml_save_group', 'woo_ml_admin_save_group');
add_action('wp_ajax_woo_ml_skip_resource_sync', 'woo_ml_skip_resource_sync');
