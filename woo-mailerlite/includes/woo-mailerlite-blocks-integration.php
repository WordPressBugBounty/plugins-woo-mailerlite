<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use MailerLite\Includes\Classes\Settings\MailerLiteSettings;

defined('ABSPATH') || exit;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WooMlBlock_Integration implements IntegrationInterface
{

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name()
    {

        return 'woo-mailerlite';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize()
    {

        $this->register_mailerlite_block_frontend_scripts();
        $this->register_mailerlite_block_editor_scripts();
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles()
    {

        return ['mailerlite-block-woo-mailerlite-block-frontend'];
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles()
    {

        return ['mailerlite-block-woo-mailerlite-block-editor'];
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data()
    {
        $mailerLiteSettings = MailerLiteSettings::getInstance();

        return [
            'MailerLiteWooActive'    => $mailerLiteSettings->isActive(),
            'MailerLiteWooLabel'     => $mailerLiteSettings->getMlOption('checkout_label'),
            'MailerLiteWooPreselect' => $mailerLiteSettings->getMlOption('checkout_preselect',
                    'no') == 'yes',
            'MailerLiteWooHidden'    => $mailerLiteSettings->getMlOption('checkout_hide', 'no') == 'yes',
            'MailerLiteNonce'        => wp_create_nonce('ml-block-settings-update'),
            'MailerLiteAdminURL'     => admin_url('admin.php?page=wc-settings&tab=integration&section=mailerlite'),
        ];
    }

    public function register_mailerlite_block_editor_scripts()
    {

        $script_path       = '/woo-ml-blocks/js/edit-block.js';
        $script_asset_path = dirname(__FILE__) . $script_path;
        $script_url        = plugins_url($script_path, __FILE__);

        $script_asset = [
            'dependencies' => [
                'wc-blocks-checkout',
                'wc-settings',
                'wp-block-editor',
                'wp-blocks',
                'wp-components',
                'wp-element',
                'wp-i18n',
            ],
            'version'      => $this->get_file_version($script_asset_path),
        ];

        wp_register_script(
            'mailerlite-block-woo-mailerlite-block-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'woo-mailerlite-block-editor',
            'woo-mailerlite',
            dirname(__FILE__) . '/../languages'
        );
    }

    public function register_mailerlite_block_frontend_scripts()
    {
        $script_path       = '/woo-ml-blocks/js/frontend-block.js';
        $script_url        = plugins_url($script_path, __FILE__);
        $script_asset_path = dirname(__FILE__) . $script_path;

        $script_asset = [
            'dependencies' => [
                'wc-blocks-checkout',
                'wc-settings',
            ],
            'version'      => $this->get_file_version($script_asset_path),
        ];

        $result = wp_register_script(
            'mailerlite-block-woo-mailerlite-block-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if ( ! $result) {

            return false;
        }

        wp_set_script_translations(
            'mailerlite-block-woo-mailerlite-block-frontend',
            'woo-mailerlite',
            dirname(__FILE__) . '/../languages'
        );

        return true;
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode.
     *
     * @param string $file Local path to the file.
     *
     * @return string The cache buster value to use for the given file.
     */
    protected function get_file_version($file)
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }

        return WOO_MAILERLITE_VER;
    }
}
