<?php
/**
 * Maffer System — Module: Panel Render (7E)
 *
 * Admin panel HTML/CSS/JS rendering — the main dashboard UI.
 * Migrated from WPCode snippet 158 (Maffer - Panel 7E Render).
 *
 * ## Dependencies (loaded BEFORE this module)
 * - helpers.php       → maffer_fmt_ciclo_date(), maffer_compute_this_week_dt(), etc.
 * - panel-core.php    → maffer_v6_procesar(), maffer_aplicar_horario(), etc.
 * - panel-ajax.php    → AJAX handlers for create/edit/delete
 *
 * ## Function inventory
 * | Function | Purpose |
 * |----------|---------|
 * | `maffer_v6_render()` | Main admin panel page rendering (HTML/CSS/JS) |
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'maffer_v6_render' ) ) {

	/**
	 * Render the full Maffer admin panel with dashboard, menus,
	 * config, and historial tabs. All CSS/JS inline.
	 */

function maffer_v6_render() {
    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos para acceder a este panel.' );
    }

    if ( function_exists( 'maffer_aplicar_horario' ) ) {
        maffer_aplicar_horario();
    }

    maffer_v6_procesar();

    // ── Datos generales ─────────────────────────────────────
    $tz      = new DateTimeZone( 'America/Santiago' );
    $ahora   = new DateTime( 'now', $tz );
    $hoy     = $ahora->format( 'Y-m-d' );
    $hoy_fmt = $ahora->format( 'd/m/Y' );

    $dias_es  = array( 'Sunday'=>'domingo','Monday'=>'lunes','Tuesday'=>'martes',
                       'Wednesday'=>'miercoles','Thursday'=>'jueves',
                       'Friday'=>'viernes','Saturday'=>'sabado' );
    $meses_es = array( 1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                       7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre' );
    $fecha_larga = $dias_es[ $ahora->format('l') ] . ' ' . $ahora->format('j') . ' de '
                 . $meses_es[ (int)$ahora->format('n') ] . ' de ' . $ahora->format('Y');

    // Estado del sistema (ciclo semanal)
    $estado  = function_exists( 'maffer_obtener_estado_sistema' )
        ? maffer_obtener_estado_sistema()
        : get_option( 'maffer_estado_sistema', 'cerrado' );
    $abierto = ( $estado === 'abierto' );

    // Horario recurrente semanal
    $dias_semana = array(
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves',  5 => 'Viernes', 6 => 'Sábado',
    );
    $dias_semana_es = array(
        0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miércoles',
        4 => 'jueves',  5 => 'viernes', 6 => 'sábado',
    );
    $ap_dia  = (int) get_option( 'maffer_apertura_dia',  6 );
    $ap_hora = get_option( 'maffer_apertura_hora', '12:00' );
    $cl_dia  = (int) get_option( 'maffer_cierre_dia',   0 );
    $cl_hora = get_option( 'maffer_cierre_hora', '18:00' );

    // Calcular próximo ciclo para mostrar en el panel
    $proximo_ap_dt = null;
    $proximo_cl_dt = null;
    $proximo_ap_fmt = '';
    $proximo_cl_fmt = '';
    if ( function_exists( 'maffer_compute_this_week_dt' ) ) {
        $proximo_ap_dt = maffer_compute_this_week_dt( $ap_dia, $ap_hora, $tz );
        $proximo_cl_dt = maffer_compute_this_week_dt( $cl_dia, $cl_hora, $tz );
        if ( $proximo_ap_dt <= $ahora ) $proximo_ap_dt->modify( '+7 days' );
        if ( $proximo_cl_dt <= $ahora ) $proximo_cl_dt->modify( '+7 days' );
        $proximo_ap_fmt = $dias_semana_es[ (int)$proximo_ap_dt->format('w') ]
                        . ' ' . $proximo_ap_dt->format('j/m') . ' ' . $ap_hora;
        $proximo_cl_fmt = $dias_semana_es[ (int)$proximo_cl_dt->format('w') ]
                        . ' ' . $proximo_cl_dt->format('j/m') . ' ' . $cl_hora;
    }

    // Mantener compatibilidad con código que usa $apertura_programada
    $apertura_programada = ''; // ya no se usa como datetime-local

    // Fecha de inicio del ciclo
    $fecha_ciclo = function_exists( 'maffer_get_fecha_ciclo' ) ? maffer_get_fecha_ciclo() : null;
    $fecha_desde = $fecha_ciclo ?: $hoy;

    // Correo y menús (solo Cena) por día
    $correo     = get_option( 'maffer_correo_destino', get_option( 'admin_email' ) );
    
    $dias = array('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
    $dias_lbl = array(
        'lunes'     => 'Lunes',
        'martes'    => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves'    => 'Jueves',
        'viernes'   => 'Viernes',
        'sabado'    => 'Sábado',
        'domingo'   => 'Domingo'
    );
    $menus_por_dia = array();
    foreach ($dias as $dia) {
        $menus_por_dia[$dia] = get_option( "maffer_menu_cena_{$dia}", array() );
        if ( empty( $menus_por_dia[$dia] ) ) {
            $menus_por_dia[$dia] = array( array( 'title' => '', 'desc' => '' ) );
        }
    }

    $panel = isset( $_GET['panel'] ) ? sanitize_key( $_GET['panel'] ) : '';
    $msg   = isset( $_GET['msg'] )   ? sanitize_key( $_GET['msg'] )   : '';

    global $wpdb;
    $tabla   = $wpdb->prefix . 'maffer_registros';
    $t_exist = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tabla ) ) === $tabla );
    $registros = array();
    $total     = 0;
    $dist      = array();

    if ( $t_exist ) {
        $tabla_d = $wpdb->prefix . 'maffer_registro_detalles';
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla} r LEFT JOIN {$tabla_d} d ON r.id = d.registro_id WHERE r.fecha >= %s AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')", $fecha_desde
        ) );
        $registros_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id, r.fecha, r.hora, r.nombre, r.rut, r.observaciones, d.dia_semana, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo 
             FROM {$tabla} r 
             LEFT JOIN {$tabla_d} d ON r.id = d.registro_id 
             WHERE r.fecha >= %s AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00') 
             ORDER BY r.fecha DESC, r.id DESC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC", 
             $fecha_desde
        ), ARRAY_A );
        $agrupados_r = array();
        foreach ( $registros_raw as $r ) {
            $id = $r['id'];
            if ( ! isset( $agrupados_r[$id] ) ) {
                $agrupados_r[$id] = $r;
                $agrupados_r[$id]['selecciones'] = array();
            }
            if ( ! empty( $r['dia_semana'] ) ) {
                $agrupados_r[$id]['selecciones'][] = array( 'dia' => $r['dia_semana'], 'menu' => $r['menu_titulo'] );
            } elseif ( ! empty( $r['menu_titulo'] ) ) {
                $agrupados_r[$id]['selecciones'][] = array( 'dia' => 'Semana', 'menu' => $r['menu_titulo'] );
            }
        }
        $registros = array_values( $agrupados_r );
        $dist_raw  = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(d.menu_seleccionado, r.menu_titulo) as menu_titulo, COUNT(*) cnt FROM {$tabla} r 
             LEFT JOIN {$tabla_d} d ON r.id = d.registro_id 
             WHERE r.fecha >= %s AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00') 
             GROUP BY COALESCE(d.menu_seleccionado, r.menu_titulo) ORDER BY cnt DESC", $fecha_desde
        ), ARRAY_A );
        foreach ( $dist_raw as $d ) {
            $dist[ $d['menu_titulo'] ] = (int) $d['cnt'];
        }
    }

    $nonce_html = wp_nonce_field( 'maffer_admin_action', '_wpnonce', true, false );
    $nonce_ajax = wp_create_nonce( 'maffer_admin_nonce' );
    $ajax_url   = admin_url( 'admin-ajax.php' );
    $post_url   = admin_url( 'admin-post.php' );
    $panel_url  = admin_url( 'admin.php?page=maffer-panel' );

    $menu_colors = array( '#E67E22','#5a7a3a','#3a7a74','#7a3a5a','#5a3a7a','#3a5a7a' );

    $user  = wp_get_current_user();
    $parts = explode( ' ', trim( $user->display_name ) );
    $inits = strtoupper( substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' ) );

    ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
    #maf6-wrap{position:fixed;inset:0;left:0;top:0;background:#1c1710;z-index:-1;pointer-events:none}
    .wrap{padding:0!important;margin:0!important;background:transparent!important}
    #wpcontent{background:#1c1710!important;margin-left:0!important}
    #wpbody-content{background:#1c1710!important;padding-bottom:0!important}
    <?php if ( ! current_user_can( 'manage_options' ) ) : ?>
    #wpadminbar{display:none!important}
    #adminmenuwrap,#adminmenu,#adminmenuback{display:none!important}
    html.wp-toolbar{padding-top:0!important}
    body.wp-admin{padding-top:0!important}
    #wpwrap{padding-left:0!important}
    #wpbody{padding-left:0!important}
    <?php endif; ?>
    #wpfooter{display:none!important}
    html,body.wp-admin{height:100%!important;margin:0!important;padding:0!important}
    body.wp-admin{overflow-y:auto!important;background:#1c1710!important}
    #wpwrap,#wpcontent,#wpbody,#wpbody-content{min-height:100%!important}
    #wpbody-content .wrap{padding:24px 0 32px!important}
    #maf6,#maf6 *,#maf6 *::before,#maf6 *::after{box-sizing:border-box}
    #maf6 p,#maf6 h1,#maf6 h2,#maf6 h3{margin:0;padding:0}
    #maf6 button{font-family:inherit;cursor:pointer}
    #maf6 input,#maf6 select,#maf6 textarea{font-family:inherit}
    #maf6{
        --or:#E67E22;--or6:#d26a10;--or05:#fdf1e5;--or1:#fbe2cc;
        --gr:#5a7a3a;--grs:#eef3e4;--rd:#b94a32;--rds:#fbebe6;
        --bg:#f6efe3;--sf:#fffaf1;--sf2:#fbf3e4;
        --ink:#2a231a;--ink2:#6b5d4c;--ink3:#97897a;
        --bdr:#e6d8bf;--bdr2:#d8c59f;
        --sh1:0 1px 2px rgba(70,50,20,.04),0 8px 24px -12px rgba(70,50,20,.14);
        --sh2:0 1px 2px rgba(70,50,20,.06),0 18px 48px -20px rgba(70,50,20,.22);
        font-family:'Inter',system-ui,sans-serif;
        -webkit-font-smoothing:antialiased;
        color:var(--ink);
        max-width:1100px;
        margin:32px auto 60px;
        background:var(--bg);
        border-radius:20px;
        padding:28px 32px 48px;
        box-shadow:0 0 0 1px rgba(255,255,255,.04),0 40px 120px -20px rgba(0,0,0,.6);
    }
    .m6hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
    .m6mk{display:flex;align-items:center;gap:12px}
    .m6bg{width:40px;height:40px;border-radius:12px;background:var(--or);display:grid;place-items:center;box-shadow:0 4px 12px rgba(230,126,34,.4);flex-shrink:0}
    .m6wd{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;letter-spacing:-.02em;line-height:1}
    .m6ws{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink3);margin-top:4px;font-weight:500}
    .m6hr{display:flex;align-items:center;gap:10px}
    .m6dc{display:inline-flex;align-items:center;gap:7px;background:var(--sf);border:1px solid var(--bdr);padding:7px 13px;border-radius:999px;font-size:12px;font-weight:500;color:var(--ink2)}
    .m6av{width:38px;height:38px;border-radius:50%;background:var(--sf);border:1px solid var(--bdr);display:grid;place-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:12px;color:var(--or6);flex-shrink:0}
    .m6hero{display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;padding:28px 32px;background:var(--sf);border:1px solid var(--bdr);border-radius:24px;margin-bottom:22px;box-shadow:var(--sh1);position:relative;overflow:hidden}
    .m6hero::before{content:'';position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--or),#f0a35a)}
    .m6ey{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--or6);margin-bottom:6px}
    .m6tit{font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;font-weight:800;letter-spacing:-.025em;line-height:1.05;margin-bottom:8px}
    .m6sub{font-size:14px;color:var(--ink2);line-height:1.5;max-width:480px}
    .m6tog{display:flex;align-items:center;gap:16px;border-radius:18px;padding:14px 16px 14px 18px;cursor:pointer;font-family:inherit;transition:all .2s;position:relative;border:1.5px solid;background:transparent}
    .m6tog.on{background:#eef4e7;border-color:rgba(90,122,58,.35)}
    .m6tog.off{background:#fbebe6;border-color:rgba(185,74,50,.25)}
    .m6tog:hover{transform:translateY(-1px);box-shadow:var(--sh1)}
    .m6dot{width:14px;height:14px;border-radius:50%;position:relative;flex-shrink:0}
    .m6dot.on{background:var(--gr)}.m6dot.off{background:var(--rd)}
    .m6pls{position:absolute;inset:-4px;border-radius:50%;opacity:.35;animation:m6pulse 1.8s ease-out infinite}
    .m6dot.on .m6pls{background:var(--gr)}.m6dot.off .m6pls{background:var(--rd)}
    @keyframes m6pulse{0%{transform:scale(.7);opacity:.5}70%,100%{transform:scale(1.7);opacity:0}}
    .m6ttx{line-height:1.2;padding-right:14px;border-right:1px dashed var(--bdr2)}
    .m6tlb{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--ink3);margin-bottom:3px}
    .m6tvl{font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;letter-spacing:-.02em}
    .m6tog.on .m6tvl{color:var(--gr)}.m6tog.off .m6tvl{color:var(--rd)}
    .m6tac{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:13px;color:#fff}
    .m6tog.off .m6tac{background:linear-gradient(180deg,#74a04a,var(--gr) 60%,#4a6830);box-shadow:0 1px 0 rgba(255,255,255,.25) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(90,122,58,.6)}
    .m6tog.on  .m6tac{background:linear-gradient(180deg,#cd5a3e,var(--rd) 60%,#9c3d28);box-shadow:0 1px 0 rgba(255,255,255,.25) inset,0 -2px 0 rgba(0,0,0,.15) inset,0 6px 14px -4px rgba(185,74,50,.6)}
    .m6krow{display:grid;grid-template-columns:1fr 1.4fr 1fr;gap:14px;margin-bottom:22px}
    .m6kpi{background:var(--sf);border:1px solid var(--bdr);border-radius:18px;padding:18px 20px;display:flex;align-items:flex-start;gap:14px;box-shadow:var(--sh1);position:relative}
    .m6ki{width:40px;height:40px;border-radius:12px;background:var(--or05);color:var(--or6);display:grid;place-items:center;flex-shrink:0}
    .m6ki.gr{background:var(--grs);color:var(--gr)}.m6ki.rd{background:var(--rds);color:var(--rd)}
    .m6kb{flex:1;min-width:0}
    .m6kv{font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;font-weight:800;letter-spacing:-.03em;line-height:1;color:var(--ink);margin-bottom:5px}
    .m6kv.sm{font-size:18px;letter-spacing:-.01em}
    .m6kl{font-size:12px;color:var(--ink3);font-weight:600;text-transform:uppercase;letter-spacing:.08em}
    .m6pie{display:flex;height:8px;border-radius:4px;overflow:hidden;background:var(--sf2);margin-bottom:10px}
    .m6pie span{display:block;height:100%}
    .m6leg{display:flex;flex-wrap:wrap;gap:10px;font-size:11px;font-weight:600;color:var(--ink2)}
    .m6leg span{display:inline-flex;align-items:center;gap:5px}
    .m6leg i{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
    .m6acts{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
    .m6act{display:flex;align-items:center;gap:14px;background:var(--sf);border:1px solid var(--bdr);border-radius:16px;padding:16px 18px;cursor:pointer;text-align:left;transition:all .18s;color:var(--ink);text-decoration:none;width:100%}
    .m6act:hover{border-color:var(--bdr2);transform:translateY(-1px);box-shadow:var(--sh1)}
    .m6ai{width:42px;height:42px;border-radius:12px;background:var(--or05);color:var(--or6);display:grid;place-items:center;flex-shrink:0}
    .m6ab{flex:1;min-width:0}
    .m6at{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;letter-spacing:-.01em;margin-bottom:3px}
    .m6as{font-size:12px;color:var(--ink3)}
    .m6ach{color:var(--ink3);transition:transform .15s;flex-shrink:0}
    .m6act:hover .m6ach{transform:translateX(3px);color:var(--or)}
    .m6ts{background:var(--sf);border:1px solid var(--bdr);border-radius:20px;padding:22px 24px 36px;box-shadow:var(--sh1)}
    .m6tth{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
    .m6ttit{font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;letter-spacing:-.02em;margin-bottom:8px}
    .m6ttit .ac{color:var(--or)}
    .m6tsub{font-size:12px;color:var(--ink3);font-weight:500}
    .m6tctr{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .m6srch{display:flex;align-items:center;gap:8px;background:var(--sf2);border:1px solid var(--bdr);border-radius:10px;padding:8px 12px;width:240px;color:var(--ink3);transition:all .15s}
    .m6srch:focus-within{border-color:var(--or);background:var(--sf);box-shadow:0 0 0 3px rgba(230,126,34,.15)}
    .m6srch input{flex:1;border:0;outline:0;background:transparent;font-family:inherit;font-size:13px;color:var(--ink)}
    .m6srch input::placeholder{color:var(--ink3)}
    .m6chs{display:flex;background:var(--sf2);border:1px solid var(--bdr);border-radius:10px;padding:3px;gap:2px}
    .m6ch{background:transparent;border:0;font-size:12px;font-weight:600;padding:6px 11px;border-radius:7px;color:var(--ink2);transition:all .15s}
    .m6ch.on{background:var(--sf);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.05),0 0 0 1px var(--bdr)}
    .m6tw{border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
    table.m6tbl{width:100%;border-collapse:collapse;font-size:13px}
    table.m6tbl thead th{background:var(--sf2);padding:11px 14px;text-align:left;font-weight:700;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink3);border-bottom:1px solid var(--bdr)}
    table.m6tbl tbody td{padding:13px 14px;border-bottom:1px solid var(--bdr);color:var(--ink)}
    table.m6tbl tbody tr:last-child td{border-bottom:0}
    table.m6tbl tbody tr{transition:background .12s}
    table.m6tbl tbody tr:hover{background:var(--sf2)}
    .m6mn{font-family:'JetBrains Mono',ui-monospace,monospace;font-size:12px;color:var(--ink2)}
    .m6nm{font-weight:600}.m6mt{color:var(--ink3)}
    .m6emp{text-align:center;color:var(--ink3);padding:36px;font-style:italic}
    .m6pll{display:inline-flex;align-items:center;gap:7px;padding:4px 10px 4px 8px;background:var(--sf2);border:1px solid var(--bdr);border-radius:999px;font-size:12px;font-weight:600;color:var(--ink)}
    .m6pll i{width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0}
    .m6mp{background:var(--sf);border:1px solid var(--bdr);border-radius:20px;padding:22px 24px;margin-top:20px;box-shadow:var(--sh1)}
    .m6stt{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;letter-spacing:-.02em;margin-bottom:16px}
    .m6mi{background:var(--sf2);border:1.5px solid var(--bdr);border-radius:14px;padding:16px;margin-bottom:12px;transition:border-color .15s}
    .m6mi:focus-within{border-color:var(--bdr2)}
    .m6mih{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .m6min{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:800;color:var(--or);text-transform:uppercase;letter-spacing:.06em}
    .m6rm{background:none;border:none;font-size:12px;font-weight:600;color:var(--ink3);padding:4px 8px;border-radius:7px;display:flex;align-items:center;gap:4px;transition:all .12s}
    .m6rm:hover{background:var(--rds);color:var(--rd)}
    .m6lb{display:block;font-size:11px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;font-family:'Plus Jakarta Sans',sans-serif}
    .m6in,.m6ta{width:100%;background:var(--sf);border:1.5px solid var(--bdr);border-radius:10px;padding:10px 12px;font-size:14px;color:var(--ink);outline:none;transition:border-color .15s,box-shadow .15s}
    .m6in:focus,.m6ta:focus{border-color:var(--or);box-shadow:0 0 0 3px rgba(230,126,34,.16)}
    .m6ta{resize:vertical;min-height:72px;line-height:1.5}
    .m6fd{margin-bottom:12px}
    .m6ab2{width:100%;display:flex;align-items:center;justify-content:center;gap:7px;background:var(--sf2);border:1.5px dashed var(--bdr2);border-radius:12px;padding:11px 16px;font-size:13px;font-weight:700;color:var(--ink2);font-family:'Plus Jakarta Sans',sans-serif;margin-bottom:18px;transition:all .12px}
    .m6ab2:hover{background:var(--or05);border-color:var(--or1);color:var(--or)}
    .btn-s{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#ef8a2d,var(--or) 60%,var(--or6));border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 18px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.6);transition:transform .1s}
    .btn-s:hover{transform:translateY(-1px)}
    .btn-s.full{width:100%;justify-content:center;padding:14px 18px;font-size:15px}
    .btn-g{background:var(--sf2);border:1.5px solid var(--bdr);color:var(--ink);font-family:inherit;font-weight:600;font-size:14px;padding:10px 16px;border-radius:10px;transition:all .15s}
    .btn-g:hover{background:var(--sf);border-color:var(--bdr2)}
    .m6bk{display:none;position:fixed;inset:0;background:rgba(20,15,8,.65);place-items:center;z-index:999990;padding:20px}
    .m6bk.open{display:grid}
    .m6md{background:#fffaf1;border:1px solid #e6d8bf;border-radius:22px;box-shadow:0 8px 60px rgba(0,0,0,.35);width:100%;max-width:500px;overflow:hidden;animation:m6rise .22s cubic-bezier(.2,1.1,.4,1)}
    @keyframes m6rise{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
    .m6mdsm{max-width:430px}
    .m6mhd{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:22px 24px 16px;background:#fffaf1}
    .m6mey{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#d26a10;margin-bottom:4px}
    .m6mtt{font-family:'Plus Jakarta Sans',sans-serif;font-size:19px;font-weight:800;letter-spacing:-.02em;color:#2a231a}
    .m6mcl{background:#fbf3e4;border:1px solid #e6d8bf;color:#6b5d4c;width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:18px;line-height:1;cursor:pointer;transition:all .12s}
    .m6mcl:hover{background:#fdf1e5;color:#E67E22}
    .m6mb{padding:0 24px 20px;background:#fffaf1}
    .m6mds{font-size:13px;color:#6b5d4c;line-height:1.55;margin-bottom:16px}
    .m6mft{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px 20px;border-top:1px solid #e6d8bf;background:#fbf3e4}
    .m6mm{margin:10px 0 0;font-size:13px;font-weight:600;padding:9px 13px;border-radius:9px;border:1px solid;display:none}
    .m6mm.ok{background:#eef3e4;color:#5a7a3a;border-color:rgba(90,122,58,.3)}
    .m6mm.er{background:#fbebe6;color:#b94a32;border-color:rgba(185,74,50,.3)}
    .m6cc{background:var(--sf);border:1px solid var(--bdr);border-radius:18px;padding:22px 24px;margin-bottom:16px;box-shadow:var(--sh1)}
    .m6ct{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;margin-bottom:14px;color:var(--ink)}
    .m6alrt{display:flex;align-items:center;gap:9px;padding:12px 16px;border-radius:11px;font-size:13px;font-weight:600;margin-bottom:18px;border:1px solid}
    .m6tst{position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#1f1a14;color:#f5ede1;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;box-shadow:0 8px 40px rgba(0,0,0,.4);z-index:1000000;border:1px solid rgba(255,255,255,.08);animation:m6toast .3s cubic-bezier(.3,1.3,.5,1) both;pointer-events:none;white-space:nowrap}
    @keyframes m6toast{from{opacity:0;transform:translate(-50%,10px)}to{opacity:1;transform:translate(-50%,0)}}
    .m6tic{color:var(--or);background:var(--or05);border-radius:50%;padding:3px;width:20px;height:20px;display:grid;place-items:center}
    @media(max-width:1160px){#maf6{margin-left:20px;margin-right:20px}}
    @media(max-width:900px){
        #maf6{padding:18px 16px 40px;margin-left:12px;margin-right:12px}
        .m6hero{grid-template-columns:1fr;padding:22px}
        .m6krow{grid-template-columns:1fr 1fr}
        .m6krow .m6kpi:nth-child(2){grid-column:1/-1}
        .m6acts{grid-template-columns:1fr 1fr}
        .m6tit{font-size:26px}
    }
    @media(max-width:560px){
        #maf6{margin-left:8px;margin-right:8px}
        .m6dc{display:none}
        .m6krow{grid-template-columns:1fr}
        .m6krow .m6kpi:nth-child(2){grid-column:auto}
        .m6srch{width:100%}
        .m6tctr{flex-direction:column;align-items:stretch}
    }
    </style>

    <div id="maf6-wrap" aria-hidden="true"></div>
    <div id="maf6">

    <!-- HEAD -->
    <header class="m6hd">
        <div class="m6mk">
            <div class="m6bg">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13c0-4.4 3.6-8 8-8s8 3.6 8 8"/><line x1="2" y1="17" x2="22" y2="17"/><line x1="4" y1="21" x2="20" y2="21"/><line x1="12" y1="3" x2="12" y2="5"/></svg>
            </div>
            <div>
                <div class="m6wd">maffer</div>
                <div class="m6ws">panel de administracion</div>
            </div>
        </div>
        <div class="m6hr">
            <div class="m6dc">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span><?php echo esc_html( $fecha_larga ); ?></span>
            </div>
            <?php if ( ! current_user_can( 'manage_options' ) ) : ?>
            <div style="position:relative" id="m6avwrap">
                <button id="m6avbtn" onclick="m6toggleUser()" title="<?php echo esc_attr( $user->display_name ); ?>"
                    style="width:38px;height:38px;border-radius:50%;background:var(--sf);border:1px solid var(--bdr);display:grid;place-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:12px;color:var(--or6);flex-shrink:0;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.borderColor='var(--bdr2)';this.style.background='var(--or05)'"
                    onmouseout="this.style.borderColor='var(--bdr)';this.style.background='var(--sf)'">
                    <?php echo esc_html( $inits ); ?>
                </button>
                <div id="m6avdrop" style="display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fffaf1;border:1px solid #e6d8bf;border-radius:14px;box-shadow:0 8px 32px rgba(70,50,20,.18);min-width:200px;z-index:99999;overflow:hidden;animation:m6rise .18s cubic-bezier(.2,1.1,.4,1)">
                    <div style="padding:14px 16px 12px;border-bottom:1px solid #f0e4c8">
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:13px;color:#2a231a;margin-bottom:2px"><?php echo esc_html( $user->display_name ); ?></div>
                        <div style="font-size:11px;color:#97897a"><?php echo esc_html( $user->user_email ); ?></div>
                    </div>
                    <div style="padding:6px">
                        <button onclick="m6toggleUser();document.getElementById('m6mpwd').classList.add('open')"
                            style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;color:#6b5d4c;font-size:13px;font-weight:600;transition:background .12s;background:transparent;border:0;width:100%;cursor:pointer;font-family:inherit"
                            onmouseover="this.style.background='#fdf1e5'" onmouseout="this.style.background='transparent'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
                            Cambiar contraseña
                        </button>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
                            style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;text-decoration:none;color:#b94a32;font-size:13px;font-weight:600;transition:background .12s"
                            onmouseover="this.style.background='#fbebe6'" onmouseout="this.style.background='transparent'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Cerrar sesión
                        </a>
                    </div>
                </div>
            </div>
            <?php else : ?>
            <div class="m6av" title="<?php echo esc_attr( $user->display_name ); ?>"><?php echo esc_html( $inits ); ?></div>
            <?php endif; ?>
        </div>
    </header>

    <?php
    $msgs = array(
        'menus_ok'     => array( 'ok',   'Menús de cena guardados correctamente.' ),
        'config_ok'    => array( 'ok',   'Configuracion guardada.' ),
        'horario_ok'   => array( 'ok',   'Configuracion guardada.' ),
        'dia_cerrado'  => array( 'info', 'Sistema cerrado.' ),
        'dia_abierto'  => array( 'info', 'Sistema abierto. Los colaboradores pueden registrarse.' ),
        'correo_ok'    => array( 'ok',   'Correo enviado correctamente.' ),
        'correo_error' => array( 'err',  'Error al enviar correo. Revisa la configuracion SMTP.' ),
    );
    if ( $msg && isset( $msgs[ $msg ] ) ) :
        list( $tipo, $texto ) = $msgs[ $msg ];
        $bg = $tipo === 'ok'  ? 'background:#eef3e4;color:#5a7a3a;border-color:rgba(90,122,58,.3)'
            : ( $tipo === 'err' ? 'background:#fbebe6;color:#b94a32;border-color:rgba(185,74,50,.3)'
            : 'background:#fdf1e5;color:#d26a10;border-color:rgba(230,126,34,.3)' );
    ?>
    <div class="m6alrt" style="<?php echo $bg; ?>">
        <?php echo esc_html( $texto ); ?>
        <button onclick="this.parentNode.remove()" style="margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:inherit;line-height:1">&times;</button>
    </div>
    <?php endif; ?>

    <?php if ( $panel === 'menus' ) : ?>
    <!-- ═══ PANEL MENUS (solo Cena) ═══ -->
    <div style="margin-bottom:16px">
        <a href="<?php echo esc_url( $panel_url ); ?>" style="font-size:13px;font-weight:600;color:var(--or6);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Volver al dashboard
        </a>
    </div>
    <div class="m6mp">
        <div class="m6stt">Gestión de menús — Cena</div>
        <p style="font-size:13px;color:var(--ink3);margin-bottom:20px;line-height:1.6">Define las opciones que verán los colaboradores en el formulario de registro.</p>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="m6fmenus">
            <?php echo $nonce_html; ?>
            <input type="hidden" name="action" value="maffer_guardar_menus">
            <input type="hidden" name="maffer_accion" value="guardar_menus">

            <style>
                .m6-dia-box { background:var(--sf2); border:1px solid var(--bdr); border-radius:12px; padding:16px; margin-bottom:16px; }
                .m6-dia-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:16px; margin-bottom:12px; text-transform:capitalize; }
            </style>
            <?php foreach ( $dias as $dia ) : ?>
            <div class="m6-dia-box">
                <div class="m6-dia-title"><?php echo esc_html( $dias_lbl[$dia] ); ?></div>
                <div id="m6icnt-cena-<?php echo esc_attr( $dia ); ?>">
                    <?php foreach ( $menus_por_dia[$dia] as $idx => $m ) : ?>
                    <div class="m6mi">
                        <div class="m6mih">
                            <span class="m6min">Menú <?php echo $idx + 1; ?></span>
                            <button type="button" class="m6rm" onclick="m6rm(this, '<?php echo esc_js( $dia ); ?>')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Eliminar
                            </button>
                        </div>
                        <div class="m6fd">
                            <label class="m6lb">Titulo *</label>
                            <input type="text" name="maffer_titles_cena_<?php echo esc_attr( $dia ); ?>[]" class="m6in" placeholder="Ej: Menú Cena Ligera" value="<?php echo esc_attr( $m['title'] ); ?>" required>
                        </div>
                        <div class="m6fd">
                            <label class="m6lb">Descripcion (visible en el formulario)</label>
                            <textarea name="maffer_descs_cena_<?php echo esc_attr( $dia ); ?>[]" class="m6ta" placeholder="Ej: Sopa, sandwich, postre"><?php echo esc_textarea( $m['desc'] ); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="m6ab2" onclick="m6add('<?php echo esc_js( $dia ); ?>')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Agregar opción de menú (<?php echo esc_html( $dia ); ?>)
                </button>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn-s full" style="margin-top:8px">Guardar menús de cena</button>
        </form>
    </div>

    <?php elseif ( $panel === 'historial' ) : ?>
    <!-- ═══ PANEL HISTORIAL POR CICLOS ═══ -->
    <?php
    // Ver detalle de un ciclo específico (por fecha de inicio del sábado)
    $ciclo_sel = isset( $_GET['ciclo'] ) ? sanitize_text_field( $_GET['ciclo'] ) : '';
    $regs_ciclo = array();
    $dist_ciclo = array();
    $ciclo_fin  = '';

    if ( $ciclo_sel && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ciclo_sel ) ) {
        // Fin del ciclo = domingo de esa misma semana (ciclo_sel = sábado, +1 día = domingo)
        $dt_fin    = new DateTime( $ciclo_sel, $tz );
        $dt_fin->modify( '+8 days' ); // hasta el sábado siguiente (exclusivo) para capturar todo el ciclo
        $ciclo_fin = $dt_fin->format( 'Y-m-d' );

        if ( $t_exist ) {
            $tabla_d = $wpdb->prefix . 'maffer_registro_detalles';
            $regs_ciclo_raw = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.id, r.fecha, r.hora, r.nombre, r.rut, r.observaciones, d.dia_semana, COALESCE(d.menu_titulo, r.menu_titulo) as menu_titulo 
                 FROM {$tabla} r LEFT JOIN {$tabla_d} d ON r.id = d.registro_id
                 WHERE DATE_SUB(r.fecha, INTERVAL (WEEKDAY(r.fecha)+2)%%7 DAY) = %s
                 AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')
                 ORDER BY r.fecha ASC, r.id ASC, FIELD(d.dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo') ASC",
                $ciclo_sel
            ), ARRAY_A );
            $agrupados_c = array();
            foreach ( $regs_ciclo_raw as $r ) {
                $mn = $r['menu_titulo'] ?: 'Sin titulo';
                $dist_ciclo[ $mn ] = isset( $dist_ciclo[ $mn ] ) ? $dist_ciclo[ $mn ] + 1 : 1;
                
                $id = $r['id'];
                if ( ! isset( $agrupados_c[$id] ) ) {
                    $agrupados_c[$id] = $r;
                    $agrupados_c[$id]['selecciones'] = array();
                }
                if ( ! empty( $r['dia_semana'] ) ) {
                    $agrupados_c[$id]['selecciones'][] = array( 'dia' => $r['dia_semana'], 'menu' => $r['menu_titulo'] );
                } elseif ( ! empty( $r['menu_titulo'] ) ) {
                    $agrupados_c[$id]['selecciones'][] = array( 'dia' => 'Semana', 'menu' => $r['menu_titulo'] );
                }
            }
            $regs_ciclo = array_values( $agrupados_c );
        }
    }

    // Lista de ciclos agrupados por sábado
    $ciclos_lista = array();
    if ( $t_exist ) {
        $ciclos_lista = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                DATE_SUB(r.fecha, INTERVAL (WEEKDAY(r.fecha)+2)%7 DAY) AS ciclo_inicio,
                COUNT(DISTINCT r.id) AS total,
                MIN(r.fecha) AS primer_dia,
                MAX(r.fecha) AS ultimo_dia
             FROM {$tabla} r
             WHERE (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')
             GROUP BY ciclo_inicio
             ORDER BY ciclo_inicio DESC"
        ), ARRAY_A );
    }

    $dias_es_h  = array( 'Sunday'=>'domingo','Monday'=>'lunes','Tuesday'=>'martes',
                         'Wednesday'=>'miércoles','Thursday'=>'jueves',
                         'Friday'=>'viernes','Saturday'=>'sábado' );
    $meses_es_h = array( 1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',
                         7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic' );
    // NOTE: maffer_fmt_ciclo_date() is in helpers.php � DO NOT redefine here
    ?>
    <div style="margin-bottom:16px">
        <a href="<?php echo esc_url( $panel_url ); ?>" style="font-size:13px;font-weight:600;color:var(--or6);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Volver al dashboard
        </a>
    </div>

    <?php if ( $ciclo_sel && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ciclo_sel ) ) : ?>
    <!-- Detalle de ciclo específico -->
    <?php
        $dt_inicio_ciclo = new DateTime( $ciclo_sel, $tz );
        $label_ciclo = 'Ciclo del ' . maffer_fmt_ciclo_date( $ciclo_sel, $dias_es_h, $meses_es_h );
    ?>
    <div style="margin-bottom:16px">
        <a href="<?php echo esc_url( admin_url('admin.php?page=maffer-panel&panel=historial') ); ?>"
            style="font-size:13px;font-weight:600;color:var(--ink2);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Todos los ciclos
        </a>
    </div>
    <div class="m6mp">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div>
                <div class="m6stt" style="margin-bottom:4px"><?php echo esc_html( $label_ciclo ); ?></div>
                <p style="font-size:13px;color:var(--ink3);margin:0"><?php echo count( $regs_ciclo ); ?> registros en este ciclo</p>
            </div>
            <?php if ( ! empty( $regs_ciclo ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php echo $nonce_html; ?>
                <input type="hidden" name="action" value="maffer_descargar_excel">
                <input type="hidden" name="fecha_desde" value="<?php echo esc_attr( $ciclo_sel ); ?>">
                <input type="hidden" name="fecha_hasta" value="<?php echo esc_attr( ( new DateTime($ciclo_sel, $tz) )->modify('+7 days')->format('Y-m-d') ); ?>">
                <button type="submit" class="btn-s" style="padding:9px 16px;font-size:13px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                    Descargar Excel
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ( ! empty( $regs_ciclo ) ) : ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:20px">
            <div style="background:var(--sf2);border:1px solid var(--bdr);border-radius:14px;padding:14px 16px">
                <div style="font-size:11px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px">Total</div>
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;font-weight:800;color:var(--or)"><?php echo count( $regs_ciclo ); ?></div>
            </div>
            <?php foreach ( $dist_ciclo as $mn => $cn ) :
                $ic = array_search( $mn, array_keys( $dist_ciclo ), true );
                $mc = $menu_colors[ $ic % count( $menu_colors ) ]; ?>
            <div style="background:var(--sf2);border:1px solid var(--bdr);border-radius:14px;padding:14px 16px">
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:5px">
                    <span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr($mc); ?>;flex-shrink:0;display:inline-block"></span>
                    <span style="font-size:10px;font-weight:700;color:var(--ink3);text-transform:uppercase;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html( $mn ); ?></span>
                </div>
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;font-weight:800;color:var(--ink)"><?php echo $cn; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="m6tw">
            <table class="m6tbl">
                <thead><tr>
                    <th style="width:90px">Fecha</th>
                    <th style="width:70px">Hora</th>
                    <th>Nombre</th>
                    <th style="width:140px">RUT</th>
                    <th>Menú</th>
                    <th>Obs.</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $regs_ciclo as $reg ) :
                    $f_row = ! empty( $reg['fecha'] ) ? ( new DateTime( $reg['fecha'] ) )->format('d/m') : '—'; ?>
                <tr>
                    <td class="m6mn m6mt"><?php echo esc_html( $f_row ); ?></td>
                    <td class="m6mn"><?php echo esc_html( substr( $reg['hora'], 0, 5 ) ); ?></td>
                    <td class="m6nm"><?php echo esc_html( $reg['nombre'] ); ?></td>
                    <td class="m6mn m6mt"><?php echo esc_html( $reg['rut'] ); ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap">
                            <?php if ( ! empty( $reg['selecciones'] ) ) : ?>
                                <?php foreach ( $reg['selecciones'] as $sel ) : 
                                    $mi_s = array_search( $sel['menu'], array_keys( $dist_ciclo ), true );
                                    $mc_s = $menu_colors[ ( $mi_s !== false ? $mi_s : 0 ) % count( $menu_colors ) ];
                                ?>
                                <span class="m6pll" style="padding-left:6px;padding-right:8px;font-size:11px"><i style="background:<?php echo esc_attr( $mc_s ); ?>;width:6px;height:6px;margin-right:4px"></i><strong style="text-transform:capitalize;margin-right:4px;color:var(--ink2)"><?php echo esc_html( substr( $sel['dia'], 0, 2 ) ); ?>:</strong><?php echo esc_html( $sel['menu'] ?: 'Sin titulo' ); ?></span>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span class="m6pll"><i style="background:#ccc"></i>Sin título</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--ink3)"><?php echo esc_html( $reg['observaciones'] ?: '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
        <div class="m6emp">Sin registros en este ciclo.</div>
        <?php endif; ?>
    </div>

    <?php else : ?>
    <!-- Lista de todos los ciclos -->
    <div class="m6mp">
        <div style="margin-bottom:20px">
            <div class="m6stt" style="margin-bottom:4px">Historial de ciclos</div>
            <p style="font-size:13px;color:var(--ink3);margin:0">Cada ciclo va del sábado al domingo. Haz clic en un ciclo para ver el detalle.</p>
        </div>
        <?php if ( empty( $ciclos_lista ) ) : ?>
        <div class="m6emp" style="padding:40px;text-align:center">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:12px"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px">Sin ciclos registrados</div>
            <div style="font-size:13px">Aún no hay registros en el sistema.</div>
        </div>
        <?php else : ?>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ( $ciclos_lista as $idx => $ciclo ) :
                $c_inicio   = $ciclo['ciclo_inicio'];
                $c_total    = (int) $ciclo['total'];
                $c_primer   = $ciclo['primer_dia'];
                $c_ultimo   = $ciclo['ultimo_dia'];
                $label_ini  = maffer_fmt_ciclo_date( $c_inicio, $dias_es_h, $meses_es_h );
                $label_ult  = maffer_fmt_ciclo_date( $c_ultimo, $dias_es_h, $meses_es_h );
                $es_actual  = ( $fecha_ciclo && $c_inicio === $fecha_ciclo );
                $det_url    = admin_url( 'admin.php?page=maffer-panel&panel=historial&ciclo=' . $c_inicio );
            ?>
            <a href="<?php echo esc_url( $det_url ); ?>"
                style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 20px;background:var(--sf);border:1.5px solid <?php echo $es_actual ? 'var(--or1)' : 'var(--bdr)'; ?>;border-radius:16px;text-decoration:none;color:var(--ink);transition:all .15s"
                onmouseover="this.style.borderColor='var(--bdr2)';this.style.background='var(--sf2)'"
                onmouseout="this.style.borderColor='<?php echo $es_actual ? 'var(--or1)' : 'var(--bdr)'; ?>';this.style.background='var(--sf)'">
                <div style="display:flex;align-items:center;gap:14px">
                    <div style="width:42px;height:42px;border-radius:12px;background:<?php echo $es_actual ? 'var(--or05)' : 'var(--sf2)'; ?>;border:1px solid <?php echo $es_actual ? 'var(--or1)' : 'var(--bdr)'; ?>;display:grid;place-items:center;flex-shrink:0">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?php echo $es_actual ? 'var(--or)' : 'var(--ink3)'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </div>
                    <div>
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;margin-bottom:3px">
                            <?php echo 'Ciclo ' . esc_html( $label_ini ); ?>
                            <?php if ( $es_actual ) : ?><span style="margin-left:8px;font-size:10px;font-weight:700;background:var(--or);color:#fff;padding:2px 8px;border-radius:99px">Actual</span><?php endif; ?>
                        </div>
                        <?php
                            // Mostrar rango completo del ciclo: sábado → viernes siguiente
                            $dt_viernes = new DateTime( $c_inicio, $tz );
                            $dt_viernes->modify( '+6 days' ); // sábado + 6 = viernes
                            $label_fin_ciclo = maffer_fmt_ciclo_date( $dt_viernes->format('Y-m-d'), $dias_es_h, $meses_es_h );
                        ?>
                        <div style="font-size:12px;color:var(--ink3)">
                            <?php echo esc_html( $label_ini ); ?> → <?php echo esc_html( $label_fin_ciclo ); ?> (<?php echo $c_total; ?> registros)
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:20px">
                    <div style="text-align:right">
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:800;color:var(--or);line-height:1"><?php echo $c_total; ?></div>
                        <div style="font-size:11px;color:var(--ink3);font-weight:600;text-transform:uppercase;letter-spacing:.06em">registros</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ink3)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; // detalle vs lista ?>

    <?php elseif ( $panel === 'config' ) : ?>
    <!-- ═══ PANEL CONFIG ═══ -->
    <div style="margin-bottom:16px">
        <a href="<?php echo esc_url( $panel_url ); ?>" style="font-size:13px;font-weight:600;color:var(--or6);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Volver al dashboard
        </a>
    </div>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <?php echo $nonce_html; ?>
        <input type="hidden" name="action" value="maffer_guardar_horario">
        <input type="hidden" name="maffer_accion" value="guardar_horario">
        <div class="m6cc">
            <div class="m6ct">Horario semanal recurrente</div>
            <p style="font-size:13px;color:var(--ink3);margin-bottom:20px;line-height:1.6">
                Configura una sola vez. El sistema abre y cierra automáticamente cada semana en estos días y horarios,
                sin necesidad de reprogramar. Al cerrar, envía el resumen por correo automáticamente.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px">
                <!-- Apertura -->
                <div>
                    <div style="display:flex;align-items:center;gap:7px;margin-bottom:12px">
                        <span style="width:8px;height:8px;border-radius:50%;background:var(--gr);display:inline-block"></span>
                        <span class="m6lb" style="margin-bottom:0">Apertura</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div class="m6fd" style="margin-bottom:0">
                            <label class="m6lb">Día</label>
                            <select name="apertura_dia" class="m6in" style="cursor:pointer">
                                <?php foreach ( $dias_semana as $val => $label ) : ?>
                                <option value="<?php echo $val; ?>" <?php selected( $ap_dia, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="m6fd" style="margin-bottom:0">
                            <label class="m6lb">Hora</label>
                            <input type="time" name="apertura_hora" class="m6in"
                                value="<?php echo esc_attr( $ap_hora ); ?>">
                        </div>
                    </div>
                </div>
                <!-- Cierre -->
                <div>
                    <div style="display:flex;align-items:center;gap:7px;margin-bottom:12px">
                        <span style="width:8px;height:8px;border-radius:50%;background:var(--rd);display:inline-block"></span>
                        <span class="m6lb" style="margin-bottom:0">Cierre + envío de correo</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div class="m6fd" style="margin-bottom:0">
                            <label class="m6lb">Día</label>
                            <select name="cierre_dia" class="m6in" style="cursor:pointer">
                                <?php foreach ( $dias_semana as $val => $label ) : ?>
                                <option value="<?php echo $val; ?>" <?php selected( $cl_dia, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="m6fd" style="margin-bottom:0">
                            <label class="m6lb">Hora</label>
                            <input type="time" name="cierre_hora" class="m6in"
                                value="<?php echo esc_attr( $cl_hora ); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximo ciclo calculado -->
            <?php if ( $proximo_ap_fmt && $proximo_cl_fmt ) : ?>
            <div style="padding:12px 16px;background:#eef3e4;border:1px solid #c2d9a0;border-radius:11px;font-size:12px;color:#5a7a3a">
                <div style="font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:6px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Próximo ciclo calculado automáticamente
                </div>
                <div style="font-weight:600">
                    Abre: <?php echo esc_html( $proximo_ap_fmt ); ?>
                    &nbsp;→&nbsp;
                    Cierra: <?php echo esc_html( $proximo_cl_fmt ); ?>
                </div>
                <div style="margin-top:4px;font-size:11px;color:#5a7a3a;opacity:.75">
                    Cada semana se repite sin necesidad de configurar nada.
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="m6cc">
            <div class="m6ct">Correo de destino</div>
            <p style="font-size:13px;color:var(--ink3);margin-bottom:16px;line-height:1.6">El resumen con el Excel se envía aquí al cerrar el ciclo (automática o manualmente).</p>
            <div class="m6fd">
                <label class="m6lb">Correo para resúmenes del ciclo</label>
                <input type="email" name="correo_destino" class="m6in" value="<?php echo esc_attr( $correo ); ?>" placeholder="admin@empresa.cl" style="max-width:380px">
            </div>
        </div>
        <button type="submit" class="btn-s full">Guardar configuración</button>
    </form>

    <?php else : ?>
    <!-- ═══ DASHBOARD ═══ -->
    <section class="m6hero">
        <div>
            <div class="m6ey">Panel de control &middot; <?php echo esc_html( $hoy_fmt ); ?></div>
            <h1 class="m6tit">Registro del Ciclo</h1>
            <div class="m6sub">Controla los pedidos, revisa los registros y gestiona la apertura programada.</div>
        </div>
        <button class="m6tog <?php echo $abierto ? 'on' : 'off'; ?>" id="m6togbtn" type="button" onclick="m6confirmToggle()">
            <span class="m6dot <?php echo $abierto ? 'on' : 'off'; ?>"><span class="m6pls"></span></span>
            <div class="m6ttx">
                <div class="m6tlb">Estado del sistema</div>
                <div class="m6tvl"><?php echo $abierto ? 'Abierto' : 'Cerrado'; ?></div>
            </div>
            <div class="m6tac">
                <?php if ( $abierto ) : ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                Cerrar pedidos
                <?php else : ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0"/></svg>
                Abrir pedidos
                <?php endif; ?>
            </div>
        </button>
    </section>

    <section class="m6krow">
        <div class="m6kpi">
            <div class="m6ki"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <div class="m6kb">
                <div class="m6kv"><?php echo $total; ?></div>
                <div class="m6kl">Registros del ciclo</div>
                <?php if ( $fecha_ciclo && $fecha_ciclo !== $hoy ) : ?>
                <div style="font-size:11px;color:var(--ink3);margin-top:4px">desde <?php echo esc_html( ( new DateTime($fecha_ciclo) )->format('d/m/Y') ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="m6kpi">
            <div class="m6ki"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-9 4 18 3-9h4"/></svg></div>
            <div class="m6kb">
                <?php if ( $total > 0 && ! empty( $dist ) ) : ?>
                <div class="m6pie">
                    <?php foreach ( $dist as $mt => $cnt ) :
                        $ic    = array_search( $mt, array_keys( $dist ), true );
                        $color = $menu_colors[ $ic % count( $menu_colors ) ];
                        $pct   = round( ( $cnt / $total ) * 100 );
                    ?>
                    <span style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr( $color ); ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="m6leg">
                    <?php foreach ( $dist as $mt => $cnt ) :
                        $ic    = array_search( $mt, array_keys( $dist ), true );
                        $color = $menu_colors[ $ic % count( $menu_colors ) ]; ?>
                    <span><i style="background:<?php echo esc_attr( $color ); ?>"></i><?php echo esc_html( $mt ?: '(Sin titulo)' ); ?> &middot; <?php echo $cnt; ?></span>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div style="font-size:13px;color:var(--ink3);padding:4px 0">Sin registros en el ciclo</div>
                <?php endif; ?>
                <div class="m6kl" style="margin-top:10px">Por menú</div>
            </div>
        </div>
        <div class="m6kpi">
            <div class="m6ki gr">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </div>
            <div class="m6kb">
                <div class="m6kv sm">
                    <?php echo esc_html( $dias_semana_es[ $ap_dia ] ); ?> <?php echo esc_html( $ap_hora ); ?>
                </div>
                <div class="m6kl">Apertura semanal</div>
                <div style="display:flex;align-items:center;gap:5px;margin-top:6px">
                    <span style="font-size:11px;font-weight:700;color:var(--rd)">Cierra:</span>
                    <span style="font-size:11px;font-weight:600;color:var(--ink2)">
                        <?php echo esc_html( $dias_semana_es[ $cl_dia ] ); ?> <?php echo esc_html( $cl_hora ); ?>
                    </span>
                </div>
                <?php if ( $proximo_ap_fmt ) : ?>
                <div style="display:inline-flex;align-items:center;gap:5px;margin-top:5px;background:#eef3e4;border:1px solid #c2d9a0;border-radius:20px;padding:3px 9px;font-size:10px;font-weight:700;color:#5a7a3a;white-space:nowrap">
                    <span style="width:5px;height:5px;border-radius:50%;background:#5a7a3a;display:inline-block"></span>
                    Próximo: <?php echo esc_html( $proximo_ap_fmt ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="m6acts">
        <form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:contents">
            <?php echo $nonce_html; ?>
            <input type="hidden" name="action" value="maffer_descargar_excel">
            <button type="submit" class="m6act">
                <div class="m6ai"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg></div>
                <div class="m6ab"><div class="m6at">Descargar Excel</div><div class="m6as"><?php echo $total; ?> registros del ciclo</div></div>
                <svg class="m6ach" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </form>
        <form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:contents">
            <?php echo $nonce_html; ?>
            <input type="hidden" name="action" value="maffer_enviar_correo">
            <button type="submit" class="m6act">
                <div class="m6ai"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>
                <div class="m6ab"><div class="m6at">Enviar por correo</div><div class="m6as">A <?php echo esc_html( $correo ); ?></div></div>
                <svg class="m6ach" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </form>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=maffer-panel&panel=config' ) ); ?>" class="m6act">
            <div class="m6ai"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></div>
            <div class="m6ab"><div class="m6at">Configuración</div><div class="m6as">Apertura programada y correo</div></div>
            <svg class="m6ach" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=maffer-panel&panel=historial' ) ); ?>" class="m6act">
            <div class="m6ai"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
            <div class="m6ab"><div class="m6at">Historial</div><div class="m6as">Ciclos anteriores</div></div>
            <svg class="m6ach" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
    </section>

    <section class="m6ts">
        <div class="m6tth">
            <div>
                <h2 class="m6ttit">Registros del <span class="ac">Ciclo</span></h2>
                <div class="m6tsub">
                    Desde <?php echo esc_html( ( new DateTime($fecha_desde) )->format('d/m/Y') ); ?>
                    &middot; <span id="m6vis"><?php echo count( $registros ); ?></span> de <?php echo count( $registros ); ?> visibles
                </div>
            </div>
            <div class="m6tctr">
                <div class="m6srch">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input id="m6q" type="text" placeholder="Buscar nombre o RUT..." oninput="m6filtrar()">
                </div>
                <div class="m6chs" id="m6chs">
                    <button class="m6ch on" data-f="todos" onclick="m6chip(this)">Todos</button>
                    <?php foreach ( array_keys( $dist ) as $mt ) : ?>
                    <button class="m6ch" data-f="<?php echo esc_attr( strtolower( $mt ) ); ?>" onclick="m6chip(this)"><?php echo esc_html( $mt ?: 'Sin titulo' ); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=maffer-panel&panel=menus' ) ); ?>" style="font-size:12px;font-weight:700;color:var(--or6);text-decoration:none;background:var(--or05);border:1px solid var(--or1);padding:5px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Gestionar menús
            </a>
            <button type="button" onclick="m6abrirCrear()"
                style="font-size:12px;font-weight:700;color:#fff;background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);border:0;padding:5px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px;cursor:pointer;box-shadow:0 2px 8px rgba(230,126,34,.4);transition:transform .1s"
                onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar registro
            </button>
        </div>
        <?php if ( ! $t_exist ) : ?>
        <div style="padding:20px;background:#fbebe6;border:1px solid rgba(185,74,50,.3);border-radius:12px;font-size:13px;color:#b94a32;font-weight:600">
            La tabla de registros no existe. Activa el Snippet 1 (creación de tabla) primero.
        </div>
        <?php else : ?>
        <div class="m6tw">
            <table class="m6tbl" id="m6tbl">
                <thead>
                    <tr>
                        <th style="width:72px">Fecha</th>
                        <th style="width:66px">Hora</th>
                        <th>Nombre</th>
                        <th style="width:140px">RUT</th>
                        <th style="width:180px">Menú</th>
                        <th style="width:80px;text-align:center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $registros ) ) : ?>
                    <tr><td colspan="6" class="m6emp">No hay registros en el ciclo actual.</td></tr>
                <?php else : ?>
                    <?php foreach ( $registros as $reg ) :
                        $f_row = isset( $reg['fecha'] ) ? ( new DateTime( $reg['fecha'] ) )->format('d/m') : '—';
                        $data_m = isset( $reg['selecciones'] ) ? implode( ' ', array_column( $reg['selecciones'], 'menu' ) ) : $reg['menu_titulo'];
                    ?>
                    <tr data-q="<?php echo esc_attr( strtolower( $reg['nombre'] . ' ' . $reg['rut'] ) ); ?>"
                        data-m="<?php echo esc_attr( strtolower( $data_m ) ); ?>">
                        <td class="m6mn m6mt"><?php echo esc_html( $f_row ); ?></td>
                        <td class="m6mn"><?php echo esc_html( substr( $reg['hora'], 0, 5 ) ); ?></td>
                        <td class="m6nm"><?php echo esc_html( $reg['nombre'] ); ?></td>
                        <td class="m6mn m6mt"><?php echo esc_html( $reg['rut'] ); ?></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap">
                                <?php if ( ! empty( $reg['selecciones'] ) ) : ?>
                                    <?php foreach ( $reg['selecciones'] as $sel ) : 
                                        $mi_s = array_search( $sel['menu'], array_keys( $dist ), true );
                                        $mc_s = $menu_colors[ ( $mi_s !== false ? $mi_s : 0 ) % count( $menu_colors ) ];
                                    ?>
                                    <span class="m6pll" style="padding-left:6px;padding-right:8px;font-size:11px"><i style="background:<?php echo esc_attr( $mc_s ); ?>;width:6px;height:6px;margin-right:4px"></i><strong style="text-transform:capitalize;margin-right:4px;color:var(--ink2)"><?php echo esc_html( substr( $sel['dia'], 0, 2 ) ); ?>:</strong><?php echo esc_html( $sel['menu'] ?: 'Sin titulo' ); ?></span>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <span class="m6pll"><i style="background:#ccc"></i>Sin título</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:6px">
                                <button type="button" title="Editar"
                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e6d8bf;background:#fffaf1;color:#d26a10;display:grid;place-items:center;cursor:pointer;transition:all .15s"
                                    onmouseover="this.style.background='#fdf1e5';this.style.borderColor='#fbe2cc'"
                                    onmouseout="this.style.background='#fffaf1';this.style.borderColor='#e6d8bf'"
                                    onclick="m6editar(<?php echo (int)$reg['id']; ?>,'<?php echo esc_js( $reg['nombre'] ); ?>','<?php echo esc_js( $reg['rut'] ); ?>','<?php echo esc_js( $reg['menu_titulo'] ); ?>','<?php echo esc_js( $reg['observaciones'] ); ?>')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button type="button" title="Eliminar"
                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e6d8bf;background:#fffaf1;color:#b94a32;display:grid;place-items:center;cursor:pointer;transition:all .15s"
                                    onmouseover="this.style.background='#fbebe6';this.style.borderColor='rgba(185,74,50,.3)'"
                                    onmouseout="this.style.background='#fffaf1';this.style.borderColor='#e6d8bf'"
                                    onclick="m6eliminar(<?php echo (int)$reg['id']; ?>)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php endif; // fin dashboard ?>
    </div><!-- #maf6 -->

    <!-- ═══ MODAL AGREGAR REGISTRO ═══ -->
    <div class="m6bk" id="m6mcre" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="m6md" style="max-width:520px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:20px 24px 18px;background:#fffaf1;border-bottom:1px solid #f0e4c8">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#ef8a2d,#d26a10);display:grid;place-items:center;flex-shrink:0;box-shadow:0 4px 10px rgba(230,126,34,.35)">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#d26a10;margin-bottom:3px">Registro manual</div>
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;letter-spacing:-.02em;color:#2a231a">Agregar colaborador</div>
                    </div>
                </div>
                <button onclick="document.getElementById('m6mcre').classList.remove('open')"
                    style="background:#fbf3e4;border:1px solid #e6d8bf;color:#6b5d4c;width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:18px;line-height:1;cursor:pointer;transition:all .12s"
                    onmouseover="this.style.background='#fdf1e5';this.style.color='#E67E22'"
                    onmouseout="this.style.background='#fbf3e4';this.style.color='#6b5d4c'">&times;</button>
            </div>
            <div style="padding:20px 24px;background:#fffaf1">
                <p style="font-size:13px;color:#6b5d4c;margin:0 0 18px;line-height:1.5">Registra manualmente a un colaborador que no pudo acceder al formulario.</p>
                <!-- Nombre -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">Nombre completo *</label>
                    <input type="text" id="m6cnm" placeholder="Nombre del colaborador"
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.background='#fffaf1';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.background='#fbf3e4';this.style.boxShadow='none'">
                </div>
                <!-- RUT -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">RUT *</label>
                    <input type="text" id="m6crt" placeholder="12.345.678-9" maxlength="12"
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:'JetBrains Mono',monospace;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.background='#fffaf1';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.background='#fbf3e4';this.style.boxShadow='none'"
                        oninput="m6fmtRut(this)">
                </div>
                <!-- Menú Cena (ancho completo, sin selector de turno) -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">
                        Menú *
                        <span style="margin-left:6px;font-size:10px;font-weight:600;background:#eaf0f6;color:#3a5a7a;padding:2px 8px;border-radius:999px;text-transform:none;letter-spacing:0">Cena</span>
                    </label>
                    <select id="m6cmn"
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;cursor:pointer;appearance:auto;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.boxShadow='none'">
                        <?php foreach ( $menus_cena as $m ) :
                            if ( ! trim( $m['title'] ) ) continue; ?>
                        <option value="<?php echo esc_attr( $m['title'] ); ?>"><?php echo esc_html( $m['title'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Observaciones -->
                <div style="margin-bottom:6px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">Observaciones <span style="font-weight:500;text-transform:none;letter-spacing:0">(opcional)</span></label>
                    <textarea id="m6cob" placeholder="Sin observaciones..."
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;resize:vertical;min-height:72px;line-height:1.5;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.background='#fffaf1';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.background='#fbf3e4';this.style.boxShadow='none'"></textarea>
                </div>
                <div id="m6cmm" class="m6mm"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 24px 20px;border-top:1px solid #f0e4c8;background:#fbf3e4;border-radius:0 0 22px 22px">
                <button onclick="document.getElementById('m6mcre').classList.remove('open')"
                    style="background:#fffaf1;border:1.5px solid #e6d8bf;color:#6b5d4c;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:14px;padding:10px 18px;border-radius:10px;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.background='#f0e4c8'" onmouseout="this.style.background='#fffaf1'">Cancelar</button>
                <button onclick="m6crearRegistro()" id="m6cbtn"
                    style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 20px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.5);cursor:pointer;transition:transform .1s"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Agregar registro
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL EDITAR REGISTRO ═══ -->
    <div class="m6bk" id="m6medt" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="m6md" style="max-width:520px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:20px 24px 18px;background:#fffaf1;border-bottom:1px solid #f0e4c8">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#ef8a2d,#d26a10);display:grid;place-items:center;flex-shrink:0;box-shadow:0 4px 10px rgba(230,126,34,.35)">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#d26a10;margin-bottom:3px">Editar registro</div>
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;letter-spacing:-.02em;color:#2a231a">Modificar datos</div>
                    </div>
                </div>
                <button onclick="document.getElementById('m6medt').classList.remove('open')"
                    style="background:#fbf3e4;border:1px solid #e6d8bf;color:#6b5d4c;width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:18px;line-height:1;cursor:pointer;transition:all .12s"
                    onmouseover="this.style.background='#fdf1e5';this.style.color='#E67E22'"
                    onmouseout="this.style.background='#fbf3e4';this.style.color='#6b5d4c'">&times;</button>
            </div>
            <div style="padding:20px 24px;background:#fffaf1">
                <input type="hidden" id="m6eid">
                <!-- Nombre -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">Nombre completo</label>
                    <input type="text" id="m6enm" placeholder="Nombre del colaborador"
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.background='#fffaf1';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.background='#fbf3e4';this.style.boxShadow='none'">
                </div>
                <!-- RUT (solo lectura) -->
                <div style="margin-bottom:14px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">
                        RUT
                        <span style="font-size:10px;font-weight:600;background:#f0e8d8;color:#97897a;padding:2px 7px;border-radius:999px;text-transform:none;letter-spacing:0">Solo lectura</span>
                    </label>
                    <input type="text" id="m6ert" readonly
                        style="width:100%;background:#f4ede0;border:1.5px solid #e0d0b8;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#97897a;outline:none;font-family:'JetBrains Mono',monospace;cursor:not-allowed">
                </div>
                <!-- Menú (ancho completo, turno estático Cena) -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">
                        Menú
                        <span style="margin-left:6px;font-size:10px;font-weight:600;background:#eaf0f6;color:#3a5a7a;padding:2px 8px;border-radius:999px;text-transform:none;letter-spacing:0">Cena</span>
                    </label>
                    <select id="m6emn"
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;cursor:pointer;appearance:auto;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.boxShadow='none'">
                        <?php foreach ( $menus_cena as $m ) :
                            if ( ! trim( $m['title'] ) ) continue; ?>
                        <option value="<?php echo esc_attr( $m['title'] ); ?>"><?php echo esc_html( $m['title'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Observaciones -->
                <div style="margin-bottom:6px">
                    <label style="display:block;font-size:11px;font-weight:700;color:#97897a;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-family:'Plus Jakarta Sans',sans-serif">Observaciones <span style="font-weight:500;text-transform:none;letter-spacing:0">(opcional)</span></label>
                    <textarea id="m6eob" placeholder="Sin observaciones..."
                        style="width:100%;background:#fbf3e4;border:1.5px solid #e6d8bf;border-radius:10px;padding:11px 14px;font-size:14px;font-weight:500;color:#2a231a;outline:none;font-family:inherit;resize:vertical;min-height:80px;line-height:1.5;transition:border-color .15s,box-shadow .15s"
                        onfocus="this.style.borderColor='#E67E22';this.style.background='#fffaf1';this.style.boxShadow='0 0 0 3px rgba(230,126,34,.15)'"
                        onblur="this.style.borderColor='#e6d8bf';this.style.background='#fbf3e4';this.style.boxShadow='none'"></textarea>
                </div>
                <div id="m6emm" class="m6mm"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 24px 20px;border-top:1px solid #f0e4c8;background:#fbf3e4;border-radius:0 0 22px 22px">
                <button onclick="document.getElementById('m6medt').classList.remove('open')"
                    style="background:#fffaf1;border:1.5px solid #e6d8bf;color:#6b5d4c;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;font-size:14px;padding:10px 18px;border-radius:10px;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.background='#f0e4c8'" onmouseout="this.style.background='#fffaf1'">Cancelar</button>
                <button onclick="m6guardar()"
                    style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 20px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.5);cursor:pointer;transition:transform .1s"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL CONFIRMAR ESTADO ═══ -->
    <div class="m6bk" id="m6mconf" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="m6md m6mdsm">
            <div class="m6mhd">
                <div>
                    <div class="m6mey"><?php echo $abierto ? 'Cerrar sistema' : 'Abrir sistema'; ?></div>
                    <h3 class="m6mtt"><?php echo $abierto ? '¿Cerrar los pedidos?' : '¿Abrir los pedidos?'; ?></h3>
                </div>
                <button class="m6mcl" onclick="document.getElementById('m6mconf').classList.remove('open')">&times;</button>
            </div>
            <div class="m6mb">
                <p class="m6mds"><?php echo $abierto
                    ? 'Los colaboradores ya no podrán registrar su menú hasta la próxima apertura.'
                    : 'Los colaboradores podrán acceder al formulario de inmediato.'; ?></p>
            </div>
            <div class="m6mft">
                <button class="btn-g" onclick="document.getElementById('m6mconf').classList.remove('open')">Cancelar</button>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline">
                    <?php echo $nonce_html; ?>
                    <input type="hidden" name="action" value="maffer_toggle_dia">
                    <input type="hidden" name="maffer_accion" value="<?php echo $abierto ? 'cerrar_dia' : 'abrir_dia'; ?>">
                    <?php if ( $abierto ) : ?>
                    <button type="submit" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#cd5a3e,#b94a32 60%,#9c3d28);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 18px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.25) inset,0 -2px 0 rgba(0,0,0,.15) inset,0 6px 14px -4px rgba(185,74,50,.6);cursor:pointer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                        Cerrar pedidos
                    </button>
                    <?php else : ?>
                    <button type="submit" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#74a04a,#5a7a3a 60%,#4a6830);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 18px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.25) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(90,122,58,.6);cursor:pointer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0"/></svg>
                        Abrir pedidos
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL CAMBIAR CONTRASEÑA ═══ -->
    <div class="m6bk" id="m6mpwd" onclick="if(event.target===this)m6closePwd()">
        <div class="m6md m6mdsm">
            <div class="m6mhd">
                <div>
                    <div class="m6mey">Mi cuenta</div>
                    <h3 class="m6mtt">Cambiar contraseña</h3>
                </div>
                <button class="m6mcl" id="m6pwdcl" onclick="m6closePwd()">&times;</button>
            </div>
            <div id="m6pwdform">
                <div class="m6mb">
                    <p class="m6mds">Elige una contraseña segura que solo tú conozcas. Deberás usarla la próxima vez que inicies sesión.</p>
                    <div class="m6fd"><label class="m6lb">Contraseña actual</label><input type="password" id="m6pwd0" class="m6in" placeholder="••••••••" autocomplete="current-password"></div>
                    <div class="m6fd"><label class="m6lb">Nueva contraseña</label><input type="password" id="m6pwd1" class="m6in" placeholder="••••••••" autocomplete="new-password"></div>
                    <div class="m6fd"><label class="m6lb">Confirmar nueva contraseña</label><input type="password" id="m6pwd2" class="m6in" placeholder="••••••••" autocomplete="new-password"></div>
                    <div id="m6pwdmsg" class="m6mm" style="display:none"></div>
                </div>
                <div class="m6mft">
                    <button class="btn-g" onclick="m6closePwd()">Cancelar</button>
                    <button id="m6pwdbtn" onclick="m6submitPwd()"
                        style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 18px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.6);cursor:pointer;transition:transform .1s">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                        Guardar contraseña
                    </button>
                </div>
            </div>
            <div id="m6pwdok" style="display:none">
                <div class="m6mb" style="text-align:center;padding-top:8px;padding-bottom:8px">
                    <div style="width:56px;height:56px;border-radius:50%;background:#eef3e4;display:grid;place-items:center;margin:0 auto 16px">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#5a7a3a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    </div>
                    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:#2a231a;margin-bottom:8px">Contraseña actualizada</div>
                    <p class="m6mds" style="margin-bottom:0">Tu sesión ha sido cerrada por seguridad.<br>Inicia sesión nuevamente con tu nueva contraseña.</p>
                </div>
                <div class="m6mft" style="justify-content:center">
                    <a href="<?php echo esc_url( home_url( '/admin-maffer/' ) ); ?>"
                        style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(180deg,#ef8a2d,#E67E22 60%,#d26a10);border:0;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px;box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 6px 14px -4px rgba(230,126,34,.6);text-decoration:none;transition:transform .1s">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Iniciar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function($){
        var AX = '<?php echo esc_js( $ajax_url ); ?>';
        var NK = '<?php echo esc_js( $nonce_ajax ); ?>';
        var filt = 'todos', qry = '';

        // Opciones de menú cena (para poblar selects de modales)
        var MENUS_CENA = <?php
            $arr = array();
            foreach ( $menus_cena as $m ) {
                if ( trim( $m['title'] ) ) $arr[] = $m['title'];
            }
            echo json_encode( $arr );
        ?>;

        // ── Filtros de la tabla ──────────────────────────────
        window.m6filtrar = function() {
            qry = ( $('#m6q').val() || '' ).toLowerCase();
            _render();
        };
        window.m6chip = function(b) {
            $('#m6chs .m6ch').removeClass('on'); $(b).addClass('on');
            filt = $(b).data('f'); _render();
        };
        function _render() {
            var v = 0;
            $('#m6tbl tbody tr').each(function() {
                var q = $(this).data('q') || '', m = $(this).data('m') || '';
                var s = q.includes(qry) && ( filt === 'todos' || m === filt );
                $(this).toggle(s); if (s) v++;
            });
            $('#m6vis').text(v);
        }

        // ── Toggle de estado (sin chequeo de horario) ────────
        window.m6confirmToggle = function() {
            $('#m6mconf').addClass('open');
        };

        // ── Abrir modal Crear ────────────────────────────────
        window.m6abrirCrear = function() {
            $('#m6cnm, #m6crt, #m6cob').val('');
            // Repoblar select de menú con opciones actuales
            var sel = document.getElementById('m6cmn');
            if (sel && MENUS_CENA.length) {
                sel.innerHTML = '';
                MENUS_CENA.forEach(function(t) {
                    var o = document.createElement('option');
                    o.value = t; o.textContent = t;
                    sel.appendChild(o);
                });
            }
            $('#m6cmm').hide().removeClass('ok er');
            $('#m6cbtn').prop('disabled', false).css('opacity', '1');
            $('#m6mcre').addClass('open');
            setTimeout(function() { $('#m6cnm').focus(); }, 200);
        };

        // ── Crear registro ───────────────────────────────────
        window.m6crearRegistro = function() {
            var n = $('#m6cnm').val().trim();
            var r = $('#m6crt').val().trim();
            var m = $('#m6cmn').val();
            if (!n) { _cmsg('El nombre es obligatorio.', false); return; }
            if (!r) { _cmsg('El RUT es obligatorio.', false); return; }
            if (!m) { _cmsg('Selecciona un menú.', false); return; }
            var rLimpio = r.replace(/\./g, '');
            $('#m6cbtn').prop('disabled', true).css('opacity', '.6');
            $.post(AX, {
                action: 'maffer_crear_registro', nonce: NK,
                nombre: n, rut: rLimpio, menu_titulo: m,
                observaciones: $('#m6cob').val().trim()
            }, function(res) {
                if (res.success) {
                    m6toast('Registro agregado correctamente.');
                    setTimeout(function() { location.reload(); }, 700);
                } else {
                    _cmsg('Error: ' + res.data, false);
                    $('#m6cbtn').prop('disabled', false).css('opacity', '1');
                }
            }).fail(function() {
                _cmsg('Error de conexión.', false);
                $('#m6cbtn').prop('disabled', false).css('opacity', '1');
            });
        };
        function _cmsg(t, ok) { $('#m6cmm').show().removeClass('ok er').addClass(ok ? 'ok' : 'er').text(t); }

        // ── Abrir modal Editar ───────────────────────────────
        // Firma: m6editar(id, nom, rut, men, obs)  — sin turno
        window.m6editar = function(id, nom, rut, men, obs) {
            $('#m6eid').val(id);
            $('#m6enm').val(nom);
            $('#m6ert').val(rut);
            // Repoblar select con menús cena y preseleccionar
            var sel = document.getElementById('m6emn');
            if (sel && MENUS_CENA.length) {
                sel.innerHTML = '';
                MENUS_CENA.forEach(function(t) {
                    var o = document.createElement('option');
                    o.value = t; o.textContent = t;
                    sel.appendChild(o);
                });
            }
            $('#m6emn').val(men);
            $('#m6eob').val(obs);
            $('#m6emm').hide().removeClass('ok er');
            $('#m6medt').addClass('open');
        };

        // ── Guardar edición ──────────────────────────────────
        window.m6guardar = function() {
            var n = $('#m6enm').val().trim();
            if (!n) { _emsg('El nombre es obligatorio.', false); return; }
            $.post(AX, {
                action: 'maffer_editar_registro', nonce: NK,
                id: $('#m6eid').val(),
                nombre: n,
                rut: $('#m6ert').val().trim(),
                menu_titulo: $('#m6emn').val(),
                observaciones: $('#m6eob').val().trim()
            }, function(r) {
                if (r.success) { m6toast('Registro actualizado.'); setTimeout(function() { location.reload(); }, 700); }
                else _emsg('Error: ' + r.data, false);
            }).fail(function() { _emsg('Error de conexión.', false); });
        };
        function _emsg(t, ok) { $('#m6emm').show().removeClass('ok er').addClass(ok ? 'ok' : 'er').text(t); }

        // ── Eliminar registro ────────────────────────────────
        window.m6eliminar = function(id) {
            if (!confirm('¿Eliminar este registro? No se puede deshacer.')) return;
            $.post(AX, { action: 'maffer_eliminar_registro', nonce: NK, id: id }, function(r) {
                if (r.success) { m6toast('Registro eliminado.'); setTimeout(function() { location.reload(); }, 600); }
                else alert('Error: ' + r.data);
            });
        };

        // ── Gestión de menús (agregar / eliminar filas) ──────
        window.m6rm = function(btn, dia) {
            var cnt = '#m6icnt-cena-' + dia;
            if ($(cnt + ' .m6mi').length <= 1) { 
                var diasL = {lunes:'Lunes',martes:'Martes',miercoles:'Miércoles',jueves:'Jueves',viernes:'Viernes',sabado:'Sábado',domingo:'Domingo'};
                if (!confirm('Si eliminas esta opción, el día ' + diasL[dia] + ' quedará sin menú (Sin Servicio). ¿Estás seguro?')) return;
            }
            $(btn).closest('.m6mi').remove(); _renum(dia);
        };
        window.m6add = function(dia) {
            var cnt = '#m6icnt-cena-' + dia;
            var n = $(cnt + ' .m6mi').length;
            var html = '<div class="m6mi">'
                + '<div class="m6mih"><span class="m6min">Menú ' + (n + 1) + '</span>'
                + '<button type="button" class="m6rm" onclick="m6rm(this, \'' + dia + '\')">'
                + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Eliminar</button></div>'
                + '<div class="m6fd"><label class="m6lb">Titulo *</label>'
                + '<input type="text" name="maffer_titles_cena_' + dia + '[]" class="m6in" placeholder="Ej: Menú Cena Especial" required></div>'
                + '<div class="m6fd"><label class="m6lb">Descripcion</label>'
                + '<textarea name="maffer_descs_cena_' + dia + '[]" class="m6ta" placeholder="Ej: Sopa, sandwich, postre"></textarea></div>'
                + '</div>';
            $(cnt).append(html);
        };
        function _renum(dia) {
            $('#m6icnt-cena-' + dia + ' .m6mi').each(function(i) { $(this).find('.m6min').text('Menú ' + (i + 1)); });
        }

        // ── Formateo de RUT ──────────────────────────────────
        window.m6fmtRut = function(input) {
            var v = input.value.replace(/[^0-9kK]/g, '').toUpperCase();
            if (v.length > 1) {
                var cuerpo = v.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                var dv = v.slice(-1);
                v = cuerpo + '-' + dv;
            }
            input.value = v;
        };

        // ── Toast ────────────────────────────────────────────
        window.m6toast = function(txt, tipo) {
            var isErr = (tipo === 'er');
            var icono = isErr
                ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                : '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
            var color = isErr ? '#b94a32' : '#5a7a3a';
            var el = $('<div class="m6tst" style="border-left:3px solid ' + color + '"><span class="m6tic" style="color:' + color + '">' + icono + '</span>' + txt + '</div>');
            $('body').append(el);
            setTimeout(function() { el.animate({ opacity: 0 }, 400, function() { el.remove(); }); }, isErr ? 3500 : 2200);
        };

        // ── Dropdown usuario ─────────────────────────────────
        window.m6toggleUser = function() {
            var d = document.getElementById('m6avdrop');
            if (!d) return;
            d.style.display = (d.style.display === 'none' || d.style.display === '') ? 'block' : 'none';
        };
        document.addEventListener('click', function(e) {
            var wrap = document.getElementById('m6avwrap');
            if (wrap && !wrap.contains(e.target)) {
                var d = document.getElementById('m6avdrop');
                if (d) d.style.display = 'none';
            }
        });

        // ── Cambiar contraseña ───────────────────────────────
        window.m6closePwd = function() {
            document.getElementById('m6mpwd').classList.remove('open');
            $('#m6pwd0, #m6pwd1, #m6pwd2').val('');
            $('#m6pwdmsg').hide().text('').removeClass('ok er');
            $('#m6pwdbtn').prop('disabled', false).html(
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg> Guardar contraseña'
            );
            $('#m6pwdform').show();
            $('#m6pwdok').hide();
            document.getElementById('m6mpwd').setAttribute('onclick', "if(event.target===this)m6closePwd()");
        };

        window.m6submitPwd = function() {
            var cur = $('#m6pwd0').val().trim();
            var nw  = $('#m6pwd1').val();
            var nw2 = $('#m6pwd2').val();
            var $msg = $('#m6pwdmsg');
            $msg.hide().removeClass('ok er');
            if (!cur) { $msg.show().addClass('er').text('Ingresa tu contraseña actual.'); return; }
            if (nw.length < 8) { $msg.show().addClass('er').text('La nueva contraseña debe tener al menos 8 caracteres.'); return; }
            if (nw !== nw2) { $msg.show().addClass('er').text('Las contraseñas no coinciden.'); return; }
            var $btn = $('#m6pwdbtn');
            $btn.prop('disabled', true).html('Guardando…');
            $.post(ajaxurl, {
                action:   'maffer_cambiar_password',
                nonce:    '<?php echo esc_js( wp_create_nonce("maffer_pwd_nonce") ); ?>',
                current:  cur,
                new_pass: nw
            }, function(r) {
                if (r.success) {
                    $('#m6pwdform').hide();
                    $('#m6pwdok').show();
                    document.getElementById('m6mpwd').removeAttribute('onclick');
                    document.getElementById('m6pwdcl').style.display = 'none';
                } else {
                    $msg.show().addClass('er').text(r.data || 'Error al cambiar la contraseña.');
                    $btn.prop('disabled', false).html(
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg> Guardar contraseña'
                    );
                }
            }).fail(function() {
                $msg.show().addClass('er').text('Error de conexión. Intenta nuevamente.');
                $btn.prop('disabled', false).html(
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg> Guardar contraseña'
                );
            });
        };

    })(jQuery);
    </script>
    <?php
}

}
