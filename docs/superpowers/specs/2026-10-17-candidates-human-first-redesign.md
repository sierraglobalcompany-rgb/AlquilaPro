# AlquilaPro — Candidatos Human-First Redesign

**Fecha:** 2026-10-17  
**Estado:** `OWNER_APPROVED`  
**Rama de diseño:** `design/candidates-human-first-baseline-20261017`  
**Base:** `main@f78d80f991334e2646aba1e6d5f8860b925ad88f`  
**Implementación:** **AUTORIZADA por el owner mediante el plan aprobado y sus gates**; esta spec conserva el contrato funcional/UX y no amplía el scope por sí sola.

---

## 1. Objetivo

Rediseñar el módulo administrativo **Candidatos** para que deje de comportarse visualmente como un expediente/Excel infinito y pase a ser una experiencia moderna, humana, clara y orientada a decisiones, manteniendo el backend, permisos y flujos reales de AlquilaPro.

El resultado debe permitir que una persona autorizada pueda, en pocos segundos:

1. identificar al candidato;
2. entender el estado real de la solicitud;
3. localizar la información necesaria sin leer todo el expediente;
4. revisar evidencia por contexto;
5. ejecutar únicamente acciones que el backend realmente permita;
6. continuar el proceso sin lenguaje técnico ni información inventada.

---

## 2. Autoridad y leyes cargadas

```text
HUMAN_FIRST_LAW_LOADED=YES
CURRENT_CHECKPOINT_LOADED=YES
TRUTH_OWNER_VERIFIED=YES
AUTHORIZATION_MODEL_VERIFIED=YES
TARGET_VIEWPORTS_DEFINED=YES
PROJECT_DESIGN_MASTER_LOADED=NO — governance gap conocido
PAGE_OVERRIDE_LOADED=NOT_PRESENT
UI_TASK_SCOPE=CANDIDATES MODULE REDESIGN SPEC
```

Jerarquía aplicada:

```text
TRUTH + SECURITY + ACCESSIBILITY
> ERP HUMAN-FIRST MASTER V2.1
> esta spec aprobada
> mockups visuales
> preferencias visuales ad-hoc
```

Leyes obligatorias:

- **KISS**: A → B; sin SPA ni arquitectura paralela.
- **Cero Ruido**: eliminar/unir/simplificar antes de agregar.
- **Anti-parche**: causa raíz antes de tratamiento.
- **Human-First**: persona > modelo de datos.
- **No Infinite Excel**.
- **No single-record-as-table** como patrón dominante.
- **No every-field-visible**.
- **No raw machine language** como UX primaria.
- **No acciones, estados, métricas ni relaciones inventadas**.
- **Browser real obligatorio** antes de GREEN visual.

---

## 3. Problema actual

### 3.1 `main`

`resources/views/candidate_detail.php` presenta casi todo el expediente como una secuencia vertical de `<section>` + `<dl>`, mezclando:

- identidad;
- contacto;
- unidad;
- trabajo;
- ocupantes;
- mascotas;
- referencias;
- documentos;
- aseguradora;
- contratos;
- administración.

La página es técnicamente correcta, pero obliga a la persona a inspeccionar demasiados campos seguidos y replica la estructura interna del modelo.

### 3.2 PR #43

PR #43 convirtió siete bloques secundarios en `<details>` cerrados. Eso redujo longitud y pasó CI/Browser QA, pero el owner rechazó la dirección visual porque seguía siendo esencialmente el mismo expediente apilado.

Por tanto:

```text
PR_43 = DRAFT_DO_NOT_MERGE
TECHNICAL_EVIDENCE = PRESERVE
VISUAL_PATTERN = SUPERSEDED
```

La lección es importante: **colapsar un Excel no lo convierte automáticamente en una experiencia humana**.

---

## 4. Dirección visual aprobada

El owner aprobó el lenguaje visual de los ocho mockups generados el 2026-10-17:

1. Solicitud
2. Información laboral
3. Ocupantes y mascotas
4. Referencias
5. Documentos
6. Estudio de aseguradora
7. Contratos
8. Administrar

