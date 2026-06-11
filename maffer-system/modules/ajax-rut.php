<?php
/**
 * Maffer System — Module: AJAX RUT Validation
 *
 * Endpoint AJAX para validar si un RUT ya está registrado en el ciclo actual.
 * Migrado desde WPCode snippet 138 (Maffer - AJAX Validar RUT).
 *
 * Lógica:
 *   - Recibe el RUT formateado desde el frontend.
 *   - Si el sistema está CERRADO → responde que el RUT está libre.
 *   - Si el sistema está ABIERTO → consulta si el RUT ya existe
 *     en el ciclo actual (fecha >= fecha_apertura_ciclo).
 *   - Devuelve JSON { disponible: true/false, mensaje: "..." }
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── AJAX: Validar RUT duplicado ─────────────────────────────────────────────

if ( ! function_exists( 'maffer_ajax_validar_rut' ) ) {

    /**
     * AJAX handler: verifica si un RUT ya tiene registro en el ciclo actual.
     *
     * @return void Sends JSON response and dies.
     */
    function maffer_ajax_validar_rut() {
        // Verificamos nonce para seguridad básica
        if ( ! check_ajax_referer( 'maffer_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'mensaje' => 'Solicitud no autorizada.' ), 403 );
        }

        $rut = isset( $_POST['rut'] ) ? sanitize_text_field( $_POST['rut'] ) : '';

        if ( empty( $rut ) ) {
            wp_send_json_success( array( 'disponible' => true, 'mensaje' => '' ) );
        }

        // Normalizamos el RUT: quitamos puntos, dejamos solo el guion
        $rut_limpio = strtoupper( preg_replace( '/\./', '', $rut ) );

        // Verificamos el estado global del sistema
        $estado = function_exists( 'maffer_obtener_estado_sistema' )
            ? maffer_obtener_estado_sistema()
            : get_option( 'maffer_estado_sistema', 'cerrado' );

        // Si el sistema está cerrado no hay restricción — puede registrar
        if ( $estado === 'cerrado' ) {
            wp_send_json_success( array(
                'disponible' => true,
                'mensaje'    => '',
            ) );
        }

        // Sistema abierto: revisamos si el RUT ya registró en el ciclo actual
        global $wpdb;
        $tabla = $wpdb->prefix . 'maffer_registros';

        // Obtener fecha de inicio del ciclo (fecha de la apertura programada)
        $fecha_ciclo = function_exists( 'maffer_get_fecha_ciclo' )
            ? maffer_get_fecha_ciclo()
            : null;

        // Si no hay ciclo configurado aún (apertura_programada no definida),
        // no bloquear en la validación previa — el control real ocurre en el submit.
        if ( ! $fecha_ciclo ) {
            wp_send_json_success( array(
                'disponible' => true,
                'mensaje'    => '',
            ) );
        }

        // Soft-delete filter: excluir registros eliminados lógicamente
        $soft_delete_filter = '( deleted_at IS NULL OR deleted_at = %s )';

        // Ciclo configurado: verificar duplicado en el rango del ciclo
        $existe = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla}
             WHERE rut = %s AND fecha >= %s AND {$soft_delete_filter}",
            $rut_limpio,
            $fecha_ciclo,
            '0000-00-00 00:00:00'
        ) );

        if ( $existe > 0 ) {
            wp_send_json_success( array(
                'disponible' => false,
                'mensaje'    => 'Este RUT ya tiene un registro para esta semana. Solo se permite un registro por semana.',
            ) );
        }

        wp_send_json_success( array(
            'disponible' => true,
            'mensaje'    => '',
        ) );
    }
}

add_action( 'wp_ajax_maffer_validar_rut',        'maffer_ajax_validar_rut' );
add_action( 'wp_ajax_nopriv_maffer_validar_rut', 'maffer_ajax_validar_rut' );
