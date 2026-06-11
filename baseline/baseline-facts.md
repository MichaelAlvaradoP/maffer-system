# Baseline pre-migracion (2026-06-10)
- Home: HTTP 200, titulo "Registro de Alimentacion | Hotel Los Cardenales" (snapshot: home-snapshot.html)
- Snippets activos: 9 (+1 draft creacion de tabla, +10 en papelera NO migrados)
- Tabla del sistema: wpig_maffer_registros (10 cols: nombre, rut, turno, menu_titulo, menu_desc, observaciones, fecha, hora, estado_dia)
- Usuarios: 4 (v2networkcl, michael_dev, casino_maffer, Administrador Maffer) — pass sandbox
- wp-login.php → 404 (plugin de ocultamiento de login activo — identificar URL real como parte del baseline de @qa)
- PENDIENTE @qa (primer WO del piloto): baseline visual Playwright + checklist funcional completo (flujo de reserva, panel, CSV, correo en modo test)
