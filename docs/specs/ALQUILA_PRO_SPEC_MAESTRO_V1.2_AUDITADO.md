# ALQUILA PRO — SPEC MAESTRA V1.2 AUDITADA

**Fecha:** 2026-10-11 (America/Bogota)  
**Estado:** revisión arquitectónica integral posterior a F3  
**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Producto:** aplicación web privada + superficie pública de catálogo, mobile-first y autoinstalable, para administración directa de arrendamientos de vivienda y parqueaderos.

---

# 0. AUTORIDAD Y MOTIVO DE V1.2

Esta SPEC sustituye a V1.1 como fuente funcional/técnica principal.

Motivo de V1.2:

- F1, F2 y F3 ya están implementadas y fusionadas;
- se congeló autenticación única para propietarios, administradores, gestores e inquilinos;
- se incorpora una superficie pública del dominio con landing + catálogo de unidades disponibles;
- el catálogo debe permitir descubrir apartamentos y parqueaderos, ver precio, fotos, descripción, ubicación pública y requisitos;
- el CTA `Aplicar` debe conducir al mismo flujo de candidatos de la aplicación, sin crear un CRM paralelo;
- se reaudita todo el roadmap bajo Ley 1 KISS y Ley 2 Cero Ruido/Convergencia.

Plan asociado:

[`../plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`](../plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md)

Punto de entrada del repositorio: `README.md`.

Orden de autoridad:

`SPEC V1.2 → PLAN V1.2 → decisiones canónicas README → SPEC/PLAN de fase → checkpoint más reciente → tests/CI → código real`

V1.1 queda histórica. Una nueva IA no debe volver a V1.1 salvo para auditoría histórica.

---

# 1. CONSTITUCIÓN TÉCNICA

## LEY SUPREMA 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe ser simple, optimizada, eficiente, eficaz, rápida, fácil de auditar, actualizar, mejorar y desplegar.

> Entre dos soluciones correctas se elige la que use menos piezas, menos estados, menos dependencias, menos tablas y menos capas, siempre que conserve seguridad, trazabilidad e integridad.

Falla KISS si se introducen sin necesidad probada:

- microservicios;
- Redis/workers/event bus/CQRS;
- contenedores obligatorios;
- repositorios/factories/interfaces/DTOs sin variación real;
- SPA completa;
- CMS paralelo para el catálogo;
- auth separado por tipo de usuario;
- tablas separadas para apartamento/parqueadero con el mismo ciclo de vida;
- tabla `catalog_listings` que duplique unidades;
- tabla `applications/leads` que duplique candidatos.

## LEY SUPREMA 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Ruido incluye código muerto, duplicación, rutas repetidas, dos fuentes de precio/disponibilidad, dos logins, tablas sin ciclo de vida propio, estados equivalentes, documentos obsoletos, botones futuros y módulos vacíos.

## Gate obligatorio de fase

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

Un FAIL bloquea cierre y merge.

## Presupuesto de arquitectura V1

- 1 aplicación PHP;
- 1 BD MariaDB/MySQL;
- 1 repositorio;
- 1 auth;
- 1 modelo de propiedades/unidades;
- 1 fuente de disponibilidad/precio comercial por unidad;
- 1 flujo de candidatos/aplicaciones;
- 1 motor financiero;
- 1 motor de recordatorios;
- 1 sistema documental;
- 1 flujo de tickets/visitas;
- 1 installer;
- 1 cron HTTP protegido;
- HTML server-side;
- JS mínimo; Alpine solo con necesidad real.

---

# 2. VISIÓN

Alquila Pro centraliza propiedades, unidades, catálogo público, candidatos, estudio de aseguradora, contratos, inquilinos, cartera, pagos, pólizas, servicios, solicitudes, visitas, convivencia, inventarios, documentos y cierre contractual.

Debe resolver dos contextos con la misma aplicación:

1. **Público:** descubrir disponibilidad real y comenzar una aplicación.
2. **Privado:** operar administración e inquilinos autenticados.

No es un ERP contable, SaaS multiempresa, portal inmobiliario masivo ni marketplace de terceros en V1.

