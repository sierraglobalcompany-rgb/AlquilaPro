---
name: kiss-ponytail
description: Adaptador local de Ley 1 KISS + Ponytail para AlquilaPro. Usar en cambios de código, arquitectura, revisión de diff o simplificación justificada; no ejecutar auditoría global por rutina.
---
# Ley 1 — KISS + Ponytail (AlquilaPro)

**Canon completo L1–L6 (versión fijada):** https://github.com/sierraglobalcompany-rgb/PingPongLaw/blob/84e961cadf482d234f4c0ee2ca60e8c7e9b388e8/docs/engineering/LEYES_IMPLEMENTACION_1_A_6.md

**Fuentes:** adaptación PingPongLaw `l1-kiss-ponytail`, basada en Ponytail de DietrichGebert, v5.3.2, commit `09c434ab73ca43761c5d8b0c5d341926c2fba710` (MIT). **Esta skill no instala el plugin ni activa hooks.**

## Aplicar, sin auditoría ritual

1. Leer `AGENTS.md` local, `README.md`, SPEC y checkpoint aplicables. Leer el canon central por la conexión disponible y comprobar el SHA; si no se puede acceder, declarar `CANON_READ=NOT_VERIFIED`, utilizar las reglas locales sin fingir que se cargó.
2. Trazar `entrada → auth/tenant/permiso → servicio/owner → persistencia/UI → salida`, con consumidores, pruebas y fixtures pertinentes.
3. Elegir en orden: **no crear lo innecesario → reutilizar repo → nativo/stdlib → dependencia existente → código mínimo completo y legible**.
4. Si hay duplicidad, comprobar usos antes de eliminar (L2); si hay fallo, corregir causa raíz y consumidores (L3); si afecta interfaz, respetar Human-First/SPEC, usando Anti UI Slop y UI UX Pro Max **solo donde aclaren algo** (L4).
5. Conservar CSRF, `AuthService`, políticas de contraseñas, permisos por tenant, privacidad, SSR, contratos del proyecto, PHP 8.3–8.5 y tests TDD/CI requeridos. Un cambio más corto pero inseguro **no cumple KISS**.
6. ChatGPT resuelve con sus herramientas lo que pueda; solo delega a Codex un lote restante necesario (L6). Verificar el resultado por SHA y evidencia real (L5), no por informe del ejecutor.

**Salida:** decisión `NO_CAMBIAR/REUTILIZAR/SIMPLIFICAR/IMPLEMENTAR`, evidencia, archivos y consumidores, cambios reales, QA `PASS/FAIL/BLOCKED/NOT_RUN`, NEXT/NINGUNO. Sin nuevos hooks, instalaciones globales, cambios remotos de producción o permisos sin autorización.

**Modo offline:** esta skill conserva el mínimo operativo para L1, pero **no es una copia de las seis leyes**; sin lectura del canon remoto el estado es de integración parcial.
