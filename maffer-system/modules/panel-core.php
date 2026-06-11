<?php
/**
 * Maffer System — Module: Panel Core
 *
 * Roles, admin menu, cron, POST handlers, XLSX export, email functions.
 * Migrated from WPCode snippet 155 (Panel 7A — Roles y Menú).
 *
 * ## Function inventory
 *
 * | Function | Purpose |
 * |----------|---------|
 * | `maffer_register_roles()` | Custom role creation safety net (init) |
 * | `maffer_register_admin_menu()` | Add admin menu page |
 * | `maffer_clean_admin_menu()` | Hide non-Maffer menus for gestores |
 * | `maffer_redirect_to_panel()` | Force redirect gestores to Maffer panel |
 * | `maffer_remove_old_menus()` | Remove deprecated Maffer menu entries |
 * | `maffer_clean_admin_bar()` | Remove admin bar nodes for gestores |
 * | `maffer_login_redirect()` | Redirect gestores after login |
 * | `maffer_aplicar_horario()` | Cron callback: auto open/close schedule |
 * | `maffer_generar_xlsx()` | Generate XLSX export in-memory |
 * | `maffer_html_correo()` | Build HTML email body |
 * | `maffer_enviar_resumen()` | Send summary email with XLSX attachment |
 * | `maffer_v6_procesar()` | Legacy POST fallback (called from panel-render) |
 * | `maffer_admin_guardar_menus()` | Admin-post: save menus |
 * | `maffer_admin_toggle_dia()` | Admin-post: open/close system |
 * | `maffer_admin_guardar_horario()` | Admin-post: save schedule config |
 * | `maffer_admin_enviar_correo()` | Admin-post: send manual email |
 * | `maffer_skip_logout_confirmation()` | Skip WP logout confirmation |
 * | `maffer_ajax_cambiar_password()` | AJAX: change gestor password |
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ════════════════════════════════════════════════════════════════
// 1. CUSTOM ROLES (safety net — also created on activation)
// ════════════════════════════════════════════════════════════════

add_action( 'init', 'maffer_register_roles' );

if ( ! function_exists( 'maffer_register_roles' ) ) {
	/**
	 * Register custom roles if they don't exist yet.
	 *
	 * Runs on every init as a safety net in case roles are deleted
	 * after plugin activation. The activator also creates these roles.
	 */
	function maffer_register_roles() {
		if ( ! get_role( 'gestor_menus_maffer' ) ) {
			add_role( 'gestor_menus_maffer', 'Gestor de Menus Maffer', array(
				'read'               => true,
				'maffer_manage_menu' => true,
			) );
		}

		if ( ! get_role( 'gestor_menus' ) ) {
			add_role( 'gestor_menus', 'Gestor de Menus Maffer v5', array(
				'read'               => true,
				'maffer_manage_menu' => true,
			) );
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 2. ADMIN MENU REGISTRATION
// ════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'maffer_register_admin_menu' );

if ( ! function_exists( 'maffer_register_admin_menu' ) ) {
	/**
	 * Register the main Maffer admin menu page.
	 *
	 * The render callback `maffer_v6_render` is defined in the panel-render
	 * module (migrated from snippet 158).
	 */
	function maffer_register_admin_menu() {
		add_menu_page(
			'Panel Maffer',
			'Panel Maffer',
			'read',
			'maffer-panel',
			'maffer_v6_render',
			'dashicons-food',
			3
		);
	}
}

// ════════════════════════════════════════════════════════════════
// 3. CLEAN ADMIN VIEW (hide non-Maffer menus for gestores)
// ════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'maffer_clean_admin_menu', 999 );

if ( ! function_exists( 'maffer_clean_admin_menu' ) ) {
	/**
	 * Remove all admin menu items except the Maffer panel for gestores.
	 * Administrators (manage_options) see the full menu.
	 */
	function maffer_clean_admin_menu() {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'maffer_manage_menu' ) ) {
			return;
		}

		global $menu;
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] !== 'maffer-panel' ) {
				unset( $menu[ $key ] );
			}
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 4. REDIRECT GESTORES TO MAFFER PANEL
// ════════════════════════════════════════════════════════════════