Escala objetivo inicial: cartera pequeña/mediana del propietario (aprox. 10 propiedades/unidades de referencia), pero sin límites codificados artificialmente.

---

# 3. STACK

- PHP 8.3 base; compatible 8.4 y 8.5.
- Slim 4.
- MariaDB/MySQL.
- PDO.
- HTML server-side.
- CSS mobile-first.
- JS mínimo.
- Alpine.js solo si aporta interacción real.
- PHPMailer/SMTP cuando corresponda.
- Composer local; `vendor/` incluido en paquete final.
- PHPUnit.
- PDF tras spike específico.

Producción NO requiere Node, npm, Redis, Docker, SSH obligatorio, workers ni colas externas.

---

# 4. BRANDING, INSTALACIÓN Y DOMINIO

Nombre inicial: **Alquila Pro**.

Branding configurable:

- nombre;
- logo;
- color primario;
- favicon cuando se implemente;
- datos de empresa/administración cuando exista necesidad real;
- textos de contacto.

Dominio público y privado comparten instalación:

`alquilapro.sierraglobalcompany.com`

El instalador funciona en subdominio o subcarpeta HTTPS.

No se crea un segundo sitio para catálogo.

---

# 5. SUPERFICIE PÚBLICA Y UX

## 5.1 Landing instalada

Cuando la aplicación está instalada y el visitante **no** tiene sesión:

`GET /` muestra una landing pública simple, con al menos:

- marca;
- botón **Iniciar sesión** → `/login`;
- botón **Catálogo** → `/catalog`;
- texto de contacto/configuración mínima cuando exista.

Cuando hay sesión válida, `GET /` puede seguir mostrando el shell privado autenticado. Esto evita crear `/app` solo para separar contextos.

Antes de instalación, `/` conserva el comportamiento del installer.

## 5.2 Catálogo público

El catálogo muestra **unidades rentables**, no una copia de la entidad propiedad.

Filtros/tabs mínimos:

- Apartamentos;
- Parqueaderos;
- ciudad/sector solo si existe más de una opción útil.

Cada tarjeta puede mostrar:

- foto principal;
- título público;
- tipo;
- ciudad/sector;
- precio mensual publicado;
- administración separada si aplica;
- resumen breve;
- estado visible solo como `Disponible` cuando realmente pueda mostrarse.

Detalle público:

- galería de fotos de marketing;
- descripción;
- características relevantes;
- precio mensual;
- administración si aplica;
- requisitos públicos resumidos;
- ubicación pública (propiedad + ciudad/sector; no exponer notas internas);
- enlace externo de video opcional;
- CTA `Aplicar` cuando F5 esté implementada y la unidad siga disponible/publicada.

## 5.3 Regla de publicación

No existe tabla `catalog_listings`.

El catálogo es una **proyección de `units`**.

Una unidad aparece públicamente solo si:

- `is_public = true`;
- estado = `Disponible`;
- propiedad activa;
- unidad activa;
- tiene datos públicos mínimos válidos.

Si pasa a `En proceso`, `Ocupada`, `Inactiva` o `Mantenimiento`, sale automáticamente del catálogo sin mantener un segundo campo de “disponibilidad de catálogo”.

## 5.4 Videos

V1 no sube/transcodifica video al hosting.

Se permite una URL HTTPS de video externo por unidad. Preferencia: YouTube/Vimeo u otro enlace configurado y seguro. No autoplay.

Razón KISS: subir video obliga streaming/range/transcoding/storage/bandwidth y no aporta suficiente valor para V1.

## 5.5 Dirección pública

Por defecto el catálogo muestra propiedad, ciudad y sector. Dirección exacta, número de apartamento, notas internas y datos sensibles no se publican por accidente.

---

# 6. AUTENTICACIÓN ÚNICA Y ROLES

## 6.1 Regla absoluta

Propietario, administrador, gestor e inquilino usan **el mismo login, sesión, CSRF, logout, cambio y recuperación**.

No existe:

- `/tenant-login`;
- `/admin-login`;
- segundo `AuthService`;
- segunda tabla de sesiones;
- recuperación duplicada.

