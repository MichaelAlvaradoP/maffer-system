<?php
/**
 * Maffer System — Email Handling
 *
 * Two email formats used by the system:
 *
 * 1. **SUMMARY** (`maffer_enviar_resumen` + `maffer_html_correo`)
 *    - Menu → count distribution table
 *    - XLSX attachment with full detail
 *    - Used by: cron auto-close (`maffer_aplicar_horario`) and manual close toggle
 *    - Origin: WPCode snippet 155 (Panel 7A)
 *
 * 2. **DETAILED** (`maffer_admin_enviar_correo_detalle` on
 *    `admin_post_maffer_enviar_correo`)
 *    - Row-by-row table (Nombre, RUT, Menú, Fecha, Hora)
 *    - XLSX attachment with full detail
 *    - Used by: manual "Send Email" button on the panel
 *    - Origin: WPCode snippet 156 (Panel 7D)
 *
 * Both formats MUST be preserved and verified separately.
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ════════════════════════════════════════════════════════════════
// 1. EMAIL HTML TEMPLATE — SUMMARY (menu → count distribution)
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_html_correo' ) ) {
	/**
	 * Build the HTML email body with a summary table of menu distribution.
	 *
	 * Used by the SUMMARY email format (maffer_enviar_resumen).
	 *
	 * @param array  $rows      Array of reservation records.
	 * @param string $rango_fmt Formatted date range (e.g. "2026-06-06 al 11/06/2026").
	 * @return string Complete HTML document string.
	 */
	function maffer_html_correo( $rows, $rango_fmt ) {
		// Total = personas registradas (1 registro por RUT por ciclo); el JOIN
		// con detalles entrega una fila por dia, no una por registro.
		$total = count( array_unique( array_column( $rows, 'rut' ) ) );

		// Distribucion menu -> cantidad, agrupada por dia de la semana.
		// Filas sin dia_semana (registros Fase 1) van a "Semana completa".
		$dias_orden = array( 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo' );
		$dias_lbl   = array(
			'lunes'     => 'Lunes',
			'martes'    => 'Martes',
			'miercoles' => 'Miércoles',
			'jueves'    => 'Jueves',
			'viernes'   => 'Viernes',
			'sabado'    => 'Sábado',
			'domingo'   => 'Domingo',
		);
		$por_dia = array();
		foreach ( $rows as $r ) {
			$dia = isset( $r['dia_semana'] ) ? strtolower( (string) $r['dia_semana'] ) : '';
			$key = in_array( $dia, $dias_orden, true ) ? $dia : '_semana';
			$t   = ! empty( $r['menu_titulo'] ) ? $r['menu_titulo'] : 'Sin titulo';
			if ( ! isset( $por_dia[ $key ][ $t ] ) ) {
				$por_dia[ $key ][ $t ] = 0;
			}
			$por_dia[ $key ][ $t ]++;
		}
		$orden_final   = $dias_orden;
		$orden_final[] = '_semana';

		$secciones = '';
		foreach ( $orden_final as $key ) {
			if ( empty( $por_dia[ $key ] ) ) {
				continue;
			}
			$titulo_dia = '_semana' === $key ? 'Semana completa' : $dias_lbl[ $key ];
			arsort( $por_dia[ $key ] );
			$filas_dia = '';
			foreach ( $por_dia[ $key ] as $nm => $cnt ) {
				$filas_dia .= '<tr>'
					. '<td style="padding:8px 16px;border-bottom:1px solid #f0e8d8;color:#2a231a">' . esc_html( $nm ) . '</td>'
					. '<td style="padding:8px 16px;border-bottom:1px solid #f0e8d8;text-align:center;font-weight:700;color:#E67E22;font-size:16px;width:80px">' . intval( $cnt ) . '</td>'
					. '</tr>';
			}
			$secciones .= '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #e6d8bf;border-radius:10px;overflow:hidden;font-size:13px;margin-bottom:14px">'
				. '<thead><tr style="background:#E67E22">'
				. '<th colspan="2" style="padding:9px 16px;text-align:left;color:#fff;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.06em">' . esc_html( $titulo_dia ) . '</th>'
				. '</tr>'
				. '<tr style="background:#fbf3e4">'
				. '<th style="padding:7px 16px;text-align:left;color:#97897a;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e6d8bf">Menú</th>'
				. '<th style="padding:7px 16px;text-align:center;color:#97897a;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e6d8bf;width:80px">Cantidad</th>'
				. '</tr></thead>'
				. '<tbody>' . $filas_dia . '</tbody></table>';
		}

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
			. '<div style="font-family:Arial,sans-serif;font-size:13px;color:#2a231a;max-width:560px;margin:0 auto">'
			. '<div style="background:#E67E22;padding:24px 28px;border-radius:12px 12px 0 0">'
			. '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">Servicio de Alimentacion Maffer</h1>'
			. '<p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:13px">Menú semanal &mdash; Resumen del ciclo · Servicio de Cena</p>'
			. '</div>'
			. '<div style="background:#fffaf1;border:1px solid #e6d8bf;border-top:0;padding:24px 28px;border-radius:0 0 12px 12px">'
			. '<p style="margin:0 0 16px;font-size:13px;color:#6b5d4c">Buen dia, se adjunta el detalle completo en el archivo Excel. Resumen del ciclo <strong>' . esc_html( $rango_fmt ) . '</strong>, pedidos de cena por día:</p>'
			. '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:18px">'
			. '<tr>'
			. '<td style="background:#fff8f0;border:1px solid #e6d8bf;border-radius:10px;padding:14px 16px;font-weight:700;color:#2a231a">Personas registradas esta semana</td>'
			. '<td style="background:#fff8f0;border:1px solid #e6d8bf;border-left:0;border-radius:10px;padding:14px 16px;text-align:center;font-weight:800;color:#E67E22;font-size:26px;width:80px">' . intval( $total ) . '</td>'
			. '</tr></table>'
			. $secciones
			. '<p style="margin:0;color:#97897a;font-size:11px;border-top:1px solid #e6d8bf;padding-top:16px">Sistema de Alimentacion Maffer &mdash; ' . esc_html( $rango_fmt ) . '</p>'
			. '</div></div></body></html>';
	}
}

