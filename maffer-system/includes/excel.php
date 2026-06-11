<?php
/**
 * Maffer System — Excel XLSX Generation
 *
 * Generates Office Open XML (XLSX) files in-memory using ZipArchive
 * and raw XML (no external library required). Extracted from WPCode
 * snippet 155 (Panel 7A — Roles y Menú).
 *
 * Columns: Nombre, RUT, Menu, Observaciones, Hora, Fecha.
 *
 * @package   MafferSystem
 * @version   1.0.0
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
