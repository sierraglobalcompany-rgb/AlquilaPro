# ALQUILA PRO — PLAN DE DESARROLLO V1.2 AUDITADO

**Fecha:** 2026-10-11 (America/Bogota)  
**Spec:** [`../specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`](../specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md)  
**Estado:** roadmap maestro vigente posterior a F3  

---

# 0. OBJETIVO

Construir Alquila Pro por fases pequeñas, verificables y sin sobrearquitectura, incorporando desde V1.2 una superficie pública de landing + catálogo sin duplicar inventario, autenticación ni candidatos.

Cada fase termina funcional, probada, documentada y auditada bajo las dos leyes.

No se avanza acumulando deuda no justificada.

---

# 1. ESTADO REAL AL EMITIR V1.2

```text
F0: CLOSED
F1: CLOSED / VERIFIED / MERGED
F2: CLOSED / VERIFIED / MERGED
F3: CLOSED / VERIFIED / MERGED
F4: NOT STARTED
```

F1–F3 no se reimplementan. Solo se modifican cuando una fase nueva necesita converger comportamiento existente y hay pruebas de regresión.

---

# 2. LAS DOS LEYES SUPREMAS

## LEY 1 — KISS / ARQUITECTURA SIMPLE

Cada fase debe producir la solución correcta con el mínimo razonable de piezas, estados, tablas, dependencias y rutas.

No justificar complejidad con “puede servir después”.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Buscar activamente:

- código muerto;
- duplicados;
- tablas equivalentes;
- dos fuentes de precio/disponibilidad;
- rutas futuras;
- auth paralelo;
- lead/application separado de candidato;
- CMS de catálogo paralelo a unidades;
- documentos/checkpoints obsoletos.

## Gate de cierre

```text
LEY_1_KISS: PASS|FAIL
LEY_2_CERO_RUIDO: PASS|FAIL
ADDED:
REMOVED:
MERGED_OR_CONVERGED:
DEPENDENCIES_DELTA:
DB_SCHEMA_DELTA:
PUBLIC_ROUTES_DELTA:
PROOF:
```

FAIL bloquea cierre y merge.

---

# 3. METODOLOGÍA

## ChatGPT

Producto, SPEC, arquitectura, UX, planes, auditoría, checkpoints y coordinación.

## Codex

Implementación concreta, tests, refactor controlado y Git cuando aporte valor.

## Antigravity

Auditoría independiente/QA cruzado cuando aporte valor.

Ninguno introduce funcionalidad futura sin actualizar SPEC/plan primero.

Ciclo:

`SCOPE → PRE-KISS → SPEC/DISEÑO → PLAN → RED → GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT → REVIEW → MERGE`

No merge sin aprobación explícita.

---

# 4. STACK CONGELADO

- PHP 8.3 base; 8.4/8.5 verificadas.
- Slim 4.
- MariaDB/MySQL.
- PDO.
- HTML server-side.
- CSS mobile-first.
- JS mínimo.
- Alpine solo si demuestra necesidad.
- PHPMailer cuando entre email.
- PHPUnit.
- Composer local.

Sin Node en producción, Redis, workers, microservicios o SPA.

---

# 5. PRESUPUESTO DE ARQUITECTURA V1.2

Base:

- 1 app;
- 1 BD;
- 1 repo;
- 1 auth;
- 1 modelo properties/units;
- 1 catálogo derivado de units;
- 1 candidato/aplicación;
- 1 motor financiero;
- 1 motor recordatorios;
- 1 sistema documental;
- 1 flujo tickets/visitas;
- 1 installer;
- 1 cron.

Una nueva pieza estructural solo entra si converger/mejorar lo existente no resuelve limpiamente.

---

# 6. REGLAS DE CONVERGENCIA ESPECÍFICAS

## Auth

Propietario/admin/gestor/inquilino usan F2. F8 no crea segundo login.

Candidato usa token/formulario de aplicación, no auth paralelo.

## Catálogo

No existe `catalog_listings`.

Catálogo = query/proyección pública de `units` publicadas y `Disponible`.

## Precio

Una unidad tiene un precio comercial/canon solicitado. Contrato guarda el valor final acordado como snapshot.

## Disponibilidad

Estado de unidad es la fuente. No agregar `available` + `catalog_available` + `occupied` simultáneos.

## Aplicación

`Aplicar` crea/continúa `candidate`. No `lead`, no `rental_application` paralela salvo que una necesidad futura demuestre ciclo de vida independiente.