// ════════════════════════════════════════════════════════════════
// 2. SEND SUMMARY EMAIL WITH XLSX ATTACHMENT
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_enviar_resumen' ) ) {
	/**
	 * Fetch records for the current cycle, generate an XLSX file,
	 * and email it to the configured destination address.
	 *
	 * This is the SUMMARY format: email body shows menu → count table,
	 * full detail is in the attached XLSX.
	 *
	 * @param string $asunto Email subject line.
	 * @return bool True if email was sent successfully.
	 */
	function maffer_enviar_resumen( $asunto ) {
		$tz      = new DateTimeZone( 'America/Santiago' );
		$dt      = new DateTime( 'now', $tz );
		$hoy     = $dt->format( 'Y-m-d' );
		$hoy_fmt = $dt->format( 'd/m/Y' );

		$fecha_ciclo = maffer_get_fecha_ciclo();
		$label       = $fecha_ciclo ? $fecha_ciclo . '_a_' . $hoy : $hoy;
		$rango_fmt   = $fecha_ciclo && $fecha_ciclo !== $hoy
			? $fecha_ciclo . ' al ' . $hoy_fmt
			: $hoy_fmt;

		global $wpdb;
		$tabla = $wpdb->prefix . 'maffer_registros';

		$tabla_d = $wpdb->prefix . 'maffer_registro_detalles';

		if ( $fecha_ciclo ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.nombre, r.rut, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo, r.observaciones, r.hora, r.fecha, d.dia_semana
					 FROM {$tabla} r
					 LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
					 WHERE r.fecha >= %s
					   AND ( r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00' )
					 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
					$fecha_ciclo
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.nombre, r.rut, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo, r.observaciones, r.hora, r.fecha, d.dia_semana
					 FROM {$tabla} r
					 LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
					 WHERE r.fecha = %s
					   AND ( r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00' )
					 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
					$hoy
				),
				ARRAY_A
			);
		}

		$dest     = get_option( 'maffer_correo_destino', 'administracion.maffer@gmail.com' );
		$tmp_xlsx = maffer_generar_xlsx( $rows, $label );

		if ( ! $tmp_xlsx ) {
			return false;
		}

		$html = maffer_html_correo( $rows, $rango_fmt );
		// Misma instancia de closure en add/remove: PHP compara closures por
		// instancia, dos closures nuevas nunca se des-registran (WO-017).
		$content_type_html = function() { return 'text/html'; };
		add_filter( 'wp_mail_content_type', $content_type_html );
		$enviado = wp_mail( $dest, $asunto, $html, array( 'Content-Type: text/html; charset=UTF-8' ), array( $tmp_xlsx ) );
		remove_filter( 'wp_mail_content_type', $content_type_html );

		@unlink( $tmp_xlsx );

		return $enviado;
	}
}

