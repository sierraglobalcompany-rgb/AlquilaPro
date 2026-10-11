# ALQUILA PRO — PLAN DE DESARROLLO V1.1 AUDITADO

**Fecha:** 2026-10-11  
**Spec:** [`../specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`](../specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md)  
**Estado:** roadmap maestro previo a implementación

---

# 0. OBJETIVO

Construir Alquila Pro local-first, por fases pequeñas, comprobables y sin sobrearquitectura. Cada fase debe terminar funcional, probada, documentada y auditada bajo las dos leyes supremas.

No se avanza acumulando deuda no justificada.

---

# 1. LAS DOS LEYES SUPREMAS

## LEY 1 — KISS / ARQUITECTURA SIMPLE

Cada fase debe producir la solución más simple, sencilla, optimizada, eficiente, eficaz, rápida, auditable, actualizable y mejorable.

No justificar complejidad con “es estándar”, “puede servir después” o “queda más profesional”. Toda pieza estructural debe demostrar necesidad real.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Buscar activamente código muerto, duplicados, capas redundantes, tablas equivalentes, rutas repetidas, dependencias sin uso, estados equivalentes, lógica dispersa, docs obsoletas y future-code.

## Gate de cierre

Cada fase debe terminar con:

- `LEY_1_KISS: PASS`
- `LEY_2_CERO_RUIDO: PASS`

Un FAIL bloquea cierre y merge.

Checkpoint mínimo:

```text
TWO_LAWS_GATE
LEY_1_KISS: PASS|FAIL
LEY_2_CERO_RUIDO: PASS|FAIL

ADDED:
- ...
REMOVED:
- ...
MERGED_OR_CONVERGED:
- ...
DEPENDENCIES_DELTA:
- ...
DB_SCHEMA_DELTA:
- ...
PUBLIC_ROUTES_DELTA:
- ...
WHY_THIS_IS_STILL_SIMPLE:
- ...
PROOF:
- pruebas/comandos
```

---

# 2. METODOLOGÍA

## ChatGPT

Responsable principal de producto, SPEC, arquitectura, UX, reglas, planes, auditorías, checkpoints, recuperación de contexto y coordinación.

## Codex

Implementación concreta, tests, refactors controlados, Git y ejecución de mini-planes cuando aporte valor.

## Antigravity

Auditoría independiente, QA, verificación cruzada, búsqueda de huecos y regresiones.

Ninguno puede introducir funcionalidad no aprobada sin actualizar primero la SPEC.

---

# 3. STACK CONGELADO

- PHP 8.3 base.
- Compatibilidad 8.4 y 8.5.
- Slim 4.
- MariaDB/MySQL.
- PDO.
- Alpine.js.
- HTML server-side.
- CSS mobile-first.
- PHPMailer.
- PHPUnit.
- Composer local.
- PDF library tras spike.
- PHPStan solo si aporta sin ruido.

Sin Node en servidor, Redis, workers, microservicios o SPA.

---

# 4. PRESUPUESTO DE ARQUITECTURA

Base:

- 1 aplicación;
- 1 BD;
- 1 repo;
- 1 auth;
- 1 motor financiero;
- 1 motor de recordatorios;
- 1 sistema documental;
- 1 flujo tickets/visitas;
- 1 installer;
- 1 cron.

Una nueva pieza estructural solo entra si unir/converger/mejorar lo existente no resuelve limpiamente el problema.

Toda dependencia nueva documenta: qué código evita, qué riesgo reduce, qué mantenimiento agrega y compatibilidad 8.3–8.5.

---

# 5. ESTRUCTURA DE REPOSITORIO PREVISTA

```text
app/
  Auth/
  Candidates/
  Contracts/
  Finance/
  Insurance/
  Maintenance/
  Properties/
  Reminders/
  Tenants/
  Shared/   # solo dos o más consumidores reales
bootstrap/
config/
database/
  migrations/
  seeds/
docs/
  specs/
  plans/
  checkpoints/
  legal/
  qa/
public/
resources/
  views/
  css/
  js/
routes/
storage/
  private/
  uploads/
  logs/
tests/
vendor/
```

La estructura no se crea por adelantado: cada carpeta nace solo cuando una fase la necesita.

No crear `Controller + Service + Repository + Interface + DTO + Mapper + Factory` por costumbre. Agrupar por feature cuando sea más fácil de entender.

---

# 6. CICLO OBLIGATORIO DE CADA FASE

## 6.1 SCOPE

Definir objetivo, alcance, entradas, salidas, estados, errores, seguridad, criterios y qué NO entra.

## 6.2 PRE-KISS