## Media

Fotos de marketing en `unit_media`; video V1 por URL externa. Inventario/documentos privados permanecen en sus módulos posteriores.

---

# 7. FASES

## F0 — SPEC + PLAN ✅

Cerrada.

V1.2 es una revisión arquitectónica posterior; no reabre F0 como implementación.

---

## F1 — FOUNDATION + INSTALLER ✅

Cerrada/mergeada.

No reimplementar.

---

## F2 — AUTH + USUARIOS + PERMISOS ✅

Cerrada/mergeada.

Auth único existente es la fuente para staff + futuro inquilino.

---

## F3 — SHELL MÓVIL + SISTEMA VISUAL ✅

Cerrada/mergeada.

Shell, branding, layouts, Apps y UI base existentes se reutilizan.

---

## F4 — PROPIEDADES + UNIDADES + LANDING + CATÁLOGO PÚBLICO

### Objetivo

Crear la fuente única de inventario rentable y publicar únicamente unidades realmente disponibles, sin todavía abrir el flujo de candidato.

### Incluye

- `properties`;
- `units` para apartamentos/parqueaderos;
- `unit_media` para fotos de marketing;
- estados de unidad;
- asociación opcional parqueadero → apartamento mediante self-FK si aplica;
- precio comercial mensual único;
- administración mensual opcional;
- publicación pública;
- slug público;
- descripción/título/requisitos públicos;
- URL externa de video;
- permisos `properties.view` y `properties.manage`;
- UI administrativa en shell F3;
- Apps muestra Propiedades solo cuando ruta real exista;
- landing pública en `/` para visitante sin sesión;
- `/login` sigue siendo auth único;
- `/catalog` público;
- `/catalog/{slug}` público;
- filtros mínimos apartamento/parqueadero;
- media pública solo si la unidad es publicable;
- soporte subcarpeta.

### No incluye

- candidato;
- formulario Aplicar;
- botón Aplicar muerto;
- contratos;
- leases;
- ocupación automática;
- documentos/inventario privado;
- video upload/transcoding;
- SEO complejo;
- CMS separado.

### Modelo mínimo esperado

`properties`, `units`, `unit_media`.

Antes de congelar migration, SPEC F4 debe demostrar que no puede reducirse más sin perder claridad/integridad.

### Pruebas

- CRUD propiedad;
- CRUD unidad;
- apartamento/parqueadero;
- código único por propiedad;
- estados válidos;
- asociación parking/apartment válida;
- unidad inactiva no pública;
- unidad ocupada/en proceso/mantenimiento no pública;
- `is_public=false` no pública;
- precio único;
- JPEG/PNG/WebP válido;
- MIME malicioso/oversize rechazado;
- media fuera webroot;
- media de unidad despublicada deja de ser pública;
- landing visitante muestra Login + Catálogo;
- usuario autenticado conserva shell;
- catálogo no filtra notas/dirección interna;
- subfolder;
- CSRF en mutaciones admin;
- permisos;
- suite F1–F3 intacta.

### Gate F4

No cerrar si existe tabla/listing paralela, precio duplicado, disponibilidad duplicada o botón Aplicar sin F5 real.

---

## F5 — APLICACIONES + CANDIDATOS + REFERENCIAS

### Objetivo

Convertir la visita de catálogo en el único candidato operativo, sin CRM/lead paralelo.

### Incluye

- CTA `Aplicar` únicamente ahora que existe ruta real;
- candidato vinculado a unidad de interés;
- formulario público;
- datos básicos;
- laboral;
- referencias;
- ocupantes;
- mascotas declaradas;
- documentos configurables necesarios;
- autorización de tratamiento;
- token criptográfico de continuación;
- estado de candidato;
- administración/listado en shell;
- enlace desde catálogo a aplicación;
- requisitos estructurados;
- política para candidato existente/nueva unidad de interés congelada antes de código.

### No incluye

- segundo login;
- tabla `leads`;
- tabla `applications` duplicada;
- email engine propio;
- decisión automática;
- estudio SURA (F6).

### Notificación

La candidatura aparece inmediatamente en administración. Email automático se conecta cuando exista F16; no duplicar motor ahora.

### Pruebas

