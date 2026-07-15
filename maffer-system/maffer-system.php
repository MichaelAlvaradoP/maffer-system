<?php
/**
 * Plugin Name:       Sistema Maffer
 * Plugin URI:        https://servicioalimentacionmaffer.cl
 * Description:       Sistema de registro y reserva de alimentación (cenas) para colaboradores. Modulo centralizado y modular migrado desde WPCode.
 * Version:           1.1.0
 * Requires PHP:      7.4
 * Requires WP:       5.8
 * Author:            Michael Alvarado
 * Author URI:        https://github.com/MichaelAlvaradoP
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub URI:        https://github.com/MichaelAlvaradoP/maffer-system
 * Text Domain:       maffer-system
 *
 * @package   MafferSystem
 * @version   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MAFFER_SYSTEM_VERSION', '1.1.0' );
define( 'MAFFER_SYSTEM_DIR', plugin_dir_path( __FILE__ ) );

// ── Includes ─────────────────────────────────────────────────────
// Helpers first — other modules depend on these functions.
require_once MAFFER_SYSTEM_DIR . 'includes/helpers.php';
require_once MAFFER_SYSTEM_DIR . 'includes/excel.php';    // WO-003 — XLSX generation (maffer_generar_xlsx)
require_once MAFFER_SYSTEM_DIR . 'includes/email.php';    // WO-003 — Email SUMMARY + DETAILED handlers
require_once MAFFER_SYSTEM_DIR . 'includes/activator.php';
require_once MAFFER_SYSTEM_DIR . 'includes/deactivator.php';

// ── Modules ──────────────────────────────────────────────────────
require_once MAFFER_SYSTEM_DIR . 'modules/panel-core.php';  // WO-002 — Roles, menu, redirects, cron, POST handlers
require_once MAFFER_SYSTEM_DIR . 'modules/panel-ajax.php';  // WO-005 — Panel 7B AJAX (soft-delete)
require_once MAFFER_SYSTEM_DIR . 'modules/panel-csv.php';   // WO-003 — XLSX download (admin_post_maffer_descargar_excel)
require_once MAFFER_SYSTEM_DIR . 'modules/ajax-rut.php';    // WO-004 — AJAX RUT validation
require_once MAFFER_SYSTEM_DIR . 'modules/form.php';        // WO-004 — Form shortcode & AJAX submit
require_once MAFFER_SYSTEM_DIR . 'modules/login.php';       // WO-007 — Login visual customizations
require_once MAFFER_SYSTEM_DIR . 'modules/page-404.php';    // WO-007 — Custom 404 page
require_once MAFFER_SYSTEM_DIR . 'modules/panel-render.php'; // WO-006 — Panel 7E Render (dashboard UI)

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
    $maffer_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/MichaelAlvaradoP/maffer-system/',
        __FILE__,
        'maffer-system'
    );
    // Descargar el zip adjunto al Release (estructura maffer-system/ limpia),
    // no el zipball del codigo fuente que trae el repo completo.
    $maffer_update_checker->getVcsApi()->enableReleaseAssets();
}

/*
 * ── Module Load Order (completed WOs) ──────────────────────────
 *
 * Order matters: includes/excel.php and includes/email.php must load
 * BEFORE modules/panel-core.php because the cron handler and toggle
 * handler call maffer_enviar_resumen() from email.php.
 *
 * WO-002: modules/panel-core.php     — Roles, menu, redirects, cron, POST handlers
 * WO-003: includes/excel.php         — XLSX generation (maffer_generar_xlsx)
 * WO-003: includes/email.php         — Email SUMMARY + DETAILED handlers
 * WO-003: modules/panel-csv.php      — XLSX download (admin-post)
 * WO-004: modules/ajax-rut.php       — AJAX RUT validation
 * WO-004: modules/form.php           — Form shortcode & AJAX submit
 * WO-005: modules/panel-ajax.php     — Panel 7B AJAX (soft-delete)
 * WO-006: modules/panel-render.php   — Panel 7E Render (dashboard UI)
 * WO-007: modules/login.php          — Login visual customizations
 * WO-007: modules/page-404.php       — Custom 404 page
 *
 * See README.md for the module migration plan.
 */