Preguntar:

1. ¿Puede resolverse con una pieza existente?
2. ¿Necesita tabla nueva?
3. ¿Necesita clase/servicio nuevo?
4. ¿Necesita dependencia nueva?
5. ¿Puede resolverse con PHP/HTML simple?
6. ¿Se está diseñando para un futuro hipotético?

## 6.3 RED

Pruebas que fallen por ausencia de capacidad.

## 6.4 GREEN

Implementación mínima correcta.

## 6.5 CONVERGE / REFACTOR

Buscar duplicación, fuentes paralelas, capas que delegan, estados equivalentes, nombres pobres y queries innecesarias.

## 6.6 QA

Focal + suite acumulada + móvil/seguridad/rendimiento cuando aplique.

## 6.7 TWO LAWS GATE

Ley 1 y Ley 2 deben quedar PASS con evidencia.

## 6.8 CHECKPOINT

SHA, rama, tests, deuda, gate de leyes, delta de dependencias/esquema/rutas y siguiente tarea.

---

# 7. FASES

## F0 — SPEC Y PLAN

Objetivo: congelar el producto antes de programar.

Entregables:

- SPEC maestra;
- PLAN maestro;
- decisiones de stack;
- P0/P1/P2/REJECT;
- dos leyes;
- UX;
- fuentes legales.

Gate: documentación aceptada y repositorio inicializado.

---

## F1 — FOUNDATION + INSTALLER

Objetivo: aplicación mínima autoinstalable local y equivalente a Hostinger.

Incluye:

- Slim/bootstrap;
- config;
- PDO;
- migraciones;
- storage/logs;
- error handling;
- installer web;
- detección base URL;
- install lock;
- health check.

Pruebas:

- PHP 8.3 arranca;
- conexión válida;
- conexión inválida no avanza;
- migraciones idempotentes;
- installer bloqueado después;
- subdominio/subcarpeta;
- secretos no aparecen.

KISS: no módulos de negocio todavía.

---

## F2 — AUTH + USUARIOS + PERMISOS

Incluye login/logout, cambio, recuperación, propietario, admin/gestor, permisos simples, sesiones, CSRF y rate limit básico.

Pruebas: login, error, expiración, usuario inactivo, último propietario, permiso denegado, reset one-use.

Evitar RBAC genérico si switches simples resuelven.

---

## F3 — SHELL MÓVIL + SISTEMA VISUAL

Incluye tokens visuales mínimos, layout, topbar, bottom nav, Apps launcher, cards, forms, sheets, empty/loading/error states, responsive y branding.

Validar Android/iPhone, touch targets, contraste y overflow.

No construir design system sobredimensionado.

---

## F4 — PROPIEDADES + UNIDADES

Propiedades, apartamentos, parqueaderos, fotos, disponibilidad, ubicación, estado y datos operativos.

Pruebas: CRUD útil, estados, unidad inactiva, archivos válidos/invalidos.

---

## F5 — CANDIDATOS + REFERENCIAS

Datos, laboral, referencias, ocupantes, mascotas declaradas, documentos configurables, enlace privado y autorización de datos.

Pruebas: token seguro/expirado, permisos, incompletos y consentimiento.

---

## F6 — ESTUDIO DE ASEGURADORA

Proveedor genérico, SURA inicial, URL personalizada, estado, fechas, resultado, soporte y portal candidato.

Pruebas: URL, estado auditado, candidato no autoaprueba, administrador registra resultado, fallback sin URL.

---

## F7 — MOTOR DE CONTRATOS

Antes: spike de PDF comparando librerías compatibles PHP 8.3–8.5, UTF-8, CSS, imágenes, saltos, header/footer, licencia y sin binarios obligatorios.

Incluye: plantillas, versiones, cláusulas, variables, bloques, preview, snapshot, PDF, hash, estados, tareas y anexos.

Pruebas: variable válida/faltante, snapshot inmutable, plantilla nueva no cambia contrato viejo, caracteres españoles, PDF y campos obligatorios.

---

## F8 — INQUILINO + PORTAL

Crear tenant/lease, asociar unidades, credenciales iniciales, cambio obligatorio, dashboard cliente y visibilidad contextual.

Pruebas: aislamiento entre usuarios, parqueadero oculta vivienda, contrato inactivo restringe acciones.

---

## F9 — CARGOS + CARTERA

Motor financiero común: canon, administración, servicio, multa, reparación y otros; períodos, estados, mora y saldos.

Pruebas: vencimientos, parciales, anulaciones justificadas y separación de conceptos.

---

## F10 — MEDIOS DE PAGO