- Aplicar solo en unidad publicable;
- unidad retirada no acepta nueva aplicación;
- token seguro/expirado/one-context;
- candidato no enumerable;
- consentimiento;
- incompletos;
- referencias;
- documentos;
- permisos admin;
- candidato único/no CRM paralelo;
- subfolder/CSRF/rate limit básico;
- catálogo → candidato preserva unidad objetivo.

---

## F6 — ESTUDIO DE ASEGURADORA

Proveedor genérico, SURA inicial, URL personalizada, estado, fechas, resultado, soporte y portal candidato.

Pruebas: URL, estado auditado, candidato no autoaprueba, admin registra resultado, fallback sin URL.

---

## F7 — MOTOR DE CONTRATOS

Antes: spike PDF compatible PHP 8.3–8.5, UTF-8, CSS, imágenes, saltos, header/footer, licencia y sin binarios obligatorios.

Plantillas, versiones, cláusulas, variables, preview, snapshot, PDF, hash, estados, tareas y anexos.

El contrato toma candidato/unidad existentes; no copia catálogo como nueva entidad.

---

## F8 — INQUILINO + LEASE + PORTAL

Crear tenant/lease, asociar unidades, activar credenciales y portal contextual.

**Regla:** reutiliza `users`, `AuthService`, SessionGuard, CSRF, recuperación y layout auth F2/F3.

No `/tenant-login` ni tabla de auth paralela.

Pruebas: aislamiento, forced password change, parqueadero oculta vivienda, contrato inactivo restringe acciones, rol/contexto correcto tras mismo login.

---

## F9 — CARGOS + CARTERA

Motor financiero común: canon, administración, servicio, multa, reparación y otros; periodos, estados, mora y saldos.

---

## F10 — MEDIOS DE PAGO

Cuenta bancaria, Bre-B, QR, billetera, orden, alcance y activo/inactivo.

---

## F11 — REPORTE DE PAGO + CONCILIACIÓN

Upload/reporte sin comprobante, fecha/hora aproximada, medio, conciliación, parciales, allocations y duplicados probables.

---

## F12 — SERVICIOS + OBLIGACIONES

Servicios públicos, soportes, estados, administración PH y multas.

---

## F13 — PÓLIZAS + RECLAMACIONES

Póliza configurable, documentos, coberturas, fechas, alertas, reclamación, radicado y checklist.

---

## F14 — SOLICITUDES + MANTENIMIENTO

Tickets, categorías, prioridad, fotos, estados, responsable, clasificación de daño y acción explícita de generar cargo.

---

## F15 — AGENDA + VISITAS

Opciones de horario, selección del inquilino, técnico, fecha/hora, reprogramación/cancelación y resultado.

---

## F16 — RECORDATORIOS + CRON + EMAIL

Motor genérico de reglas, SMTP, cron protegido, notification log e idempotencia.

Aquí se conecta email de eventos de candidatura sin motor paralelo.

---

## F17 — WHATSAPP ASISTIDO

Plantillas, variables, URL WhatsApp y trazabilidad de apertura; sin entrega falsa.

---

## F18 — DOCUMENTOS + MANUAL + INVENTARIOS

Sistema documental privado, tipos/versiones, manual convivencia e inventario inicial/final.

No reutilizar `unit_media` de marketing para evidencia privada si tienen privacidad/ciclo distintos.

---

## F19 — CONVIVENCIA + CONTENIDO LEGAL

Incidentes, soportes, contenido legal, fuentes/versiones y avisos.

---

## F20 — MEMBRESÍAS / SERVICIOS DIGITALES

Servicio real, precio, duración, renovación, aceptación e histórico.

---

## F21 — BONO + LIQUIDACIÓN

Reglas de bono separadas + liquidación contractual.

---

## F22 — SEGURIDAD + PRIVACIDAD

OWASP básica + revisión específica de nueva superficie pública:

- catálogo no filtra datos internos;
- media pública no sirve unidad despublicada;
- slug no permite enumerar datos privados;
- aplicación no enumera candidatos;
- token candidato;
- IDOR;
- path traversal;
- upload PHP;
- auth fixation/CSRF;
- storage directo;
- headers/logs.

---

## F23 — DASHBOARD FINAL

Solo datos reales. Incluir disponibilidad/publicación/candidatos únicamente si hay acción útil.

No analytics decorativa de catálogo.

---

## F24 — QA PHP 8.3 / 8.4 / 8.5

Composer, PHPUnit, migración limpia/upgrade, installer, catálogo, aplicaciones, auth único, PDF, email mock, cron, uploads, permisos, UTF-8, timezone, responsive.

