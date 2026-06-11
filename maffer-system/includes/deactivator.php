<?php
/**
 * Maffer System — Deactivation Handler
 *
 * Runs on plugin deactivation:
 *  - Clears scheduled cron jobs
 *  - Flushes rewrite rules
 *
 * NOTE: Roles, options, and database tables are NOT removed.
 * Rollback = reactivate the original WPCode snippets.
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maffer_Deactivator
 */
class Maffer_Deactivator {

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        self::clear_cron();
        flush_rewrite_rules();
    }

    /**
     * Clear the Maffer check schedule cron job.
     */
    private static function clear_cron() {
        wp_clear_scheduled_hook( 'maffer_check_schedule' );
    }
}