Pack visual persistente en ChatGPT Library:

```text
/AlquilaPro/Design/Candidatos/2026-10-17/
```

Manifiesto repo:

```text
docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md
```

### 4.1 Qué SÍ es autoridad visual

- sidebar limpia;
- header único del candidato;
- avatar por iniciales derivadas del nombre;
- estado visible como badge humano;
- navegación contextual por secciones;
- fondo neutro;
- cards con propósito distinto;
- espacios amplios;
- jerarquía tipográfica fuerte;
- verde AlquilaPro como acento;
- estados legibles;
- iconografía secundaria;
- desktop limpio y modular;
- evitar una pared uniforme de filas/campos.

### 4.2 Qué NO es autoridad funcional

Los mockups son **dirección visual**, no definición de backend.

No se implementarán sin contrato funcional previo:

- `Editar candidato` desde admin;
- `Enviar mensaje`;
- score de referencias;
- ratings/estrellas;
- historial de llamadas;
- notas del evaluador;
- score 82/100 de aseguradora;
- riesgo bajo/alto calculado;
- capacidad de pago calculada;
- historial crediticio;
- historial laboral múltiple;
- otros ingresos;
- antigüedad laboral calculada si no existe fecha real;
- ficha individual de cada ocupante;
- nombre/raza/edad/vacunación de mascota;
- firmas contractuales no soportadas;
- pre-aprobar/aprobar/rechazar candidato si no existe transición real;
- notas internas e historial administrativo si no existe owner factual.

**Una imagen bonita nunca derrota la verdad del backend.**

---

## 5. Usuarios y permisos

### Usuario primario

Persona administrativa/gestora con permiso:

```text
candidates.view
```

### Acciones de gestión

Requieren:

```text
candidates.manage
```

Actualmente incluye, según las rutas reales:

- crear/actualizar estudio de aseguradora;
- ejecutar transiciones válidas del candidato;
- regenerar enlace de continuación.

### Contratos

La sección Contratos sólo existe si el usuario tiene:

```text
contracts.view
```

La capacidad de crear borrador no se presume: se obtiene de:

```text
ContractService::canCreateDraftForCandidate()
```

No mostrar controles muertos ni teasers de acciones sin permiso.

---

## 6. Owners de verdad

| Dominio | Owner real |
|---|---|
| candidato, estado, identidad, contacto, trabajo, ocupantes, mascotas, referencias | `CandidateService` |
| requisitos/documentos del candidato | `CandidateDocumentService` |
| estudio de aseguradora | `ScreeningService` |
| contratos y capacidad para crear borrador | `ContractService` |
| permisos | `Permissions` + guardas de rutas |
| etiquetas humanas existentes | `HumanLabels` |
| render SSR | `View` + `resources/views/*` |

No crear `CandidateDashboardService`, `CandidatePresenterService`, API nueva ni segundo owner de dominio sólo para satisfacer el diseño.

---

## 7. Arquitectura UX elegida

### Decisión

Usar **una ruta SSR existente + una sección activa**:

```text
GET /admin/candidates/{id}?section=<key>
```

No crear ocho endpoints si el mismo controller ya posee la verdad necesaria.

### Keys permitidas

```text
overview
work
household
references
documents
screening
contracts
manage
```

`overview` es default.

### Por qué

- KISS;
- cero SPA;
- casi cero JS;
- URL compartible;
- back/forward del navegador correcto;
- sólo se renderiza el bloque activo;
- reduce DOM, scroll y ruido;
- conserva un solo endpoint/owner;
- permite Browser QA aislado por sección.

### Regla

En desktop/tablet la navegación puede mostrarse como tabs/enlaces contextuales.

En mobile no se permitirá una barra horizontal interminable. Preferencia SSR/nativa:

- sección activa visible;
- `<details>`/menú compacto de “Secciones” con enlaces;
- sin dependencia JS para navegación básica.

---

## 8. Shell único del candidato

El header global se muestra una sola vez y persiste entre secciones.

Debe contener únicamente hechos globales de alta utilidad:

1. volver a Candidatos;
2. avatar por iniciales derivadas;
3. nombre completo;
4. estado humano del candidato;
5. inmueble + unidad usando `property_name` y `unit_code`/`unit_id`;
6. navegación contextual disponible según permisos/capabilities.

### No repetir en el shell

- teléfono;
- correo;
- dirección actual;
- documento;
- fecha de nacimiento;
- timestamps;
- datos laborales.

Esos hechos viven en su sección correspondiente.

### No mostrar hoy

- dirección del inmueble si la ruta actual no la entrega;
- editar candidato;
- enviar mensaje;
- menú de acciones ficticias.

---

## 9. Contrato por sección

### 9.1 Solicitud — `overview`

**Job:** entender quién es la persona, qué unidad solicita y en qué punto real está.

Mostrar:

- estado humano;
- documento;
- teléfono;
- correo;
- fecha de nacimiento si existe;
- dirección actual si existe;
- inmueble/unidad;
- fecha de creación;
- fecha de envío si existe;
- última actualización si aporta contexto.

Composición recomendada:

1. **Resumen de solicitud** — identidad/contacto esencial.
2. **Estado actual** — badge + explicación humana basada exclusivamente en estado real.
3. **Unidad solicitada** — propiedad + unidad, sin datos inmobiliarios inventados.
4. **Actividad temporal** — fechas relevantes agrupadas, no como 4 tarjetas repetidas.

No mostrar “progreso 80%” ni un “siguiente paso” automático salvo que exista una regla determinista, auditada y testeada.

### 9.2 Información laboral — `work`

**Job:** revisar la información laboral declarada, sin inferir solvencia.

Hechos reales disponibles:

- `employment_status`;
- `employer_name`;
- `occupation`;
- `monthly_income`;
- `employment_notes`.

Composición:

- una card principal “Información laboral”;
- ingreso mensual destacado sólo si existe, formateado para persona;
- notas como bloque secundario;
- empty state claro si no se declaró información.

No inventar sector, área, contrato laboral, fecha de ingreso, antigüedad, otros ingresos, perfil sólido o nivel de riesgo.

### 9.3 Ocupantes y mascotas — `household` (contrato integral ratificado 2026-10-23)

**Autoridad de diseño:** `03_OCUPANTES_MASCOTAS.png`, 1448×1086. El owner rechazó la simplificación a tres cards y ratificó **implementar completamente** ocupantes y mascotas individuales, fotografías reales y manual de convivencia. Este apartado prevalece sobre las antiguas exclusiones de datos ausentes **solo para CAND-03**. No alterar el PNG ni fingir contenido para imitar sus cifras.

**Objetivo humano:** desde el wizard, el postulante puede declarar el hogar completo, agregar/editar/eliminar otros ocupantes y una o varias mascotas, adjuntar fotografías opcionales, revisar y enviar; el administrador ve información veraz, puede gestionarla con autorización y consultar el manual pertinente, sin duplicar al solicitante ni cruzar propiedades.

**Seis regiones obligatorias** del cuerpo `section=household`:
1. **Resumen del hogar**, dominante y numérico: ocupantes según cantidad declarada, mascotas según filas efectivas, y contexto factual sin «hogar estable».
2. **Estado de información** lateral: completitud/pendientes verdaderos, sin riesgo, score, «sin alertas» ni verificación automática.
3. **Ocupantes del hogar**: solicitante principal proveniente de `candidates` (una sola autoridad) más fichas de otros ocupantes reales, edad aproximada *opcional*, vínculo y ocupación/actividad opcional. Imagen personal opcional cuando proceda y haya consentimiento; nunca requerir fotos de menores ni documentos excesivos. Acciones agregar/editar/retirar efectivas y autorizadas.
4. **Mascotas**: una ficha por mascota real con nombre, especie, raza/tamaño/edad aproximada opcionales, observaciones, **foto privada opcional por cada mascota**, vacunación solo como *declaración explícita* opcional, nunca «verificada» sin respaldo. Acciones reales según rol. Estado sin mascotas y fotos no aportadas.
5. **Reglas de convivencia y observaciones**: manual real del inmueble (`DocumentService`, versionado, archivo PDF o JSON) solo con permisos `candidates.view` + `properties.view` + `documents.view`; texto descriptivo del solicitante separado y escapado. Mostrar ausencia honesta y no inferir que está permitido/prohibido tener mascotas.
6. **Próximos pasos**: lista contextual verificable y enlaces/acciones existentes, sin CTAs muertos.