---

## F25 — PACKAGE SERVER_READY

Incluye vendor, installer, config ejemplo, web config, storage, permisos, release notes, checksum y versión.

Salida final: `ALQUILA_PRO_V1_SERVER_READY.zip`.

---

## F26 — HOSTINGER + SMOKE TEST

Desplegar en `alquilapro.sierraglobalcompany.com`.

Smoke mínimo:

- HTTPS;
- installer;
- landing pública;
- catálogo;
- login único;
- propiedad/unidad;
- aplicación candidato;
- portal;
- uploads;
- PDF;
- email;
- cron;
- Bre-B/QR;
- permisos/logs.

No usar datos sensibles reales en smoke inicial.

---

# 8. GATE OBLIGATORIO POR FASE

Antes de cerrar:

- [ ] Ley 1 PASS.
- [ ] Ley 2 PASS.
- [ ] mínimo razonable de piezas.
- [ ] sin capas delegadoras vacías.
- [ ] sin dependencia evitable.
- [ ] sin tabla/ruta/flag sin uso real.
- [ ] sin botones futuros.
- [ ] sin fuentes paralelas de verdad.
- [ ] revisado eliminar.
- [ ] revisado converger.
- [ ] revisado simplificar.
- [ ] added/removed/merged documentado.
- [ ] delta dependencias/esquema/rutas.
- [ ] focal PASS.
- [ ] suite acumulada PASS.
- [ ] checkpoint canónico.
- [ ] docs/README coherentes.

Funcional + arquitectura innecesaria = fase NO terminada.

---

# 9. TESTING

## Unit

Estados, validaciones, permisos, reglas de publicación, cálculos y offsets.

## Integration

PDO/migrations/transacciones, storage/media, candidatos, pagos, cron.

## HTTP

Landing, catálogo, aplicación, auth, permisos, CSRF y subcarpeta.

## Security

Casos por fase, especialmente superficie pública.

## Visual/mobile

360/390/412/tablet/desktop, teclado, safe-area, uploads, forms, dialog/sheets cuando aplique.

---

# 10. DATOS Y TRANSACCIONES

Toda modificación estructural usa migration.

Seeds solo desarrollo y sin datos personales reales.

Transacciones en operaciones multi-registro críticas.

Antes de tabla nueva: eliminar/unir/converger.

---

# 11. GIT / CONTINUIDAD

- ramas por fase;
- commits pequeños/semánticos;
- PR draft durante ejecución;
- Ready solo con cierre + CI exacto;
- merge solo con aprobación explícita;
- no dejar estado importante solo en chat;
- README siempre apunta a SPEC/PLAN vigentes;
- checkpoint temporal se elimina/converge al cierre cuando deje de aportar.

---

# 12. DEFINITION OF DONE V1

V1 lista cuando:

- installer funciona;
- PHP 8.3–8.5 validado;
- landing pública funciona;
- catálogo muestra solo disponibilidad real;
- apartamentos/parqueaderos comparten fuente;
- Aplicar crea/continúa candidato único;
- candidato → estudio → contrato → inquilino funciona;
- staff/inquilino usan auth único;
- contratos PDF funcionan;
- cartera/pagos/conciliación funcionan;
- Bre-B/QR funciona;
- cron/email/WhatsApp asistido funcionan;
- solicitudes/visitas funcionan;
- documentos/inventarios funcionan;
- pólizas/reclamaciones funcionan;
- convivencia funciona;
- membresía/bono separados;
- liquidación funciona;
- seguridad/auditoría pasan;
- SERVER_READY instala limpio;
- no hay módulos/tablas/dependencias/checkpoints obsoletos sin justificación.

---

# 13. ORDEN DE EJECUCIÓN V1.2

`F4 → F5 → F6 → F7 → F8 → F9 → F10 → F11 → F12 → F13 → F14 → F15 → F16 → F17 → F18 → F19 → F20 → F21 → F22 → F23 → F24 → F25 → F26`

F0–F3 ya están cerradas.

No abrir F5 con F4 abierta salvo spike explícito autorizado.

---

# 14. PRÓXIMO GATE

Crear/revisar SPEC específica F4 contra esta V1.2.

F4 debe congelar migration mínima y rutas exactas antes del primer RED.

F5 no se implementa hasta que F4 esté cerrada/mergeada; por tanto F4 no debe mostrar CTA `Aplicar` todavía.
