
/**
 * ============================================================
 * SNIPPET 7A — MAFFER: ROLES, MENÚ, CRON, REDIRECCIÓN
 * Nombre en WPCode: Maffer - Panel 7A Roles y Menú
 * Tipo: PHP Snippet | Inserción: Run Everywhere
 * v2 — Servicio único Cena + Apertura Programada por Fecha/Hora
 * ============================================================
 */

// ── 1. ROL PERSONALIZADO ────────────────────────────────────
add_action( 'init', function () {
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
} );

// ── 2. MENÚ LATERAL ─────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_menu_page(
        'Panel Maffer',
        'Panel Maffer',
        'read',
        'maffer-panel',
        'maffer_v6_render',
        'dashicons-food',
        3
    );
} );

// ── 3. VISTA LIMPIA SOLO PARA EL GESTOR ─────────────────────
add_action( 'admin_menu', function () {
    if ( current_user_can( 'manage_options' ) ) return;
    if ( ! current_user_can( 'maffer_manage_menu' ) ) return;
    global $menu;
    foreach ( $menu as $key => $item ) {
        if ( isset( $item[2] ) && $item[2] !== 'maffer-panel' ) {
            unset( $menu[ $key ] );
        }
    }
}, 999 );

// ── 3C. REDIRIGIR GESTOR AL PANEL MAFFER SI LLEGA AL ESCRITORIO DE WP ──
add_action( 'admin_init', function () {
    // Solo aplica a gestores (no a admins)
    if ( current_user_can( 'manage_options' ) ) return;
    if ( ! current_user_can( 'maffer_manage_menu' ) ) return;

    // No interrumpir AJAX ni admin-post.php (formularios y acciones del panel)
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
    global $pagenow;
    if ( $pagenow === 'admin-post.php' ) return;

    // Si no está en el panel Maffer, redirigir
    $page = isset( $_GET['page'] ) ? $_GET['page'] : '';
    if ( $page !== 'maffer-panel' ) {
        wp_redirect( admin_url( 'admin.php?page=maffer-panel' ) );
        exit;
    }
} );

// ── 3B. ELIMINAR MENÚS ANTIGUOS ──────────────────────────────
add_action( 'admin_menu', function () {
    global $menu, $submenu;
    foreach ( $menu as $key => $item ) {
        if ( ! isset( $item[2] ) ) continue;
        $slug = $item[2];
        if ( $slug === 'maffer-panel' ) continue;
        if ( stripos( $slug, 'maffer' ) !== false || stripos( $slug, 'cierre' ) !== false ) {
            unset( $menu[ $key ] );
            if ( isset( $submenu[ $slug ] ) ) unset( $submenu[ $slug ] );
        }
    }
}, 9999 );

add_action( 'admin_bar_menu', function ( $bar ) {
    if ( current_user_can( 'manage_options' ) ) return;
    if ( ! current_user_can( 'maffer_manage_menu' ) ) return;
    $nodes = $bar->get_nodes();
    if ( ! empty( $nodes ) ) {
        foreach ( $nodes as $node ) {
            $bar->remove_node( $node->id );
        }
    }
}, 999 );

// ── 4. REDIRECCIÓN AL LOGIN ──────────────────────────────────
add_filter( 'login_redirect', function ( $to, $req, $user ) {
    if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) return $to;
    if ( in_array( 'gestor_menus_maffer', $user->roles, true )
      || in_array( 'gestor_menus', $user->roles, true ) ) {
        return admin_url( 'admin.php?page=maffer-panel' );
    }
    return $to;
}, 10, 3 );

// ── 5. CRON — APERTURA AUTOMÁTICA ────────────────────────────
add_filter( 'cron_schedules', function ( $s ) {
    if ( ! isset( $s['maffer_minuto'] ) ) {
        $s['maffer_minuto'] = array(
            'interval' => 60,
            'display'  => 'Cada minuto Maffer',
        );
    }
    return $s;
} );

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'maffer_check_schedule' ) ) {
        wp_schedule_event( time(), 'maffer_minuto', 'maffer_check_schedule' );
    }
} );

/**
 * Dado un día de semana (0=Dom…6=Sáb) y una hora "HH:MM",
 * devuelve el DateTime correspondiente dentro de la semana actual
 * (semana que empieza el sábado).
 */
