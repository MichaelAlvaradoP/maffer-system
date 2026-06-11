<?php
/**
 * Panel 7B AJAX — Editar, Eliminar (soft-delete) y Crear Registros
 *
 * Migrated from WPCode snippet 157 (Maffer - Panel 7B Ajax).
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── EDITAR REGISTRO ──────────────────────────────────────────────────────────

if ( ! function_exists( 'maffer_editar_registro' ) ) {
    /**
     * AJAX handler: editar un registro existente.
     *
     * @return void Sends JSON response and dies.
     */
    function maffer_editar_registro() {
        if ( ! check_ajax_referer( 'maffer_admin_nonce', 'nonce', false )
          || ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $id     = intval( isset( $_POST['id'] )                ? $_POST['id']                : 0 );
        $nombre = sanitize_text_field( isset( $_POST['nombre'] )         ? $_POST['nombre']         : '' );
        $rut    = sanitize_text_field( isset( $_POST['rut'] )            ? $_POST['rut']            : '' );
        $menu   = sanitize_text_field( isset( $_POST['menu_titulo'] )    ? $_POST['menu_titulo']    : '' );
        $obs    = sanitize_textarea_field( isset( $_POST['observaciones'] ) ? $_POST['observaciones'] : '' );

        // turno siempre es 'cena' — no se acepta del POST
        $turno = 'cena';

        if ( ! $id || ! $nombre ) {
            wp_send_json_error( 'Datos incompletos.' );
        }

        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'maffer_registros',
            array(
                'nombre'        => $nombre,
                'rut'           => $rut,
                'turno'         => $turno,
                'menu_titulo'   => $menu,
                'observaciones' => $obs,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $res === false ) {
            wp_send_json_error( 'Error al guardar.' );
        }
        wp_send_json_success( 'ok' );
    }
}
add_action( 'wp_ajax_maffer_editar_registro', 'maffer_editar_registro' );



// ── ELIMINAR REGISTRO (soft-delete) ──────────────────────────────────────────

if ( ! function_exists( 'maffer_eliminar_registro' ) ) {
    /**
     * AJAX handler: eliminar (soft-delete) un registro.
     *
     * Establece deleted_at en lugar de borrar físicamente el registro.
     * Todas las consultas SELECT del sistema filtran por deleted_at IS NULL.
     *
     * @return void Sends JSON response and dies.
     */
    function maffer_eliminar_registro() {
        if ( ! check_ajax_referer( 'maffer_admin_nonce', 'nonce', false )
          || ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $id = intval( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
        if ( ! $id ) {
            wp_send_json_error( 'ID invalido.' );
        }

        global $wpdb;

        // Soft-delete: marcar con la fecha/hora actual en lugar de borrar.
        $res = $wpdb->update(
            $wpdb->prefix . 'maffer_registros',
            array( 'deleted_at' => current_time( 'mysql' ) ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $res === false ) {
            wp_send_json_error( 'Error al eliminar.' );
        }
        wp_send_json_success( 'ok' );
    }
}
add_action( 'wp_ajax_maffer_eliminar_registro', 'maffer_eliminar_registro' );



// ── CREAR REGISTRO (manual desde panel admin) ────────────────────────────────

if ( ! function_exists( 'maffer_crear_registro' ) ) {
    /**
     * AJAX handler: crear un nuevo registro manual desde el panel admin.
     *
     * Incluye verificación de duplicado considerando el ciclo semanal
     * y filtra registros soft-deleteados en la comprobación.
     *
     * @return void Sends JSON response and dies.
     */
    function maffer_crear_registro() {
        if ( ! check_ajax_referer( 'maffer_admin_nonce', 'nonce', false )
          || ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $nombre = sanitize_text_field( isset( $_POST['nombre'] )         ? $_POST['nombre']         : '' );
        $rut    = sanitize_text_field( isset( $_POST['rut'] )            ? $_POST['rut']            : '' );
        $menu   = sanitize_text_field( isset( $_POST['menu_titulo'] )    ? $_POST['menu_titulo']    : '' );
        $obs    = sanitize_textarea_field( isset( $_POST['observaciones'] ) ? $_POST['observaciones'] : '' );

        // turno siempre 'cena'
        $turno = 'cena';

        // Normalizar RUT: quitar puntos, conservar guion
        $rut = str_replace( '.', '', $rut );

        if ( ! $nombre || ! $rut || ! $menu ) {
            wp_send_json_error( 'Nombre, RUT y menu son obligatorios.' );
        }

        $tz   = new DateTimeZone( 'America/Santiago' );
        $dt   = new DateTime( 'now', $tz );
        $hoy  = $dt->format( 'Y-m-d' );
        $hora = $dt->format( 'H:i:s' );

        global $wpdb;
        $tabla = $wpdb->prefix . 'maffer_registros';

        // Verificar duplicado en el ciclo actual (no solo en el día)
        $fecha_ciclo = function_exists( 'maffer_get_fecha_ciclo' )
            ? maffer_get_fecha_ciclo()
            : $hoy;

        // Soft-delete filter: excluir registros eliminados lógicamente.
        $soft_delete_filter = '(deleted_at IS NULL OR deleted_at = %s)';

        if ( $fecha_ciclo ) {
            $existe = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla} WHERE rut = %s AND fecha >= %s AND {$soft_delete_filter}",
                $rut, $fecha_ciclo, '0000-00-00 00:00:00'
            ) );
        } else {
            $existe = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla} WHERE rut = %s AND fecha = %s AND {$soft_delete_filter}",
                $rut, $hoy, '0000-00-00 00:00:00'
            ) );
        }

        if ( $existe > 0 ) {
            wp_send_json_error( 'Este RUT ya tiene un registro en el ciclo actual.' );
        }

        // Estado actual del sistema para registrar en el campo estado_dia
        $estado = function_exists( 'maffer_obtener_estado_sistema' )
            ? maffer_obtener_estado_sistema()
            : get_option( 'maffer_estado_sistema', 'cerrado' );

        $res = $wpdb->insert(
            $tabla,
            array(
                'nombre'        => $nombre,
                'rut'           => $rut,
                'turno'         => $turno,
                'menu_titulo'   => $menu,
                'menu_desc'     => '',
                'observaciones' => $obs,
                'fecha'         => $hoy,
                'hora'          => $hora,
                'estado_dia'    => $estado,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $res === false ) {
            wp_send_json_error( 'Error al guardar en la base de datos.' );
        }
        wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
    }
}
add_action( 'wp_ajax_maffer_crear_registro', 'maffer_crear_registro' );

