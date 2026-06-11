# Maffer System

**Version:** 1.0.0  
**Requires:** WordPress 5.8+, PHP 7.4+  
**License:** GPL v2 or later  
**Repository:** [github.com/maffer/maffer-system](https://github.com/maffer/maffer-system)

Sistema de registro de alimentación para Hotel Maffer (Iquique, Chile).  
Reemplaza los 9 snippets activos de WPCode por un plugin estructurado y modular.

---

## File Structure

```
maffer-system/
├── maffer-system.php          # Main plugin — loader, hooks, update checker
├── includes/
│   ├── helpers.php            # Shared utility functions (extracted from snippets 155, 158)
│   ├── activator.php          # Activation: table schema, roles, cron scheduling
│   └── deactivator.php        # Deactivation: cron cleanup, flush rewrite
├── vendor/
│   └── plugin-update-checker/ # YahnisElsts/plugin-update-checker v5.7
├── modules/                   # Created in future WOs
└── README.md
```

---

## Installation

1. Upload the `maffer-system/` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. The plugin creates the database table, custom roles, and cron schedule on activation.

### Requirements

- The original WPCode snippets must be **deactivated** before the plugin can work correctly.
- If rolling back, deactivate the plugin and re-enable the snippets.

---

## Module Migration Plan

The 9 snippets are being migrated one module at a time. Each module matches one or more original WPCode snippets.

| WO    | Module / File               | Source Snippet(s)         | Status   |
|-------|-----------------------------|---------------------------|----------|
| —     | `includes/helpers.php`      | 155, 158                  | ✅ Done  |
| —     | `includes/activator.php`    | 137                       | ✅ Done  |
| —     | `includes/deactivator.php`  | —                         | ✅ Done  |
| WO-002 | `modules/module-roles-menu.php` | 155 (roles, menu, redirects) | ⏳ Pending |
| WO-003 | `modules/module-ajax.php`       | 157 (AJAX handlers)          | ⏳ Pending |
| WO-004 | `modules/module-csv.php`        | 159 (CSV/Excel export)       | ⏳ Pending |
| WO-005 | `modules/module-form.php`       | 140 (shortcode formulario)   | ⏳ Pending |
| WO-006 | `modules/module-render.php`     | 158 (panel render)           | ⏳ Pending |
| WO-007 | `modules/module-email.php`      | 156 (email handling)         | ⏳ Pending |
| WO-008 | `modules/module-rut-ajax.php`   | 138 (RUT validation)         | ⏳ Pending |
| WO-009 | `modules/module-login-visual.php` | 160 (login customization)  | ⏳ Pending |
| WO-010 | `modules/module-404.php`        | 164 (404 page)               | ⏳ Pending |

---

## Database

**Table:** `{prefix}_maffer_registros`

| Column        | Type                | Notes                     |
|---------------|---------------------|---------------------------|
| id            | BIGINT(20) UNSIGNED | Auto-increment, primary    |
| nombre        | VARCHAR(150)        | Guest name                 |
| rut           | VARCHAR(15)         | Chilean RUT (PII)          |
| turno         | VARCHAR(20)         | Default 'almuerzo'         |
| menu_titulo   | VARCHAR(200)        | Menu title                 |
| menu_desc     | TEXT                | Menu description           |
| observaciones | TEXT                | Notes                      |
| fecha         | DATE                | Reservation date           |
| hora          | TIME                | Reservation time           |
| estado_dia    | VARCHAR(20)         | Default 'abierto'          |
| deleted_at    | DATETIME            | Soft-delete (NULL = alive) |

**Index:** `idx_rut_fecha` on `(rut, fecha)`.

---

## Update Mechanism

This plugin uses [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5.7  
to check for updates via GitHub Releases.

1. Create a new release on GitHub with a version tag (e.g., `v1.1.0`).
2. The `GitHub URI` plugin header points to the repository.
3. WordPress will check for updates every 12 hours by default.

---

## Users & Roles

| Role                     | Capability          | Description                  |
|--------------------------|---------------------|------------------------------|
| gestor_menus_maffer      | maffer_manage_menu  | Full panel access (v1 name)  |
| gestor_menus             | maffer_manage_menu  | Full panel access (v5 name)  |

Administrators (`manage_options`) also have full access.

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

## Development

### Adding a new module

1. Create the file in `modules/module-{name}.php`.
2. Add the `require_once` line in `maffer-system.php` under the module placeholders comment.
3. All functions must be wrapped in `if ( ! function_exists() )` guards.

### Coding standards

- All DB queries use `$wpdb->prepare()` — no string concatenation.
- Input sanitization at the boundary (first entry point).
- Output escaped with `esc_html()`, `esc_attr()`, `esc_js()`, etc.
- Capability checks on every state-changing action.
- Soft-delete enabled — never hard-delete from the database.
