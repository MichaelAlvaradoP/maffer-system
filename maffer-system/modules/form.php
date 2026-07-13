<?php
/**
 * Maffer System — Module: Form Shortcode and AJAX Submit
 *
 * Formulario personalizado con shortcode [maffer_formulario].
 * Maneja el renderizado del formulario, validación de RUT (Módulo 11),
 * y envío AJAX con verificación de duplicado por ciclo semanal.
 *
 * Migrado desde WPCode snippet 140 (Formulario personalizado shortcode 6).
 *
 * CAMBIOS v2 (ciclo semanal / cena único):
 *   - Turno eliminado — siempre cena.
 *   - Formulario: 3 secciones (Datos, Menú Cena, Observaciones).
 *   - Duplicado por ciclo: fecha >= fecha_ciclo.
 *   - Cuenta regresiva cuando sistema cerrado + apertura futura.
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Encolar fuentes ──────────────────────────────────────────────────────────

if ( ! function_exists( 'maffer_enqueue_form_assets' ) ) {

    /**
     * Enqueue Google Fonts for the form.
     */
    function maffer_enqueue_form_assets() {
        wp_enqueue_style(
            'maffer-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap',
            array(),
            null
        );
    }
}
add_action( 'wp_enqueue_scripts', 'maffer_enqueue_form_assets' );


// ── AJAX: Guardar registro desde formulario público ─────────────────────────