**Modelo mínimo nuevo** (nombrar migración con siguiente índice libre, sin modificar migrations aplicadas):
- `candidate_household_occupants`: PK; FK `candidate_id`; `full_name`, `relationship`, `age_years` opcional, `occupation_or_activity` opcional, foto opcional privada con metadatos validados, auditoría de fecha. **No duplicar** al candidato principal como fila.
- `candidate_pets`: PK; FK `candidate_id`; `name`, `species`, `breed`, `size_category`, `age_years`, `vaccination_declaration`, `notes`, foto privada opcional con metadatos de integridad, fechas. Solo nombre/especie obligatorios; vacunación `not_provided` es diferente de vacunación comprobada.
- `candidates.pets_answered_at NULL`: indica respuesta **explícita**, sin reinterpretar los ceros históricos de `pets_declared NOT NULL DEFAULT 0`. Nuevas selecciones Sí/No obligatorias al completar el wizard. Guardar la respuesta, fecha y cambios consistentemente. Un `pets_declared=true` histórico es una señal afirmativa, un falso histórico sin marca es **no confirmado**. Sin migración masiva inventando consentimiento.
- Conservar `occupants_count` para historiales; para capturas nuevas aclarar en el formulario que **incluye al solicitante**, registrar además las fichas de los adicionales y señalar diferencias pendientes, sin crear personas automáticas ni normalizar conteos históricos a ciegas. Validar cardinalidad cuando un envío nuevo exige ficha de cada ocupante.
- **Servicio único del dominio household:** preferir owner focal para entidades y fotos, integrado con `CandidateService`, `CandidateDocumentService` y rutas existentes; no nuevo framework, APIs paralelas, SPA ni motor documental duplicado. Reutilizar guardas de seguridad y almacenamiento privado, sin usar `unit_media` de propiedad para fotografías personales.

**Flujos autorizados:** en el wizard existente, editar fichas/fotos sólo con token válido y estado editable; administración sólo con `candidates.manage` para mutaciones y `candidates.view` para lectura. CSRF en cada POST, validación de pertenencia candidato→ocupante/mascota/foto, formatos/bytes/MIME/firma/dimensiones, rutas privadas sin exposición pública, limpieza y reversibilidad de reemplazos, auditoría administrativa. Roles sin permiso ven lectura permitida, sin CTA activo; 403/404 en accesos indebidos. Después de enviar, el solicitante no edita mediante token; el administrador autorizado sí.

**Documento de convivencia:** `documents.type=manual_convivencia` es hoy JSON de propiedad; permitir **también PDF privado** del mismo tipo mediante el servicio documental y sus controles/versionado ya existentes, no tratar un `property_document` cualquiera como manual. Mostrar solo documentos de `candidate.unit_id → units.property_id` autorizado. La interpretación/restricción jurídica no se automatiza.

**Estados obligatorios**: solicitud histórica, borrador nuevo no contestado, Sí/No explícitos, 0/1/N mascotas, 0/1/N fichas adicionales, foto ausente/presente/reemplazada, manual ausente/actualizado, rol solo lectura/administrador, errores/guardado, móvil hasta el final de scroll. No afirmar PASS global por CI sin Browser QA de seis regiones.

**Diseño:** desktop retícula visual equivalente a tres filas de ancho asimétrico del PNG; mobile stack de seis cards, tipografía, iconografía y estados propios, navegación/foco visibles. El shell global conserva dueño aparte. `UX_VISUAL` y `FUNCIONAL` son gates separados.

### 9.4 Referencias — `references`

**Job:** consultar las referencias declaradas.

Por referencia real:

- tipo;
- nombre;
- relación;
- teléfono;
- correo.

Cada referencia puede ser una card clara, pero no una tabla.

No implementar todavía:

- score;
- rating;
- recomendado/no recomendado;
- contactada/no contactada;
- historial de intentos;
- notas de evaluador;
- botón de llamada con tracking.

### 9.5 Documentos — `documents`

**Job:** saber qué documentos se recibieron y poder abrirlos.

Verdad disponible hoy en admin:

- documentos efectivamente adjuntos;
- requisito/label asociado;
- nombre original;
- ruta privada de apertura.

La vista admin actual no recibe el catálogo completo de requisitos faltantes. Por tanto, en el primer GREEN sólo se muestran documentos existentes con un empty state si no hay ninguno.

Si luego se quiere “faltante / recibido / obligatorio”, deberá ampliarse explícitamente el truth contract usando `CandidateDocumentService::listRequirementsForUnit()`; eso será un microbloque separado y testable.

No inventar “en revisión”, checklist del revisor, aprobación de sección ni carga admin si no existe ruta.

### 9.6 Estudio de aseguradora — `screening`

**Job:** gestionar o consultar el estudio real.

Mostrar según permisos/estado:

- proveedor;
- producto;
- estado humano;
- correo de contacto;
- URL del portal si existe;
- resultado;
- notas internas existentes del proceso;
- radicado/soporte;
- fechas reales disponibles.

Cuando `candidates.manage` y las reglas del servicio lo permitan, usar el formulario real de crear/actualizar.

No inventar score, riesgo, capacidad de pago, historial crediticio ni ETA de aseguradora.

### 9.7 Contratos — `contracts`

**Job:** ver los contratos reales del candidato y abrirlos.

Sólo si `contracts.view`.

Mostrar:

- contratos reales de `ContractService::listForCandidate()`;
- estado real;
- plantilla/nombre disponible;
- unidad;
- acción “Abrir contrato”.

Mostrar “Crear contrato” sólo si:

```text
can_create === true
```

No duplicar dentro de Candidatos el módulo completo de Contratos.

No inventar firma, historial de firma, recordatorios o aprobaciones.

### 9.8 Administrar — `manage`

**Job:** ejecutar acciones administrativas reales de alto riesgo.

Sólo si `candidates.manage`.

Mostrar:

1. estado actual;
2. transiciones realmente permitidas;
3. regenerar enlace de continuación.

Transiciones reales actuales:

```text
new              -> incomplete | withdrawn
incomplete       -> withdrawn
verification     -> pending_screening | withdrawn
pending_screening-> withdrawn
withdrawn        -> none
```

Copy debe usar `HumanLabels` y explicación humana.

No mostrar pre-aprobar/aprobar/rechazar candidato, historial de actividad, notas internas generales o archivar hasta que exista backend factual.

---

## 10. Regla de no redundancia

Un hecho tiene **un hogar principal**.

```text
Nombre + estado + unidad -> shell
Identidad/contacto       -> Solicitud
Trabajo                  -> Información laboral
Hogar/mascotas           -> Ocupantes y mascotas
Referencias              -> Referencias
Archivos                 -> Documentos
Aseguradora              -> Estudio de aseguradora
Contratos                -> Contratos
Acciones sensibles       -> Administrar
```

Se permite repetir un dato únicamente cuando es imprescindible para contextualizar una acción crítica.

Ejemplo válido:
- estado actual puede repetirse en “Administrar” porque condiciona las transiciones.

Ejemplo inválido:
- repetir teléfono, correo y dirección en Solicitud, Referencias, Contratos y Administrar.

---

## 11. Presupuesto de densidad visual

Por viewport desktop:

- 1 foco principal claro;
- máximo 1 CTA primario visible por sección;
- evitar más de 3–4 grandes superficies simultáneas sin necesidad;
- no crear “10 cards iguales”;
- no convertir cada dato en KPI;
- no usar charts decorativos;
- agrupar datos relacionados por significado;
- espacio en blanco comunica relación y prioridad.

Una pantalla debe responder:

```text
¿Dónde miro primero?
¿Qué significa el estado?
¿Qué puedo hacer aquí?
¿Qué información es secundaria?
```

sin leer todo el DOM.

---

## 12. Copy Human-First

Debe ser:

```text
breve
específica
accionable
sin jerga
```

### Ejemplos

Preferir:

- `En verificación`
- `Pendiente de estudio`
- `No hay documentos adjuntos`
- `Abrir documento`
- `Crear estudio`
- `Actualizar estudio`
- `Regenerar enlace`

Evitar como UI primaria:

- `pending_screening`
- `in_progress`
- IDs internos;
- timestamps crudos cuando una fecha humana basta;
- copy de dominio inventada como “Perfil sólido” o “Riesgo bajo”.

### Fechas y dinero

En UI humana:

- fechas legibles para Colombia;
- dinero legible en COP cuando corresponda;
- raw values pueden permanecer en datos/auditoría, no como presentación primaria.

---

## 13. Estados de experiencia

Cada sección debe definir:

### Empty
Ejemplos:

- `No se registró información laboral.`
- `No hay referencias registradas.`
- `No hay documentos adjuntos.`
- `No hay estudio de aseguradora registrado.`
- `No hay contratos registrados para este candidato.`

### Unknown
No convertir ausencia en `0`, `No` o “Aprobado”.

### Permission
La sección/acción no autorizada no aparece.

### Error
Errores de operación deben ser humanos y no stack traces.

### Loading
SSR normal no necesita skeleton artificial; feedback sólo donde exista operación asíncrona real.

---

## 14. Responsive

Viewports obligatorios de QA:

```text
Desktop  1440×900
Tablet    768×1024
Mobile    375×812
```

### Desktop

- shell + navegación contextual horizontal;
- composición de 1–2 columnas cuando ayude;
- máximo ancho legible;
- sin pared de cards pequeñas.

### Tablet

- cards principales apilables;
- navegación debe conservar labels sin clipping;
- acciones no deben salir del viewport.

### Mobile

- una columna;
- header compacto;
- menú nativo de secciones en lugar de ocho tabs horizontales;
- sin scroll horizontal de página;
- target de interacción cómodo;
- CTA primario de ancho útil;
- datos secundarios debajo, no comprimidos lado a lado.

---

## 15. Accesibilidad

Obligatorio:

- landmarks semánticos;
- `<nav aria-label="Secciones del candidato">`;
- `aria-current="page"` o equivalente en sección activa;
- foco visible;
- navegación completa con Tab/Shift+Tab/Enter/Space;
- status no comunicado sólo por color;
- botones/links con nombres claros;
- forms con labels reales;
- errores asociados a la acción;
- contraste suficiente;
- no focus trap.

---

## 16. Pantalla de listado `Candidatos`

El listado forma parte del módulo, pero **no debe absorber el primer microbloque**.

Se conserva funcionalmente:

```text
GET /admin/candidates
```

En una fase posterior del mismo rediseño se alineará visualmente con la nueva baseline:

- header limpio;
- cards/listado de candidatos más escaneable;
- nombre + estado + unidad como foco;
- contacto secundario;
- una acción principal `Ver candidato`.

No implementar todavía filtros, búsqueda, paginación o KPIs porque eso pertenece a HF-P2A y requiere su propio contrato.

---

## 17. Estrategia de implementación por microbloques

No implementar las ocho secciones de una vez.

Orden propuesto:

```text
CAND-01 Shell + navegación + Solicitud
CAND-02 Información laboral
CAND-03 Ocupantes y mascotas
CAND-04 Referencias
CAND-05 Documentos
CAND-06 Estudio de aseguradora
CAND-07 Contratos
CAND-08 Administrar
CAND-09 Lista Candidatos — alineación visual, sin HF-P2A
```

Cada bloque:

```text
TRUTH
→ UX CONTRACT
→ RED estructural/factual
→ MINIMAL GREEN
→ focal tests
→ full CI
→ real Browser QA
→ Human-First audit
→ checkpoint
```