function maffer_compute_this_week_dt( $target_dow, $target_time, $tz ) {
    $now         = new DateTime( 'now', $tz );
    $current_dow = (int) $now->format( 'w' ); // 0=Dom … 6=Sáb

    // Días transcurridos desde el último sábado
    $days_since_sat = ( $current_dow === 6 ) ? 0 : ( $current_dow + 1 );

    // Posición del día objetivo dentro de la semana sábado-viernes
    // Sáb=0, Dom=1, Lun=2, Mar=3, Mié=4, Jue=5, Vie=6
    $week_pos = array( 6=>0, 0=>1, 1=>2, 2=>3, 3=>4, 4=>5, 5=>6 );
    $pos = isset( $week_pos[ $target_dow ] ) ? $week_pos[ $target_dow ] : 0;

    $dt = clone $now;
    $dt->modify( '-' . $days_since_sat . ' days' ); // retroceder al sábado
    $dt->modify( '+' . $pos . ' days' );            // avanzar al día objetivo

    $parts = explode( ':', $target_time );
    $dt->setTime( (int) $parts[0], isset( $parts[1] ) ? (int) $parts[1] : 0, 0 );

    return $dt;
}

/**
 * Abre y cierra el sistema automáticamente según el horario recurrente semanal.
 *  - Apertura: primer día/hora de la ventana (ej. sábado 12:00)
 *  - Cierre: segundo día/hora (ej. domingo 18:00) + envío de correo
 * Ambos se repiten cada semana sin necesidad de reprogramar.
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

    // ── Auto-apertura: estamos dentro de la ventana [apertura, cierre] ──────
    if ( $now >= $dt_apertura && $now < $dt_cierre && $estado !== 'abierto' ) {
        $semana_key = $dt_apertura->format( 'Y-W' ); // Año + semana ISO
        if ( get_option( 'maffer_ultima_apertura_auto', '' ) !== $semana_key ) {
            update_option( 'maffer_estado_sistema', 'abierto' );
            update_option( 'maffer_ultima_apertura_auto', $semana_key );
            $estado = 'abierto';
        }
    }

    // ── Auto-cierre: pasó la hora de cierre y el sistema sigue abierto ──────
    if ( $now >= $dt_cierre && $estado === 'abierto' ) {
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
add_action( 'maffer_check_schedule', 'maffer_aplicar_horario' );

// ── HELPERS DE ESTADO ────────────────────────────────────────

/**
 * Estado global del sistema: 'abierto' | 'cerrado'
 */
function maffer_obtener_estado_sistema() {
    return get_option( 'maffer_estado_sistema', 'cerrado' );
}

/**
 * Alias de compatibilidad con snippets anteriores.
 */
if ( ! function_exists( 'maffer_obtener_estado_dia' ) ) {
    function maffer_obtener_estado_dia() {
        return maffer_obtener_estado_sistema();
    }
}

/**
 * Devuelve el DateTime de la PRÓXIMA apertura (siempre en el futuro).
 * Usado en snippet-6 para la cuenta regresiva.
 */
function maffer_get_apertura_dt() {
    $tz   = new DateTimeZone( 'America/Santiago' );
    $now  = new DateTime( 'now', $tz );
    $dia  = (int) get_option( 'maffer_apertura_dia',  6 );
    $hora = get_option( 'maffer_apertura_hora', '12:00' );

    $dt = maffer_compute_this_week_dt( $dia, $hora, $tz );

    // Si ya pasó esta semana, calcular la próxima ocurrencia
    if ( $dt <= $now ) {
        $dt->modify( '+7 days' );
    }

    return $dt;
}

/**
 * Devuelve el DateTime del cierre de la semana actual.
 * Usado en snippet-6 para mostrar "cierra el domingo X a las 18:00".
 */
function maffer_get_cierre_dt() {
    $tz   = new DateTimeZone( 'America/Santiago' );
    $dia  = (int) get_option( 'maffer_cierre_dia',  0 );
    $hora = get_option( 'maffer_cierre_hora', '18:00' );
    return maffer_compute_this_week_dt( $dia, $hora, $tz );
}

/**
 * Retorna la fecha Y-m-d del inicio del ciclo actual.
 * NUNCA retorna null — siempre garantiza una fecha válida.
 * Prioridad:
 *  1. maffer_compute_this_week_dt() con el día configurado (sistema nuevo)
 *  2. maffer_apertura_programada guardada (sistema anterior)
 *  3. Cálculo directo del sábado más reciente (fallback universal)
 */
