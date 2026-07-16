<?php
/**
 * Maffer System — Excel XLSX Generation
 *
 * Generates Office Open XML (XLSX) files in-memory using ZipArchive
 * and raw XML (no external library required). Extracted from WPCode
 * snippet 155 (Panel 7A — Roles y Menú).
 *
 * Layout v1.1.1 — "Menú Semanal" grouped by day:
 *   MENÚ SEMANAL — SERVICIO DE CENA
 *   {rango del ciclo}
 *   LUNES
 *   Nombre | RUT | Menú | Observaciones | Hora | Fecha
 *   ...registros del lunes...
 *   MARTES
 *   ...
 * Fase 1 records (no per-day detail) are grouped under SEMANA COMPLETA.
 *
 * @package   MafferSystem
 * @version   1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ════════════════════════════════════════════════════════════════
// XLSX GENERATION (in-memory via ZipArchive + raw XML)
// ════════════════════════════════════════════════════════════════

if ( ! function_exists( 'maffer_generar_xlsx' ) ) {
	/**
	 * Generate an XLSX file in the system temp directory using raw
	 * OpenXML spreadsheet XML plus a ZIP wrapper (no library needed).
	 *
	 * Rows are grouped by dia_semana (lunes..domingo); rows without a
	 * dia_semana (Fase 1 legacy records) fall into SEMANA COMPLETA.
	 *
	 * @param array  $rows  Array of associative arrays with keys:
	 *                      nombre, rut, menu_titulo, observaciones,
	 *                      hora, fecha, dia_semana (optional).
	 * @param string $label Label for the temp filename / range display.
	 * @return string|false Path to the generated temp file, or false on failure.
	 */
	function maffer_generar_xlsx( $rows, $label ) {
		$cabeceras  = array( 'Nombre', 'RUT', 'Menú', 'Observaciones', 'Hora', 'Fecha' );
		$col_letras = array( 'A', 'B', 'C', 'D', 'E', 'F' );

		$dias_orden = array( 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo' );
		$dias_lbl   = array(
			'lunes'     => 'LUNES',
			'martes'    => 'MARTES',
			'miercoles' => 'MIÉRCOLES',
			'jueves'    => 'JUEVES',
			'viernes'   => 'VIERNES',
			'sabado'    => 'SÁBADO',
			'domingo'   => 'DOMINGO',
		);

		// ── Group rows by day (legacy rows without dia_semana go last) ──
		$grupos = array();
		foreach ( $rows as $r ) {
			$dia = isset( $r['dia_semana'] ) ? strtolower( (string) $r['dia_semana'] ) : '';
			$key = in_array( $dia, $dias_orden, true ) ? $dia : '_semana';
			$grupos[ $key ][] = $r;
		}
		$orden_final = $dias_orden;
		$orden_final[] = '_semana';

		// Within each day: sort by menu then name so equal menus stack together.
		foreach ( $grupos as $k => $g ) {
			usort( $g, function ( $a, $b ) {
				$c = strcasecmp( (string) $a['menu_titulo'], (string) $b['menu_titulo'] );
				return 0 !== $c ? $c : strcasecmp( (string) $a['nombre'], (string) $b['nombre'] );
			} );
			$grupos[ $k ] = $g;
		}

		// ── Shared strings ──────────────────────────────────────────
		$strings = array();
		$str_idx = array();
		$agregar = function ( $v ) use ( &$strings, &$str_idx ) {
			$v = (string) $v;
			if ( ! isset( $str_idx[ $v ] ) ) {
				$str_idx[ $v ] = count( $strings );
				$strings[]     = $v;
			}
			return $str_idx[ $v ];
		};

		// ── Build sheet rows: array of [style, cells[]] ──────────────
		// style: 0 normal, 1 bold, 2 bold day-section
		$rango_disp = str_replace( '_a_', ' al ', $label );
		$sheet_rows   = array();
		$sheet_rows[] = array( 2, array( $agregar( 'MENÚ SEMANAL — SERVICIO DE CENA' ) ) );
		$sheet_rows[] = array( 0, array( $agregar( 'Ciclo: ' . $rango_disp ) ) );
		$sheet_rows[] = array( 0, array() ); // blank

		$cab_idx = array();
		foreach ( $cabeceras as $c ) {
			$cab_idx[] = $agregar( $c );
		}

		foreach ( $orden_final as $key ) {
			if ( empty( $grupos[ $key ] ) ) {
				continue;
			}
			$titulo_dia = '_semana' === $key ? 'SEMANA COMPLETA' : $dias_lbl[ $key ];
			$sheet_rows[] = array( 2, array( $agregar( $titulo_dia ) ) );
			$sheet_rows[] = array( 1, $cab_idx );
			foreach ( $grupos[ $key ] as $r ) {
				$fi   = array();
				$fi[] = $agregar( $r['nombre'] );
				$fi[] = $agregar( $r['rut'] );
				$fi[] = $agregar( $r['menu_titulo'] );
				$fi[] = $agregar( isset( $r['observaciones'] ) ? $r['observaciones'] : '' );
				$fi[] = $agregar( substr( (string) $r['hora'], 0, 5 ) );
				$fi[] = $agregar( isset( $r['fecha'] ) ? $r['fecha'] : '' );
				$sheet_rows[] = array( 0, $fi );
			}
			$sheet_rows[] = array( 0, array() ); // blank between days
		}

		// ── Worksheet XML ────────────────────────────────────────────
		$sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$sheet .= '<cols>'
			. '<col min="1" max="1" width="24" customWidth="1"/>'
			. '<col min="2" max="2" width="14" customWidth="1"/>'
			. '<col min="3" max="3" width="28" customWidth="1"/>'
			. '<col min="4" max="4" width="26" customWidth="1"/>'
			. '<col min="5" max="5" width="8"  customWidth="1"/>'
			. '<col min="6" max="6" width="12" customWidth="1"/>'
			. '</cols>';
		$sheet .= '<sheetData>';
		foreach ( $sheet_rows as $ri => $row_def ) {
			$rn    = $ri + 1;
			$style = $row_def[0];
			$cells = $row_def[1];
			$sheet .= '<row r="' . $rn . '">';
			foreach ( $cells as $ci => $si ) {
				$s_attr = $style > 0 ? ' s="' . ( 2 === $style ? 2 : 1 ) . '"' : '';
				$sheet .= '<c r="' . $col_letras[ $ci ] . $rn . '" t="s"' . $s_attr . '><v>' . $si . '</v></c>';
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';

		// ── Shared strings XML ───────────────────────────────────────
		$sst  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sst .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">';
		foreach ( $strings as $s ) {
			$sst .= '<si><t>' . htmlspecialchars( $s, ENT_XML1, 'UTF-8' ) . '</t></si>';
		}
		$sst .= '</sst>';

		// ── Styles: 0 normal, 1 bold, 2 bold + fill (day sections) ──
		$styles  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$styles .= '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>';
		$styles .= '<fills count="3">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFFBE8D5"/><bgColor indexed="64"/></patternFill></fill>'
			. '</fills>';
		$styles .= '<borders count="1"><border/></borders>';
		$styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
		$styles .= '<cellXfs count="3">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '</cellXfs>';
		$styles .= '</styleSheet>';

		$wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$wb .= '<sheets><sheet name="Menu Semanal" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
		$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
		$ct .= '<Default Extension="xml" ContentType="application/xml"/>';
		$ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
		$ct .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		$ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
		$ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$ct .= '</Types>';

		$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
		$rels .= '</Relationships>';

		$wbrels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$wbrels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$wbrels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
		$wbrels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
		$wbrels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
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
		$zip->addFromString( 'xl/styles.xml',              $styles );
		$zip->close();

		return $tmp;
	}
}
