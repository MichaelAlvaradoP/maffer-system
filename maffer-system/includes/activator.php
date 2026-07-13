<?php
/**
 * Maffer System — Activation Handler
 *
 * Runs on plugin activation:
 *  - Creates/upgrades the wpig_maffer_registros table (with soft-delete)
 *  - Creates custom roles
 *  - Schedules the minute cron job
 *  - Flushes rewrite rules
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maffer_Activator
 */
class Maffer_Activator {

    /**
     * Activate the plugin.
     */
    public static function activate() {
        self::create_or_upgrade_table();
        self::create_roles();
        self::schedule_cron();
        flush_rewrite_rules();
    }

    /**
     * Create or upgrade the registros table via dbDelta.
     *
     * Schema version 1.2 adds deleted_at column (soft-delete).
     */
    private static function create_or_upgrade_table() {
        global $wpdb;

        $tabla          = $wpdb->prefix . 'maffer_registros';
        $tabla_detalles = $wpdb->prefix . 'maffer_registro_detalles';
        $charset        = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tabla} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre        VARCHAR(150)        NOT NULL,
            rut           VARCHAR(15)         NOT NULL,
            turno         VARCHAR(20)         NOT NULL DEFAULT 'almuerzo',
            menu_titulo   VARCHAR(200)        NOT NULL,
            menu_desc     TEXT,
            observaciones TEXT,
            fecha         DATE                NOT NULL,
            hora          TIME                NOT NULL,
            estado_dia    VARCHAR(20)         NOT NULL DEFAULT 'abierto',
            deleted_at    DATETIME            DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_rut_fecha (rut, fecha)
        ) {$charset};";

        $sql_detalles = "CREATE TABLE {$tabla_detalles} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            registro_id   BIGINT(20) UNSIGNED NOT NULL,
            dia_semana    VARCHAR(20)         NOT NULL,
            menu_titulo   VARCHAR(200)        NOT NULL,
            menu_desc     TEXT,
            PRIMARY KEY  (id),
            KEY idx_registro (registro_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql_detalles );

        update_option( 'maffer_db_version', '1.3' );
    }

    /**
     * Create custom roles for the Maffer panel.
     *
     * - gestor_menus_maffer: Gestor de Menus Maffer
     * - gestor_menus: Gestor de Menus Maffer v5
     */
    private static function create_roles() {
        add_role(
            'gestor_menus_maffer',
            'Gestor de Menus Maffer',
            array(
                'read'               => true,
                'maffer_manage_menu' => true,
            )
        );

        add_role(
            'gestor_menus',
            'Gestor de Menus Maffer v5',
            array(
                'read'               => true,
                'maffer_manage_menu' => true,
            )
        );

        // El menu del panel exige maffer_manage_menu (WO-017): el
        // administrador tambien la necesita.
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( 'maffer_manage_menu' );
        }
    }

    /**
     * Schedule the minute cron job if it is not already scheduled.
     *
     * The cron_schedules filter for 'maffer_minuto' is registered in the
     * main plugin file (maffer-system.php).
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'maffer_check_schedule' ) ) {
            wp_schedule_event( time(), 'maffer_minuto', 'maffer_check_schedule' );
        }
    }
}