function maffer_get_fecha_ciclo() {
    $tz  = new DateTimeZone( 'America/Santiago' );
    $now = new DateTime( 'now', $tz );

    // 1. Sistema recurrente nuevo
    if ( function_exists( 'maffer_compute_this_week_dt' ) ) {
        $dia = (int) get_option( 'maffer_apertura_dia', 6 );
        $dt  = maffer_compute_this_week_dt( $dia, '00:00', $tz );
        // Si la fecha calculada es futura (ej. apertura = jueves y hoy es miércoles),
        // retroceder una semana para tomar la ocurrencia más reciente en el pasado.
        if ( $dt > $now ) {
            $dt->modify( '-7 days' );
        }
        return $dt->format( 'Y-m-d' );
    }

    // 2. Fecha específica guardada (compatibilidad hacia atrás)
    $ap = get_option( 'maffer_apertura_programada', '' );
    if ( ! empty( $ap ) ) {
        try {
            return ( new DateTime( $ap, $tz ) )->format( 'Y-m-d' );
        } catch ( Exception $e ) {}
    }

    // 3. Fallback universal: calcular la ocurrencia más reciente del día configurado
    $dia       = (int) get_option( 'maffer_apertura_dia', 6 ); // default sábado
    $dow       = (int) $now->format( 'w' ); // 0=Dom … 6=Sáb
    // Días hasta retroceder para llegar al día configurado
    $days_back = ( $dow >= $dia ) ? ( $dow - $dia ) : ( 7 - $dia + $dow );
    $dt        = clone $now;
    if ( $days_back > 0 ) $dt->modify( '-' . $days_back . ' days' );
    return $dt->format( 'Y-m-d' );
}

// ── Helper: limpiar caché de Elementor y SpeedyCache ─────────
function maffer_limpiar_cache() {
    if ( class_exists( '\Elementor\Plugin' ) ) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    if ( function_exists( 'speedycache_clear_all_cache' ) ) {
        speedycache_clear_all_cache();
    }
    wp_cache_flush();
}

// ── 6. PROCESAR POST (menús, config, estado) ─────────────────
function maffer_v6_procesar() {
    $accion = sanitize_key( isset( $_POST['maffer_accion'] ) ? $_POST['maffer_accion'] : '' );
    if ( ! $accion ) return;
    if ( ! check_admin_referer( 'maffer_admin_action' ) ) {
        wp_die( 'No autorizado.' );
    }
    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }

    // Guardar menús de Cena (único turno)
    if ( $accion === 'guardar_menus' ) {
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

    // Guardar configuración (horario recurrente semanal + correo)
    if ( $accion === 'guardar_horario' ) {
        $ap_dia  = (int) ( isset( $_POST['apertura_dia'] )  ? $_POST['apertura_dia']  : 6 );
        $ap_hora = sanitize_text_field( isset( $_POST['apertura_hora'] ) ? $_POST['apertura_hora'] : '12:00' );
        $cl_dia  = (int) ( isset( $_POST['cierre_dia'] )    ? $_POST['cierre_dia']    : 0 );
        $cl_hora = sanitize_text_field( isset( $_POST['cierre_hora'] )  ? $_POST['cierre_hora']  : '18:00' );

        if ( $ap_dia >= 0 && $ap_dia <= 6 ) update_option( 'maffer_apertura_dia',  $ap_dia );
        if ( preg_match( '/^\d{2}:\d{2}$/', $ap_hora ) ) update_option( 'maffer_apertura_hora', $ap_hora );
        if ( $cl_dia >= 0 && $cl_dia <= 6 ) update_option( 'maffer_cierre_dia',   $cl_dia );
        if ( preg_match( '/^\d{2}:\d{2}$/', $cl_hora ) ) update_option( 'maffer_cierre_hora',  $cl_hora );

        $correo = sanitize_email( isset( $_POST['correo_destino'] ) ? $_POST['correo_destino'] : '' );
        if ( $correo ) update_option( 'maffer_correo_destino', $correo );

        maffer_limpiar_cache();
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=horario_ok' ) );
        exit;
    }

    if ( $accion === 'cerrar_dia' ) {
        update_option( 'maffer_estado_sistema', 'cerrado' );
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_cerrado' ) );
        exit;
    }

    if ( $accion === 'abrir_dia' ) {
        update_option( 'maffer_estado_sistema', 'abierto' );
        wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=dia_abierto' ) );
        exit;
    }
}