if ( ! function_exists( 'maffer_ajax_submit_form' ) ) {

    /**
     * AJAX handler: guardar un registro enviado desde el formulario público.
     *
     * Incluye validación de RUT (Módulo 11), verificación de duplicado
     * por ciclo semanal, y filtro de soft-delete.
     *
     * @return void Sends JSON response and dies.
     */
    function maffer_ajax_submit_form() {
        if ( ! check_ajax_referer( 'maffer_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'mensaje' => 'Solicitud no autorizada.' ), 403 );
        }

        $nombre        = isset( $_POST['nombre'] )        ? sanitize_text_field( $_POST['nombre'] )            : '';
        $rut_raw       = isset( $_POST['rut'] )           ? sanitize_text_field( $_POST['rut'] )               : '';
        $menus_json_str= isset( $_POST['menus'] )         ? stripslashes($_POST['menus'])                      : '';
        $observaciones = isset( $_POST['observaciones'] ) ? sanitize_textarea_field( $_POST['observaciones'] ) : '';
        $terminos      = isset( $_POST['terminos'] )      ? (bool) $_POST['terminos']                          : false;

        // Validaciones básicas
        if ( empty( $nombre ) || strlen( $nombre ) < 2 ) {
            wp_send_json_error( array( 'campo' => 'nombre', 'mensaje' => 'El nombre es requerido.' ) );
        }
        if ( empty( $rut_raw ) ) {
            wp_send_json_error( array( 'campo' => 'rut', 'mensaje' => 'El RUT es requerido.' ) );
        }
        $menus_seleccionados = json_decode( $menus_json_str, true );
        if ( empty( $menus_seleccionados ) || ! is_array( $menus_seleccionados ) ) {
            wp_send_json_error( array( 'campo' => 'menu', 'mensaje' => 'Debes seleccionar un menú.' ) );
        }
        if ( ! $terminos ) {
            wp_send_json_error( array( 'campo' => 'terminos', 'mensaje' => 'Debes aceptar las condiciones.' ) );
        }

        // Estado del sistema
        $estado = function_exists( 'maffer_obtener_estado_sistema' )
            ? maffer_obtener_estado_sistema()
            : get_option( 'maffer_estado_sistema', 'cerrado' );

        if ( $estado !== 'abierto' ) {
            wp_send_json_error( array( 'mensaje' => 'El sistema está cerrado. No se aceptan registros en este momento.' ) );
        }

        // Normalizar y validar RUT (Módulo 11)
        $rut = strtoupper( preg_replace( '/\./', '', $rut_raw ) );
        if ( ! maffer_validar_rut_php( $rut ) ) {
            wp_send_json_error( array( 'campo' => 'rut', 'mensaje' => 'El RUT ingresado no es válido.' ) );
        }

        // Zona horaria y fechas
        $tz_chile = new DateTimeZone( 'America/Santiago' );
        $ahora    = new DateTime( 'now', $tz_chile );
        $hoy      = $ahora->format( 'Y-m-d' );
        $hora_cl  = $ahora->format( 'H:i:s' );

        global $wpdb;
        $tabla = $wpdb->prefix . 'maffer_registros';
        $tabla_detalles = $wpdb->prefix . 'maffer_registro_detalles';

        // Soft-delete filter: excluir registros eliminados lógicamente
        $soft_delete_filter = '( deleted_at IS NULL OR deleted_at = %s )';

        // Verificar duplicado por ciclo semanal
        $fecha_ciclo = function_exists( 'maffer_get_fecha_ciclo' )
            ? maffer_get_fecha_ciclo()
            : null;

        if ( $fecha_ciclo ) {
            $existe = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla} WHERE rut = %s AND fecha >= %s AND {$soft_delete_filter}",
                $rut,
                $fecha_ciclo,
                '0000-00-00 00:00:00'
            ) );
        } else {
            $existe = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla} WHERE rut = %s AND fecha = %s AND {$soft_delete_filter}",
                $rut,
                $hoy,
                '0000-00-00 00:00:00'
            ) );
        }

        if ( $existe > 0 ) {
            wp_send_json_error( array(
                'campo'   => 'rut',
                'mensaje' => 'Este RUT ya tiene un registro para esta semana. Solo se permite un registro por semana.',
            ) );
        }

        // Guardar registro maestro
        $result = $wpdb->insert(
            $tabla,
            array(
                'nombre'        => $nombre,
                'rut'           => $rut,
                'turno'         => 'cena',
                'menu_titulo'   => 'Selección Semanal',
                'menu_desc'     => 'Ver detalles por día',
                'observaciones' => $observaciones,
                'fecha'         => $hoy,
                'hora'          => $hora_cl,
                'estado_dia'    => $estado,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            wp_send_json_error( array( 'mensaje' => 'Error al guardar el registro. Intenta de nuevo.' ) );
        }
        
        $registro_id = $wpdb->insert_id;

        // Guardar detalles (opciones de menú por día)
        foreach ( $menus_seleccionados as $dia => $menu_valor ) {
            $partes      = explode( '##', (string) $menu_valor, 2 );
            $menu_titulo = sanitize_text_field( trim( isset( $partes[0] ) ? $partes[0] : $menu_valor ) );
            $menu_desc   = sanitize_textarea_field( trim( isset( $partes[1] ) ? $partes[1] : '' ) );
            
            $wpdb->insert(
                $tabla_detalles,
                array(
                    'registro_id' => $registro_id,
                    'dia_semana'  => sanitize_key( $dia ),
                    'menu_titulo' => $menu_titulo,
                    'menu_desc'   => $menu_desc,
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }

        wp_send_json_success( array(
            'mensaje'     => '¡Registro completado!',
            'nombre'      => $nombre,
            'rut'         => $rut,
            'menu_titulo' => 'Selección Semanal',
            'hora'        => $ahora->format( 'H:i' ),
        ) );
    }
}

add_action( 'wp_ajax_maffer_submit_form',        'maffer_ajax_submit_form' );
add_action( 'wp_ajax_nopriv_maffer_submit_form', 'maffer_ajax_submit_form' );


// ── Validación Módulo 11 (servidor) ─────────────────────────────────────────

if ( ! function_exists( 'maffer_validar_rut_php' ) ) {

    /**
     * Valida un RUT chileno usando el algoritmo del Módulo 11.
     *
     * @param string $rut RUT completo con guion (ej: "12345678-5").
     * @return bool True si el dígito verificador es correcto.
     */
    function maffer_validar_rut_php( $rut ) {
        $rut = trim( strtoupper( $rut ) );
        if ( ! preg_match( '/^\d{1,8}-[\dK]$/', $rut ) ) {
            return false;
        }
        list( $cuerpo, $dv ) = explode( '-', $rut );
        $suma   = 0;
        $factor = 2;
        foreach ( array_reverse( str_split( $cuerpo ) ) as $d ) {
            $suma  += (int) $d * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $resto  = $suma % 11;
        $dvCalc = $resto === 0 ? '0' : ( $resto === 1 ? 'K' : (string) ( 11 - $resto ) );
        return $dv === $dvCalc;
    }
}


// ── SHORTCODE: Renderizar formulario ─────────────────────────────────────────

if ( ! function_exists( 'maffer_render_formulario' ) ) {

    /**
     * Renderiza el formulario de registro de alimentación.
     *
     * Incluye header con logo, hero con estado del sistema,
     * cuenta regresiva si aplica, formulario activo con 3 secciones,
     * y pantalla de éxito post-registro.
     *
     * @return string HTML del formulario.
     */
    function maffer_render_formulario() {

        $estado_dia = function_exists( 'maffer_obtener_estado_sistema' )
            ? maffer_obtener_estado_sistema()
            : maffer_obtener_estado_dia();

        $hoy_fmt  = date_i18n( 'd \d\e F', current_time( 'timestamp' ) );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'maffer_nonce' );

        // Apertura programada para cuenta regresiva
        $apertura_unix   = 0;
        $server_now_unix = time();
        $ap_str          = '';

        if ( function_exists( 'maffer_get_apertura_dt' ) ) {
            $ap_dt = maffer_get_apertura_dt();
            if ( $ap_dt ) {
                $apertura_unix = $ap_dt->getTimestamp();
                $ap_str = $ap_dt->setTimezone( new DateTimeZone( 'America/Santiago' ) )
                                ->format( 'd/m/Y \a \l\a\s H:i' );
            }
        }

        $es_futuro              = ( $apertura_unix > 0 && $apertura_unix > $server_now_unix );
        $segundos_para_apertura = $es_futuro ? ( $apertura_unix - $server_now_unix ) : 0;

        // Cierre programado — para mostrar en el formulario cuando está abierto
        $cierre_unix = 0;
        $cl_str      = '';
        if ( function_exists( 'maffer_get_cierre_dt' ) ) {
            $cl_dt = maffer_get_cierre_dt();
            if ( $cl_dt ) {
                $cierre_unix = $cl_dt->getTimestamp();
                $dias_frm = array(
                    'Sunday'    => 'domingo',
                    'Monday'    => 'lunes',
                    'Tuesday'   => 'martes',
                    'Wednesday' => 'miércoles',
                    'Thursday'  => 'jueves',
                    'Friday'    => 'viernes',
                    'Saturday'  => 'sábado',
                );
                $cl_str = $dias_frm[ $cl_dt->format( 'l' ) ] . ' '
                        . $cl_dt->format( 'j' )
                        . ' a las ' . $cl_dt->format( 'H:i' );
            }
        }

        // Menús de cena por día
        $dias = array('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
        $menus_json = array();
        $tiene_cena = false;

        foreach ( $dias as $dia ) {
            $menus_json[$dia] = array();
            $menus_raw = get_option( "maffer_menu_cena_{$dia}", array() );
            foreach ( $menus_raw as $idx => $item ) {
                $titulo = sanitize_text_field( isset( $item['title'] ) ? $item['title'] : '' );
                $desc   = sanitize_text_field( isset( $item['desc'] )  ? $item['desc']  : '' );
                if ( empty( $titulo ) ) {
                    continue;
                }
                $menus_json[$dia][] = array(
                    'id'     => 'cena_' . $dia . '_' . $idx,
                    'titulo' => $titulo,
                    'desc'   => $desc,
                    'valor'  => $titulo . ' ## ' . $desc,
                );
            }
            if ( ! empty( $menus_json[$dia] ) ) {
                $tiene_cena = true;
            }
        }

        ob_start();
        ?>
        <div id="maffer-app" class="maffer-wrap theme-cream">

            <!-- ── FORMULARIO ─────────────────────────────────── -->
            <div class="maffer-shell" id="maffer-form-screen">

                <!-- Header -->
                <div class="maffer-head">
                    <div class="maffer-mark">
                        <div class="maffer-badge">
                            <img src="<?php echo esc_url( content_url( '/uploads/2026/04/LOGO-MAFFER-scaled.webp' ) ); ?>"
                                 alt="Maffer"
                                 width="120" height="120"
                                 style="width:120px;height:120px;object-fit:contain;display:block;" />
                        </div>
                        <div class="maffer-mark-text">
                            <div class="maffer-wordmark">Sistema de Alimentación</div>
                            <div class="maffer-sub">Registro de pedidos · Cena</div>
                            <div class="maffer-head-date"><?php echo esc_html( $hoy_fmt ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Hero -->
                <div class="maffer-hero">
                    <h1 class="maffer-hero-title">
                        <?php if ( $estado_dia === 'abierto' ) : ?>
                            ¿Qué menú vas a tomar?
                        <?php elseif ( $es_futuro && $segundos_para_apertura < 3600 ) : ?>
                            El registro abre pronto
                        <?php elseif ( $es_futuro && $segundos_para_apertura < 86400 ) : ?>
                            El registro abre hoy
                        <?php elseif ( $es_futuro ) : ?>
                            El registro está cerrado
                        <?php else : ?>
                            Registro cerrado
                        <?php endif; ?>
                    </h1>
                    <div class="maffer-hero-turno">
                        <span class="maffer-chip" id="maffer-chip-turno">SERVICIO DE CENA</span>
                        <span class="maffer-chip-date">hoy, <?php echo esc_html( strtolower( $hoy_fmt ) ); ?></span>
                        <?php if ( $estado_dia === 'abierto' && $cl_str ) : ?>
                        <span class="maffer-chip-date" style="color:#b94a32;font-weight:600">
                            · cierra el <?php echo esc_html( $cl_str ); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( $estado_dia === 'cerrado' && $es_futuro ) : ?>
                <!-- ── CUENTA REGRESIVA ─────────────────────── -->
                <div class="maffer-countdown-wrap" id="maffer-countdown">
                    <div class="maffer-countdown-label">El sistema abrirá en:</div>
                    <div class="maffer-countdown-digits">
                        <div class="maffer-cd-unit">
                            <span class="maffer-cd-num" id="mf-cd-d">00</span>
                            <span class="maffer-cd-lbl">Días</span>
                        </div>
                        <span class="maffer-cd-sep">:</span>
                        <div class="maffer-cd-unit">
                            <span class="maffer-cd-num" id="mf-cd-h">00</span>
                            <span class="maffer-cd-lbl">Horas</span>
                        </div>
                        <span class="maffer-cd-sep">:</span>
                        <div class="maffer-cd-unit">
                            <span class="maffer-cd-num" id="mf-cd-m">00</span>
                            <span class="maffer-cd-lbl">Min</span>
                        </div>
                        <span class="maffer-cd-sep">:</span>
                        <div class="maffer-cd-unit">
                            <span class="maffer-cd-num" id="mf-cd-s">00</span>
                            <span class="maffer-cd-lbl">Seg</span>
                        </div>
                    </div>
                    <?php if ( $ap_str ) : ?>
                    <div class="maffer-apertura-hint">Apertura programada: <?php echo esc_html( $ap_str ); ?></div>
                    <?php endif; ?>
                </div>

                <?php elseif ( $estado_dia === 'cerrado' ) : ?>
                <!-- ── CERRADO SIN FECHA ────────────────────── -->
                <div class="maffer-cerrado-banner">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-1-7v2h2v-2h-2zm0-8v6h2V7h-2z" fill="currentColor"/>
                    </svg>
                    Los registros del período están cerrados. Consulta con el encargado.
                </div>

                <?php else : ?>
                <!-- ── FORMULARIO ACTIVO ───────────────────── -->
                <form id="maffer-registro-form" novalidate>
                    <?php wp_nonce_field( 'maffer_nonce', 'maffer_nonce_field' ); ?>

                    <!-- Sección 1: Datos -->
                    <div class="maffer-section">
                        <div class="maffer-section-label">
                            <div class="maffer-step">1</div>
                            TUS DATOS
                        </div>
                        <div class="maffer-field" id="field-nombre">
                            <div class="maffer-field-row">
                                <label class="maffer-label" for="maffer-nombre">Nombre completo</label>
                            </div>
                            <input class="maffer-input" type="text" id="maffer-nombre" name="nombre"
                                   placeholder="Tu nombre y apellido"
                                   autocomplete="name" inputmode="text" />
                            <div class="maffer-field-hint" id="hint-nombre"></div>
                        </div>
                        <div class="maffer-field" id="field-rut">
                            <div class="maffer-field-row">
                                <label class="maffer-label" for="maffer-rut">RUT</label>
                                <span class="maffer-label-hint">Con puntos y guion</span>
                            </div>
                            <input class="maffer-input" type="text" id="maffer-rut" name="rut"
                                   placeholder="12.345.678-9"
                                   autocomplete="off" inputmode="text" maxlength="12" />
                            <div class="maffer-field-hint" id="hint-rut"></div>
                            <div class="maffer-field-check" id="check-rut" style="display:none">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
                                </svg>
                                RUT disponible
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Menú de Cena -->
                    <div class="maffer-section">
                        <div class="maffer-section-label">
                            <div class="maffer-step">2</div>
                            ELIGE TU MENÚ DE CENA
                        </div>
                        <?php if ( ! $tiene_cena ) : ?>
                            <div class="maffer-empty-menu">No hay menús de cena configurados por el momento.</div>
                        <?php else : ?>
                            <?php foreach ( $dias as $dia ) : ?>
                            <div class="maffer-dia-group" style="margin-bottom:24px;">
                                <div class="maffer-dia-label" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;color:var(--orange-600);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid var(--border);padding-bottom:4px;">
                                    Cena · <?php echo esc_html( $dia ); ?>
                                </div>
                                <?php if ( empty( $menus_json[$dia] ) ) : ?>
                                    <div class="maffer-empty-menu" style="padding:10px;font-size:13px;">No hay servicio de cena este día.</div>
                                <?php else : ?>
                                    <div class="maffer-menu-list maffer-menu-dia-list" data-dia="<?php echo esc_attr($dia); ?>" role="radiogroup" aria-label="Menú de cena <?php echo esc_attr($dia); ?>">
                                        <?php foreach ( $menus_json[$dia] as $menu ) : ?>
                                        <button type="button" class="maffer-menu-card"
                                            data-valor="<?php echo esc_attr( $menu['valor'] ); ?>"
                                            data-id="<?php echo esc_attr( $menu['id'] ); ?>"
                                            role="radio" aria-checked="false">
                                            <div class="maffer-card-head">
                                                <div>
                                                    <div class="maffer-card-title"><?php echo esc_html( $menu['titulo'] ); ?></div>
                                                    <?php if ( ! empty( $menu['desc'] ) ) : ?>
                                                    <div class="maffer-card-desc"><?php echo esc_html( $menu['desc'] ); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="maffer-radio-dot" aria-hidden="true">
                                                    <div class="maffer-radio-inner">
                                                        <svg width="10" height="10" viewBox="0 0 12 12" fill="none">
                                                            <path d="M2 6l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="maffer-field-hint" id="hint-menu"></div>
                    </div>

                    <!-- Sección 3: Observaciones -->
                    <div class="maffer-section">
                        <div class="maffer-section-label">
                            <div class="maffer-step">3</div>
                            OBSERVACIONES <span class="maffer-step-optional">(opcional)</span>
                        </div>
                        <div class="maffer-field" id="field-obs">
                            <textarea class="maffer-input maffer-textarea"
                                      id="maffer-observaciones" name="observaciones"
                                      placeholder="Alergias, restricciones alimentarias u otro comentario..."
                                      rows="3" maxlength="500"></textarea>
                        </div>
                    </div>

                    <!-- Checkbox aceptación -->
                    <div class="maffer-check-wrap" id="field-terminos">
                        <label class="maffer-check">
                            <input type="checkbox" id="maffer-terminos" name="terminos" />
                            <div class="maffer-check-box" aria-hidden="true">
                                <svg width="10" height="10" viewBox="0 0 12 12" fill="none">
                                    <path d="M2 6l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="maffer-check-text">
                                Confirmo que los datos ingresados son correctos y autorizo el registro de mi pedido de cena.
                            </div>
                        </label>
                        <div class="maffer-field-hint" id="hint-terminos"></div>
                    </div>

                    <!-- Botón enviar -->
                    <button type="submit" class="maffer-btn-primary maffer-btn-disabled"
                            id="maffer-submit-btn" disabled>
                        <span class="maffer-btn-text">Registrar Pedido</span>
                        <svg class="maffer-spinner" style="display:none" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="white" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>
                <?php endif; ?>
            </div><!-- /maffer-shell -->

            <!-- ── PANTALLA DE ÉXITO ───────────────────────────── -->
            <div class="maffer-shell maffer-success-screen" id="maffer-success-screen" style="display:none">
                <div class="maffer-success-check">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                        <path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2 class="maffer-success-title">¡Pedido registrado!</h2>
                <p class="maffer-success-sub">Tu elección fue guardada correctamente.</p>
                <div class="maffer-success-card">
                    <div class="maffer-success-row">
                        <span>Nombre</span>
                        <strong id="success-nombre">—</strong>
                    </div>
                    <div class="maffer-success-row">
                        <span>RUT</span>
                        <strong id="success-rut">—</strong>
                    </div>
                    <div class="maffer-success-divider"></div>
                    <div class="maffer-success-row">
                        <span>Turno</span>
                        <strong>Cena</strong>
                    </div>
                    <div class="maffer-success-row">
                        <span>Menú seleccionado</span>
                        <strong id="success-menu">—</strong>
                    </div>
                    <div class="maffer-success-row">
                        <span>Hora de registro</span>
                        <strong id="success-hora">—</strong>
                    </div>
                </div>
                <p class="maffer-success-note">Si necesitas corregir tu pedido, comunícate con el encargado.</p>
            </div>

        </div><!-- /maffer-app -->

        <!-- ── CSS ─────────────────────────────────────────────── -->
        <style>
        #maffer-app {
            --orange:     #E67E22;
            --orange-600: #d26a10;
            --orange-50:  #fdf1e5;
            --orange-100: #fbe2cc;
            --green:      #5a7a3a;
            --red:        #b94a32;
            --bg:         #f6efe3;
            --surface:    #fffaf1;
            --surface-2:  #fbf3e4;
            --ink:        #2a231a;
            --ink-2:      #6b5d4c;
            --ink-3:      #97897a;
            --border:     #e6d8bf;
            --border-2:   #d8c59f;
            --shadow-1:   0 1px 2px rgba(70,50,20,.04), 0 8px 24px -12px rgba(70,50,20,.14);
            --shadow-2:   0 1px 2px rgba(70,50,20,.06), 0 18px 48px -20px rgba(70,50,20,.22);
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 28px 16px 80px;
        }
        #maffer-app *, #maffer-app *::before, #maffer-app *::after { box-sizing: border-box; }

        /* Shell */
        .maffer-shell {
            width: 100%; max-width: 560px;
            background: var(--surface);
            border-radius: 28px;
            padding: 28px 28px 26px;
            box-shadow: var(--shadow-2);
            border: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        @media(min-width:640px){ .maffer-shell { max-width:620px; padding:32px 36px 30px; } }
        .maffer-shell::before {
            content:''; position:absolute; inset:0 0 auto 0;
            height:4px; background:linear-gradient(90deg,var(--orange),#f0a35a);
        }

        /* Header */
        .maffer-head { margin-bottom:20px; padding-bottom:18px; border-bottom:1px solid var(--border); }
        .maffer-mark { display:flex; align-items:center; gap:16px; }
        .maffer-badge { width:120px; height:120px; background:transparent; border-radius:0; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .maffer-mark-text { display:flex; flex-direction:column; justify-content:center; gap:4px; }
        .maffer-wordmark { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:20px; color:var(--ink); line-height:1.15; letter-spacing:-.01em; }
        .maffer-sub { font-size:13px; color:var(--ink-3); font-weight:500; line-height:1.4; }
        .maffer-head-date { display:inline-flex; align-items:center; font-size:11px; color:var(--orange); font-weight:600; background:var(--orange-50); border:1px solid var(--orange-100); border-radius:20px; padding:3px 10px; margin-top:6px; width:fit-content; letter-spacing:.02em; }
        @media(max-width:400px){ .maffer-badge{ width:90px; height:90px; } .maffer-badge img{ width:90px!important; height:90px!important; } .maffer-wordmark{ font-size:17px; } }

        /* Hero */
        .maffer-hero { margin-bottom:22px; }
        .maffer-hero-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:26px; color:var(--ink); line-height:1.15; margin:0 0 8px; letter-spacing:-.02em; }
        .maffer-hero-turno { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .maffer-chip { font-size:10px; font-weight:700; letter-spacing:.06em; color:var(--orange); background:var(--orange-50); border:1px solid var(--orange-100); border-radius:20px; padding:3px 10px; }
        .maffer-chip-date { font-size:12px; color:var(--ink-3); font-weight:500; }

        /* Secciones */
        .maffer-section { margin-bottom:22px; }
        .maffer-section-label { display:flex; align-items:center; gap:8px; font-size:10px; font-weight:700; letter-spacing:.08em; color:var(--ink-3); text-transform:uppercase; margin-bottom:12px; }
        .maffer-step { width:20px; height:20px; background:var(--orange); color:white; border-radius:50%; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-family:'Plus Jakarta Sans',sans-serif; }
        .maffer-step-optional { font-size:9px; font-weight:500; opacity:.7; text-transform:none; letter-spacing:0; }

        /* Campos */
        .maffer-field { margin-bottom:14px; }
        .maffer-field-row { display:flex; align-items:baseline; justify-content:space-between; margin-bottom:6px; }
        .maffer-label { font-size:13px; font-weight:600; color:var(--ink-2); font-family:'Plus Jakarta Sans',sans-serif; }
        .maffer-label-hint { font-size:11px; color:var(--ink-3); }
        .maffer-input { width:100%; background:var(--surface-2); border:1.5px solid var(--border); border-radius:12px; padding:14px; font-size:16px; font-family:'Inter',sans-serif; color:var(--ink); outline:none; transition:border-color .15s,background .15s,box-shadow .15s; -webkit-appearance:none; appearance:none; }
        .maffer-input::placeholder { color:var(--ink-3); }
        .maffer-input:focus { border-color:var(--orange); background:var(--surface); box-shadow:0 0 0 4px rgba(230,126,34,.16); }
        .maffer-textarea { resize:vertical; min-height:80px; line-height:1.5; }
        .maffer-field.err .maffer-input { border-color:#c97060; background:#fdf4f2; }
        .maffer-field.ok .maffer-input { border-color:#7ea860; }
        .maffer-field-hint { font-size:12px; margin-top:5px; min-height:16px; transition:color .15s; }
        .maffer-field.err .maffer-field-hint { color:var(--red); }
        .maffer-field-check { display:flex; align-items:center; gap:4px; font-size:12px; color:var(--green); font-weight:600; margin-top:5px; }

        /* Tarjetas menú */
        .maffer-menu-list { display:flex; flex-direction:column; gap:10px; }
        .maffer-menu-card { width:100%; text-align:left; background:var(--surface-2); border:1.5px solid var(--border); border-radius:16px; padding:16px; cursor:pointer; transition:border-color .15s,background .15s,box-shadow .15s,transform .12s; font-family:inherit; color:var(--ink); -webkit-tap-highlight-color:transparent; }
        .maffer-menu-card:hover { border-color:#c8a97a; background:#fef6ec; transform:translateY(-1px); box-shadow:var(--shadow-1); }
        .maffer-menu-card.selected { border-color:var(--orange); border-width:2px; padding:15px; background:#fef6ec; box-shadow:0 0 0 4px rgba(230,126,34,.12),var(--shadow-1); }
        .maffer-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; min-width:0; }
        .maffer-card-head > div:first-child { flex:1; min-width:0; overflow:hidden; }
        .maffer-card-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:16px; color:var(--ink); line-height:1.3; margin-bottom:4px; word-break:break-word; }
        .maffer-card-desc { font-size:13px; color:var(--ink-2); line-height:1.6; word-break:break-word; white-space:normal; }
        .maffer-radio-dot { width:22px; height:22px; border-radius:50%; border:2px solid var(--border-2); background:var(--surface); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:border-color .2s,background .2s; margin-top:2px; }
        .maffer-menu-card.selected .maffer-radio-dot { border-color:var(--orange); background:var(--orange); }
        .maffer-radio-inner { width:0; height:0; overflow:hidden; display:flex; align-items:center; justify-content:center; transition:width .22s cubic-bezier(.3,1.4,.5,1),height .22s cubic-bezier(.3,1.4,.5,1); }
        .maffer-menu-card.selected .maffer-radio-inner { width:14px; height:14px; }
        .maffer-empty-menu { font-size:14px; color:var(--ink-3); text-align:center; padding:20px; background:var(--surface-2); border-radius:12px; border:1px dashed var(--border-2); }

        /* Checkbox */
        .maffer-check-wrap { margin-bottom:18px; }
        .maffer-check { display:flex; align-items:flex-start; gap:10px; cursor:pointer; user-select:none; }
        .maffer-check input[type="checkbox"] { position:absolute; opacity:0; width:0; height:0; }
        .maffer-check-box { width:20px; height:20px; border-radius:6px; border:1.5px solid var(--border-2); background:var(--surface-2); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; transition:background .15s,border-color .15s; }
        .maffer-check-box svg { opacity:0; transform:scale(.5); transition:opacity .15s,transform .15s; }
        .maffer-check input:checked ~ .maffer-check-box { background:var(--orange); border-color:var(--orange); }
        .maffer-check input:checked ~ .maffer-check-box svg { opacity:1; transform:scale(1); }
        .maffer-check-text { font-size:13px; color:var(--ink-2); line-height:1.5; }

        /* Botón */
        .maffer-btn-primary { width:100%; padding:16px; border:none; border-radius:14px; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:16px; color:white; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; background:linear-gradient(180deg,#ef8a2d,var(--orange) 60%,var(--orange-600)); box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 8px 20px -6px rgba(230,126,34,.55),0 2px 4px rgba(180,90,20,.2); transition:transform .12s,box-shadow .12s,opacity .12s; -webkit-tap-highlight-color:transparent; }
        .maffer-btn-primary:hover:not(.maffer-btn-disabled):not(.maffer-btn-loading) { transform:translateY(-1px); box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 -2px 0 rgba(0,0,0,.12) inset,0 12px 28px -6px rgba(230,126,34,.55),0 3px 6px rgba(180,90,20,.25); }
        .maffer-btn-disabled { opacity:.45; cursor:not-allowed; background:var(--border-2); box-shadow:none; }
        .maffer-spinner { width:18px; height:18px; animation:maffer-spin .7s linear infinite; }
        @keyframes maffer-spin { to{ transform:rotate(360deg); } }

        /* Cerrado */
        .maffer-cerrado-banner { display:flex; align-items:center; gap:10px; background:var(--surface-2); border:1px solid var(--border-2); border-radius:12px; padding:16px; font-size:14px; color:var(--ink-2); margin-bottom:16px; }

        /* Cuenta regresiva */
        .maffer-countdown-wrap { background:linear-gradient(135deg,#fff8f2,#fff3e8); border:2px solid #F0C080; border-radius:16px; padding:20px 24px; margin-bottom:16px; text-align:center; }
        .maffer-countdown-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#B08040; margin-bottom:14px; }
        .maffer-countdown-digits { display:flex; gap:10px; align-items:center; justify-content:center; }
        .maffer-cd-unit { text-align:center; }
        .maffer-cd-num { font-size:34px; font-weight:800; color:var(--orange); line-height:1; display:block; font-variant-numeric:tabular-nums; font-family:'Plus Jakarta Sans',sans-serif; }
        .maffer-cd-lbl { font-size:10px; font-weight:700; color:#B08040; text-transform:uppercase; letter-spacing:.5px; }
        .maffer-cd-sep { font-size:28px; font-weight:800; color:#E0A050; padding-bottom:14px; }
        .maffer-apertura-hint { font-size:12px; color:#B08040; font-weight:600; margin-top:12px; }

        /* Success */
        .maffer-success-screen { text-align:center; padding-top:32px; animation:maffer-rise .4s ease both; }
        @keyframes maffer-rise { from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
        .maffer-success-check { width:72px; height:72px; background:linear-gradient(135deg,var(--green),#3d5a28); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; box-shadow:0 8px 24px -8px rgba(90,122,58,.45); animation:maffer-pop .4s .1s cubic-bezier(.3,1.4,.5,1) both; }
        @keyframes maffer-pop { from{ transform:scale(.5); opacity:0; } to{ transform:scale(1); opacity:1; } }
        .maffer-success-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:26px; color:var(--ink); margin:0 0 8px; }
        .maffer-success-sub { font-size:14px; color:var(--ink-2); margin:0 0 24px; }
        .maffer-success-card { background:var(--surface-2); border:1px solid var(--border); border-radius:16px; padding:16px; text-align:left; margin-bottom:20px; }
        .maffer-success-row { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:6px 0; font-size:14px; }
        .maffer-success-row span { color:var(--ink-3); flex-shrink:0; }
        .maffer-success-row strong { color:var(--ink); font-weight:600; text-align:right; font-family:'Plus Jakarta Sans',sans-serif; }
        .maffer-success-divider { height:1px; background:var(--border); margin:6px 0; }
        .maffer-success-note { font-size:12px; color:var(--ink-3); margin:0; }

        @media(max-width:480px){ #maffer-app{ padding:16px 10px 60px; } .maffer-shell{ padding:20px 18px 18px; border-radius:22px; } .maffer-hero-title{ font-size:22px; } .maffer-cd-num{ font-size:26px; } }

        #maffer-app { margin-left:calc(-1 * var(--elementor-widget-padding,0px)); margin-right:calc(-1 * var(--elementor-widget-padding,0px)); width:calc(100% + 2 * var(--elementor-widget-padding,0px)); }
        </style>

        <!-- ── JavaScript ────────────────────────────────────── -->
        <script>
        (function() {
            'use strict';
            var AJAX_URL         = <?php echo json_encode( $ajax_url ); ?>;
            var NONCE            = <?php echo json_encode( $nonce ); ?>;
            var APERTURA_UNIX    = <?php echo intval( $apertura_unix ); ?>;
            var SERVER_NOW_UNIX  = <?php echo intval( $server_now_unix ); ?>;
            var ESTADO           = <?php echo json_encode( $estado_dia ); ?>;

            // Corrección desfase reloj cliente
            var CLIENT_NOW_UNIX = Math.floor(Date.now() / 1000);
            var TIME_OFFSET     = SERVER_NOW_UNIX - CLIENT_NOW_UNIX;

            var DIAS_DISPONIBLES = <?php 
                $dias_disp = array();
                foreach($dias as $d) { if(!empty($menus_json[$d])) $dias_disp[] = $d; }
                echo json_encode($dias_disp); 
            ?>;

            var state = {
                rutValido:    false,
                menuValores:  {},
                nombreValido: false,
                terminosOk:   false,
            };

            function pad2(n){ return String(n).padStart(2,'0'); }

            // ── Inicializar ────────────────────────────────────
            document.addEventListener('DOMContentLoaded', function() {
                initForm();
                initCountdown();
            });

            function initForm() {
                var form = document.getElementById('maffer-registro-form');
                if (!form) return;

                var inputNombre = document.getElementById('maffer-nombre');
                var inputRut    = document.getElementById('maffer-rut');
                var chkTerminos = document.getElementById('maffer-terminos');
                var btnSubmit   = document.getElementById('maffer-submit-btn');

                // Nombre
                inputNombre.addEventListener('blur', function() {
                    validateNombre(this.value); updateSubmitButton();
                });
                inputNombre.addEventListener('input', function() {
                    state.nombreValido = this.value.trim().length >= 2;
                    if (getField('nombre').classList.contains('err')) validateNombre(this.value);
                    updateSubmitButton();
                });

                // RUT
                var rutTimeout = null;
                inputRut.addEventListener('input', function() {
                    var formatted = formatRut(this.value);
                    this.value = formatted;
                    clearTimeout(rutTimeout);
                    resetRutFeedback();
                    state.rutValido = false;
                    updateSubmitButton();
                    var rutSinPuntos = formatted.replace(/\./g, '');
                    if (rutSinPuntos.replace('-','').length < 7) return;
                    if (!validarDV(rutSinPuntos)) {
                        setFieldError('rut', 'RUT inválido. Verifica el dígito verificador.');
                        return;
                    }
                    rutTimeout = setTimeout(function() { checkRutAjax(rutSinPuntos); }, 600);
                });

                // Menú
                document.querySelectorAll('.maffer-menu-card').forEach(function(card) {
                    card.addEventListener('click', function() {
                        var list = this.closest('.maffer-menu-dia-list');
                        var dia = list.dataset.dia;
                        list.querySelectorAll('.maffer-menu-card').forEach(function(c) {
                            c.classList.remove('selected');
                            c.setAttribute('aria-checked','false');
                        });
                        this.classList.add('selected');
                        this.setAttribute('aria-checked','true');
                        state.menuValores[dia] = this.dataset.valor;
                        clearFieldError('menu');
                        updateSubmitButton();
                    });
                });

                // Términos
                chkTerminos.addEventListener('change', function() {
                    state.terminosOk = this.checked;
                    if (this.checked) clearFieldError('terminos');
                    updateSubmitButton();
                });

                // Submit
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var valid = true;
                    if (!validateNombre(inputNombre.value)) valid = false;
                    if (!state.rutValido) { setFieldError('rut','Por favor verifica el RUT antes de enviar.'); valid = false; }
                    var faltan = DIAS_DISPONIBLES.some(function(d) { return !state.menuValores[d]; });
                    if (faltan) { setMenuError('Debes seleccionar un menú para todos los días.'); valid = false; }
                    if (!state.terminosOk) { setCheckError('terminos','Debes aceptar las condiciones.'); valid = false; }
                    if (!valid) return;
                    submitForm(form, btnSubmit);
                });
            }

            // ── Envío AJAX ──────────────────────────────────────
            function submitForm(form, btn) {
                setLoading(btn, true);
                var data = new FormData();
                data.append('action',       'maffer_submit_form');
                data.append('nonce',        NONCE);
                data.append('nombre',       document.getElementById('maffer-nombre').value.trim());
                data.append('rut',          document.getElementById('maffer-rut').value.trim());
                data.append('menus',        JSON.stringify(state.menuValores));
                data.append('observaciones',document.getElementById('maffer-observaciones').value.trim());
                data.append('terminos',     document.getElementById('maffer-terminos').checked ? '1' : '');

                fetch(AJAX_URL, { method:'POST', body:data })
                    .then(function(r){ return r.json(); })
                    .then(function(resp) {
                        setLoading(btn, false);
                        if (resp.success) {
                            showSuccess(resp.data);
                        } else {
                            var err = resp.data || {};
                            if (err.campo) {
                                setFieldError(err.campo, err.mensaje || 'Error en el campo.');
                            } else {
                                alert(err.mensaje || 'Error al enviar. Intenta de nuevo.');
                            }
                        }
                    })
                    .catch(function() {
                        setLoading(btn, false);
                        alert('Error de conexión. Verifica tu internet e intenta de nuevo.');
                    });
            }

            // ── Pantalla éxito ───────────────────────────────────
            function showSuccess(data) {
                document.getElementById('maffer-form-screen').style.display = 'none';
                var screen = document.getElementById('maffer-success-screen');
                screen.style.display = 'block';
                document.getElementById('success-nombre').textContent = data.nombre      || '—';
                document.getElementById('success-rut').textContent    = data.rut         || '—';
                document.getElementById('success-menu').textContent   = data.menu_titulo || '—';
                document.getElementById('success-hora').textContent   = data.hora        || '—';
                screen.scrollIntoView({ behavior:'smooth', block:'start' });
            }

            // ── Verificar RUT AJAX ───────────────────────────────
            function checkRutAjax(rut) {
                setRutLoading();
                var data = new FormData();
                data.append('action','maffer_validar_rut');
                data.append('nonce', NONCE);
                data.append('rut',   rut);
                fetch(AJAX_URL, { method:'POST', body:data })
                    .then(function(r){ return r.json(); })
                    .then(function(resp) {
                        if (resp.success) {
                            if (resp.data.disponible) {
                                setRutOk(); state.rutValido = true;
                            } else {
                                setFieldError('rut', resp.data.mensaje); state.rutValido = false;
                            }
                        } else {
                            setFieldError('rut','Error al verificar. Intenta de nuevo.'); state.rutValido = false;
                        }
                        updateSubmitButton();
                    })
                    .catch(function() {
                        setFieldError('rut','Error de conexión al verificar RUT.'); state.rutValido = false; updateSubmitButton();
                    });
            }

            // ── Cuenta regresiva ─────────────────────────────────
            function initCountdown() {
                if (ESTADO !== 'cerrado' || APERTURA_UNIX <= 0) return;
                var cdTimer = setInterval(function() {
                    var now  = Math.floor(Date.now() / 1000) + TIME_OFFSET;
                    var diff = APERTURA_UNIX - now;
                    if (diff <= 0) { clearInterval(cdTimer); window.location.reload(); return; }
                    var d = Math.floor(diff / 86400);
                    var h = Math.floor((diff % 86400) / 3600);
                    var m = Math.floor((diff % 3600) / 60);
                    var s = diff % 60;
                    var elD = document.getElementById('mf-cd-d'); if(elD) elD.textContent = pad2(d);
                    var elH = document.getElementById('mf-cd-h'); if(elH) elH.textContent = pad2(h);
                    var elM = document.getElementById('mf-cd-m'); if(elM) elM.textContent = pad2(m);
                    var elS = document.getElementById('mf-cd-s'); if(elS) elS.textContent = pad2(s);
                }, 1000);
            }

            // ── Helpers ──────────────────────────────────────────
            function updateSubmitButton() {
                var btn   = document.getElementById('maffer-submit-btn');
                var todosLosDiasSeleccionados = DIAS_DISPONIBLES.length > 0 && !DIAS_DISPONIBLES.some(function(d) { return !state.menuValores[d]; });
                var ready = state.nombreValido && state.rutValido && todosLosDiasSeleccionados && state.terminosOk;
                btn.disabled = !ready;
                btn.classList.toggle('maffer-btn-disabled', !ready);
            }
            function setLoading(btn, on) {
                var txt = btn.querySelector('.maffer-btn-text');
                var sp  = btn.querySelector('.maffer-spinner');
                if (on) { btn.classList.add('maffer-btn-loading'); btn.disabled=true; txt.textContent='Guardando…'; sp.style.display='block'; }
                else    { btn.classList.remove('maffer-btn-loading'); txt.textContent='Registrar Pedido'; sp.style.display='none'; }
            }
            function validateNombre(val) {
                if (val.trim().length < 2) { setFieldError('nombre','Ingresa tu nombre completo.'); state.nombreValido=false; return false; }
                clearFieldError('nombre'); state.nombreValido=true; return true;
            }
            function resetRutFeedback() {
                var f = getField('rut'); var h = document.getElementById('hint-rut'); var c = document.getElementById('check-rut');
                if(f){ f.classList.remove('err','ok'); } if(h){ h.textContent=''; } if(c){ c.style.display='none'; }
            }
            function setRutLoading() {
                var h = document.getElementById('hint-rut');
                if(h){ h.textContent='Verificando…'; h.style.color='var(--ink-3)'; }
            }
            function setRutOk() {
                var f = getField('rut'); var h = document.getElementById('hint-rut'); var c = document.getElementById('check-rut');
                if(f){ f.classList.remove('err'); f.classList.add('ok'); }
                if(h){ h.textContent=''; }
                if(c){ c.style.display='flex'; }
            }
            function setFieldError(id, msg) {
                var f = getField(id); var h = document.getElementById('hint-'+id);
                if(f){ f.classList.add('err'); f.classList.remove('ok'); }
                if(h){ h.textContent=msg; }
                if(id==='rut'){ var c=document.getElementById('check-rut'); if(c) c.style.display='none'; }
            }
            function clearFieldError(id) {
                var f = getField(id); var h = document.getElementById('hint-'+id);
                if(f) f.classList.remove('err');
                if(h) h.textContent='';
            }
            function setMenuError(msg) {
                var h = document.getElementById('hint-menu');
                if(h){ h.textContent=msg; h.style.color='var(--red)'; }
            }
            function setCheckError(id, msg) {
                var w = document.getElementById('field-'+id); var h = document.getElementById('hint-'+id);
                if(w) w.classList.add('err');
                if(h){ h.textContent=msg; h.style.color='var(--red)'; }
            }
            function getField(id) { return document.getElementById('field-'+id); }
            function validarDV(rut) {
                rut = rut.toUpperCase().replace(/\s/g,'');
                if(!rut.includes('-')) return false;
                var p = rut.split('-'); if(p.length!==2) return false;
                var cuerpo = p[0].replace(/\./g,''), dv = p[1];
                if(!/^\d+$/.test(cuerpo) || !/^[\dK]$/.test(dv)) return false;
                var sum=0,f=2;
                cuerpo.split('').reverse().forEach(function(d){ sum+=parseInt(d,10)*f; f=f===7?2:f+1; });
                var r=sum%11, calc=r===0?'0':(r===1?'K':String(11-r));
                return dv===calc;
            }
            function formatRut(val) {
                var l = val.toUpperCase().replace(/[^0-9K]/g,'');
                if(l.length<2) return l;
                var dv=l.slice(-1), b=l.slice(0,-1).replace(/\B(?=(\d{3})+(?!\d))/g,'.');
                return b+'-'+dv;
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode( 'maffer_formulario', 'maffer_render_formulario' );
