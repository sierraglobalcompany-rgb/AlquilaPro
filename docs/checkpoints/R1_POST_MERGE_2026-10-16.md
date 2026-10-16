# AlquilaPro — R1 post-merge checkpoint

**Fecha:** 2026-10-16  
**Task:** R1-MERGE  
**Estado:** CLOSED GREEN  
**DEPLOYMENT_AUTHORIZED:** NO  
**R2:** READY TO OPEN AFTER THIS CHECKPOINT

## Autoridad post-merge

- Repo: `sierraglobalcompany-rgb/AlquilaPro`
- `main`: `71445785f39caec7f4e7059a2a80ffbd75468b2d`
- Merge commit: `71445785f39caec7f4e7059a2a80ffbd75468b2d`
- Parent base: `b45f625e68622702335f5e606ae34d7410a2b7e6`
- Parent R1 head: `2fabaf4800414fa807d6acd54ccd49f75da06919`
- PR: `#31`
- PR state: CLOSED / MERGED
- PR merged_at: `2026-10-16T05:01:25Z`
- Product GREEN SHA: `25bf8a97002495f3288bf23de02c563d7a1e39c4`

## Evidencia CI

CI final previa al merge:
- workflow: CI #1040
- run: `38852857032`
- head exacto: `2fabaf4800414fa807d6acd54ccd49f75da06919`
- PHP 8.3: SUCCESS
- PHP 8.4: SUCCESS
- PHP 8.5: SUCCESS
- workflow conclusion: SUCCESS

No existe workflow adicional asociado al merge commit `71445785…` en la consulta post-merge. El merge commit usa el mismo tree del head R1 certificado: `7ccd1fd6622b2a9af4ef6513920b84d709f96cae`.

## R1 cerrado

Contratos integrados en `main`:
- una sola autoridad `AuthService` para email / username / documento;
- ambigüedad de identidad => fail-closed;
- `PasswordPolicy` canónica con mínimo 6;
- admin/gestor con username/documento opcionales;
- opcionales vacíos => `NULL`;
- validación cruzada centralizada en `UserService` + UNIQUE como segunda barrera;
- tenant conserva exactamente documento del candidato tras `trim`;
- contraseña inicial tenant = documento hasheado;
- `must_change_password = 0`;
- sin IdentityService, tabla, provider o middleware paralelo.

## Regresión T5 cerrada

CI #1035 detectó que uppercase de documento gestionado se había reutilizado indebidamente para tenant.

Root cause:
- admin/gestor y tenant tienen contratos de normalización distintos.

Fix mínimo:
- `25bf8a97002495f3288bf23de02c563d7a1e39c4`;
- 2 líneas en `UserService::createTenantIdentity()`;
- `LeaseActivationTest` protege documento/password exactos.

## Leyes

### KISS
PASS.
- owners existentes;
- no arquitectura paralela;
- cambio funcional mínimo.

### Cero Ruido
PASS.
- checkpoints transitorios absorbidos;
- un checkpoint canónico R1 quedó en la rama integrada;
- README y AGENTS convergidos al roadmap post-UAT.

### Anti-parche
PASS.
- regresión reproducida;
- causa raíz aislada;
- fix mínimo;
- suite completa certificada.

## Deuda transitoria controlada

`app/Auth/routes.php` conserva `identifier ?? email` para helpers/tests legados.

- no es segunda autoridad;
- UI pública usa `identifier`;
- gate de eliminación: **R3**.

## Bloqueo visual antes de R3

Resolver y congelar una sola autoridad antes de implementar R3:
- spec textual: login sin X/cerrar;
- mockup mobile: muestra una X;
- copy: `Usuario` vs `Usuario, correo o cédula`.

No implementar por interpretación.

## Tasks

```text
R0: LOCKED
R1-FINAL-CI: CLOSED GREEN
R1-MERGE-GATE-AUDIT: CLOSED GREEN
R1-MERGE: CLOSED GREEN
R1 main: 71445785f39caec7f4e7059a2a80ffbd75468b2d
R2-FOUNDATION-PREFLIGHT: READY
R3: NOT STARTED
PRE_F26_OWNER_UAT: REJECTED
F26: FROZEN
DEPLOYMENT_AUTHORIZED: NO
```

## Próxima acción exacta

1. Tomar `main@71445785f39caec7f4e7059a2a80ffbd75468b2d` como base de R2.
2. Leer el plan/spec de R2 design foundation y auditar sus owners actuales.
3. Crear rama R2 desde ese main exacto.
4. Definir Task R2.1 mínima y RED antes de tocar runtime/estilos.
5. Mantener checkpoints por task/bloque.
6. No abrir R3 hasta cerrar R2.
7. Deployment sigue NO autorizado.