La diferencia ocurre después de autenticar, según rol, permisos, estado y contexto.

## 6.2 Propietario

Control total. No se elimina/desactiva el último propietario activo.

## 6.3 Administrador

Permisos simples/configurables.

## 6.4 Gestor

Operación limitada según permisos.

## 6.5 Inquilino

Portal privado contextual. F8 crea/activa su usuario reutilizando auth F2.

Credencial inicial prevista: documento como identificador y contraseña inicial igual al documento, con cambio obligatorio. Debe revisarse seguridad/UX al implementar F8, sin crear auth nuevo.

## 6.6 Candidato

El candidato **no necesita un segundo login tradicional** para aplicar.

F5 usa formulario público + enlace/token criptográfico de continuación cuando sea necesario. Ese token no se convierte en una segunda plataforma de autenticación.

---

# 7. PROPIEDADES, UNIDADES Y CATÁLOGO ADMINISTRATIVO

## 7.1 Propiedad / conjunto

Campos mínimos:

- nombre;
- dirección interna;
- ciudad;
- sector;
- administración/contacto cuando aplique;
- notas internas;
- activo/inactivo.

El manual/documentos versionados pertenecen al sistema documental de F18; no duplicarlos en F4.

## 7.2 Unidad única

Una sola entidad `units` para:

- apartamento;
- parqueadero.

Campos comunes:

- property_id;
- código único dentro de la propiedad;
- tipo;
- ubicación interna;
- estado;
- descripción pública;
- observaciones internas;
- precio mensual publicado/canon solicitado;
- administración mensual opcional;
- `is_public`;
- slug público;
- título público;
- resumen/requisitos públicos;
- video_url opcional;
- timestamps.

No crear `apartments` y `parking_spaces` como CRUDs paralelos si nullable type-specific fields resuelven con claridad.

## 7.3 Apartamento

Campos específicos nullable en `units`:

- número;
- torre;
- piso;
- área;
- habitaciones;
- baños;
- matrícula opcional.

## 7.4 Parqueadero

Campos específicos nullable:

- nivel/sótano;
- ubicación interna;
- dimensiones opcionales;
- tipo de vehículo;
- `linked_apartment_id` opcional.

`linked_apartment_id` permite varios parqueaderos asociados a un apartamento sin tabla puente cuando cada parqueadero solo pertenece como máximo a un apartamento.

## 7.5 Estados

- Disponible;
- En proceso;
- Ocupada;
- Inactiva;
- Mantenimiento.

No existe booleano `available` adicional.

## 7.6 Precio

La unidad tiene una sola fuente de precio comercial publicado.

El contrato posterior guarda el canon final negociado/snapshot. No duplicar `catalog_price`, `suggested_rent` y `asking_price` como tres conceptos equivalentes.

## 7.7 Media de marketing

Una tabla `unit_media` únicamente si las fotos tienen orden/ciclo de vida propio.

V1 media:

- imágenes JPEG/PNG/WebP;
- orden;
- foto principal derivada por menor orden o flag único si demuestra necesidad;
- nombre aleatorio;
- MIME por bytes;
- tamaño máximo;
- archivos fuera del webroot;
- ruta pública controlada que solo sirve media de unidades actualmente publicables.

El video externo vive como URL de unidad, no como archivo multimedia administrado.

## 7.8 Módulo administrativo

No crear un CMS `Catálogo` independiente.

Dentro de Propiedades/Unidades se administran:

- datos;
- precio;
- fotos;
- video URL;
- requisitos/resumen público;
- publicación;
- vista previa/enlace al catálogo.

Puede existir una vista/filtro denominada **Catálogo** en UI, pero usa los mismos datos de `units`.

Permisos mínimos:

- `properties.view`;
- `properties.manage`.

No crear `catalog.manage` mientras `properties.manage` cubra la operación sin ambigüedad.

---

# 8. APLICACIONES, CANDIDATOS Y REFERENCIAS

## 8.1 Fuente única

No existe una tabla/entidad `lead` o `application` separada que replique al candidato.

Al presionar `Aplicar`:

