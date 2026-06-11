# AGENTS.md — Proyecto Maffer (sistema de registro de alimentacion)

## Context (load first)
- Vault: `03-Projects/maffer/context.md` + `progress.md` + brief activo en `03-Projects/maffer/briefs/`
- Flujo WP: vault `07-AI-Ecosystem/wordpress-dev-flow.md` (sandbox + puente WPCode) — VINCULANTE
- Git: vault `07-AI-Ecosystem/git-workflow.md` — commits atomicos `WO-NNN: ...`, commit antes de modificar

## Sandbox (el unico WordPress que tocas)
- URL: http://127.0.0.1:8081 — stack Docker en `C:\Users\chest\AI-Ecosystem\wp-sandbox-maffer`
- wp-cli: `docker compose run --rm cli wp <cmd>` (desde esa carpeta)
- DB: tabla del sistema `wpig_maffer_registros`; prefijo `wpig_`
- Usuarios sandbox: pass `sandbox` (PII anonimizada — mantenerla asi: fixtures sinteticos SIEMPRE)
- Verificacion visual: playwright MCP contra 127.0.0.1:8081
- wp-login.php oculto (404) — URL real de login: identificar y documentar aqui en el primer WO

## La logica del sistema
- HOY: 9 snippets WPCode activos, extraidos a `snippets/` + `manifest.json` (fuente de referencia, NO editar sin WO)
- Piloto Fase 1: migrarlos a plugin propio `maffer-system/` con comportamiento IDENTICO (criterio de aceptacion maestro)
- Comparar SIEMPRE contra `baseline/` antes de declarar identidad de comportamiento

## Reglas duras (Rule 0 + proyecto)
- PROHIBIDO tocar produccion (servicioalimentacionmaffer.cl) — el deploy es de @devops post-aceptacion con backup
- PROHIBIDO borrar registros/tablas (soft-delete) y desactivar snippets en sandbox sin WO que lo indique
- Emails: el sandbox no envia correo real — probar logica de correo en modo log/test
- Datos de huespedes = PII: jamas datos reales en codigo, tests o logs
