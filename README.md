# Sistema Maffer — repo de codigo

Sistema de registro de alimentacion del Hotel (WordPress). La logica vive HOY en snippets WPCode
(extraidos a `snippets/` + `manifest.json` el 2026-06-10 desde el backup de prod).

- Sandbox local: http://127.0.0.1:8081 (stack en C:\Users\chest\AI-Ecosystem\wp-sandbox-maffer; usuarios con pass `sandbox`, PII anonimizada)
- Flujo y reglas: vault `07-AI-Ecosystem/wordpress-dev-flow.md` + `07-AI-Ecosystem/git-workflow.md`
- Piloto Fase 1: migrar estos snippets a plugin `maffer-system/` (comportamiento identico) — brief en vault `03-Projects/maffer/briefs/`

## Estructura
- `snippets/` — codigo extraido de WPCode (fuente actual, NO editar sin WO)
- `baseline/` — referencia del comportamiento pre-migracion
- `maffer-system/` — (lo creara el equipo) el plugin destino
