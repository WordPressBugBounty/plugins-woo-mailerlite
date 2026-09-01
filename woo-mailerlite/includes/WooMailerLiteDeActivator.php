<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WooMailerlite
 */
class WooMailerLiteDeActivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(WooMailerLiteCartCleanupJob::class);
        }
        WooMailerLiteCache::delete('cart_cleanup_scheduled');

        try {
           WooMailerLiteApi::client()->toggleShop(home_url(), 0);
        } catch (Throwable $th) {
            WooMailerLiteLog()->error('WooMailerLiteDeActivator::deactivate toggleShop failed', [
                'error' => $th->getMessage(),
            ]);
        }
    }

}
