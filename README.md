# AlquilaPro

AlquilaPro es un sistema web SSR en PHP para administrar alquileres residenciales: propiedades y unidades, candidatos, contratos, arrendamientos, cartera, pagos, mantenimiento, documentos, seguros, comunidad, membresías, notificaciones y auditoría.

## Estado vigente — recuperación post-UAT

La autoridad viva es GitHub + CI + el checkpoint canónico de la fase activa.

- `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Rama de recuperación R1: `impl/r1-r3-post-uat-recovery-20261015`
- PR de recuperación: `#31` — DRAFT / NO MERGE sin autorización explícita del owner.
- Pre-F26 owner UAT: **REJECTED**.
- F26: **FROZEN**.
- Deployment: **NO autorizado**.
- El artefacto F25 anterior es evidencia histórica; no es candidato de despliegue después de los cambios post-UAT.

### R0

Baseline visual público aprobado y bloqueado en:

`docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`

### R1 — Auth recovery

El código funcional de R1 está GREEN certificado en:

`25bf8a97002495f3288bf23de02c563d7a1e39c4`

CI #1037 / run `38851445594`:
- PHP 8.3: SUCCESS — 642 tests / 7.659 assertions / 1 skipped;
- PHP 8.4: SUCCESS;
- PHP 8.5: SUCCESS.

Contrato R1 vigente:
- un solo `AuthService` para email / username / documento;
- identificadores ambiguos fallan cerrados;
- `PasswordPolicy` es la única autoridad del mínimo de contraseña: 6 caracteres;
- admin/gestor: email obligatorio, username y documento opcionales;
- campos opcionales vacíos convergen a `NULL`;
- username gestionado: lower + trim;
- documento gestionado: upper + trim;
- tenant conserva exactamente el documento canónico proveniente del candidato tras `trim` y lo usa como contraseña inicial hasheada;
- `must_change_password = 0` para tenant;
- una sola comprobación cruzada de colisiones en `UserService` + índices `UNIQUE` como segunda barrera;
- el login público usa `identifier`;
- queda un fallback interno transitorio `email -> identifier` para helpers/tests legados; su gate de eliminación es R3, cuando se reabre la superficie pública de login. No es una segunda autoridad de autenticación.

Checkpoint canónico de recuperación R1:

`docs/checkpoints/R1_AUTH_GREEN_CHECKPOINT_2026-10-16.md`

## Roadmap post-UAT vigente

```text
R0  baseline visual
R1  auth
R2  design foundation
R3  landing + catálogo + detalle + login
R4  imágenes/assets demo
R5  dashboard + shell
R6  propiedades + unidades + media
R7  candidatos + wizard
R8  contratos + tenant
R9  finanzas
R10 módulos restantes
R11 demo completa
R12 human owner UAT
R13 reconstrucción F25
R14 Hostinger / F26
```

No abrir una fase nueva antes de cerrar la anterior conforme al checkpoint y CI. Merge y despliegue requieren autorización explícita del owner.

## Leyes de desarrollo

### 1. KISS

La solución debe ser simple, directa, eficiente, optimizada y eficaz. Preferir A -> B. No crear nuevas tablas, servicios, providers, middlewares o capas si el owner actual puede resolver el contrato de forma clara.

### 2. Cero ruido

Orden obligatorio antes de agregar algo:

`ELIMINAR -> UNIR/CONVERGER -> SIMPLIFICAR -> MEJORAR/OPTIMIZAR -> AGREGAR`

No mantener autoridades duplicadas ni estados documentales supersedidos en el HEAD activo.

### 3. Anti-parche

Para bugs o gaps:

`REPRODUCE -> ROOT CAUSE -> OWNER -> BUSCAR DUPLICACIÓN -> RED -> VERIFICAR RED -> MINIMAL GREEN -> RETIRAR SUPERSEDED -> REGRESSION`

No resolver fallos con excepciones laterales o parches sobre parches.

## Método de trabajo

1. Contrato pequeño y verificable.
2. RED que falle por la causa esperada.
3. GREEN mínimo dentro del owner existente.
4. Suite focal.
5. Suite completa.
6. Matriz CI PHP 8.3 / 8.4 / 8.5 sobre SHA exacto.
7. Auditoría KISS / ruido / parches.
8. Checkpoint canónico.
9. Merge solo con autorización explícita.

## Stack validado

- PHP soportado/validado: 8.3–8.5.
- MariaDB/MySQL mediante PDO.
- Slim SSR.
- Composer.
- PHPUnit.
- Sin SPA ni framework frontend paralelo.

## Fuente de verdad

Orden operativo:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. Checkpoint canónico de la fase activa.
4. Specs y planes aprobados.
5. README / AGENTS para identidad, leyes y orientación.
6. Chats y handoffs solo como recuperación cuando no contradicen las fuentes anteriores.

Los documentos y artefactos históricos no sustituyen el estado vivo del repositorio y de CI.
