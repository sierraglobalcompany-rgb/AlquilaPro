# Alquila Pro

Sistema privado, mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos: candidatos, estudio con aseguradora, contratos, inquilinos, cartera, pagos, medios de pago, documentos, pólizas, solicitudes, mantenimiento, convivencia, recordatorios, liquidación y trazabilidad.

**Dominio previsto:** `https://alquilapro.sierraglobalcompany.com`  
**Estado actual:** F1 — Foundation + Installer **CLOSED y verificada en rama**; PR #1 permanece DRAFT / UNMERGED.  
**Stack objetivo:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + Alpine.js + PHPMailer.  
**Destino:** Hostinger, instalación web portable en subdominio o subcarpeta HTTPS.

---

# 1. LEER ESTO ANTES DE HACER CUALQUIER CAMBIO

Este `README.md` es el **punto de entrada obligatorio de continuidad** para ChatGPT, Codex, Antigravity o cualquier otra IA/persona que retome el proyecto.

No se debe reconstruir el proyecto desde memoria ni desde conversaciones anteriores si este repositorio está disponible.

## Orden de autoridad

1. **SPEC maestra:** [`docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`](docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md)
2. **Plan maestro:** [`docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`](docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md)
3. **Checkpoint actual:** [`docs/checkpoints/F1_CLOSED_2026-10-11.md`](docs/checkpoints/F1_CLOSED_2026-10-11.md)
4. Tests ejecutables.
5. Código real del repositorio.
6. Conversaciones externas solo como contexto auxiliar.

Si hay contradicción, **no se adivina**: se detiene la expansión, se audita la diferencia y se corrige la fuente correspondiente.

## Regla anti-duplicación documental

La SPEC y el PLAN **están anclados desde este README**, pero su contenido completo vive en sus archivos canónicos para evitar dos copias que luego diverjan. El README contiene el resumen operativo y el protocolo de recuperación; los archivos enlazados contienen el detalle completo.

---

# 2. LAS DOS LEYES SUPREMAS DEL PROYECTO

Estas dos leyes tienen prioridad sobre patrones, preferencias de framework y propuestas de nuevas funcionalidades.

## LEY 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe ser:

- **simple**;
- **sencilla**;
- **optimizada**;
- **eficiente**;
- **eficaz**;
- **rápida**;
- fácil de auditar;
- fácil de actualizar;
- fácil de mejorar;
- fácil de desplegar.

> Entre dos soluciones correctas, gana la que use menos piezas, menos estados, menos dependencias, menos capas y menos conocimiento implícito, siempre que conserve seguridad, trazabilidad y los requisitos funcionales.

KISS no significa código improvisado. Significa la **mínima arquitectura razonable**.

### Presupuesto base de arquitectura

- 1 aplicación PHP.
- 1 base de datos MariaDB/MySQL.
- 1 repositorio.
- 1 autenticación.
- 1 motor financiero.
- 1 motor de recordatorios.
- 1 sistema documental.
- 1 flujo de tickets/visitas.
- 1 instalador.
- 1 cron HTTP protegido.
- HTML renderizado en servidor.
- Alpine.js solo donde aporte interacción real.

No microservicios, Redis, workers, CQRS, event bus, SPA completa, capas genéricas o infraestructura futura sin necesidad demostrada.

## LEY 2 — CERO RUIDO / CONVERGENCIA

No se conserva ruido conocido ni ruido potencial demostrable.

Se considera ruido, entre otros:

- código muerto;
- duplicación;
- rutas duplicadas;
- tablas sin ciclo de vida propio;
- estados equivalentes;
- validaciones contradictorias;
- lógica repartida sin razón;
- capas que solo delegan;
- interfaces con una sola implementación sin motivo real;
- DTOs/mappers/factories innecesarios;
- dependencias sin uso;
- documentación obsoleta;
- flags temporales permanentes;
- módulos, botones o integraciones futuras que todavía no funcionan.

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Antes de crear algo nuevo se pregunta:

1. ¿Se puede eliminar la necesidad?
2. ¿Algo existente puede absorberla?
3. ¿Dos conceptos pueden unirse?
4. ¿Puede convergerse en una sola fuente de verdad?
5. ¿Puede simplificarse lo actual?
6. ¿Puede mejorarse sin nueva arquitectura?
7. Solo entonces: ¿realmente hay que agregar una pieza?

## Gate obligatorio de fase

Ninguna fase puede cerrarse con alguna ley en FAIL.