Cuenta bancaria, Bre-B, QR, billetera, orden, alcance y activo/inactivo.

Pruebas: llave documento/celular/correo/@, QR, método inactivo y seguridad de datos.

---

## F11 — REPORTE DE PAGO + CONCILIACIÓN

Upload, reporte sin comprobante, fecha/hora aproximada, medio, captura opcional, conciliación, parcial, allocations y duplicados probables.

Pruebas: archivo válido/malicioso, sin archivo, monto distinto, parcial, un pago a varios cargos, duplicado y audit log.

---

## F12 — SERVICIOS + OBLIGACIONES

Servicios públicos, soportes, estados, recordatorios, administración PH y multas.

Pruebas: servicio no controlado, soporte, vencido, multa separada de cobertura aseguradora.

---

## F13 — PÓLIZAS + RECLAMACIONES

Póliza configurable, SURA, documentos, coberturas, fechas, alertas, reclamación, radicado y checklist.

Pruebas: vencimiento, mora según configuración, reclamación y pago posterior con alerta.

---

## F14 — SOLICITUDES + MANTENIMIENTO

Tickets, categorías, prioridad, fotos, estados, responsable, clasificación de daño y acción explícita de generar cargo.

Pruebas: creación, cambios, cierre, permisos y no-cargo automático.

---

## F15 — AGENDA + VISITAS

Varias opciones de horario, selección del inquilino, técnico, fecha/hora, recordatorios, reprogramación/cancelación y resultado.

---

## F16 — RECORDATORIOS + CRON + EMAIL

Motor genérico: reglas, offsets, destinatarios, templates, notification log, SMTP, cron protegido e idempotencia.

Pruebas: -5/-3/0/+N, cron doble sin duplicado, SMTP failure, pago reportado y múltiples destinatarios.

---

## F17 — WHATSAPP ASISTIDO

Plantillas, variables, URL de WhatsApp, pagos, solicitudes, visitas y trazabilidad de apertura.

Pruebas: teléfono normalizado, URL encoding, caracteres, falta de teléfono y no registrar entrega falsa.

---

## F18 — DOCUMENTOS + MANUAL + INVENTARIOS

Sistema documental privado, tipos/versiones, manual convivencia, inventario inicial/final y comparación.

Pruebas: aislamiento de archivos, nueva versión preserva vieja, fotos y comparación.

---

## F19 — CONVIVENCIA + CONTENIDO LEGAL

Incidentes, ruido, mascotas, zonas comunes, soportes, contenido legal, fuentes, versiones y aviso.

Pruebas: incidente, permisos, no convertir automáticamente en terminación y conservar historia.

---

## F20 — MEMBRESÍAS / SERVICIOS DIGITALES

Nombre, servicio real, precio, duración 6/12/N, hasta fin de contrato, renovación, aceptación e histórico.

Guardrails: no depósito, no reembolso condicionado, no afirmar legalidad automática, separar de bono.

---

## F21 — BONO + LIQUIDACIÓN

Reglas, evaluación, inventario, saldos, servicios, multas, daños, llaves, resultado, pago bono y documento cierre.

Guardrails: bono no es charge, no saldo retenido, no se referencia membresía, desactivado por defecto.

---

## F22 — SEGURIDAD + PRIVACIDAD

Revisión OWASP básica: sesiones, CSRF, XSS, SQLi, uploads, access control, tokens, headers, secretos, logs y datos sensibles.

QA: path traversal, IDOR, upload PHP, token reuse, fixation, CSRF, storage directo y enumeración.

---

## F23 — DASHBOARD FINAL

Construirlo con datos reales, no mocks decorativos.

Mostrar cartera, conciliaciones, solicitudes, visitas, seguros, contratos, candidatos y propiedades.

Criterio: no métrica sin acción útil.

---

## F24 — QA PHP 8.3 / 8.4 / 8.5

Verificar Composer, PHPUnit, migración desde cero, upgrade DB, installer, PDF, email mock, cron, uploads, permisos, UTF-8, timezone y responsive.

---

## F25 — PACKAGE SERVER_READY

Incluir vendor, installer, config example, web config cuando aplique, storage, permisos, release notes, checksum y versión.

Salida: `ALQUILA_PRO_V1_SERVER_READY.zip`.

Gate: instalación limpia en entorno equivalente a Hostinger.

---

## F26 — HOSTINGER + SMOKE TEST

Desplegar en `alquilapro.sierraglobalcompany.com`.

Verificar HTTPS, installer, DB, login, upload, PDF, email, cron, portal, Bre-B/QR, permisos y logs.

No usar datos sensibles reales en el smoke inicial.

