# AlquilaPro — Checkpoint R1-T5 GREEN candidate / PAUSED

Fecha: 2026-10-16
Estado: PAUSED BY OWNER

## Autoridad actual

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- Base `main`: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Rama activa: `impl/r1-r3-post-uat-recovery-20261015`
- PR: `#31` — OPEN / DRAFT / NOT MERGED
- HEAD funcional candidato: `032a2058fcd57901062500d015762b637216fb5a`
- Commit: `feat(auth): close managed identity and password UI gaps`
- MERGE_AUTHORIZED: NO
- DEPLOYMENT_AUTHORIZED: NO
- F26: FROZEN
- R2/R3: NOT OPENED

## R1 previo

- R1-T1: GREEN / cerrado
- R1-T2: GREEN / cerrado
- R1-T3A: GREEN / cerrado
- R1-T3B: GREEN / cerrado
- R1-T4: GREEN / cerrado
- R1-T5 RED: confirmado previamente en CI #1034 sobre `6f348dfbc03670cbbe696d1bcb9affb91749419c`

## R1-T5 — GREEN candidato publicado

Commit funcional candidato:

`032a2058fcd57901062500d015762b637216fb5a`

Cambios limitados a owners existentes:

1. `app/Auth/UserService.php`
   - `create()` acepta `username` y `document_number` opcionales sin romper llamadas existentes;
   - username: `trim + lowercase`;
   - documento: `trim + uppercase`;
   - vacíos convergen a `NULL`;
   - una sola comprobación cross-field sobre `email`, `username`, `document_number`;
   - índices UNIQUE existentes permanecen como segunda barrera;
   - `listUsers()` proyecta username/document_number;
   - `createTenantIdentity()` reutiliza la misma comprobación de colisión;
   - no se creó tabla, provider, servicio o middleware nuevo.

2. `app/Auth/routes.php`
   - `/admin/users` pasa username/document_number al mismo `UserService`;
   - manejo transaccional existente se conserva;
   - no se añadió capa de errores paralela.

3. `resources/views/admin_users.php`
   - campos opcionales username/document_number;
   - `minlength=6` para contraseña temporal;
   - lista muestra identificadores cuando existen.

4. `resources/views/change_password.php`
   - `minlength=6`.

5. `resources/views/reset_password.php`
   - `minlength=6`.

6. `resources/views/install.php`
   - `minlength=6`;
   - texto visible actualizado a 6 caracteres.

## Leyes

### KISS

PASS candidato:
- un solo `UserService` conserva la autoridad de usuarios gestionados;
- `PasswordPolicy` sigue siendo la autoridad de mínimo de contraseña;
- no se agregó arquitectura nueva.

### Anti-ruido

PASS candidato para T5:
- no se añadieron capas duplicadas;
- identificadores opcionales vacíos se representan como `NULL`, no como cadenas vacías;
- no se abrió R2/R3.

Pendiente para cierre posterior de R1:
- decidir/eliminar el fallback transitorio POST `email` del login si no existe necesidad real;
- converger documentación stale y checkpoints superseded antes del merge final.

### Anti-parches

PASS candidato:
- se partió de RED confirmado;
- cambios hechos en owners existentes;
- no se modificaron tests RED para forzar GREEN;
- no hay fallback o segunda autoridad nueva para T5.

## CI exacta en curso al pausar

- Workflow: CI #1035
- Run id: `38850715340`
- SHA: `032a2058fcd57901062500d015762b637216fb5a`
- Estado observado al crear este checkpoint: `IN_PROGRESS`
- Conclusión: `NONE` todavía

Por tanto:

```text
R1-T5: GREEN_CANDIDATE_ONLY
R1: NOT CERTIFIED YET
```

No afirmar PASS hasta que la matriz PHP 8.3 / 8.4 / 8.5 termine y se inspeccione el resultado exacto.

## Próximo paso exacto al reanudar

1. Refrescar PR #31 y verificar que su HEAD parte de este checkpoint.
2. Consultar CI #1035 / run `38850715340` del SHA funcional `032a2058...`.
3. Si falla: inspeccionar root cause exacto; no parchear tests ni abrir arquitectura.
4. Si pasa PHP 8.3 / 8.4 / 8.5: auditar diff R1 completo bajo KISS / anti-ruido / anti-parches.
5. Resolver solo huecos demostrados de cierre R1.
6. Crear checkpoint canónico `R1_AUTH_GREEN` únicamente con evidencia fresca.
7. No abrir R2 hasta que R1 esté certificado.
8. No mergear ni desplegar sin autorización explícita del owner.

## Regla de parada

El owner pidió explícitamente: `crea un checkpoint y para`.

STOP después de publicar este checkpoint. No continuar implementación, CI follow-up, documentación adicional, R2, R3, merge ni deployment hasta nueva instrucción.