Cada checkpoint debe registrar como mínimo:

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
- tests/comandos/evidencia
```

---

# 3. QUÉ RESUELVE ALQUILA PRO

Alquila Pro centraliza la operación que normalmente termina dispersa entre WhatsApp, correo, Excel, memoria y carpetas.

Debe responder de forma rápida:

- qué propiedades/parqueaderos están disponibles u ocupados;
- quién es el inquilino;
- quién debe pagar y cuándo;
- qué pago está reportado y pendiente de conciliación;
- qué contrato vence;
- qué póliza requiere seguimiento;
- qué solicitud de mantenimiento está abierta;
- qué visita está programada;
- qué documentos e inventarios existen;
- qué obligaciones están pendientes al terminar un contrato;
- qué administrador realizó cada acción relevante.

La V1 **no** pretende ser un ERP contable, un SaaS multiempresa ni una plataforma masiva de inmobiliarias.

---

# 4. USUARIOS

## Propietario

Control total, configuración, administradores, permisos, contratos, candidatos, pagos, pólizas, liquidaciones, plantillas, auditoría y branding.

## Administrador

Permisos configurables sobre cartera, candidatos, contratos, solicitudes, pagos, documentos, pólizas y operación.

## Gestor

Rol operativo limitado para cobros, conciliación si se autoriza, seguimiento, agenda y contacto.

## Inquilino

Portal privado para ver contrato, propiedad/parqueadero, fechas, saldos, pagos, documentos, póliza cuando aplique, solicitudes, visitas, manual y perfil.

## Candidato

Portal limitado previo al contrato para datos, documentos, referencias y estudio de aseguradora.

---

# 5. UX / DISEÑO

Dirección: **Residential OS / PropTech premium**, moderna, limpia y humana.

## Administrador

Navegación inferior móvil:

**Inicio · Cartera · [APPS] · Solicitudes · Cuenta**

`APPS` es el launcher central elevado para funciones secundarias.

## Inquilino

**Inicio · Pagos · [APPS] · Solicitudes · Cuenta**

Las opciones son contextuales: un usuario de solo parqueadero no ve módulos de póliza/inventario de vivienda que no le aplican.

Principios visuales:

- mobile-first;
- tarjetas con jerarquía clara;
- fotos reales de propiedades;
- estados visibles;
- touch targets cómodos;
- cero tablas si una tarjeta resuelve mejor;
- cero gráficas decorativas;
- progressive disclosure;
- sin sidebar como navegación principal móvil.

---

# 6. FLUJO MAESTRO DEL NEGOCIO

```text
CANDIDATO
  ↓
Datos + referencias + documentos
  ↓
Estudio de aseguradora (SURA inicial)
  ↓
Decisión del propietario
  ↓
Generación de contrato
  ↓
Firma / notaría opcional / anexos
  ↓
Inventario inicial
  ↓
ACTIVAR ARRENDAMIENTO
  ↓
Cartera + pagos + póliza + servicios + solicitudes + convivencia
  ↓
Renovación o terminación
  ↓
Inventario final
  ↓
Liquidación
  ↓
Bono potencial si aplica
  ↓
