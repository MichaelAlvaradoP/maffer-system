<?php
/**
 * Maffer System — Helper Functions
 *
 * Shared utility functions extracted from WPCode snippets 155 and 158.
 * Every function is wrapped in function_exists() guard for backward
 * compatibility with snippets that may be loaded independently.
 *
 * @package   MafferSystem
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Estados del sistema ─────────────────────────────────────────

if ( ! function_exists( 'maffer_obtener_estado_sistema' ) ) {
    /**
     * Obtiene el estado global del sistema: 'abierto' | 'cerrado'.
     *
     * @return string
     */
    function maffer_obtener_estado_sistema() {
        return get_option( 'maffer_estado_sistema', 'cerrado' );
    }
}

if ( ! function_exists( 'maffer_obtener_estado_dia' ) ) {
    /**
     * Alias de compatibilidad con snippets anteriores.
     *
     * @return string
     */
    function maffer_obtener_estado_dia() {
        return maffer_obtener_estado_sistema();
    }
}

// ── Cálculo de fechas semanales (ciclo sábado-viernes) ─────────

if ( ! function_exists( 'maffer_compute_this_week_dt' ) ) {
    /**
     * Dado un día de semana (0=Dom…6=Sáb) y una hora "HH:MM",
     * devuelve el DateTime correspondiente dentro de la semana actual
     * (semana que empieza el sábado).
     *
     * @param int          $target_dow
     * @param string       $target_time Formato "HH:MM"
     * @param DateTimeZone $tz
     * @return DateTime
     */
    function maffer_compute_this_week_dt( $target_dow, $target_time, $tz ) {
        $now         = new DateTime( 'now', $tz );
        $current_dow = (int) $now->format( 'w' ); // 0=Dom … 6=Sáb

        // Días transcurridos desde el último sábado
        $days_since_sat = ( $current_dow === 6 ) ? 0 : ( $current_dow + 1 );

        // Posición del día objetivo dentro de la semana sábado-viernes
        // Sáb=0, Dom=1, Lun=2, Mar=3, Mié=4, Jue=5, Vie=6
        $week_pos = array( 6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6 );
        $pos      = isset( $week_pos[ $target_dow ] ) ? $week_pos[ $target_dow ] : 0;

        $dt = clone $now;
        $dt->modify( '-' . $days_since_sat . ' days' ); // retroceder al sábado
        $dt->modify( '+' . $pos . ' days' );            // avanzar al día objetivo

        $parts = explode( ':', $target_time );
        $dt->setTime( (int) $parts[0], isset( $parts[1] ) ? (int) $parts[1] : 0, 0 );

        return $dt;
    }
}

if ( ! function_exists( 'maffer_get_apertura_dt' ) ) {
    /**
     * Devuelve el DateTime de la PRÓXIMA apertura (siempre en el futuro).
     *
     * @return DateTime
     */
    function maffer_get_apertura_dt() {
        $tz   = new DateTimeZone( 'America/Santiago' );
        $now  = new DateTime( 'now', $tz );
        $dia  = (int) get_option( 'maffer_apertura_dia', 6 );
        $hora = get_option( 'maffer_apertura_hora', '12:00' );

        $dt = maffer_compute_this_week_dt( $dia, $hora, $tz );

        // Si ya pasó esta semana, calcular la próxima ocurrencia
        if ( $dt <= $now ) {
            $dt->modify( '+7 days' );
        }

        return $dt;
    }
}

if ( ! function_exists( 'maffer_get_cierre_dt' ) ) {
    /**
     * Devuelve el DateTime del cierre de la semana actual.
     *
     * @return DateTime
     */
    function maffer_get_cierre_dt() {
        $tz   = new DateTimeZone( 'America/Santiago' );
        $dia  = (int) get_option( 'maffer_cierre_dia', 0 );
        $hora = get_option( 'maffer_cierre_hora', '18:00' );
        return maffer_compute_this_week_dt( $dia, $hora, $tz );
    }
}

if ( ! function_exists( 'maffer_get_fecha_ciclo' ) ) {
    /**
     * Retorna la fecha Y-m-d del inicio del ciclo actual.
     * NUNCA retorna null — siempre garantiza una fecha válida.
     *
     * @return string
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
            } catch ( Exception $e ) {
                // Silently fall through to fallback.
            }
        }

        // 3. Fallback universal: calcular la ocurrencia más reciente del día configurado.
        $dia       = (int) get_option( 'maffer_apertura_dia', 6 ); // default sábado
        $dow       = (int) $now->format( 'w' ); // 0=Dom … 6=Sáb
        $days_back = ( $dow >= $dia ) ? ( $dow - $dia ) : ( 7 - $dia + $dow );
        $dt        = clone $now;
        if ( $days_back > 0 ) {
            $dt->modify( '-' . $days_back . ' days' );
        }
        return $dt->format( 'Y-m-d' );
    }
}

if ( ! function_exists( 'maffer_fmt_ciclo_date' ) ) {
    /**
     * Formatea una fecha Y-m-d con nombres de día y mes en español.
     *
     * @param string $ymd   Fecha en formato Y-m-d.
     * @param array  $dias  Mapa de nombres de días (inglés → español).
     * @param array  $meses Mapa numérico de nombres de meses.
     * @return string       Ej: "sábado 12 abril" o "—" si la fecha es inválida.
     */
    function maffer_fmt_ciclo_date( $ymd, $dias, $meses ) {
        if ( ! $ymd ) {
            return '—';
        }
        $dt = new DateTime( $ymd );
        return $dias[ $dt->format( 'l' ) ] . ' ' . $dt->format( 'j' ) . ' ' . $meses[ (int) $dt->format( 'n' ) ];
    }
}

// ── Cache ───────────────────────────────────────────────────────

if ( ! function_exists( 'maffer_limpiar_cache' ) ) {
    /**
     * Limpia la caché de Elementor, SpeedyCache y WordPress.
     */
    function maffer_limpiar_cache() {
        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        if ( function_exists( 'speedycache_clear_all_cache' ) ) {
            speedycache_clear_all_cache();
        }
        wp_cache_flush();
    }
}
