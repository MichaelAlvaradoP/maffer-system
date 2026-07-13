<?php
/**
 * Maffer System — Module: Panel Core
 *
 * Roles, admin menu, cron, POST handlers, logout/redirect utilities.
 * Migrated from WPCode snippet 155 (Panel 7A — Roles y Menú).
 *
 * ## Related modules
 *
 * - `includes/excel.php` — `maffer_generar_xlsx()` (extracted from this file)
 * - `includes/email.php`  — `maffer_html_correo()`, `maffer_enviar_resumen()`,
 *                            `maffer_admin_enviar_correo_detalle()` (DETAILED email)
 * - `modules/panel-csv.php` — `admin_post_maffer_descargar_excel` (CSV/Excel download)
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
 * | `maffer_v6_procesar()` | Legacy POST fallback (called from panel-render) |
 * | `maffer_admin_guardar_menus()` | Admin-post: save menus |
 * | `maffer_admin_toggle_dia()` | Admin-post: open/close system |
 * | `maffer_admin_guardar_horario()` | Admin-post: save schedule config |
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
		// Los updates via PUC no re-ejecutan el activation hook, asi que el
		// administrador podria no tener la capability todavia: otorgar aqui
		// es idempotente (add_cap solo escribe si falta) (WO-017).
		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( 'maffer_manage_menu' ) ) {
			$admin_role->add_cap( 'maffer_manage_menu' );
		}

		add_menu_page(
			'Panel Maffer',
			'Panel Maffer',
			'maffer_manage_menu',
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

// NOTE: Sections 9 (XLSX), 10 (Email HTML), and 11 (Send Summary Email)
// have been moved to:
//   includes/excel.php  — maffer_generar_xlsx()
//   includes/email.php   — maffer_html_correo(), maffer_enviar_resumen()

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

		if ( 'guardar_menus' === $accion ) {
			$dias = array( 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo' );

			foreach ( $dias as $dia ) {
				$titles = isset( $_POST["maffer_titles_cena_{$dia}"] ) ? (array) $_POST["maffer_titles_cena_{$dia}"] : array();
				$descs  = isset( $_POST["maffer_descs_cena_{$dia}"] )  ? (array) $_POST["maffer_descs_cena_{$dia}"]  : array();
				$data   = array();
				for ( $i = 0; $i < count( $titles ); $i++ ) {
					$t = sanitize_text_field( isset( $titles[ $i ] ) ? $titles[ $i ] : '' );
					$d = sanitize_textarea_field( isset( $descs[ $i ] ) ? $descs[ $i ] : '' );
					if ( trim( $t ) !== '' ) {
						$data[] = array( 'title' => $t, 'desc' => $d );
					}
				}
				update_option( "maffer_menu_cena_{$dia}", $data );
			}
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

		$dias = array( 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo' );

		foreach ( $dias as $dia ) {
			$titles = isset( $_POST["maffer_titles_cena_{$dia}"] ) ? (array) $_POST["maffer_titles_cena_{$dia}"] : array();
			$descs  = isset( $_POST["maffer_descs_cena_{$dia}"] )  ? (array) $_POST["maffer_descs_cena_{$dia}"]  : array();
			$data   = array();
			
			for ( $i = 0; $i < count( $titles ); $i++ ) {
				$t = sanitize_text_field( isset( $titles[ $i ] ) ? $titles[ $i ] : '' );
				$d = sanitize_textarea_field( isset( $descs[ $i ] ) ? $descs[ $i ] : '' );
				if ( trim( $t ) !== '' ) {
					$data[] = array( 'title' => $t, 'desc' => $d );
				}
			}
			update_option( "maffer_menu_cena_{$dia}", $data );
		}

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

// NOTE: Section 16 (Admin-post: manual email) has been replaced by the
// DETAILED handler in includes/email.php (maffer_admin_enviar_correo_detalle).
// The SUMMARY handler is still used by cron auto-close and toggle-dia.

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
			$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url();
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
