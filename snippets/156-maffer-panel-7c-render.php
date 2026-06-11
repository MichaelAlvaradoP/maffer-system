
/**
 * ============================================================
 * SNIPPET 7D — MAFFER: ENVÍO DE CORREO MANUAL
 * Nombre en WPCode: Maffer - Panel 7D Correo
 * Tipo: PHP Snippet | Run Everywhere
 * Activar DESPUÉS de 7A (requiere maffer_generar_xlsx y maffer_get_fecha_ciclo).
 * ============================================================
 *
 * Registra el handler admin_post_maffer_enviar_correo.
 * 7A NO debe registrar este mismo hook para evitar conflicto.
 *
 * CAMBIOS v2 (ciclo semanal):
 *  - Consulta: WHERE fecha >= fecha_ciclo  (rango del ciclo completo).
 *  - Adjunto : XLSX generado con maffer_generar_xlsx() de 7A.
 *  - Asunto  : indica rango del ciclo.
 * ============================================================
 */

add_action( 'admin_post_maffer_enviar_correo', function () {

    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos para enviar correo.' );
    }

    if ( ! check_admin_referer( 'maffer_admin_action', '_wpnonce', false ) ) {
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error&razon=nonce' ) );
        exit;
    }

    $tz      = new DateTimeZone( 'America/Santiago' );
    $dt      = new DateTime( 'now', $tz );
    $hoy     = $dt->format( 'Y-m-d' );
    $hoy_fmt = $dt->format( 'd/m/Y' );

    // ── Determinar rango del ciclo ───────────────────────────
    $fecha_ciclo = function_exists( 'maffer_get_fecha_ciclo' )
        ? maffer_get_fecha_ciclo()
        : null;

    if ( ! $fecha_ciclo ) {
        $fecha_ciclo = $hoy;
    }

    $rango_label = ( $fecha_ciclo === $hoy )
        ? $hoy
        : $fecha_ciclo . '_a_' . $hoy;

    $fecha_ciclo_fmt = date( 'd/m/Y', strtotime( $fecha_ciclo ) );
    $rango_fmt = ( $fecha_ciclo === $hoy )
        ? $hoy_fmt
        : $fecha_ciclo_fmt . ' al ' . $hoy_fmt;

    // ── Consultar registros del ciclo ────────────────────────
    global $wpdb;
    $tabla = $wpdb->prefix . 'maffer_registros';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT nombre, rut, menu_titulo, observaciones, hora, fecha
             FROM {$tabla}
             WHERE fecha >= %s
             ORDER BY fecha ASC, id ASC",
            $fecha_ciclo
        ),
        ARRAY_A
    );

    $total = count( $rows );
    $dest  = get_option( 'maffer_correo_destino', get_option( 'admin_email' ) );

    // ── Generar XLSX ─────────────────────────────────────────
    if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error' ) );
        exit;
    }

    $tmp_xlsx = maffer_generar_xlsx( $rows, $rango_label );
    if ( ! $tmp_xlsx ) {
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error' ) );
        exit;
    }

    $nombre_adjunto = 'registros-maffer-' . $rango_label . '.xlsx';

    // ── Construir tabla HTML del cuerpo ──────────────────────
    $filas_html = '';
    foreach ( $rows as $r ) {
        $fecha_fila = ! empty( $r['fecha'] )
            ? date( 'd/m/Y', strtotime( $r['fecha'] ) )
            : '—';
        $filas_html .= '<tr>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #f0e8d8">'
                . esc_html( $r['nombre'] ) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #f0e8d8;font-family:monospace">'
                . esc_html( $r['rut'] ) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #f0e8d8">'
                . esc_html( $r['menu_titulo'] ) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #f0e8d8;text-align:center">'
                . esc_html( $fecha_fila ) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #f0e8d8;text-align:center">'
                . esc_html( substr( $r['hora'], 0, 5 ) ) . '</td>'
            . '</tr>';
    }

    $body = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#333;max-width:640px">'
        . '<div style="background:#E67E22;padding:20px 24px;border-radius:10px 10px 0 0">'
        . '<h2 style="margin:0;color:#fff;font-size:18px">Servicio de Alimentación Maffer</h2>'
        . '<p style="margin:4px 0 0;color:rgba(255,255,255,.85);font-size:12px">Resumen del ciclo — Cena</p>'
        . '</div>'
        . '<div style="background:#fffaf1;border:1px solid #e6d8bf;border-top:0;padding:20px 24px;border-radius:0 0 10px 10px">'
        . '<p style="margin:0 0 14px">Hola,</p>'
        . '<p style="margin:0 0 16px">Adjunto el resumen de <strong>' . intval( $total ) . ' registros</strong> del ciclo <strong>' . esc_html( $rango_fmt ) . '</strong>.</p>'
        . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:13px;border:1px solid #e6d8bf;border-radius:8px;overflow:hidden;margin-bottom:16px">'
        . '<thead><tr style="background:#E67E22;color:#fff">'
        . '<th style="padding:10px 10px;text-align:left">Nombre</th>'
        . '<th style="padding:10px 10px;text-align:left">RUT</th>'
        . '<th style="padding:10px 10px;text-align:left">Menú</th>'
        . '<th style="padding:10px 10px;text-align:center">Fecha</th>'
        . '<th style="padding:10px 10px;text-align:center">Hora</th>'
        . '</tr></thead>'
        . '<tbody>' . $filas_html . '</tbody>'
        . '</table>'
        . '<p style="color:#aaa;font-size:11px;margin:0">Sistema de Alimentación Maffer &mdash; Ciclo ' . esc_html( $rango_fmt ) . '</p>'
        . '</div></div>';

    // ── Enviar con XLSX adjunto ──────────────────────────────
    $asunto  = 'Resumen ciclo Maffer ' . $rango_fmt . ' — Servicio de Cena';
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    add_filter( 'wp_mail_content_type', function () { return 'text/html'; } );
    $enviado = wp_mail( $dest, $asunto, $body, $headers, array( $tmp_xlsx ) );
    remove_filter( 'wp_mail_content_type', function () { return 'text/html'; } );

    @unlink( $tmp_xlsx );

    $msg = $enviado ? 'correo_ok' : 'correo_error';
    wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=' . $msg ) );
    exit;
} );