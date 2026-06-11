/**
 * ============================================================
 * SNIPPET 1 — CREAR TABLA DE REGISTROS
 * Dónde pegarlo: WPCode → Añadir Snippet → PHP Snippet
 * Nombre sugerido: Maffer - Crear Tabla de Registros
 * Inserción: "Run Everywhere" (se ejecuta una vez y no vuelve a crear la tabla)
 * ============================================================
 */

function maffer_crear_tabla_registros() {
    global $wpdb;

    $tabla      = $wpdb->prefix . 'maffer_registros';
    $charset    = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$tabla} (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre        VARCHAR(150)        NOT NULL,
        rut           VARCHAR(15)         NOT NULL,
        turno         VARCHAR(20)         NOT NULL DEFAULT 'almuerzo',
        menu_titulo   VARCHAR(200)        NOT NULL,
        menu_desc     TEXT,
        observaciones TEXT,
        fecha         DATE                NOT NULL,
        hora          TIME                NOT NULL,
        estado_dia    VARCHAR(20)         NOT NULL DEFAULT 'abierto',
        PRIMARY KEY (id),
        INDEX idx_rut_fecha (rut, fecha)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Guardamos la versión de la tabla para futuros upgrades
    update_option( 'maffer_db_version', '1.0' );
}

// Crea o actualiza la tabla
// v1.1 agrega columna turno (almuerzo/cena)
add_action( 'init', function() {
    $ver = get_option( 'maffer_db_version', '0' );
    if ( version_compare( $ver, '1.1', '<' ) ) {
        maffer_crear_tabla_registros();
        // Agrega columna turno si la tabla ya existía
        global $wpdb;
        $tabla = $wpdb->prefix . 'maffer_registros';
        $cols  = $wpdb->get_col( "DESCRIBE {$tabla}", 0 );
        if ( ! in_array( 'turno', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN turno VARCHAR(20) NOT NULL DEFAULT 'almuerzo' AFTER rut" );
        }
        update_option( 'maffer_db_version', '1.1' );
    }
} );