// ════════════════════════════════════════════════════════════════
// 3. DETAILED EMAIL — ADMIN-POST HANDLER (row-by-row table)
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_admin_enviar_correo_detalle' ) ) {
	/**
	 * Handle admin-post action: send a DETAILED email with a row-by-row
	 * table (Nombre, RUT, Menú, Fecha, Hora) plus XLSX attachment.
	 *
	 * This replaces the old SUMMARY-only handler on the same hook.
	 * The SUMMARY format is still used by cron auto-close
	 * (maffer_enviar_resumen).
	 *
	 * Origin: WPCode snippet 156 (Panel 7D — Correo).
	 */
	function maffer_admin_enviar_correo_detalle() {
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

		// ── Determine cycle range ─────────────────────────────
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

		// ── Query records for the cycle ───────────────────────
		global $wpdb;
		$tabla = $wpdb->prefix . 'maffer_registros';

		$tabla_d = $wpdb->prefix . 'maffer_registro_detalles';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.nombre, r.rut, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo, r.observaciones, r.hora, r.fecha, d.dia_semana
				 FROM {$tabla} r
				 LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
				 WHERE r.fecha >= %s
				   AND ( r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00' )
				 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
				$fecha_ciclo
			),
			ARRAY_A
		);

		// Total = personas (el JOIN entrega una fila por dia seleccionado).
		$total = count( array_unique( array_column( $rows, 'rut' ) ) );
		$dest  = get_option( 'maffer_correo_destino', get_option( 'admin_email' ) );

		// ── Generate XLSX ─────────────────────────────────────
		if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error' ) );
			exit;
		}

		$tmp_xlsx = maffer_generar_xlsx( $rows, $rango_label );
		if ( ! $tmp_xlsx ) {
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error' ) );
			exit;
		}

		// ── Build DETAILED HTML table grouped by day ──────────
		$dias_orden = array( 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo' );
		$dias_lbl   = array(
			'lunes'     => 'Lunes',
			'martes'    => 'Martes',
			'miercoles' => 'Miércoles',
			'jueves'    => 'Jueves',
			'viernes'   => 'Viernes',
			'sabado'    => 'Sábado',
			'domingo'   => 'Domingo',
		);
		$grupos = array();
		foreach ( $rows as $r ) {
			$dia = isset( $r['dia_semana'] ) ? strtolower( (string) $r['dia_semana'] ) : '';
			$key = in_array( $dia, $dias_orden, true ) ? $dia : '_semana';
			$grupos[ $key ][] = $r;
		}
		$orden_final   = $dias_orden;
		$orden_final[] = '_semana';

		$filas_html = '';
		foreach ( $orden_final as $key ) {
			if ( empty( $grupos[ $key ] ) ) {
				continue;
			}
			$titulo_dia = '_semana' === $key ? 'Semana completa' : $dias_lbl[ $key ];
			$filas_html .= '<tr><td colspan="5" style="padding:8px 10px;background:#fbf3e4;border-bottom:1px solid #e6d8bf;font-weight:700;color:#b95e0a;text-transform:uppercase;letter-spacing:.05em;font-size:12px">'
				. esc_html( $titulo_dia ) . '</td></tr>';
			// Mismo menu junto: ordenar por menu y luego nombre dentro del dia.
			usort( $grupos[ $key ], function ( $a, $b ) {
				$c = strcasecmp( (string) $a['menu_titulo'], (string) $b['menu_titulo'] );
				return 0 !== $c ? $c : strcasecmp( (string) $a['nombre'], (string) $b['nombre'] );
			} );
			foreach ( $grupos[ $key ] as $r ) {
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
		}

		$body = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#333;max-width:680px">'
			. '<div style="background:#E67E22;padding:20px 24px;border-radius:10px 10px 0 0">'
			. '<h2 style="margin:0;color:#fff;font-size:18px">Servicio de Alimentación Maffer</h2>'
			. '<p style="margin:4px 0 0;color:rgba(255,255,255,.85);font-size:12px">Menú semanal — Detalle del ciclo · Cena</p>'
			. '</div>'
			. '<div style="background:#fffaf1;border:1px solid #e6d8bf;border-top:0;padding:20px 24px;border-radius:0 0 10px 10px">'
			. '<p style="margin:0 0 14px">Hola,</p>'
			. '<p style="margin:0 0 16px">Adjunto el detalle de <strong>' . intval( $total ) . ' personas registradas</strong> en el ciclo <strong>' . esc_html( $rango_fmt ) . '</strong>, agrupado por día:</p>'
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

		// ── Send with XLSX attachment ─────────────────────────
		$asunto  = 'Resumen ciclo Maffer ' . $rango_fmt . ' — Servicio de Cena';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Misma instancia de closure en add/remove (WO-017).
		$content_type_html = function () { return 'text/html'; };
		add_filter( 'wp_mail_content_type', $content_type_html );
		$enviado = wp_mail( $dest, $asunto, $body, $headers, array( $tmp_xlsx ) );
		remove_filter( 'wp_mail_content_type', $content_type_html );

		@unlink( $tmp_xlsx );

		$msg = $enviado ? 'correo_ok' : 'correo_error';
		wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=' . $msg ) );
		exit;
	}
}

// ── Register the DETAILED handler for the manual "Send Email" button ────
add_action( 'admin_post_maffer_enviar_correo', 'maffer_admin_enviar_correo_detalle' );
