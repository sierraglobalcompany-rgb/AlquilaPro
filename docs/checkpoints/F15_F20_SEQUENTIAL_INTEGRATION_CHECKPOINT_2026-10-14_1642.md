# AlquilaPro — Checkpoint integración secuencial F15→F20

Fecha/hora de corte: 2026-10-14 16:42 America/Bogota

## Estado global

- Ley KISS: vigente.
- Ley Cero Ruido / Convergencia: vigente.
- Ley Antiparches: vigente.
- `continua` fue aclarado por el usuario como autorización para ejecutar el siguiente gate, incluidos merges secuenciales cuando ese sea el gate preparado.
- Este checkpoint está en una rama documental separada para NO mover `main` ni alterar la CI activa de F18.

## Integraciones ya completadas

### F15 — Agenda y visitas
- PR #18: MERGED.
- Head final pre-merge: `24cbddd93a4da35abc25d99ae0dc9a0f7e553ad6`.
- Merge SHA: `cfab94ce1a5b3a5bdd0f8a73cd49da07319a7915`.
- Reconciliación previa: README canónico + 2 reparaciones test-only de F8/F9; producto F15 no fue modificado.
- CI #910 (`38637583608`): PASS PHP 8.3 / 8.4 / 8.5.

### F16 — Reminders / protected cron / SMTP
- PR #19: MERGED.
- Retargeteada a `main` después de F15.
- Reconciliación README docs-only: `b6f23ad23dc00b4e93142d433765e924ff03bde9`.
- Merge-ref certificado antes de merge: `e0547822a7841699cb30366c743061fe05dfa8c1`.
- CI #911 (`38640461901`): PASS PHP 8.3 / 8.4 / 8.5.
- Merge SHA: `01d8cda30f6a883954b42ff6538b534c0c91e768`.

### F17 — WhatsApp assisted actions
- PR #20: MERGED.
- Retargeteada a `main` después de F16.
- Reconciliación README docs-only: `614747f7d9af47c19d8bbf72beeb18ff5032076e`.
- Merge-ref certificado antes de merge: `e539bf50ad1323829c9a2b56e4228c6e4c4848c1`.
- CI #912 (`38641347396`): PASS PHP 8.3 / 8.4 / 8.5.
- Merge SHA actual de `main`: `cfb57fc349a6e84da63282604c5e1a5f170e5242`.

## F18 — Punto exacto de pausa

PR #21: `[F18] Private documents and inventory snapshots`.

- Base actual: `main` en `cfb57fc349a6e84da63282604c5e1a5f170e5242`.
- Product/closure original certificado previamente: Product `b0f06ac...`, closure `24f2da7dcf043ff4750434e18dcca66dfd37aab1`.
- Durante la convergencia documental se realizaron dos commits docs-only sobre README:
  - `411d53d2b49c7ca927c0b0a7c4c11e13574e24da` — copia inicial que introdujo accidentalmente una duplicación documental.
  - `cb55ee4a8f3632684d80ae5a339c3723803fb036` — corrección inmediata que restaura el README exacto canónico de `main`.
- El blob README final de F18 vuelve a ser el canónico de `main`: `eb13e9facdf147c3a74a0f77a9d3efeba489e618`.
- No se modificó producto F18 durante esta convergencia.
- PR #21 ya fue recalculada como mergeable antes de iniciar la CI actual.
- Merge-ref actual observado: `3ca25034ab06bf187e18738a041e146eda7e5de3`.
- CI #913 (`38642079431`) corre sobre head exacto `cb55ee4a8f3632684d80ae5a339c3723803fb036` y base exacta `cfb57fc349a6e84da63282604c5e1a5f170e5242`.
- Estado al corte: PHP 8.3 / 8.4 / 8.5 estaban ejecutando `Run tests`; Composer, checkout, setup e instalación habían pasado en las tres matrices. Todavía NO declarar GREEN ni fusionar hasta comprobar las tres conclusiones `success`.

## Pendiente después de F18

### F19
- PR #22.
- Head remoto conocido antes de integración: `3d92694370ab7909d275a1c64327de9218af3e92`.
- Reparación previa test-only de `FinanceSecurityRegressionTest` ya certificada por CI #906 en 8.3/8.4/8.5.
- Al llegar su turno: retarget a `main`, reconciliar solo ruido documental/apilado si existe, generar merge-ref nuevo, CI exacta y merge solo si GREEN.

### F20
- PR #23.
- Product Head real: `5f46c63f82672e6d34566284051a5f62e1db56db`.
- Cierre docs original: `5d2488286ce8370402544eaf4276a34a8d83a40d`.
- Convergencia test-only: `be3ee4b761bdfb31733662f3d1c1f03b40b3128a`.
- CI #907 fue GREEN 8.3/8.4/8.5 sobre el stack F19+F20 previo a integración.
- Al llegar su turno: retarget a `main`, recertificar merge-ref actual y fusionar solo si GREEN.

## F21 y posteriores

- No iniciar implementación F21 antes de que F15→F20 estén integradas y auditadas en `main`.
- F21/F22 SPEC y PLAN existen en ramas documentales separadas.
- PLAN F21 ya fue ajustado para reutilizar `MembershipService::listForLease()` + `charge_decision_required`; no crear `hasPendingPaidChargeDecision()` ni otra API paralela.
- F23→F26 diseño/planes están preparados; no existe F27.

## Reanudación exacta

1. Consultar CI #913 (`38642079431`).
2. Exigir `success` en PHP 8.3, 8.4 y 8.5.
3. Confirmar PR #21 sigue `mergeable: true` y head `cb55ee4a...` no cambió.
4. Si GREEN: merge F18 con expected head SHA `cb55ee4a8f3632684d80ae5a339c3723803fb036`.
5. Retarget PR #22 F19 a `main` y repetir protocolo completo.
6. Después F20 igual.
7. Tras F20: auditoría final de `main` antes de iniciar F21.

## Hard stop

Checkpoint solicitado por el usuario. PARAR aquí. No ejecutar más merges, retargets, CI provocadas, commits de producto ni implementación hasta nuevo `continua`.