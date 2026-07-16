# Maffer System

**Version:** 1.1.1  
**Requires:** WordPress 5.8+, PHP 7.4+  
**License:** GPL v2 or later  
**Repository:** [github.com/MichaelAlvaradoP/maffer-system](https://github.com/MichaelAlvaradoP/maffer-system)

Sistema de registro de alimentación para Hotel Maffer (Iquique, Chile).  
Reemplaza los 9 snippets activos de WPCode por un plugin estructurado y modular.

## v1.1.0 — Fase 2: Opciones diarias

- El formulario público muestra los **7 días de la semana** (lunes-domingo), cada uno con sus opciones de cena; el huésped elige una opción por día y envía todo de una vez (se mantiene 1 registro por RUT por ciclo semanal).
- Las opciones de menú se gestionan **por día** en el tab Menús (`wp_options`: `maffer_menu_cena_{lunes..domingo}`).
- Nueva tabla `{prefix}maffer_registro_detalles` (registro_id, dia_semana, menu_titulo, menu_desc): guarda un **snapshot inmutable** de cada selección — editar o borrar opciones después no altera reservas existentes. `maffer_db_version` = 1.3.
- Excel, correo detallado y panel muestran la selección por día; los registros de Fase 1 (menú único semanal) siguen visibles.
- El menú de administración ahora exige la capability `maffer_manage_menu` (se otorga a `administrator` y a los roles gestores). Tras actualizar sin reactivar, la primera carga del panel la auto-otorga.

---

## File Structure

```
maffer-system/
├── maffer-system.php          # Main plugin — loader, hooks, update checker
├── includes/
│   ├── activator.php          # Activation: table schema, roles, cron scheduling
│   ├── deactivator.php        # Deactivation: cron cleanup, flush rewrite
│   ├── email.php              # Email handling (Snippet 156)
│   ├── excel.php              # XLSX generation (Snippet 159)
│   └── helpers.php            # Shared utility functions (Snippet 155, 158)
├── modules/
│   ├── ajax-rut.php           # RUT validation (Snippet 138)
│   ├── form.php               # Form shortcode (Snippet 140)
│   ├── login.php              # Login customization (Snippet 160)
│   ├── page-404.php           # Custom 404 page (Snippet 164)
│   ├── panel-ajax.php         # AJAX handlers (Snippet 157)
│   ├── panel-core.php         # Panel core (Snippet 161)
│   ├── panel-csv.php          # CSV/Excel download (Snippet 159)
│   └── panel-render.php       # Panel render (Snippet 158)
├── vendor/
│   └── plugin-update-checker/ # YahnisElsts/plugin-update-checker v5.7
└── README.md
```

---

## Module Mapping

| Module File | Source Snippet(s) | Key Functions |
| :--- | :--- | :--- |
| `includes/activator.php` | 137 | `maffer_activar_plugin` |
| `includes/email.php` | 156 | `maffer_html_correo`, `maffer_enviar_resumen` |
| `includes/excel.php` | 159 | `maffer_generar_xlsx` |
| `includes/helpers.php` | 155, 158 | `maffer_fmt_ciclo_date`, `maffer_get_menus` |
| `modules/ajax-rut.php` | 138 | `maffer_ajax_validar_rut` |
| `modules/form.php` | 140 | `maffer_formulario_shortcode` |
| `modules/login.php` | 160 | `maffer_login_logo_url`, `maffer_login_head_css` |
| `modules/page-404.php` | 164 | `maffer_custom_404_template` |
| `modules/panel-ajax.php` | 157 | `maffer_editar_registro`, `maffer_eliminar_registro` |
| `modules/panel-core.php` | 161 | `maffer_admin_menu`, `maffer_admin_page` |
| `modules/panel-csv.php` | 159 | `maffer_admin_descargar_excel` |
| `modules/panel-render.php` | 158 | `maffer_v6_render` |

---

## Installation

1. Upload the `maffer-system/` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. The plugin creates the database table, custom roles, and cron schedule on activation.

### Requirements

- The original WPCode snippets must be **deactivated** before the plugin can work correctly.
- If rolling back, deactivate the plugin and re-enable the snippets.

---

## Update Mechanism

This plugin uses [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7 to check for updates via GitHub Releases.

1. Create a new release on GitHub with a version tag (e.g., `v1.1.0`).
2. The `GitHub URI` plugin header points to the repository.
3. WordPress will check for updates every 12 hours by default.

---

## Rollback / Deactivation

**Deactivating** the plugin:
- Stops the cron schedule (`maffer_check_schedule`).
- Does NOT remove roles, options, or database tables.

To fully roll back:
1. Deactivate `Maffer System`.
2. Re-enable the original 9 WPCode snippets.
3. Data remains intact.

---

## Known Issues / Technical Debt

- **Legacy Compatibility**: `maffer_v6_procesar()` is kept as a POST fallback for compatibility.
- **CSS/JS**: Currently inline; extraction to separate files is pending.
- **Soft-delete**: Records are marked with `deleted_at` instead of physically deleted (by design).
- **Day completeness**: the requirement to pick a menu for every available day is enforced client-side only; the server accepts partial selections (pending product decision).
- **Snapshot values**: detail rows store the title/description sent by the client (sanitized and escaped on output); they are not cross-checked against the configured options.

Resolved in v1.1.0: `remove_filter` closure no-op (fixed), admin menu capability `read` → `maffer_manage_menu`, `redirect_to` sanitization, XLSX filename sanitization.