---

# 8. GATE OBLIGATORIO PARA CADA FASE

Antes de cerrar cualquiera de F0–F26:

- [ ] LEY 1/KISS PASS.
- [ ] LEY 2/Cero Ruido PASS.
- [ ] Solución usa mínimo razonable de piezas.
- [ ] No quedaron capas que solo delegan.
- [ ] No hay dependencia evitable.
- [ ] No hay tabla/ruta/flag sin uso real.
- [ ] Se revisó qué eliminar.
- [ ] Se revisó qué unir/converger.
- [ ] Se revisó qué simplificar.
- [ ] Se registró added/removed/merged.
- [ ] Delta dependencias/esquema/rutas.
- [ ] Pruebas focales pasan.
- [ ] Suite acumulada pasa.
- [ ] Checkpoint creado.

Funcionalmente correcto + arquitectura innecesariamente compleja = fase NO terminada.

---

# 9. TESTING

## Unit

Reglas de estado, cálculos, offsets, allocations, variables contractuales y autorización.

## Integration

PDO, migraciones, transacciones, repositorios/persistencia real, documentos, pagos y cron.

## HTTP

Auth, permisos, endpoints, formularios y CSRF.

## Security

Casos específicos por fase.

## Mobile

Navegación, forms, sheets, uploads, teclado, safe areas y errores.

---

# 10. DATOS Y TRANSACCIONES

Toda modificación estructural usa migration.

Seeds solo de desarrollo y sin datos personales reales.

Transacciones en conciliación, allocations, activación contractual, cierre y operaciones críticas multi-registro.

Antes de tabla nueva: evaluar eliminar/unir/converger.

---

# 11. GIT

Commits pequeños y significativos.

Ejemplos:

- `feat(installer): add database connection step`
- `feat(auth): add forced first-password change`
- `feat(payments): add manual payment report`
- `test(reminders): prove cron idempotency`
- `refactor(finance): remove duplicate balance calculation`

No cerrar/mergear una fase si fallan tests, hay working tree inesperado, docs desactualizadas o cualquiera de las dos leyes está FAIL.

---

# 12. CHECKPOINTS

Cada fase cerrada crea `docs/checkpoints/...` con:

- fase;
- fecha;
- rama;
- SHA;
- working tree;
- pruebas;
- decisiones;
- added/removed/merged;
- dependencias;
- esquema;
- rutas;
- Ley 1;
- Ley 2;
- deuda conocida;
- siguiente tarea.

No dejar estado importante únicamente en el chat.

---

# 13. PSE FUTURO

No se implementa en V1.

El motor financiero debe permitir posteriormente:

`charge -> payment attempt/source -> payment -> allocation`

Cuando se decida PSE: investigar proveedor, costos, webhook, firma, conciliación, estados, devoluciones y pruebas.

Sin código muerto hoy.

---

# 14. DEFINITION OF DONE V1

V1 lista cuando:

- installer funciona;
- PHP 8.3–8.5 validado;
- UX móvil operativa;
- candidato→estudio→contrato→inquilino funciona;
- contratos PDF funcionan;
- cartera/pagos/conciliación funcionan;
- Bre-B/QR y reporte sin comprobante funcionan;
- cron idempotente;
- email y WhatsApp asistido funcionan;
- solicitudes/visitas funcionan;
- documentos/inventarios funcionan;
- pólizas/reclamaciones funcionan;
- convivencia funciona;
- membresía/bono separados;
- liquidación funciona;
- auditoría funciona;
- seguridad validada;
- package SERVER_READY instala limpio;
- todas las fases cerraron con Ley 1 y Ley 2 PASS;
- auditoría final no encuentra código/módulos/dependencias sin uso justificable.

---

# 15. ORDEN DE EJECUCIÓN

F1 → F2 → F3 → F4 → F5 → F6 → F7 → F8 → F9 → F10 → F11 → F12 → F13 → F14 → F15 → F16 → F17 → F18 → F19 → F20 → F21 → F22 → F23 → F24 → F25 → F26.

No abrir una fase futura con la anterior abierta salvo spike explícito autorizado.

---

# 16. CÓMO RETOMAR

Una nueva IA/persona debe:

1. leer `README.md`;
2. leer la SPEC;
3. leer este PLAN;
4. leer checkpoint más reciente;
5. inspeccionar Git/HEAD/working tree;
6. ejecutar/leer tests reales;
7. identificar primer gate abierto;
8. continuar desde ahí, sin repetir trabajo cerrado por memoria del chat.

**FIN — PLAN MAESTRO V1.1 AUDITADO**
