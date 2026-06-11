
/**
 * ============================================================
 * SNIPPET 7C — MAFFER: DESCARGA XLSX (CICLO SEMANAL)
 * Nombre en WPCode: Maffer - Panel 7C Excel Descarga
 * Tipo: PHP Snippet
 * Insercion: Run Everywhere
 * Activar DESPUÉS de 7A y 7B.
 * Requiere que maffer_generar_xlsx() esté definida en 7A.
 * ============================================================
 * CAMBIOS v2 (ciclo semanal):
 *  - Descarga por rango del ciclo: WHERE fecha >= fecha_desde.
 *  - fecha_desde por defecto = fecha de apertura del ciclo actual
 *    (maffer_get_fecha_ciclo()), no un único día.
 *  - Nombre del archivo refleja el rango: registros-maffer-DESDE-a-HOY.xlsx
 * ============================================================
 */

add_action( 'admin_post_maffer_descargar_excel', function () {
    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'maffer_admin_action' );

    $tz  = new DateTimeZone( 'America/Santiago' );
    $hoy = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d' );

    // ── Determinar fecha de inicio del rango ─────────────────────────────
    // Puede venir explícita desde el formulario (historial puntual)
    // o usar la fecha de inicio del ciclo actual por defecto.
    $fecha_desde = '';
    $fecha_hasta = ''; // opcional: limita el ciclo al rango del historial

    if ( ! empty( $_POST['fecha_desde'] ) ) {
        $fecha_desde = sanitize_text_field( $_POST['fecha_desde'] );
    } elseif ( ! empty( $_POST['fecha_descarga'] ) ) {
        // Compatibilidad con parámetro anterior (descarga de un día exacto)
        $fecha_desde = sanitize_text_field( $_POST['fecha_descarga'] );
    } elseif ( ! empty( $_GET['fecha_desde'] ) ) {
        $fecha_desde = sanitize_text_field( $_GET['fecha_desde'] );
    }

    if ( ! empty( $_POST['fecha_hasta'] ) ) {
        $fecha_hasta = sanitize_text_field( $_POST['fecha_hasta'] );
    } elseif ( ! empty( $_GET['fecha_hasta'] ) ) {
        $fecha_hasta = sanitize_text_field( $_GET['fecha_hasta'] );
    }

    // Validar formato fecha_desde
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha_desde ) ) {
        // Sin parámetro válido: usar inicio del ciclo actual
        $fecha_desde = function_exists( 'maffer_get_fecha_ciclo' )
            ? maffer_get_fecha_ciclo()
            : null;

        if ( ! $fecha_desde ) {
            $fecha_desde = $hoy;
        }
    }

    // Validar fecha_hasta (si viene y tiene formato válido la usamos)
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta ) ) {
        $fecha_hasta = '';
    }

    // ── Consultar registros del rango ────────────────────────────────────
    global $wpdb;
    $tabla = $wpdb->prefix . 'maffer_registros';

    if ( $fecha_hasta ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT nombre, rut, menu_titulo, observaciones, hora, fecha
                 FROM {$tabla}
                 WHERE fecha >= %s AND fecha < %s
                 ORDER BY fecha ASC, id ASC",
                $fecha_desde,
                $fecha_hasta
            ),
            ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT nombre, rut, menu_titulo, observaciones, hora, fecha
                 FROM {$tabla}
                 WHERE fecha >= %s
                 ORDER BY fecha ASC, id ASC",
                $fecha_desde
            ),
            ARRAY_A
        );
    }

    // ── Generar XLSX usando la función compartida de 7A ──────────────────
    if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
        wp_die( 'Error: funcion maffer_generar_xlsx no disponible. Verifica que el snippet 7A este activo.' );
    }

    // Etiqueta de rango para el nombre del archivo y la hoja
    $rango_label = ( $fecha_desde === $hoy )
        ? $hoy
        : $fecha_desde . '_a_' . $hoy;

    $tmp = maffer_generar_xlsx( $rows, $rango_label );
    if ( ! $tmp ) {
        wp_die( 'Error al generar el archivo Excel.' );
    }

    $fname = 'registros-maffer-' . $rango_label . '.xlsx';

    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
    header( 'Content-Length: ' . filesize( $tmp ) );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    readfile( $tmp );
    @unlink( $tmp );
    exit;
} );