No abrir el siguiente microbloque si el anterior no está cerrado.

---

## 18. Primer microbloque autorizado DESPUÉS del plan

### CAND-01 — Shell + navegación + Solicitud

Superficie esperada mínima:

- `resources/views/candidate_detail.php`;
- `app/Candidates/routes.php` sólo si es necesario pasar `section`/truth de forma mínima;
- `assets/app.css` usando tokens/primitives existentes;
- tests focales de Candidates.

Preferencias:

1. reutilizar layout/app shell existente;
2. reutilizar tokens existentes;
3. no crear framework de componentes;
4. no crear JS si HTML/SSR resuelve navegación;
5. no migraciones;
6. no nuevas tablas;
7. no nuevo service de presentación.

---

## 19. RED / aceptación estructural para CAND-01

Antes de GREEN, tests deben demostrar al menos:

1. `overview` es sección default;
2. navegación Human-First existe;
3. sección activa usa estado semántico;
4. sólo el contenido de la sección seleccionada se renderiza como cuerpo principal;
5. Contracts no aparece sin `contracts.view`;
6. Administrar no aparece sin `candidates.manage`;
7. no aparecen acciones ficticias `Editar candidato` / `Enviar mensaje`;
8. estado usa label humano;
9. no se imprimen raw codes como foco principal;
10. funcionalidades existentes no desaparecen de sus secciones autorizadas.

No testear píxeles frágiles.

---

## 20. Browser QA para cada microbloque

Obligatorio:

- 1440×900;
- 768×1024;
- 375×812;
- consola sin errores relevantes;
- sin overflow horizontal;
- teclado/foco;
- navegación de sección real;
- links/forms reales;
- estados vacíos;
- rol con `candidates.view` solamente;
- rol con `candidates.manage`;
- contracts visibility según permiso;
- screenshot de primera capa y sección activa.

La QA debe evaluar **comprensión humana**, no sólo si la página carga.

---

## 21. Definition of Done del módulo

```text
AUTHORITY_LOADED=YES
UX_CONTRACT_DEFINED=YES
TRUTH_VERIFIED=YES
NO_FAKE_CAPABILITIES=YES
NO_REDUNDANT_PRIMARY_FACTS=YES
NO_INFINITE_EXCEL=YES
COPY_HUMAN=GREEN
AUTOMATED_QA=GREEN
BROWSER_QA=GREEN
ACCESSIBILITY=GREEN
RESPONSIVE=GREEN
HUMAN_COMPREHENSION=GREEN
CHECKPOINT=PERSISTED
```

Hasta entonces:

```text
CANDIDATES_HUMAN_FIRST_REDESIGN = NOT_FINISHED
```

---

## 22. Fuera de alcance

En este rediseño NO abrir:

- HF-P1E Obligations;
- tenant portal IA;
- HF-P2A paginación/filtros;
- R3;
- R4;
- deployment;
- mensajería nueva;
- edición admin completa de candidatura;
- scoring/risk engines;
- CRM de referencias;
- firmas nuevas;
- historial administrativo nuevo;
- modelo estructurado de ocupantes/mascotas.

Si el producto necesita esas capacidades después, cada una tendrá truth contract y gate independiente.

---

## 23. Decisión del owner

Esta spec formaliza la dirección aprobada visualmente y elimina de los mockups todo lo que no está respaldado por el backend actual.

**Decisión del owner:** `APPROVED` el 2026-10-17.

El plan detallado por microbloques fue creado y aprobado posteriormente. La implementación ya inició; el estado operativo vivo se mantiene en `docs/CURRENT_CHECKPOINT.md`, no en esta spec.

```text
SPEC_STATUS=OWNER_APPROVED
OWNER_APPROVED_DATE=2026-10-17
IMPLEMENTATION_STARTED=YES
IMPLEMENTATION_AUTHORITY=APPROVED_PLAN_PLUS_OWNER_GATES
PR_43=DRAFT_DO_NOT_MERGE
R3=FROZEN
R4=NOT_STARTED
DEPLOYMENT=NOT_AUTHORIZED
```
