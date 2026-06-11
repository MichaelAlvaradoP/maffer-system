param(
    [string]$SourceFile = "C:\Users\chest\Dev\maffer\snippets\158-maffer-panel-7e-render.php",
    [string]$DestFile = "C:\Users\chest\Dev\maffer\maffer-system\modules\panel-render.php"
)

# Read the source file
$content = Get-Content $SourceFile -Raw

# ============ TRANSFORMATIONS ============

# 1. Remove the snippet header comment (everything from <?php to the first blank line after the closing */)
# We'll replace the whole file header anyway

# 2. Fix hardcoded URL: http://127.0.0.1:8081/admin-maffer/ -> dynamic home_url
$oldUrl = 'http://127.0.0.1:8081/admin-maffer/'
$newUrl = '<?php echo esc_url( home_url( ' + "'/admin-maffer/'" + ' ) ); ?>'
$content = $content.Replace($oldUrl, $newUrl)

# 3. Remove the local maffer_fmt_ciclo_date definition, replace with comment
$oldFunc = @"
    function maffer_fmt_ciclo_date( `$ymd, `$dias, `$meses ) {
        if ( ! `$ymd ) return '—';
        `$dt = new DateTime( `$ymd );
        return `$dias[ `$dt->format('l') ] . ' ' . `$dt->format('j') . ' ' . `$meses[ (int)`$dt->format('n') ];
    }
"@
$newFuncComment = @"
    // NOTE: maffer_fmt_ciclo_date() is in helpers.php -- DO NOT redefine here
"@
$content = $content.Replace($oldFunc, $newFuncComment)

# 4. Fix SHOW TABLES query to use prepare
$oldShow = '`$t_exist = ( `$wpdb->get_var( "SHOW TABLES LIKE ''{`$tabla}''" ) === `$tabla );'
$newShow = '`$t_exist = ( `$wpdb->get_var( `$wpdb->prepare( "SHOW TABLES LIKE %s", `$tabla ) ) === `$tabla );'
$content = $content.Replace($oldShow, $newShow)

# 5. Add soft-delete filter to COUNT query
$oldCount = "SELECT COUNT(*) FROM {`$tabla} WHERE fecha >= %s"
$newCount = "SELECT COUNT(*) FROM {`$tabla} WHERE fecha >= %s AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')"
$content = $content.Replace($oldCount, $newCount)

# 6. Add soft-delete filter to SELECT registros query
$oldSelect = "SELECT * FROM {`$tabla} WHERE fecha >= %s ORDER BY fecha DESC, id DESC"
$newSelect = "SELECT * FROM {`$tabla} WHERE fecha >= %s AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY fecha DESC, id DESC"
$content = $content.Replace($oldSelect, $newSelect)

# 7. Add soft-delete filter to GROUP BY distribution query
$oldGroup = "SELECT menu_titulo, COUNT(*) cnt FROM {`$tabla}`n             WHERE fecha >= %s GROUP BY menu_titulo ORDER BY cnt DESC"
$newGroup = "SELECT menu_titulo, COUNT(*) cnt FROM {`$tabla}`n             WHERE fecha >= %s AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') GROUP BY menu_titulo ORDER BY cnt DESC"
$content = $content.Replace($oldGroup, $newGroup)

# 8. Add soft-delete filter to historial detail query
$oldHistDetail = "WHERE DATE_SUB(fecha, INTERVAL (WEEKDAY(fecha)+2)%%7 DAY) = %s`n             ORDER BY fecha ASC, id ASC"
$newHistDetail = "WHERE DATE_SUB(fecha, INTERVAL (WEEKDAY(fecha)+2)%%7 DAY) = %s`n             AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')`n             ORDER BY fecha ASC, id ASC"
$content = $content.Replace($oldHistDetail, $newHistDetail)

# 9. Add soft-delete filter to historial list query (the one without WHERE clause)
$oldHistList = "SELECT`n                DATE_SUB(fecha, INTERVAL (WEEKDAY(fecha)+2)%7 DAY) AS ciclo_inicio,`n                COUNT(*) AS total,`n                MIN(fecha) AS primer_dia,`n                MAX(fecha) AS ultimo_dia`n             FROM {`$tabla}`n             GROUP BY ciclo_inicio`n             ORDER BY ciclo_inicio DESC"
$newHistList = "SELECT`n                DATE_SUB(fecha, INTERVAL (WEEKDAY(fecha)+2)%7 DAY) AS ciclo_inicio,`n                COUNT(*) AS total,`n                MIN(fecha) AS primer_dia,`n                MAX(fecha) AS ultimo_dia`n             FROM {`$tabla}`n             WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')`n             GROUP BY ciclo_inicio`n             ORDER BY ciclo_inicio DESC"
$content = $content.Replace($oldHistList, $newHistList)

# ============ BUILD FINAL FILE ============

$header = @'
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

'@

$footer = @'

}
'@

# Remove the original <?php tag and snippet header
$content = $content -replace '^<\?php.*?\*/\s*', ''
$content = $content.Trim()

$final = $header + $content + "`r`n" + $footer + "`r`n"

# Write the output
[System.IO.File]::WriteAllText($DestFile, $final, [System.Text.UTF8Encoding]::new($false))

Write-Output "Panel render module written successfully!"
Write-Output "Source: $SourceFile ($( (Get-Item $SourceFile).Length ) bytes)"
Write-Output "Dest: $DestFile ($( (Get-Item $DestFile).Length ) bytes)"
