# AlquilaPro — F15→F20 MERGED FINAL

Fecha: 2026-10-14
Estado: `CLOSED / VERIFIED / MERGED`

## Autoridad canónica

```text
REPO: sierraglobalcompany-rgb/AlquilaPro
MAIN_FINAL_SHA: 2884f41b145f912f0137dc498a5aef9d4fb24768
F15→F20: MERGED SECUENCIALMENTE
PRODUCT_REGRESSION_FOUND_DURING_INTEGRATION: NO
KISS: PASS
CERO_RUIDO: PASS
ANTIPARCHES: PASS
```

## Secuencia de integración certificada

| Fase | PR | Head final certificado | CI final | Merge SHA |
|---|---:|---|---|---|
| F15 | #18 | `24cbddd93a4da35abc25d99ae0dc9a0f7e553ad6` | #910 / `38637583608` / PHP 8.3-8.5 PASS | `cfab94ce1a5b3a5bdd0f8a73cd49da07319a7915` |
| F16 | #19 | `b6f23ad23dc00b4e93142d433765e924ff03bde9` | #911 / `38640461901` / PHP 8.3-8.5 PASS | `01d8cda30f6a883954b42ff6538b534c0c91e768` |
| F17 | #20 | `614747f7d9af47c19d8bbf72beeb18ff5032076e` | #912 / `38641347396` / PHP 8.3-8.5 PASS | `cfb57fc349a6e84da63282604c5e1a5f170e5242` |
| F18 | #21 | `cb55ee4a8f3632684d80ae5a339c3723803fb036` | #913 / `38642079431` / PHP 8.3-8.5 PASS | `9a9d3f6ab36b929e911b265a9e50a5a554410527` |
| F19 | #22 | `1fdceca79df2a9efe66b14f870b73444b1acb946` | #914 / `38644228908` / PHP 8.3-8.5 PASS | `5166e3ead05014682a71b160a41189dac7aeffe4` |
| F20 | #23 | `4644a9b1159be3deab111058c8d859027c8c9b74` | #915 / `38645213296` / PHP 8.3-8.5 PASS | `2884f41b145f912f0137dc498a5aef9d4fb24768` |

## Presupuesto arquitectónico resultante

```text
F15: maintenance_visits + VisitService
F16: notification_jobs + NotificationService + PHPMailer
F17: whatsapp_actions + WhatsAppService + whatsapp.use
F18: documents + DocumentService + documents.view/manage
F19: community_incidents + CommunityService + reutilización de documents
F20: digital_services + service_memberships + MembershipService
```

Migraciones canónicas en `main`:

```text
015_create_maintenance_visits.sql
016_create_notification_jobs.sql
017_create_whatsapp_actions.sql
018_create_documents.sql
019_create_community_incidents.sql
020_create_digital_services_memberships.sql
```

No se introdujo una segunda autoridad financiera, documental, de notificaciones, autenticación o almacenamiento.

## Convergencias durante la integración

### F15

`main` había avanzado documentalmente y PR #18 tenía conflicto de README. Se adoptó el README canónico de `main`. La recertificación expuso dos tests F8/F9 estructurales antiguos que congelaban la lista exacta de rutas tenant y no contemplaban F15.

Reparaciones exclusivamente test-only:

```text
28d5f6f3eed1612603e68df1de00f43deb741146
24cbddd93a4da35abc25d99ae0dc9a0f7e553ad6
```

Producto F15 no cambió.

### F19

CI inicial expuso `FinanceSecurityRegressionTest` sobreacoplado a la lista literal `use (...)` de una closure. La ownership real seguía siendo correcta: `LeaseService::findForUser()` y 404 antes de lookup lateral.

Reparación test-only:

```text
3d92694370ab7909d275a1c64327de9218af3e92
```

El test pasó a verificar orden semántico de ownership.

### F20

Producto funcional real:

```text
PRODUCT_HEAD: 5f46c63f82672e6d34566284051a5f62e1db56db
DOCS_CLOSURE_ORIGINAL: 5d2488286ce8370402544eaf4276a34a8d83a40d
TEST_CONVERGENCE: be3ee4b761bdfb31733662f3d1c1f03b40b3128a
FINAL_INTEGRATION_HEAD: 4644a9b1159be3deab111058c8d859027c8c9b74
```

Un checkpoint histórico escribió por error `5f46c638...`; este documento corrige la autoridad: el Product Head real es `5f46c63f...`. El snapshot histórico no se reescribe.

F20 conserva:

- aceptación/renovación con términos actuales y rechazo de oferta obsoleta;
- no auto-cargo al aceptar;
- `CREATE` usa `FinanceService` como writer canónico;
- `SKIP` es terminal sin cargo;
- orden de bloqueo lease → membership para decisiones de cargo;
- regresión concurrente CREATE/renew sin deadlock;
- `MembershipService::listForLease()` como API canónica para consumidores posteriores.

## Deudas diferidas explícitas

No son bloqueantes de F15→F20 y no deben convertirse en arquitectura paralela:

1. F16: scans de recordatorios sin paginación.
2. F16: retención de recipient name/email; F22 ya define redacción terminal a 90 días usando el cron existente.
3. F17: historial latest 100 sin paginación.
4. F17: nombres de actores no renderizados.
5. F18: prueba symlink no ejecutable en host Windows; CI Linux pasó.
6. F19: lista administrativa limitada a 200 incidentes sin paginación.
7. Scratch local `.phpunit.cache/` y `app/graphify-out/`: nunca formar parte de producto sin demostrar referencia/owner.
8. Gobernanza/protección real de `main`: F22 debe verificarla y registrar `GOVERNANCE_ACTION_REQUIRED` si sigue ausente.

## Gate F21

F21 puede abrirse solo desde `main` posterior a este cierre.

Antes de implementar:

1. PRE-KISS del plan F21 contra el contrato real F20.
2. No crear `hasPendingPaidChargeDecision()` ni otra API paralela; derivar el blocker desde `MembershipService::listForLease()` + `charge_decision_required`.
3. Reconciliar el nombre exacto del feature flag del bono; preferir una única convención canónica `ALQUILAPRO_*` si el código/configuración existente la confirma.
4. Revisar la lista de dependencias de `TerminationService`; eliminar colaboradores que no sean necesarios.
5. Evitar transacciones anidadas: la finalización debe usar una sola transacción/orden de locks coherente.
6. Mantener `LeaseService::end()` como único writer del lifecycle del lease.
7. No crear ledger/payout/deposit/history/status-history ni writers financieros nuevos.

## Estado de roadmap

```text
F0→F20: CLOSED / VERIFIED / MERGED
F21: SPEC + PLAN READY / IMPLEMENTATION NOT STARTED
F22: SPEC + PLAN READY / IMPLEMENTATION NOT STARTED
F23→F26: SPEC/PLANS READY / IMPLEMENTATION NOT STARTED
F27: DOES NOT EXIST
```

## Próximo punto de reanudación

```text
main@2884f41b145f912f0137dc498a5aef9d4fb24768
→ canonical docs convergence
→ PRE-KISS F21
→ RED F21 Task 1
→ VERIFY RED
→ MINIMAL GREEN
```
