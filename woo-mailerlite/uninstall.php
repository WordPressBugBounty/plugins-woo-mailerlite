<?php
/**
 * Fired when the plugin is uninstalled (deleted), not merely deactivated.
 *
 * This is the only place that is allowed to destroy plugin data: it drops the
 * plugin tables and removes all stored options. Deactivation must preserve
 * settings and carts (see WooMailerLiteDeActivator).
 *
 * @package WooMailerlite
 */

// If uninstall is not called from WordPress, abort.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Load the autoloader and helpers (db(), migrations, options, cache) without
// booting the full plugin.
require_once __DIR__ . '/bootstrap.php';

// Stop any scheduled background jobs.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions(WooMailerLiteCartCleanupJob::class);
}
WooMailerLiteCache::delete('cart_cleanup_scheduled');

// Drop plugin tables and remove all stored options.
WooMailerLiteMigration::rollback();
WooMailerLiteOptions::deleteAll();
delete_option('woo_mailerlite');