- la unidad objetivo se identifica por slug/ID seguro;
- se inicia/crea el **mismo candidato** de F5;
- queda vinculado a la unidad de interés;
- el administrador ve esa candidatura en el mismo módulo de candidatos.

Si el mismo candidato aplica a otra unidad antes de cerrar su proceso, F5 debe definir si cambia interés o conserva historial sin duplicar persona; la decisión concreta se congela en SPEC F5 antes de código.

## 8.2 Aplicación pública

El formulario permite completar/continuar:

- nombre;
- documento;
- fecha nacimiento cuando aplique;
- celular/WhatsApp;
- correo;
- dirección actual;
- información laboral;
- ocupantes;
- mascotas declaradas;
- referencias;
- documentos requeridos;
- autorización/tratamiento de datos.

Puede usarse un token seguro de continuación para no exigir cuenta tradicional al candidato.

## 8.3 Requisitos

`public_requirements_text` de F4 es texto comercial resumido.

Los campos/documentos obligatorios estructurados pertenecen a F5. No confundir ambos.

## 8.4 Estados de candidato

Nuevo → Incompleto → Verificación → Pendiente estudio → En estudio → Aprobado/No aprobado aseguradora → Decisión propietario → Contrato/Desistió.

La decisión final es humana.

## 8.5 Notificación

Antes de F16 no se crea un segundo motor de notificaciones.

La nueva aplicación debe ser visible inmediatamente en administración. Email automático al propietario/candidato se conecta al motor real cuando F16 exista.

---

# 9. ESTUDIO DE ASEGURADORA

Modelo genérico `screening_process`; SURA proveedor inicial.

Guardar proveedor, producto, estado, inicio, correo, URL personalizada, fechas, resultado, observaciones, soporte y responsable.

No automatizar terceros sin API oficial.

---

# 10. CONTRATOS

Tipos iniciales:

- vivienda urbana;
- parqueadero independiente;
- otrosí;
- renovación;
- acta de entrega;
- acta de terminación;
- aviso de reajuste;
- aviso de terminación.

Flujo:

Candidato aprobado → datos existentes → unidad → condiciones económicas → duración → cláusulas → anexos → preview → snapshot → PDF → firmas/notaría opcional → final → activar arrendamiento.

Plantillas versionadas. Contratos generados son snapshots inmutables.

No construir un Word web.

No automatizar conclusiones jurídicas sensibles.

---

# 11. INVENTARIOS Y DAÑOS

Inventario inicial/final por zonas/elementos, estado, fotos y observaciones.

Clasificación:

- Sin determinar;
- Imputable al inquilino;
- Deterioro normal;
- Propietario/estructural;
- Tercero;
- Pendiente diagnóstico.

Nunca generar cargo de daño automáticamente.

---

# 12. ARRENDAMIENTO Y PORTAL DEL INQUILINO

Vivienda: unidad, fechas, canon, administración, saldo, pagos, contrato, póliza, inventario, documentos, servicios, solicitudes, visitas, convivencia y perfil.

Parqueadero: ubicación/fotos, canon, saldo, pagos, documentos y solicitudes pertinentes.

Módulos no aplicables se ocultan.

El portal usa el auth único.

---

# 13. MOTOR FINANCIERO

Un solo motor para cargos/pagos/allocations.

Cargos: canon, administración, membresía, servicio público, multa PH, reparación atribuible y otro.

Estados: Pendiente · Parcial · Reportado · Comprobante recibido · Pagado · Vencido · En reclamación · Anulado con justificación.

---

# 14. MEDIOS DE PAGO

V1:

- cuenta bancaria;
- Llave Bre-B;
- QR Bre-B;
- billetera/depósito electrónico;
- otro manual.

No inventar QR bancario interoperable. PSE sigue P2/futuro.

---

# 15. REPORTE DE PAGO Y CONCILIACIÓN

Con o sin comprobante. Estado inicial `Pendiente de conciliación`.

Conciliación humana; advertir duplicados probables sin impedir segundo reporte legítimo.

---

# 16. RECORDATORIOS / EMAIL / WHATSAPP

