# AlquilaPro — Checkpoint R1 Post-UAT Recovery

Fecha: 2026-10-15
Estado: PAUSED BY OWNER

## Regla de parada

El owner pidió: `crea un checkpoint y para`.

No continuar implementación después de este checkpoint hasta nueva instrucción.

---

## Autoridad / base

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Rama activa: `impl/r1-r3-post-uat-recovery-20261015`
- HEAD al crear este checkpoint antes de este commit: `1798b9641d3f3891a263e6e809b34460b377ce41`
- Draft PR de CI/TDD: `#31` — `WIP: R1–R3 post-UAT recovery`
- PR: OPEN / DRAFT / NOT MERGED
- MERGE_AUTHORIZED: NO
- DEPLOYMENT_AUTHORIZED: NO
- F26: FROZEN
- PRE_F26_OWNER_UAT: REJECTED

---

## Baseline visual aprobado — preservación durable

Los mockups aprobados están guardados en Library persistente:

`/AlquilaPro/Approved UI 2026-10-15/`

Archivos:

- `ALQUILAPRO_UI_BASELINE_MOBILE_APPROVED_2026-10-15.png`
- `ALQUILAPRO_UI_BASELINE_DESKTOP_APPROVED_2026-10-15.png`
- `ALQUILAPRO_UI_BASELINE_RESPONSIVE_APPROVED_2026-10-15.png`
- `ALQUILAPRO_PLAN_IMPLEMENTACION_POST_UAT_BASELINE_APROBADO_2026-10-15.md`

Spec versionada en Git:

`docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`

Commit de bloqueo del baseline:

`82cce1edbce4f4aac7b12bc0ba618663a68c5ce0`

### Contrato visual resumido

Mobile-first:
1. `375x812`
2. `768x1024`
3. `1440x900`

Landing pública aprobada:
- logo AlquilaPro arriba;
- propiedades disponibles primero, sin hero de marketing;
- `Ver todas` arriba a la derecha;
- carrusel protagonista;
- login fijo debajo;
- login sin X/cerrar;
- sin Google login;
- sin navegación inferior pública antes de login;
- sin segunda sección de propiedades.

Desktop:
- carrusel en la zona superior;
- una propiedad principal centrada;
- anterior/siguiente parcialmente visibles;
- tarjeta horizontal con fotos a la izquierda e información a la derecha;
- login debajo.

No desviarse de este baseline sin nueva aprobación del owner.

---

## Plan vigente

Orden aprobado:

```text
R0 baseline visual
R1 auth
R2 design foundation
R3 landing/catalog/login
R4 imágenes demo
R5 dashboard/shell
R6 propiedades/media
R7 candidatos/wizard
R8 contratos/tenant
R9 finanzas
R10 resto módulos
R11 demo completa
R12 human UAT
R13 nuevo F25
R14 Hostinger/F26
```

Actualmente estamos SOLO en `R1`.

---

## R1 — progreso exacto

### R1-T1 schema RED

Se modificó:

`tests/Integration/Auth/AuthSchemaTest.php`

para exigir:
- `users.username`;
- `users.document_number`;
- unique index sobre username;
- unique index sobre document_number.

Commit test-only RED:

`d1bf35e348e2b8d27f2abba53d3a6de55feeb7ae`

CI #1006 / run id `38836565509` confirmó RED real.

Evidencia observada PHP 8.3:

```text
Tests: 632
Assertions: 7576
Failures: 1
Skipped: 1
Failure: AuthSchemaTest
Missing: username
```

El RED fue correcto: falló exactamente porque la columna nueva aún no existía.

### R1-T1 GREEN mínimo intentado

Se agregó únicamente:

`database/migrations/023_extend_users_login_identifiers.sql`

Contenido conceptual:
- nullable `username VARCHAR(80)`;
- nullable `document_number VARCHAR(80)`;
- unique key username;
- unique key document_number.

Commit:

`1798b9641d3f3891a263e6e809b34460b377ce41`

No se han tocado todavía:
- `AuthService`;
- `UserService`;
- `Installer`;
- login view;
- tenant provisioning;
- password minimum;
- design foundation;
- landing implementation.

---

## CI del GREEN — IMPORTANTE

CI #1007 / run id `38836928481` está asociado al commit `1798b964...`.

Último estado observado antes de pausar:

```text
PHP 8.3: COMPLETED / FAILURE
PHP 8.4: IN_PROGRESS
PHP 8.5: IN_PROGRESS
```

No se inspeccionó ni corrigió todavía la causa exacta del fallo de PHP 8.3 en CI #1007.

Por tanto:

```text
R1_T1_SCHEMA_GREEN: NOT CERTIFIED
```

No afirmar que la migración 023 está GREEN hasta:
1. inspeccionar logs de CI #1007;
2. identificar root cause;
3. aplicar RED→GREEN si procede;
4. obtener suite verde.

---

## Próxima acción exacta al reanudar

1. Releer ESTE checkpoint, no reconstruir historia.
2. Revisar estado final de CI #1007.
3. Leer log PHP 8.3 de CI #1007 y clasificar fallo.
4. Si fallo pertenece al test/migración 023: corregir por root cause, sin parche.
5. Confirmar R1-T1 GREEN.
6. Crear checkpoint `R1_AUTH_SCHEMA_GREEN`.
7. Continuar R1-T2 login por identificador con TDD.

NO saltar directamente a UI.
NO tocar Hostinger.
NO merge.
NO reconstruir F25.

---

## Requisitos Auth todavía pendientes

- un solo campo `Usuario`;
- login por email / username / documento;
- mínimo contraseña = 6;
- staff/admin puede usar identificadores configurados;
- tenant:
  - documento como identificador;
  - contraseña inicial = documento;
  - `must_change_password = 0`;
- documento menor a 6 debe fallar de forma controlada;
- mantener PASSWORD_DEFAULT, lockout, CSRF, sesiones y reset;
- no crear segundo auth stack;
- evitar colisiones ambiguas de identificadores.

---

## Leyes

### KISS
Arquitectura directa y mínima.

### Cero Ruido
`ELIMINAR → UNIR → SIMPLIFICAR → MEJORAR → AGREGAR`

### Anti-parche
`REPRODUCE → ROOT CAUSE → OWNER → RED → MINIMAL GREEN → REMOVE SUPERSEDED → REGRESSION`

---

## Estado de cierre de este checkpoint

```text
R0_VISUAL_BASELINE: LOCKED
R1_T1_RED: PASS
R1_T1_GREEN: UNRESOLVED / CI RED
R1_AUTH: IN_PROGRESS
R2: NOT STARTED
R3: NOT STARTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
OWNER_REQUESTED_STOP: YES
```
