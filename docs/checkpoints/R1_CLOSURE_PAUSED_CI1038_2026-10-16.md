# AlquilaPro — R1 closure paused — checkpoint

**Fecha:** 2026-10-16  
**Estado:** PARADA CONTROLADA solicitada por el owner.  
**MERGE_AUTHORIZED:** NO  
**DEPLOYMENT_AUTHORIZED:** NO  
**F26:** FROZEN  
**R2:** NO INICIAR

## Autoridad exacta al pausar

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Branch: `impl/r1-r3-post-uat-recovery-20261015`
- PR: `#31` — OPEN / DRAFT / NOT MERGED
- Product GREEN certificado: `25bf8a97002495f3288bf23de02c563d7a1e39c4`
- Product CI: #1037 / run `38851445594` — PHP 8.3 / 8.4 / 8.5 SUCCESS
- Closure docs parent HEAD: `f5db488547f479f596ea5f116ef40eebccd2a357`
- Closure CI: #1038 / run `38852142599`

## Estado exacto de CI #1038 al pausar

Sobre `f5db488547f479f596ea5f116ef40eebccd2a357`:

- PHP 8.3: `IN_PROGRESS` — inicializando contenedor; tests aún no iniciados.
- PHP 8.4: `IN_PROGRESS` — setup PHP; tests aún no iniciados.
- PHP 8.5: `IN_PROGRESS` — `Run tests` activo.

Por tanto:

`R1_CLOSURE_HEAD_CERTIFIED: NO TODAVÍA`

No afirmar que #1038 pasó hasta observar los tres jobs completados.

## Lo cerrado antes de esta pausa

### Funcional

R1 Auth está GREEN en el SHA funcional `25bf8a97…`:
- login único por email / username / documento;
- ambigüedad fail-closed en el mismo `AuthService`;
- `PasswordPolicy` canónica con mínimo 6;
- admin/gestor con username/document opcionales;
- campos opcionales vacíos -> `NULL`;
- una sola validación cruzada de colisiones en `UserService`;
- tenant conserva exactamente el documento canónico del candidato tras `trim`;
- contraseña inicial tenant = documento, almacenada hasheada;
- `must_change_password = 0`;
- sin nueva tabla/provider/middleware/capa de identidad.

CI #1037:
- PHP 8.3 SUCCESS — 642 tests / 7.659 assertions / 1 skipped;
- PHP 8.4 SUCCESS;
- PHP 8.5 SUCCESS.

### Regresión corregida

CI #1035 detectó que T5 había reutilizado por error el uppercase de documento gestionado también para tenant.

Root cause:
- admin/gestor y tenant no comparten la misma normalización de documento.

Fix mínimo:
- commit `25bf8a97002495f3288bf23de02c563d7a1e39c4`;
- 2 líneas funcionales en `UserService::createTenantIdentity()`;
- tenant vuelve a conservar casing exacto del documento del candidato.

LeaseActivationTest protege:
- `temporary_password` exacto;
- `users.document_number` exacto;
- `password_verify()` contra el documento exacto.

### Cierre documental anti-ruido

Commit `f5db488547f479f596ea5f116ef40eebccd2a357`:
- actualiza `README.md` al roadmap post-UAT R0–R14;
- corrige en `AGENTS.md` un único orden de autoridad;
- mantiene las tres leyes KISS / Cero Ruido / Anti-parche;
- crea el checkpoint canónico `R1_AUTH_GREEN_CHECKPOINT_2026-10-16.md`;
- retira del HEAD cinco checkpoints transitorios ya absorbidos;
- no toca runtime.

Diff final de PR #31 antes de este checkpoint:
- 27 archivos cambiados;
- solo un checkpoint R1 canónico en el diff;
- PR seguía `OPEN / DRAFT / NOT MERGED`;
- título/body del PR seguían obsoletos y pendientes de converger después de CI #1038.

## Deuda/transición deliberada

`app/Auth/routes.php` conserva un fallback interno:

`identifier ?? email`

Clasificación:
- no es segunda autoridad de autenticación;
- la UI pública ya usa `identifier`;
- existe por helpers/tests legados;
- su gate de eliminación queda fijado para R3, cuando se reabra la superficie pública de login, evitando churn aislado ahora.

## Visual baseline pendiente para R3

Antes de R3 resolver y congelar una sola autoridad sobre:
- spec textual: login sin X/cerrar;
- mockup mobile aprobado: muestra visualmente una X;
- copy: `Usuario` vs `Usuario, correo o cédula`.

No implementar por interpretación.

## Gobernanza pendiente

- `main` fue observado sin branch protection/status checks obligatorios.
- Tratar esto como gate de gobernanza separado del runtime.
- No mergear sin autorización explícita del owner.
- No desplegar.

## Próxima acción exacta al reanudar

1. Leer este checkpoint primero.
2. Consultar CI #1038 / run `38852142599` sobre `f5db4885…`.
3. Si PHP 8.3 / 8.4 / 8.5 = SUCCESS, certificar closure head.
4. Verificar PR #31 apunta al HEAD esperado y no hay drift.
5. Converger título/body de PR a `R1 Auth GREEN / merge gate` sin abrir R2.
6. Auditar diff final una última vez bajo KISS / Cero Ruido / Anti-parche.
7. Parar en merge gate.
8. No mergear hasta autorización explícita del owner.

## Estado corto

```text
R0: LOCKED
R1_PRODUCT: GREEN @ 25bf8a97002495f3288bf23de02c563d7a1e39c4
R1_PRODUCT_CI: #1037 PASS 8.3/8.4/8.5
R1_CLOSURE_DOCS_PARENT: f5db488547f479f596ea5f116ef40eebccd2a357
R1_CLOSURE_CI: #1038 IN_PROGRESS
PR: #31 OPEN / DRAFT / NOT MERGED
R2: NOT STARTED
F26: FROZEN
MERGE_AUTHORIZED: NO
DEPLOYMENT_AUTHORIZED: NO
OWNER_REQUESTED_STOP: YES
```

**STOP.**