Un motor de reglas: evento + offset + destinatarios + canal + plantilla + activo.

Cron idempotente.

Email automático vía SMTP.

WhatsApp manual asistido en V1; no afirmar entrega/leído sin API.

---

# 17. SERVICIOS, MULTAS Y PÓLIZAS

Servicios configurables por contrato. Multas PH separadas de cobertura aseguradora. Póliza/reclamación versionable/configurable.

No codificar condiciones SURA como eternas.

---

# 18. SOLICITUDES, MANTENIMIENTO Y VISITAS

Ticket con categoría, prioridad, descripción, archivos, estado y responsable.

Visitas con opciones de horario, selección, técnico, contacto, fecha/hora, resultado y recordatorios.

Video de mantenimiento se evalúa por hosting; no heredar automáticamente la decisión de video externo del catálogo a documentos privados.

---

# 19. CONVIVENCIA Y CONTENIDO LEGAL

Manual versionado, incidentes con hechos/evidencia, mascotas declaradas y orientación legal versionada con fuentes.

La app organiza; no reemplaza autoridad/abogado/aseguradora.

---

# 20. MEMBRESÍA / SERVICIO DIGITAL

Concepto comercial separado. No depósito ni garantía retenida.

---

# 21. BONO POTENCIAL DE BUEN CUMPLIMIENTO

Entidad separada de cargos/pagos/membresía. Desactivado por defecto hasta revisión contractual.

---

# 22. LIQUIDACIÓN DE TERMINACIÓN

Consolida entrega, inventario, llaves, cargos, servicios, administración, multas, daños, reclamaciones, bono y documentos finales.

---

# 23. ADMINISTRADORES, AUDITORÍA Y NOTIFICACIONES

Eventos pueden notificar propietario/admins según reglas del motor real.

Audit log mínimo: actor, fecha/hora, acción, entidad/ID, resumen, IP cuando aplique y metadatos mínimos.

Historial no se borra desde operación normal.

---

# 24. SEGURIDAD / PRIVACIDAD

- HTTPS;
- hashing seguro;
- CSRF;
- XSS escaping;
- PDO prepared;
- sesiones seguras;
- uploads MIME/tamaño/nombre aleatorio;
- impedir ejecución PHP;
- storage privado;
- tokens criptográficos;
- rate limiting básico;
- logs sin secretos;
- IDOR/path traversal checks;
- tratamiento de datos;
- catálogo público solo expone campos públicos explícitos;
- media pública se sirve solo si la unidad sigue publicable;
- formulario de aplicación no enumera candidatos existentes;
- no almacenar claves/PIN/tokens bancarios.

---

# 25. MODELO DE DATOS — REGLA KISS V1.2

Candidatos de persistencia principales:

- users;
- properties;
- units;
- unit_media;
- candidates;
- candidate_references;
- candidate_occupants;
- screening_processes;
- tenants;
- leases;
- lease_units;
- contract_templates/versiones;
- contracts;
- contract_tasks;
- documents;
- inventories/items;
- insurance_policies/claims;
- memberships;
- charges;
- payments;
- payment_allocations;
- payment_methods;
- payment_reports;
- utility_periods;
- tickets;
- appointments;
- reminder_rules;
- notifications;
- compliance_bonuses;
- community_incidents;
- legal_content_versions;
- audit_log;
- settings;
- schema_migrations.

Explícitamente **NO** candidatos de tabla V1 salvo necesidad futura demostrada:

- catalog_listings;
- apartment_catalog;
- parking_catalog;
- public_properties;
- leads;
- applications duplicadas de candidates;
- tenant_users duplicada de users;
- admin_users duplicada de users.

Antes de tabla nueva:

1. ¿puede usarse una existente sin ambigüedad?
2. ¿puede unirse/converger?
3. ¿tiene ciclo de vida independiente?
4. ¿qué integridad/consulta real aporta?
5. ¿qué ruido añade?

---

# 26. MARCO LEGAL / FUENTES BASE

Revisar/versionar antes de afirmaciones exactas:

- Ley 820 de 2003;
- Ley 1581 de 2012;
- Ley 1801 de 2016 y normas posteriores;
- manual/reglamento de propiedad horizontal;
- jurisprudencia aplicable;
- condicionado vigente SURA;
- fuentes oficiales Bre-B.

El catálogo y formularios públicos deben incluir tratamiento/autorización de datos cuando corresponda, sin recopilar información innecesaria.

---

# 27. P0 / P1 / P2 / REJECT

## P0 V1

Installer, auth único, roles/permisos, UX móvil, landing pública, catálogo, propiedades/unidades, media de catálogo, candidatos/aplicaciones/referencias, aseguradora, contratos, portal inquilino, pólizas, cargos, pagos, conciliación, Bre-B/QR, servicios, documentos, inventarios, tickets, visitas, email, cron, WhatsApp asistido, convivencia, membresía, bono, liquidación, auditoría y seguridad.

## P1

Filtros avanzados de catálogo, SEO avanzado, exportaciones, plantillas avanzadas, proveedores adicionales e importaciones.

## P2

PSE, WhatsApp API, firma electrónica, integración bancaria, app nativa, OCR, analítica avanzada, video self-hosted/transcoding.

## REJECT V1

Microservicios, Redis, SPA completa, CMS de catálogo independiente, dos auth, tabla de listings duplicada, lead CRM paralelo, PSE vacío, integraciones falsas, dashboards decorativos.

---

# 28. CRITERIOS DE ACEPTACIÓN V1

V1 debe:

- instalar desde navegador;
- operar PHP 8.3–8.5;
- funcionar mobile-first;
- mostrar landing pública con Login + Catálogo;
- mostrar solo apartamentos/parqueaderos realmente disponibles y publicados;
- permitir descubrir precio/fotos/requisitos/video externo;
- permitir iniciar `Aplicar` y llevarlo al candidato único;
- completar candidato → estudio → contrato → inquilino;
- usar un solo auth para staff/inquilino;
- generar contratos PDF versionados;
- manejar cartera/pagos/conciliación;
- Bre-B/QR;
- cron idempotente;
- email + WhatsApp asistido;
- solicitudes/visitas;
- documentos/inventarios;
- pólizas/reclamaciones;
- convivencia;
- membresía/bono separados;
- liquidación;
- auditoría/seguridad;
- cero módulos muertos;
- paquete SERVER_READY final.

---

# 29. DECISIONES CONGELADAS V1.2

- Alquila Pro.
- `alquilapro.sierraglobalcompany.com`.
- branding configurable.
- mobile-first.
- PHP 8.3–8.5 + Slim + MariaDB/PDO.
- una app / una BD / un repo.
- **un auth**.
- landing pública en la misma aplicación.
- catálogo como proyección de `units`.
- apartamentos y parqueaderos comparten `units`.
- disponibilidad derivada de estado + publicación; no segundo availability.
- una fuente de precio comercial en unidad.
- fotos de marketing mediante `unit_media`.
- video mediante URL externa en V1.
- sin tabla `catalog_listings`.
- `Aplicar` usa el candidato único; sin lead/application CRM paralelo.
- candidatos pueden continuar con token seguro, sin segundo login.
- catálogo/admin reutiliza permisos de propiedades; no `catalog.manage` salvo necesidad futura demostrada.
- SURA dentro de modelo genérico.
- PSE no activo.
- WhatsApp API no activa.
- Ley 1 + Ley 2 obligatorias.

---

# 30. ESTADO REAL Y PRÓXIMO GATE

Estado al emitir V1.2:

- F0: CLOSED.
- F1: CLOSED / VERIFIED / MERGED.
- F2: CLOSED / VERIFIED / MERGED.
- F3: CLOSED / VERIFIED / MERGED.
- F4: NO implementada.

F4 debe diseñarse/planificarse contra V1.2, no contra V1.1.

Primer gate F4:

`Propiedades + unidades + media + publicación + landing pública + catálogo browse/detail`, sin candidato todavía.

F5 incorpora `Aplicar` y el flujo completo de candidatos/referencias, enlazado al catálogo sin crear tablas paralelas.
