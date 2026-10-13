# F8 — TASK 5 GREEN CHECKPOINT — 2026-10-13

## Estado

- Proyecto: AlquilaPro
- Fase: F8 — Inquilino + lease + portal usando auth único
- Rama: `impl/f8-tenant-lease-portal-20261013`
- Task 4: `GREEN / VERIFIED`
- Task 5: `GREEN / VERIFIED`
- Task 6: `NOT STARTED`
- Merge: `NOT AUTHORIZED / NOT PERFORMED`

Este checkpoint es el punto de reanudación canónico. No iniciar Task 6 salvo nuevo `continua` explícito del usuario.

## Base anterior

Checkpoint RED previo:

- archivo: `docs/checkpoints/F8_TASK5_RED_CHECKPOINT_2026-10-13.md`
- commit: `3a07f314791187943411b2974ac963410ddb6825`

Task 5 RED:

- commit: `f038dc0dc5dbe0882f2b78159fd06b171519ef0c`
- CI: `38424737650`
- PHP 8.3: `300 tests / 2434 assertions / 3 failures`
- los tres fallos correspondían exclusivamente a la integración administrativa F8 ausente.

## Task 5 GREEN productivo

Primer GREEN productivo:

- commit: `97ea77b9d08e5772ff9bcefee5b4f50d576c8e4b`
- mensaje: `feat(f8): integrate lease administration routes`

Superficie mínima implementada:

1. `app/Leases/routes.php`
   - `POST /admin/contracts/{id}/lease`
   - `POST /admin/leases/{id}/end`
   - reutiliza sesión/auth existente, forced-password gate, `contracts.manage` y CSRF global.
2. `bootstrap/app.php`
   - una sola instancia compartida de `UserService`.
   - una sola instancia de `LeaseService`.
   - registra el único route file F8.
3. `app/Contracts/routes.php`
   - el detalle contractual existente recibe la proyección del lease; no se creó CRUD administrativo paralelo.
4. `resources/views/contract_detail.php`
   - contrato finalizado sin lease: CTA de activación solo para manager.
   - lease activo: contexto + CTA de terminación solo para manager.
   - lease terminado: lectura sin reactivar ni liberar automáticamente la unidad.
5. `resources/views/lease_activation_credential.php`
   - muestra la contraseña temporal únicamente en la respuesta inmediata de activación.
   - `Cache-Control: private, no-store`.
   - la credencial no se persiste ni reaparece en el detalle contractual.

No se agregó:

- índice/CRUD administrativo de leases,
- segundo auth/login/session,
- permiso nuevo,
- ruta pública F8,
- tabla nueva fuera de `leases`,
- servicio productivo adicional fuera de `LeaseService`,
- dependencia directa nueva.

## Hallazgo de regresión durante VERIFY GREEN

El CI del primer GREEN encontró un único fallo histórico:

- `ContractSecurityRegressionTest::testF7KeepsSinglePrivateContractArchitectureWithoutParallelStacks`
- causa: el guardrail F7 concatenaba `ContractService + ContractPdfRenderer + app/Contracts/routes.php` y prohibía literalmente `LeaseService` en toda esa composición.
- las tres pruebas nuevas de Task 5 ya estaban verdes.

No se ocultó el tipo ni se debilitó el freeze completo.

Corrección de convergencia:

- commit: `97053bb113c55a775d309ef62a846c9fea66627b`
- mensaje: `test(f8): preserve f7 core boundary during lease integration`

El guardrail quedó con la intención correcta:

- `LeaseService` sigue prohibido dentro del núcleo F7: `ContractService + ContractPdfRenderer`.
- la composición de rutas puede conocer la integración F8 aprobada.
- continúan prohibidos repositorios/capas paralelas, `TenantService`, motores de plantillas alternos, Guzzle, sesiones/CSRF paralelos y demás ruido histórico.

## Evidencia GREEN exacta

HEAD verificado antes de este checkpoint documental:

`97053bb113c55a775d309ef62a846c9fea66627b`

CI exacto:

`38426573790`

Matriz:

- PHP 8.3: `SUCCESS`
- PHP 8.4: `SUCCESS`
- PHP 8.5: `SUCCESS`

PHP 8.3:

`OK (300 tests, 2472 assertions)`

Pruebas Task 5 verificadas:

- `Lease mutations reuse login manage password gate and global csrf` — PASS
- `Finalized contract activation shows credential only in immediate private response` — PASS
- `Ending lease redirects to owning contract and keeps base path` — PASS

También pasa nuevamente el freeze F7:

- `F 7 keeps single private contract architecture without parallel stacks` — PASS

## Two Laws gate de Task 5

### KISS — PASS

- integración en el detalle contractual existente;
- un único route file F8;
- no nuevo CRUD administrativo;
- no nuevo permiso;
- no nueva dependencia;
- no nueva autoridad de estado.

### Cero Ruido / Convergencia — PASS

- auth/session/CSRF existentes reutilizados;
- `contracts.manage` reutilizado;
- `UserService` compartido;
- `LeaseService` es la única autoridad F8 del lease;
- el guardrail F7 fue actualizado para distinguir núcleo F7 de composición cross-phase sin eliminar sus prohibiciones reales.

## Próxima acción única

Solo ante un nuevo `continua`:

1. verificar que la rama desciende de este checkpoint;
2. abrir **Task 6 — RED solamente** para portal privado del tenant;
3. verificar que el RED falla por funcionalidad de portal ausente;
4. mantener el bloque pequeño y crear checkpoint al cerrar el GREEN de Task 6.

No abrir Task 7 ni Task 8 dentro del mismo bloque.