// ── 7. ADMIN-POST: GUARDAR MENÚS ────────────────────────────
add_action( 'admin_post_maffer_guardar_menus', function () {
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
} );

// ── 8. ADMIN-POST: TOGGLE SISTEMA ────────────────────────────
add_action( 'admin_post_maffer_toggle_dia', function () {
    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'maffer_admin_action' );

    $accion  = sanitize_key( isset( $_POST['maffer_accion'] ) ? $_POST['maffer_accion'] : '' );
    $tz      = new DateTimeZone( 'America/Santiago' );
    $hoy_fmt = ( new DateTime( 'now', $tz ) )->format( 'd/m/Y' );

    if ( $accion === 'cerrar_dia' ) {
        update_option( 'maffer_estado_sistema', 'cerrado' );
        // Enviar resumen al cerrar manualmente
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
} );

// ── 8B. ADMIN-POST: GUARDAR CONFIGURACIÓN ────────────────────
add_action( 'admin_post_maffer_guardar_horario', function () {
    if ( ! current_user_can( 'maffer_manage_menu' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'maffer_admin_action' );

    $ap_dia  = (int) ( isset( $_POST['apertura_dia'] )  ? $_POST['apertura_dia']  : 6 );
    $ap_hora = sanitize_text_field( isset( $_POST['apertura_hora'] ) ? $_POST['apertura_hora'] : '12:00' );
    $cl_dia  = (int) ( isset( $_POST['cierre_dia'] )    ? $_POST['cierre_dia']    : 0 );
    $cl_hora = sanitize_text_field( isset( $_POST['cierre_hora'] )  ? $_POST['cierre_hora']  : '18:00' );

    if ( $ap_dia >= 0 && $ap_dia <= 6 ) update_option( 'maffer_apertura_dia',  $ap_dia );
    if ( preg_match( '/^\d{2}:\d{2}$/', $ap_hora ) ) update_option( 'maffer_apertura_hora', $ap_hora );
    if ( $cl_dia >= 0 && $cl_dia <= 6 ) update_option( 'maffer_cierre_dia',   $cl_dia );
    if ( preg_match( '/^\d{2}:\d{2}$/', $cl_hora ) ) update_option( 'maffer_cierre_hora',  $cl_hora );

    if ( ! empty( $_POST['correo_destino'] ) ) {
        $correo = sanitize_email( $_POST['correo_destino'] );
        if ( $correo ) update_option( 'maffer_correo_destino', $correo );
    }
    maffer_limpiar_cache();
    wp_redirect( admin_url( 'admin.php?page=maffer-panel&msg=horario_ok' ) );
    exit;
} );

// ── FUNCIÓN: GENERAR XLSX (columnas sin Turno, con Fecha) ─────
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
    foreach ( $cabeceras as $c ) { $cab_idx[] = $agregar( $c ); }
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

// ── FUNCIÓN: HTML DEL CORREO (Ciclo semanal) ─────────────────
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

// ── FUNCIÓN: ENVIAR CORREO CON XLSX (ciclo actual) ────────────
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
    if ( ! $tmp_xlsx ) return false;

    $html = maffer_html_correo( $rows, $rango_fmt );
    add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
    $enviado = wp_mail( $dest, $asunto, $html, array( 'Content-Type: text/html; charset=UTF-8' ), array( $tmp_xlsx ) );
    remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
    @unlink( $tmp_xlsx );
    return $enviado;
}

// ── 9. ADMIN-POST: ENVIAR CORREO MANUAL ──────────────────────
add_action( 'admin_post_maffer_enviar_correo', function () {
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
} );

// ── Saltar confirmación de logout de WordPress ───────────────
add_action( 'check_admin_referer', function( $action ) {
    if ( $action === 'log-out' ) {
        $redirect = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : home_url();
        wp_logout();
        wp_safe_redirect( $redirect );
        exit;
    }
}, 10, 1 );

// ── AJAX: Cambiar contraseña del gestor ──────────────────────
add_action( 'wp_ajax_maffer_cambiar_password', function () {
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
} );