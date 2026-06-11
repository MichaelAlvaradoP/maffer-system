
/**
 * ============================================================
 * SNIPPET 2 — ENDPOINT AJAX: VALIDAR RUT DUPLICADO DEL CICLO
 * Dónde pegarlo: WPCode → Añadir Snippet → PHP Snippet
 * Nombre sugerido: Maffer - AJAX Validar RUT
 * Inserción: "Run Everywhere"
 * Activar DESPUÉS de snippet 7A.
 * ============================================================
 *
 * Lógica:
 *  - Recibe el RUT formateado desde el frontend.
 *  - Si el sistema está CERRADO → responde que el RUT está libre.
 *  - Si el sistema está ABIERTO → consulta si el RUT ya existe
 *    en el ciclo actual (fecha >= fecha_apertura_ciclo).
 *  - Devuelve JSON { disponible: true/false, mensaje: "..." }
 *
 * NOTA: maffer_obtener_estado_dia() ya NO se define aquí.
 *       El alias está en snippet-7A con guard if(!function_exists).
 */

add_action( 'wp_ajax_maffer_validar_rut',        'maffer_ajax_validar_rut' );
add_action( 'wp_ajax_nopriv_maffer_validar_rut', 'maffer_ajax_validar_rut' );

function maffer_ajax_validar_rut() {
    // Verificamos nonce para seguridad básica
    if ( ! check_ajax_referer( 'maffer_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'mensaje' => 'Solicitud no autorizada.' ], 403 );
    }

    $rut = isset( $_POST['rut'] ) ? sanitize_text_field( $_POST['rut'] ) : '';

    if ( empty( $rut ) ) {
        wp_send_json_success( [ 'disponible' => true, 'mensaje' => '' ] );
    }

    // Normalizamos el RUT: quitamos puntos, dejamos solo el guion
    $rut_limpio = strtoupper( preg_replace( '/\./', '', $rut ) );

    // Verificamos el estado global del sistema
    $estado = function_exists( 'maffer_obtener_estado_sistema' )
        ? maffer_obtener_estado_sistema()
        : get_option( 'maffer_estado_sistema', 'cerrado' );

    // Si el sistema está cerrado no hay restricción — puede registrar
    if ( $estado === 'cerrado' ) {
        wp_send_json_success( [
            'disponible' => true,
            'mensaje'    => '',
        ] );
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
        wp_send_json_success( [
            'disponible' => true,
            'mensaje'    => '',
        ] );
    }

    // Ciclo configurado: verificar duplicado en el rango del ciclo
    $existe = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tabla}
         WHERE rut = %s AND fecha >= %s",
        $rut_limpio,
        $fecha_ciclo
    ) );

    if ( $existe > 0 ) {
        wp_send_json_success( [
            'disponible' => false,
            'mensaje'    => 'Este RUT ya tiene un registro para esta semana. Solo se permite un registro por semana.',
        ] );
    }

    wp_send_json_success( [
        'disponible' => true,
        'mensaje'    => '',
    ] );
}