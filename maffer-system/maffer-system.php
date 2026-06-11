<?php
/**
 * Plugin Name:       Maffer System
 * Plugin URI:        https://github.com/maffer/maffer-system
 * Description:       Sistema de registro de alimentación para Hotel Maffer. Reemplaza los 9 snippets WPCode con una estructura modular.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Requires WP:       5.8
 * Author:            Michael Alvarado
 * Author URI:        https://github.com/chest
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub URI:        https://github.com/maffer/maffer-system
 * Text Domain:       maffer-system
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MAFFER_SYSTEM_VERSION', '1.0.0' );
define( 'MAFFER_SYSTEM_DIR', plugin_dir_path( __FILE__ ) );

// ── Includes ─────────────────────────────────────────────────────
// Helpers first — other modules depend on these functions.
require_once MAFFER_SYSTEM_DIR . 'includes/helpers.php';
require_once MAFFER_SYSTEM_DIR . 'includes/activator.php';
require_once MAFFER_SYSTEM_DIR . 'includes/deactivator.php';

// ── Modules ──────────────────────────────────────────────────────
require_once MAFFER_SYSTEM_DIR . 'modules/panel-core.php';  // WO-002 — Roles, menu, redirects, cron, POST handlers
require_once MAFFER_SYSTEM_DIR . 'modules/panel-ajax.php';  // WO-005 — Panel 7B AJAX (soft-delete)

// ── Activation / Deactivation Hooks ─────────────────────────────
register_activation_hook( __FILE__, array( 'Maffer_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Maffer_Deactivator', 'deactivate' ) );

// ── Cron Schedule (60-second interval) ──────────────────────────
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['maffer_minuto'] ) ) {
        $schedules['maffer_minuto'] = array(
            'interval' => 60,
            'display'  => 'Cada minuto Maffer',
        );
    }
    return $schedules;
} );

// ── Plugin Update Checker (GitHub Releases) ─────────────────────
if ( file_exists( MAFFER_SYSTEM_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once MAFFER_SYSTEM_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
    \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/maffer/maffer-system/',
        __FILE__,
        'maffer-system'
    );
}

/*
 * ── Module Load Order (completed WOs) ──────────────────────────
 *
 * WO-002: modules/panel-core.php   — Roles, menu, redirects, cron, POST handlers, XLSX, email
 *
 * ── Future Modules (to be migrated in pending WOs) ────────────
 *
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-ajax.php';         // WO-003 — AJAX handlers
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-csv.php';          // WO-004 — CSV/Excel export
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-form.php';         // WO-005 — Shortcode form
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-render.php';       // WO-006 — Panel render
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-email.php';        // WO-007 — Email handling
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-rut-ajax.php';     // WO-008 — RUT validation AJAX
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-login-visual.php'; // WO-009 — Login customization
 * require_once MAFFER_SYSTEM_DIR . 'modules/module-404.php';          // WO-010 — 404 page
 *
 * See README.md for the module migration plan.
 */