add_action( 'admin_init', 'maffer_redirect_to_panel' );

if ( ! function_exists( 'maffer_redirect_to_panel' ) ) {
	/**
	 * Force gestores (non-admin Maffer users) to the Maffer panel
	 * when they attempt to access any other admin page.
	 *
	 * AJAX requests and admin-post.php submissions are exempt.
	 */
	function maffer_redirect_to_panel() {
		// Only applies to gestores, not admins.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'maffer_manage_menu' ) ) {
			return;
		}

		// Do not interrupt AJAX or form submissions.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		global $pagenow;
		if ( 'admin-post.php' === $pagenow ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 'maffer-panel' !== $page ) {
			wp_redirect( admin_url( 'admin.php?page=maffer-panel' ) );
			exit;
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 5. REMOVE OLD/DUPLICATE MAFFER MENUS
// ════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'maffer_remove_old_menus', 9999 );

if ( ! function_exists( 'maffer_remove_old_menus' ) ) {
	/**
	 * Remove any menu items whose slug contains 'maffer' or 'cierre',
	 * except the current 'maffer-panel'. This cleans up leftovers
	 * from older plugin/snippet versions.
	 */
	function maffer_remove_old_menus() {
		global $menu, $submenu;

		foreach ( $menu as $key => $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			$slug = $item[2];
			if ( 'maffer-panel' === $slug ) {
				continue;
			}
			if ( stripos( $slug, 'maffer' ) !== false || stripos( $slug, 'cierre' ) !== false ) {
				unset( $menu[ $key ] );
				if ( isset( $submenu[ $slug ] ) ) {
					unset( $submenu[ $slug ] );
				}
			}
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 6. CLEAN ADMIN BAR FOR GESTORES
// ════════════════════════════════════════════════════════════════

add_action( 'admin_bar_menu', 'maffer_clean_admin_bar', 999 );

if ( ! function_exists( 'maffer_clean_admin_bar' ) ) {
	/**
	 * Remove all admin bar nodes for gestores so they only see
	 * the minimal interface.
	 *
	 * @param WP_Admin_Bar $bar Admin bar object.
	 */
	function maffer_clean_admin_bar( $bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'maffer_manage_menu' ) ) {
			return;
		}

		$nodes = $bar->get_nodes();
		if ( ! empty( $nodes ) ) {
			foreach ( $nodes as $node ) {
				$bar->remove_node( $node->id );
			}
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 7. LOGIN REDIRECT FOR GESTORES
// ════════════════════════════════════════════════════════════════

add_filter( 'login_redirect', 'maffer_login_redirect', 10, 3 );

if ( ! function_exists( 'maffer_login_redirect' ) ) {
	/**
	 * After login, redirect gestores directly to the Maffer panel
	 * instead of the WordPress dashboard.
	 *
	 * @param string  $to   Default redirect URL.
	 * @param mixed   $req  Requested redirect (if any).
	 * @param WP_User $user Logged-in user object.
	 * @return string
	 */
	function maffer_login_redirect( $to, $req, $user ) {
		if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
			return $to;
		}
		if ( in_array( 'gestor_menus_maffer', $user->roles, true )
			|| in_array( 'gestor_menus', $user->roles, true )
		) {
			return admin_url( 'admin.php?page=maffer-panel' );
		}
		return $to;
	}
}

// ════════════════════════════════════════════════════════════════
// 8. CRON — AUTO OPEN/CLOSE SCHEDULE
// ════════════════════════════════════════════════════════════════

add_action( 'maffer_check_schedule', 'maffer_aplicar_horario' );

if ( ! function_exists( 'maffer_aplicar_horario' ) ) {
	/**
	 * Opens and closes the system automatically according to the
	 * weekly recurring schedule.
	 *
	 * - Opening: first day/time of the window (e.g. Saturday 12:00)
	 * - Closing: second day/time (e.g. Sunday 18:00) + email summary
	 *
	 * Both repeat weekly without needing to reschedule.
	 */
	function maffer_aplicar_horario() {
		update_option( 'maffer_ultimo_cron', time(), false );

		$tz  = new DateTimeZone( 'America/Santiago' );
		$now = new DateTime( 'now', $tz );

		$ap_dia  = (int) get_option( 'maffer_apertura_dia',  6 );   // 6 = sábado
		$ap_hora = get_option( 'maffer_apertura_hora', '12:00' );
		$cl_dia  = (int) get_option( 'maffer_cierre_dia',   0 );    // 0 = domingo
		$cl_hora = get_option( 'maffer_cierre_hora', '18:00' );

		$dt_apertura = maffer_compute_this_week_dt( $ap_dia, $ap_hora, $tz );
		$dt_cierre   = maffer_compute_this_week_dt( $cl_dia, $cl_hora, $tz );

		$estado = get_option( 'maffer_estado_sistema', 'cerrado' );

		// ── Auto-open: we are inside the window [apertura, cierre] ──────
		if ( $now >= $dt_apertura && $now < $dt_cierre && 'abierto' !== $estado ) {
			$semana_key = $dt_apertura->format( 'Y-W' ); // Year + ISO week
			if ( get_option( 'maffer_ultima_apertura_auto', '' ) !== $semana_key ) {
				update_option( 'maffer_estado_sistema', 'abierto' );
				update_option( 'maffer_ultima_apertura_auto', $semana_key );
				$estado = 'abierto';
			}
		}

		// ── Auto-close: past closing time and system is still open ──────
		if ( $now >= $dt_cierre && 'abierto' === $estado ) {
			$semana_key = $dt_cierre->format( 'Y-W' );
			if ( get_option( 'maffer_ultimo_cierre_auto', '' ) !== $semana_key ) {
				update_option( 'maffer_estado_sistema', 'cerrado' );
				update_option( 'maffer_ultimo_cierre_auto', $semana_key );
				if ( function_exists( 'maffer_enviar_resumen' ) ) {
					$asunto = '[AUTO-CIERRE] Resumen Semanal Cena '
							. $dt_cierre->format( 'd/m/Y' )
							. ' - Servicio de Alimentacion Maffer';
					maffer_enviar_resumen( $asunto );
				}
			}
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 9. XLSX GENERATION (in-memory via ZipArchive + raw XML)
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
	/**
	 * Generate an XLSX file in the system temp directory using raw
	 * OpenXML spreadsheet XML plus a ZIP wrapper (no library needed).
	 *
	 * Columns: Nombre, RUT, Menu, Observaciones, Hora, Fecha.
	 *
	 * @param array  $rows  Array of associative arrays with keys:
	 *                      nombre, rut, menu_titulo, observaciones, hora, fecha.
	 * @param string $label Label for the temp filename.
	 * @return string|false Path to the generated temp file, or false on failure.
	 */
	function maffer_generar_xlsx( $rows, $label ) {
		$cabeceras  = array( 'Nombre', 'RUT', 'Menu', 'Observaciones', 'Hora', 'Fecha' );
		$col_letras = array( 'A', 'B', 'C', 'D', 'E', 'F' );

		$strings = array();
		$str_idx = array();
		$agregar = function( $v ) use ( &$strings, &$str_idx ) {
			$v = (string) $v;
			if ( ! isset( $str_idx[ $v ] ) ) {
				$str_idx[ $v ] = count( $strings );
				$strings[]     = $v;
			}
			return $str_idx[ $v ];
		};

		$filas_xl = array();
		$cab_idx  = array();
		foreach ( $cabeceras as $c ) {
			$cab_idx[] = $agregar( $c );
		}
		foreach ( $rows as $r ) {
			$fi   = array();
			$fi[] = $agregar( $r['nombre'] );
			$fi[] = $agregar( $r['rut'] );
			$fi[] = $agregar( $r['menu_titulo'] );
			$fi[] = $agregar( isset( $r['observaciones'] ) ? $r['observaciones'] : '' );
			$fi[] = $agregar( substr( $r['hora'], 0, 5 ) );
			$fi[] = $agregar( isset( $r['fecha'] ) ? $r['fecha'] : '' );
			$filas_xl[] = $fi;
		}

		$sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$sheet .= '<row r="1">';
		foreach ( $cab_idx as $ci => $si ) {
			$sheet .= '<c r="' . $col_letras[ $ci ] . '1" t="s"><v>' . $si . '</v></c>';
		}
		$sheet .= '</row>';
		foreach ( $filas_xl as $ri => $fila ) {
			$rn = $ri + 2;
			$sheet .= '<row r="' . $rn . '">';
			foreach ( $fila as $ci => $si ) {
				$sheet .= '<c r="' . $col_letras[ $ci ] . $rn . '" t="s"><v>' . $si . '</v></c>';
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';

		$sst  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sst .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">';
		foreach ( $strings as $s ) {
			$sst .= '<si><t>' . htmlspecialchars( $s, ENT_XML1, 'UTF-8' ) . '</t></si>';
		}
		$sst .= '</sst>';

		$wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$wb .= '<sheets><sheet name="Registros" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
		$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
		$ct .= '<Default Extension="xml" ContentType="application/xml"/>';
		$ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
		$ct .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		$ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
		$ct .= '</Types>';

		$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
		$rels .= '</Relationships>';

		$wbrels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$wbrels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$wbrels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
		$wbrels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
		$wbrels .= '</Relationships>';

		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maffer_' . $label . '_' . time() . '.xlsx';
		$zip = new ZipArchive();
		if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml',        $ct );
		$zip->addFromString( '_rels/.rels',                $rels );
		$zip->addFromString( 'xl/workbook.xml',            $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $wbrels );
		$zip->addFromString( 'xl/worksheets/sheet1.xml',   $sheet );
		$zip->addFromString( 'xl/sharedStrings.xml',       $sst );
		$zip->close();

		return $tmp;
	}
}

// ════════════════════════════════════════════════════════════════
// 10. EMAIL HTML TEMPLATE
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_html_correo' ) ) {
	/**
	 * Build the HTML email body with a summary table of menu distribution.
	 *
	 * @param array  $rows      Array of reservation records.
	 * @param string $rango_fmt Formatted date range (e.g. "2026-06-06 al 11/06/2026").
	 * @return string Complete HTML document string.
	 */
	function maffer_html_correo( $rows, $rango_fmt ) {
		$total = count( $rows );
		$dist  = array();

		foreach ( $rows as $r ) {
			$t = ! empty( $r['menu_titulo'] ) ? $r['menu_titulo'] : 'Sin titulo';
			$dist[ $t ] = isset( $dist[ $t ] ) ? $dist[ $t ] + 1 : 1;
		}

		$filas_dist = '';
		foreach ( $dist as $nm => $cnt ) {
			$filas_dist .= '<tr>'
				. '<td style="padding:10px 16px;border-bottom:1px solid #f0e8d8;color:#2a231a">' . esc_html( $nm ) . '</td>'
				. '<td style="padding:10px 16px;border-bottom:1px solid #f0e8d8;text-align:center;font-weight:700;color:#E67E22;font-size:18px">' . intval( $cnt ) . '</td>'
				. '</tr>';
		}

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
			. '<div style="font-family:Arial,sans-serif;font-size:13px;color:#2a231a;max-width:560px;margin:0 auto">'
			. '<div style="background:#E67E22;padding:24px 28px;border-radius:12px 12px 0 0">'
			. '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">Servicio de Alimentacion Maffer</h1>'
			. '<p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:13px">Resumen del ciclo &mdash; Servicio de Cena</p>'
			. '</div>'
			. '<div style="background:#fffaf1;border:1px solid #e6d8bf;border-top:0;padding:24px 28px;border-radius:0 0 12px 12px">'
			. '<p style="margin:0 0 20px;font-size:13px;color:#6b5d4c">Buen dia, se adjunta el detalle completo en el archivo Excel. Aqui el resumen del ciclo <strong>' . esc_html( $rango_fmt ) . '</strong>:</p>'
			. '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border:1px solid #e6d8bf;border-radius:10px;overflow:hidden;font-size:13px;margin-bottom:20px">'
			. '<thead><tr style="background:#fbf3e4">'
			. '<th style="padding:10px 16px;text-align:left;color:#97897a;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e6d8bf">Menu</th>'
			. '<th style="padding:10px 16px;text-align:center;color:#97897a;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e6d8bf">Cantidad</th>'
			. '</tr></thead>'
			. '<tbody>'
			. $filas_dist
			. '<tr style="background:#fff8f0">'
			. '<td style="padding:12px 16px;font-weight:700;color:#2a231a;border-top:2px solid #e6d8bf">Total registros</td>'
			. '<td style="padding:12px 16px;text-align:center;font-weight:800;color:#E67E22;font-size:22px;border-top:2px solid #e6d8bf">' . intval( $total ) . '</td>'
			. '</tr>'
			. '</tbody></table>'
			. '<p style="margin:0;color:#97897a;font-size:11px;border-top:1px solid #e6d8bf;padding-top:16px">Sistema de Alimentacion Maffer &mdash; ' . esc_html( $rango_fmt ) . '</p>'
			. '</div></div></body></html>';
	}
}

// ════════════════════════════════════════════════════════════════
// 11. SEND SUMMARY EMAIL WITH XLSX ATTACHMENT
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_enviar_resumen' ) ) {
	/**
	 * Fetch records for the current cycle, generate an XLSX file,
	 * and email it to the configured destination address.
	 *
	 * @param string $asunto Email subject line.
	 * @return bool True if email was sent successfully.
	 *
	 * ## Known Issue: remove_filter with anonymous closure
	 *
	 * The original snippet 155 uses an anonymous closure for
	 * `add_filter('wp_mail_content_type', ...)` and then attempts
	 * `remove_filter()` with a *new* anonymous closure instance.
	 * Because PHP compares closures by instance (not by code),
	 * the remove_filter call is a no-op — the filter remains in place.
	 *
	 * In practice, `wp_mail()` resets the content type internally for
	 * each call, so the bug has not caused observable side effects.
	 * It is left as-is for behavioral identity with the original.
	 *
	 * @see https://www.php.net/manual/en/function.spl-object-id.php
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

		if ( $fecha_ciclo ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT nombre, rut, menu_titulo, observaciones, hora, fecha FROM {$tabla} WHERE fecha >= %s ORDER BY fecha ASC, id ASC",
					$fecha_ciclo
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT nombre, rut, menu_titulo, observaciones, hora, fecha FROM {$tabla} WHERE fecha = %s ORDER BY id ASC",
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
		add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );

		/**
		 * NOTE: The remove_filter below uses a new anonymous closure instance.
		 * This does NOT remove the filter added above. See the docblock for details.
		 */
		$enviado = wp_mail( $dest, $asunto, $html, array( 'Content-Type: text/html; charset=UTF-8' ), array( $tmp_xlsx ) );
		remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );

		@unlink( $tmp_xlsx );

		return $enviado;
	}
}

// ════════════════════════════════════════════════════════════════
// 12. LEGACY POST FALLBACK
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_v6_procesar' ) ) {
	/**
	 * Legacy POST processing fallback.
	 *
	 * Called from the panel-render module (migrated from snippet 158)
	 * when actions are submitted directly from the admin panel page.
	 *
	 * Handles:
	 * - 'guardar_menus'   — Save menu items for Cena
	 * - 'guardar_horario' — Save recurring schedule + destination email
	 * - 'cerrar_dia'      — Manually close the system
	 * - 'abrir_dia'       — Manually open the system
	 *
	 * Note: Standalone admin-post actions (admin_post_maffer_guardar_menus,
	 * admin_post_maffer_toggle_dia, admin_post_maffer_guardar_horario) also
	 * exist in this module. This function serves as an alternative entry point
	 * when the action is submitted via a form on the Maffer panel page itself.
	 */
	function maffer_v6_procesar() {
		$accion = sanitize_key( isset( $_POST['maffer_accion'] ) ? $_POST['maffer_accion'] : '' );
		if ( ! $accion ) {
			return;
		}
		if ( ! check_admin_referer( 'maffer_admin_action' ) ) {
			wp_die( 'No autorizado.' );
		}
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}

		// ── Save Cena menus ────────────────────────────────────────────
		if ( 'guardar_menus' === $accion ) {
			$titles = isset( $_POST['maffer_titles_cena'] ) ? (array) $_POST['maffer_titles_cena'] : array();
			$descs  = isset( $_POST['maffer_descs_cena'] )  ? (array) $_POST['maffer_descs_cena']  : array();
			$data   = array();
			for ( $i = 0; $i < count( $titles ); $i++ ) {
				$t = sanitize_text_field( isset( $titles[ $i ] ) ? $titles[ $i ] : '' );
				$d = sanitize_textarea_field( isset( $descs[ $i ] ) ? $descs[ $i ] : '' );
				if ( trim( $t ) !== '' ) {
					$data[] = array( 'title' => $t, 'desc' => $d );
				}
			}
			update_option( 'maffer_menu_cena', $data );
			maffer_limpiar_cache();
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&panel=menus&msg=menus_ok' ) );
			exit;
		}

		// ── Save weekly schedule + email ───────────────────────────────
		if ( 'guardar_horario' === $accion ) {
			$ap_dia  = (int) ( isset( $_POST['apertura_dia'] )  ? $_POST['apertura_dia']  : 6 );
			$ap_hora = sanitize_text_field( isset( $_POST['apertura_hora'] ) ? $_POST['apertura_hora'] : '12:00' );
			$cl_dia  = (int) ( isset( $_POST['cierre_dia'] )    ? $_POST['cierre_dia']    : 0 );
			$cl_hora = sanitize_text_field( isset( $_POST['cierre_hora'] )  ? $_POST['cierre_hora']  : '18:00' );

			if ( $ap_dia >= 0 && $ap_dia <= 6 ) {
				update_option( 'maffer_apertura_dia', $ap_dia );
			}
			if ( preg_match( '/^\d{2}:\d{2}$/', $ap_hora ) ) {
				update_option( 'maffer_apertura_hora', $ap_hora );
			}
			if ( $cl_dia >= 0 && $cl_dia <= 6 ) {
				update_option( 'maffer_cierre_dia', $cl_dia );
			}
			if ( preg_match( '/^\d{2}:\d{2}$/', $cl_hora ) ) {
				update_option( 'maffer_cierre_hora', $cl_hora );
			}

			$correo = sanitize_email( isset( $_POST['correo_destino'] ) ? $_POST['correo_destino'] : '' );
			if ( $correo ) {
				update_option( 'maffer_correo_destino', $correo );
			}

			maffer_limpiar_cache();
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=horario_ok' ) );
			exit;
		}

		// ── Close system ───────────────────────────────────────────────
		if ( 'cerrar_dia' === $accion ) {
			update_option( 'maffer_estado_sistema', 'cerrado' );
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_cerrado' ) );
			exit;
		}

		// ── Open system ────────────────────────────────────────────────
		if ( 'abrir_dia' === $accion ) {
			update_option( 'maffer_estado_sistema', 'abierto' );
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_abierto' ) );
			exit;
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 13. ADMIN-POST: GUARDAR MENÚS
// ════════════════════════════════════════════════════════════════

add_action( 'admin_post_maffer_guardar_menus', 'maffer_admin_guardar_menus' );

if ( ! function_exists( 'maffer_admin_guardar_menus' ) ) {
	/**
	 * Handle admin-post action: save Cena menu items.
	 *
	 * Expects POST fields: maffer_titles_cena[], maffer_descs_cena[].
	 */
	function maffer_admin_guardar_menus() {
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'maffer_admin_action' );

		$titles = isset( $_POST['maffer_titles_cena'] ) ? (array) $_POST['maffer_titles_cena'] : array();
		$descs  = isset( $_POST['maffer_descs_cena'] )  ? (array) $_POST['maffer_descs_cena']  : array();
		$data   = array();
		for ( $i = 0; $i < count( $titles ); $i++ ) {
			$t = sanitize_text_field( isset( $titles[ $i ] ) ? $titles[ $i ] : '' );
			$d = sanitize_textarea_field( isset( $descs[ $i ] ) ? $descs[ $i ] : '' );
			if ( trim( $t ) !== '' ) {
				$data[] = array( 'title' => $t, 'desc' => $d );
			}
		}
		update_option( 'maffer_menu_cena', $data );
		maffer_limpiar_cache();

		wp_redirect( admin_url( 'admin.php?page=maffer-panel&panel=menus&msg=menus_ok' ) );
		exit;
	}
}

// ════════════════════════════════════════════════════════════════
// 14. ADMIN-POST: TOGGLE SISTEMA (open/close)
// ════════════════════════════════════════════════════════════════

add_action( 'admin_post_maffer_toggle_dia', 'maffer_admin_toggle_dia' );

if ( ! function_exists( 'maffer_admin_toggle_dia' ) ) {
	/**
	 * Handle admin-post action: manually open or close the system.
	 *
	 * Expects POST field: maffer_accion = 'cerrar_dia' | 'abrir_dia'.
	 * On close, also sends a summary email.
	 */
	function maffer_admin_toggle_dia() {
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'maffer_admin_action' );

		$accion  = sanitize_key( isset( $_POST['maffer_accion'] ) ? $_POST['maffer_accion'] : '' );
		$tz      = new DateTimeZone( 'America/Santiago' );
		$hoy_fmt = ( new DateTime( 'now', $tz ) )->format( 'd/m/Y' );

		if ( 'cerrar_dia' === $accion ) {
			update_option( 'maffer_estado_sistema', 'cerrado' );

			// Send summary email on manual close.
			if ( function_exists( 'maffer_enviar_resumen' ) ) {
				$asunto = '[CIERRE] Resumen Semanal Cena ' . $hoy_fmt . ' - Servicio de Alimentacion Maffer';
				maffer_enviar_resumen( $asunto );
			}

			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_cerrado' ) );
		} else {
			update_option( 'maffer_estado_sistema', 'abierto' );
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_abierto' ) );
		}
		exit;
	}
}

// ════════════════════════════════════════════════════════════════
// 15. ADMIN-POST: GUARDAR HORARIO (schedule config)
// ════════════════════════════════════════════════════════════════

add_action( 'admin_post_maffer_guardar_horario', 'maffer_admin_guardar_horario' );

if ( ! function_exists( 'maffer_admin_guardar_horario' ) ) {
	/**
	 * Handle admin-post action: save recurring weekly schedule
	 * and destination email address.
	 *
	 * Expects POST fields: apertura_dia, apertura_hora, cierre_dia,
	 * cierre_hora, correo_destino.
	 */
	function maffer_admin_guardar_horario() {
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'maffer_admin_action' );

		$ap_dia  = (int) ( isset( $_POST['apertura_dia'] )  ? $_POST['apertura_dia']  : 6 );
		$ap_hora = sanitize_text_field( isset( $_POST['apertura_hora'] ) ? $_POST['apertura_hora'] : '12:00' );
		$cl_dia  = (int) ( isset( $_POST['cierre_dia'] )    ? $_POST['cierre_dia']    : 0 );
		$cl_hora = sanitize_text_field( isset( $_POST['cierre_hora'] )  ? $_POST['cierre_hora']  : '18:00' );

		if ( $ap_dia >= 0 && $ap_dia <= 6 ) {
			update_option( 'maffer_apertura_dia', $ap_dia );
		}
		if ( preg_match( '/^\d{2}:\d{2}$/', $ap_hora ) ) {
			update_option( 'maffer_apertura_hora', $ap_hora );
		}
		if ( $cl_dia >= 0 && $cl_dia <= 6 ) {
			update_option( 'maffer_cierre_dia', $cl_dia );
		}
		if ( preg_match( '/^\d{2}:\d{2}$/', $cl_hora ) ) {
			update_option( 'maffer_cierre_hora', $cl_hora );
		}

		if ( ! empty( $_POST['correo_destino'] ) ) {
			$correo = sanitize_email( $_POST['correo_destino'] );
			if ( $correo ) {
				update_option( 'maffer_correo_destino', $correo );
			}
		}

		maffer_limpiar_cache();
		wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=horario_ok' ) );
		exit;
	}
}

// ════════════════════════════════════════════════════════════════
// 16. ADMIN-POST: ENVIAR CORREO MANUAL
// ════════════════════════════════════════════════════════════════

add_action( 'admin_post_maffer_enviar_correo', 'maffer_admin_enviar_correo' );

if ( ! function_exists( 'maffer_admin_enviar_correo' ) ) {
	/**
	 * Handle admin-post action: manually send the summary email.
	 */
	function maffer_admin_enviar_correo() {
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'maffer_admin_action' );

		$tz      = new DateTimeZone( 'America/Santiago' );
		$dt      = new DateTime( 'now', $tz );
		$hoy_fmt = $dt->format( 'd/m/Y' );

		$asunto = 'Resumen ciclo Maffer ' . $hoy_fmt . ' — Servicio de Cena';

		if ( ! function_exists( 'maffer_enviar_resumen' ) ) {
			wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=correo_error' ) );
			exit;
		}

		$enviado = maffer_enviar_resumen( $asunto );
		$msg     = $enviado ? 'correo_ok' : 'correo_error';
		wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=' . $msg ) );
		exit;
	}
}

// ════════════════════════════════════════════════════════════════
// 17. SKIP LOGOUT CONFIRMATION
// ════════════════════════════════════════════════════════════════

add_action( 'check_admin_referer', 'maffer_skip_logout_confirmation', 10, 1 );

if ( ! function_exists( 'maffer_skip_logout_confirmation' ) ) {
	/**
	 * Bypass the WordPress logout confirmation screen for all users.
	 * When the 'log-out' action is requested, log out immediately and
	 * redirect to the configured redirect URL or home page.
	 *
	 * @param string $action The nonce action being verified.
	 */
	function maffer_skip_logout_confirmation( $action ) {
		if ( 'log-out' === $action ) {
			$redirect = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url();
			wp_logout();
			wp_safe_redirect( $redirect );
			exit;
		}
	}
}

// ════════════════════════════════════════════════════════════════
// 18. AJAX: CHANGE GESTOR PASSWORD
// ════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_maffer_cambiar_password', 'maffer_ajax_cambiar_password' );

if ( ! function_exists( 'maffer_ajax_cambiar_password' ) ) {
	/**
	 * AJAX handler for changing the current user's password.
	 *
	 * Expects POST fields:
	 * - nonce      (maffer_pwd_nonce)
	 * - current    (current password)
	 * - new_pass   (new password, min 8 chars)
	 *
	 * On success, the user is logged out and must log in again.
	 */
	function maffer_ajax_cambiar_password() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'maffer_pwd_nonce' ) ) {
			wp_send_json_error( 'Solicitud no válida.' );
		}
		if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$current  = isset( $_POST['current'] )  ? $_POST['current']  : '';
		$new_pass = isset( $_POST['new_pass'] ) ? $_POST['new_pass'] : '';

		if ( empty( $current ) || empty( $new_pass ) ) {
			wp_send_json_error( 'Completa todos los campos.' );
		}
		if ( strlen( $new_pass ) < 8 ) {
			wp_send_json_error( 'La nueva contraseña debe tener al menos 8 caracteres.' );
		}

		$user = wp_get_current_user();
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			wp_send_json_error( 'La contraseña actual no es correcta.' );
		}

		wp_set_password( $new_pass, $user->ID );
		wp_logout();
		wp_send_json_success( 'Contraseña actualizada.' );
	}
}