CERRADO
```

---

# 7. MÓDULOS P0

- instalación web;
- autenticación;
- multiadministrador y permisos simples;
- branding configurable;
- propiedades/urbanizaciones;
- apartamentos;
- parqueaderos;
- candidatos;
- referencias y ocupantes;
- documentos de candidato;
- estudio de aseguradora;
- inquilinos;
- contratos y plantillas;
- PDF y snapshots contractuales;
- portal del inquilino;
- pólizas y reclamaciones;
- cartera y cargos;
- pagos y conciliación;
- medios de pago;
- Bre-B / QR;
- reporte de pago sin comprobante;
- servicios públicos;
- multas/cargos adicionales;
- documentos;
- inventario inicial/final;
- solicitudes/novedades;
- mantenimiento;
- agenda y visitas;
- recordatorios configurables;
- email SMTP;
- WhatsApp asistido sin API;
- manual de convivencia;
- incidentes de convivencia;
- orientación legal versionada;
- membresía/servicio digital configurable;
- bono potencial de buen cumplimiento;
- liquidación de terminación;
- auditoría;
- seguridad/privacidad;
- cron HTTP idempotente.

---

# 8. CONTRATOS

El contrato no es un PDF subido sin estructura. Se genera desde plantillas versionadas y datos existentes.

## Variables

Ejemplos:

```text
{{inquilino.nombre}}
{{inquilino.documento}}
{{arrendador.nombre}}
{{arrendador.documento}}
{{inmueble.direccion}}
{{contrato.canon}}
{{contrato.fecha_inicio}}
{{contrato.fecha_fin}}
```

El administrador insertará variables desde un selector visual; no tendrá que memorizarlas.

## Editor

Editor por bloques/cláusulas, no un “Word” dentro del navegador.

## Versionado

- editar una plantilla publicada crea nueva versión;
- un contrato generado queda como snapshot inmutable;
- cambiar la plantilla nunca modifica contratos anteriores.

## Estados

`Borrador → Revisión → Listo para firma → Firmado → Notaría opcional → Vigente → Renovación/Terminación → Terminado → Liquidado`

El sistema controlará tareas como firma, notaría, copia final, póliza e inventario.

---

# 9. PAGOS Y CARTERA

Motor financiero único.

## Cargos

- canon;
- administración;
- membresía/servicio real;
- servicio público;
- multa PH;
- reparación atribuible;
- otro.

Cada concepto conserva su naturaleza.

## Pagos

- completos;
- parciales;
- un pago puede aplicarse a varios cargos;
- conciliación humana en V1.

## Reportar pago sin comprobante

El inquilino puede informar:

- monto;
- fecha;
- hora aproximada/rango;
- destino;
- banco de origen opcional;
- observación;
- captura opcional.

Reportar no significa pagar automáticamente: queda **pendiente de conciliación**.

## Medios de pago

Configurables:

- cuenta bancaria;
- Llave Bre-B;
- QR Bre-B;
- billetera/depósito electrónico;
- otro medio manual.

Mostrar entidad, titular esperado, llave/número, copiar y descargar QR cuando aplique.

No almacenar PIN, claves bancarias, tokens ni credenciales.

## PSE

Futuro. Se deja un seam lógico en el motor financiero, pero **sin módulo vacío, tablas muertas ni botón falso en V1**.

---

# 10. RECORDATORIOS Y COMUNICACIONES

Un solo motor genérico de recordatorios.

Ejemplos:

- pago -5 días;
- pago -3 días;
- pago 0;
- contrato -60;
- póliza -30;
- visita -1 día;
- visita -3 horas.

El cron debe ser idempotente.

## Email

Automático vía SMTP.

## WhatsApp

Sin API en V1. El administrador pulsa un botón que abre WhatsApp/WhatsApp Business con destinatario y mensaje preparados.

No se registrará “enviado/entregado/leído” si el sistema no puede demostrarlo.

---

# 11. MANTENIMIENTO Y CONVIVENCIA

## Solicitudes

Ticket simple con categoría, prioridad, archivos, estado y responsable.

## Visitas

El administrador propone horarios; el inquilino selecciona uno; se generan recordatorios.

## Daños

Nunca se convierten automáticamente en deuda.

Clasificación:

- sin determinar;
- imputable al inquilino;
- deterioro normal;
- propietario/estructural;
- tercero;
- pendiente de diagnóstico.

Crear cargo exige acción explícita del administrador.

## Manual de convivencia

Versionado por propiedad. Una nueva versión no destruye la anterior.

## Incidentes

Registrar hechos objetivos: ruido, fiestas, zonas comunes, mascotas, seguridad u otros. No crear perfiles subjetivos de personas.

---

# 12. MEMBRESÍA Y BONO

## Membresía / servicio digital

Concepto comercial separado con servicio real, precio, vigencia y aceptación. Puede durar 6, 12, N meses o ligarse a vigencia contractual según configuración.

No es depósito ni garantía.

## Bono potencial de buen cumplimiento

Entidad separada de cartera y membresía.

Estados:

`Potencial → En evaluación → Aprobado/No causado → Pagado`

Puede considerar pagos, servicios, obligaciones, inventario, llaves y estado de entrega.

Viene desactivado por defecto hasta contar con redacción contractual revisada.

---

# 13. LEGAL / GUARDRAILS

La aplicación organiza y orienta; no reemplaza abogado, aseguradora ni autoridad.

Fuentes base de la SPEC:

- Ley 820 de 2003 — arrendamiento de vivienda urbana.
- Ley 1581 de 2012 — protección de datos.
- Ley 1801 de 2016 y normativa posterior de ruido/convivencia.
- Manual/reglamento aplicable de cada propiedad horizontal.
- Condiciones vigentes de SURA para el producto contratado.
- Reglas actuales de Bre-B del Banco de la República.

Guardrails:

- no disfrazar depósitos/garantías;
- no afirmar que una membresía es legal por el nombre;
- no afirmar cobertura SURA sin revisar póliza;
- no automatizar responsabilidad por daños;
- no automatizar terminación contractual;
- no modificar documentos firmados retroactivamente;
- contenido jurídico versionado y actualizable.

Antes de usar una plantilla contractual con dinero real, debe existir revisión profesional del modelo maestro.

---

# 14. SEGURIDAD Y DATOS

- HTTPS obligatorio;
- contraseñas con hash seguro;
- CSRF;
- escape XSS;
- PDO prepared statements;
- sesiones seguras;
- validación MIME/tamaño;
- nombres aleatorios de archivos;
- impedir ejecución PHP en uploads;
- documentos privados protegidos por autorización;
- tokens criptográficos;
- rate limiting básico;
- logs sin secretos;
- instalador bloqueado al terminar;
- trazabilidad de acciones relevantes.

---

# 15. INSTALADOR Y HOSTINGER

Despliegue previsto:

1. subir paquete a una carpeta/subdominio;
2. entrar por HTTPS;
3. diagnóstico PHP/extensiones/permisos;
4. configurar DB;
5. probar conexión;
6. ejecutar migraciones;
7. crear propietario;
8. branding;
9. SMTP;
10. medio de pago inicial;
11. mostrar URL de cron;
12. finalizar y bloquear installer.

Producción no dependerá de Node, npm, Composer, Redis, Docker, SSH obligatorio o procesos permanentes.

---

# 16. METODOLOGÍA DE DESARROLLO

## ChatGPT

Producto, arquitectura, especificación, UX, reglas, plan, auditorías, checkpoints y coordinación.

## Codex

Implementación, pruebas, refactors controlados, Git y ejecución de planes cuando aporte valor.

## Antigravity

Auditoría independiente, QA, verificación cruzada y búsqueda de huecos/regresiones.

Ninguno puede introducir requisitos nuevos sin actualizar primero la SPEC.

---

# 17. PLAN MAESTRO DE FASES

Detalle completo: [`docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`](docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md)

Roadmap:

- **F0:** spec y plan.
- **F1:** foundation + installer.
- **F2:** auth + usuarios + permisos.
- **F3:** shell móvil + sistema visual.
- **F4:** propiedades + unidades.
- **F5:** candidatos + referencias.
- **F6:** aseguradora/SURA.
- **F7:** contratos.
- **F8:** inquilino + portal.
- **F9:** cargos + cartera.
- **F10:** medios de pago.
- **F11:** reporte + conciliación.
- **F12:** servicios + obligaciones.
- **F13:** pólizas + reclamaciones.
- **F14:** solicitudes + mantenimiento.
- **F15:** agenda + visitas.
- **F16:** recordatorios + cron + email.
- **F17:** WhatsApp asistido.
- **F18:** documentos + manual + inventarios.
- **F19:** convivencia + contenido legal.
- **F20:** membresías/servicios.
- **F21:** bono + liquidación.
- **F22:** seguridad + privacidad.
- **F23:** dashboard final con datos reales.
- **F24:** QA PHP 8.3/8.4/8.5.
- **F25:** packaging `SERVER_READY`.
- **F26:** despliegue Hostinger + smoke test.

Cada fase: `SCOPE → PRE-KISS → RED → GREEN → CONVERGE/REFACTOR → QA → TWO LAWS GATE → CHECKPOINT`.

---

# 18. PROTOCOLO PARA RETOMAR EL PROYECTO EN OTRO CHAT / IA

Toda nueva sesión debe ejecutar esta secuencia antes de tocar código:

1. Leer este README completo.
2. Leer la SPEC maestra completa.
3. Leer el PLAN maestro completo.
4. Si existe, leer el checkpoint más reciente.
5. Revisar rama/HEAD/working tree real.
6. Revisar tests y CI reales.
7. Identificar la fase actual y el último gate cerrado.
8. Confirmar `LEY_1_KISS` y `LEY_2_CERO_RUIDO` del último checkpoint.
9. No repetir fases ya cerradas sin evidencia de regresión.
10. No asumir estado desde memoria del chat.
11. No abrir una fase futura si la anterior está abierta.
12. No hacer merge si el gate no está aprobado.

## Prompt mínimo de recuperación

> Audita el repositorio `sierraglobalcompany-rgb/AlquilaPro`. Lee primero `README.md`, luego la SPEC maestra, el PLAN maestro y el checkpoint más reciente. Recupera el estado real desde Git/archivos/tests, identifica la fase actual y continúa únicamente desde el primer gate abierto. Respeta obligatoriamente Ley 1 KISS y Ley 2 Cero Ruido/Convergencia. No introduzcas sobrearquitectura ni código futuro sin uso real.

---

# 19. ESTADO DEL PROYECTO

```text
PROJECT: Alquila Pro
REPO: sierraglobalcompany-rgb/AlquilaPro
TARGET: alquilapro.sierraglobalcompany.com
CURRENT_PHASE: F1 CLOSED ON FEATURE BRANCH
SPEC: V1.1 AUDITADA
PLAN: V1.1 AUDITADO
IMPLEMENTATION: F1 VERIFIED / PR #1 DRAFT / UNMERGED
LATEST_CHECKPOINT: docs/checkpoints/F1_CLOSED_2026-10-11.md
FUNCTIONAL_HEAD: 019231be590da614a75319b41fe122d4c33b8b03
FINAL_F1_QA: PHP 8.3 / 8.4 / 8.5 — 32 tests / 110 assertions
LEY_1_KISS: PASS F1
LEY_2_CERO_RUIDO: PASS F1
```

Siguiente gate: revisar/aprobar PR #1 y fusionar F1. **No abrir F2 antes del merge/aprobación de F1.**
