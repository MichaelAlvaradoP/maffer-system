<?php
/**
 * Maffer System — Module: CSV / Excel Download
 *
 * Provides an admin-post handler for downloading the current cycle's
 * registration data as an XLSX file.
 *
 * Migrated from WPCode snippet 159 (Panel 7C — Excel Descarga).
 *
 * ## Features
 * - Download by cycle range (fecha_desde → hoy)
 * - Supports explicit fecha_desde / fecha_hasta from POST or GET
 * - Falls back to current cycle start (maffer_get_fecha_ciclo())
 * - Uses maffer_generar_xlsx() from includes/excel.php
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ════════════════════════════════════════════════════════════════
// ADMIN-POST: DOWNLOAD XLSX (cycle range)
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_admin_descargar_excel' ) ) {
	/**
	 * Handle admin-post action: generate and download an XLSX file
	 * with registration records for the specified date range.
	 *
	 * Accepts optional parameters (in order of precedence):
	 * - POST: fecha_desde, fecha_hasta
	 * - POST: fecha_descarga (legacy, single day)
	 * - GET:  fecha_desde, fecha_hasta
	 *
	 * Falls back to the current cycle start (maffer_get_fecha_ciclo())
	 * when no valid range is provided.
	 */
	function maffer_admin_descargar_excel() {
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'maffer_admin_action' );

		$tz  = new DateTimeZone( 'America/Santiago' );
		$hoy = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d' );

		// ── Determine date range ──────────────────────────────
		$fecha_desde = '';
		$fecha_hasta = '';

		if ( ! empty( $_POST['fecha_desde'] ) ) {
			$fecha_desde = sanitize_text_field( $_POST['fecha_desde'] );
		} elseif ( ! empty( $_POST['fecha_descarga'] ) ) {
			$fecha_desde = sanitize_text_field( $_POST['fecha_descarga'] );
		} elseif ( ! empty( $_GET['fecha_desde'] ) ) {
			$fecha_desde = sanitize_text_field( $_GET['fecha_desde'] );
		}

		if ( ! empty( $_POST['fecha_hasta'] ) ) {
			$fecha_hasta = sanitize_text_field( $_POST['fecha_hasta'] );
		} elseif ( ! empty( $_GET['fecha_hasta'] ) ) {
			$fecha_hasta = sanitize_text_field( $_GET['fecha_hasta'] );
		}

		// Validate fecha_desde format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha_desde ) ) {
			$fecha_desde = function_exists( 'maffer_get_fecha_ciclo' )
				? maffer_get_fecha_ciclo()
				: null;

			if ( ! $fecha_desde ) {
				$fecha_desde = $hoy;
			}
		}

		// Validate fecha_hasta format
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta ) ) {
			$fecha_hasta = '';
		}

		// ── Query records for the range ───────────────────────
		global $wpdb;
		$tabla = $wpdb->prefix . 'maffer_registros';

		$tabla_d = $wpdb->prefix . 'maffer_registro_detalles';

		if ( $fecha_hasta ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.nombre, r.rut, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo, r.observaciones, r.hora, r.fecha, d.dia_semana
					 FROM {$tabla} r
					 LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
					 WHERE r.fecha >= %s
					   AND r.fecha < %s
					   AND ( r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00' )
					 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
					$fecha_desde,
					$fecha_hasta
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.nombre, r.rut, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo, r.observaciones, r.hora, r.fecha, d.dia_semana
					 FROM {$tabla} r
					 LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
					 WHERE r.fecha >= %s
					   AND ( r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00' )
					 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
					$fecha_desde
				),
				ARRAY_A
			);
		}

		// ── Generate XLSX ─────────────────────────────────────
		if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
			wp_die( 'Error: funcion maffer_generar_xlsx no disponible.' );
		}

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
	}
}

// ── Register the download handler ────────────────────────────────
add_action( 'admin_post_maffer_descargar_excel', 'maffer_admin_descargar_excel' );
