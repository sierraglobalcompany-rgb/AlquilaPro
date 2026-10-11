# ALQUILA PRO — SPEC MAESTRA V1.1 AUDITADA

**Fecha:** 2026-10-11  
**Estado:** F0 — especificación funcional/técnica auditada  
**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Producto:** aplicación web privada, mobile-first y autoinstalable para administración directa de arrendamientos de vivienda y parqueaderos.

---

# 0. AUTORIDAD DEL DOCUMENTO

Esta SPEC define qué debe hacer Alquila Pro y qué límites no puede cruzar. El plan de implementación asociado es:

[`../plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`](../plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md)

El punto de entrada del repositorio es `README.md`.

Orden de autoridad: SPEC → PLAN → checkpoint más reciente → tests → código real.

---

# 1. CONSTITUCIÓN TÉCNICA

## LEY SUPREMA 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe ser simple, sencilla, optimizada, eficiente, eficaz, rápida, fácil de auditar, fácil de actualizar, fácil de mejorar y fácil de desplegar.

Regla:

> Entre dos soluciones correctas se elige la de menor arquitectura y menor número de piezas, siempre que mantenga seguridad, trazabilidad y requisitos.

Falla KISS si se introducen sin necesidad probada: microservicios, Redis, workers, CQRS, event bus, contenedores obligatorios, factories sin variación, repositorios genéricos, DTOs/mappers triviales, interfaces de una sola implementación sin razón, capas que solo delegan, SPA completa o tablas separadas para conceptos con el mismo ciclo de vida.

## LEY SUPREMA 2 — CERO RUIDO / CONVERGENCIA

No se conserva ruido conocido ni potencial comprobable.

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Ruido incluye código muerto, duplicación, capas redundantes, rutas repetidas, tablas sin ciclo de vida propio, estados equivalentes, lógica dispersa, dependencias no usadas, flags temporales permanentes, documentación obsoleta, módulos o botones futuros sin implementación real.

## Gate obligatorio

Toda fase debe cerrar con:

- `LEY_1_KISS: PASS`
- `LEY_2_CERO_RUIDO: PASS`

Y demostrar added/removed/merged, cambios de dependencias, esquema, rutas y pruebas.

## Presupuesto de arquitectura V1

- 1 app PHP.
- 1 BD MariaDB/MySQL.
- 1 repo.
- 1 auth.
- 1 motor financiero.
- 1 motor de recordatorios.
- 1 sistema documental.
- 1 flujo de tickets/visitas.
- 1 installer.
- 1 cron HTTP protegido.
- server-side HTML.
- Alpine.js solo donde aporte interacción real.

---

# 2. VISIÓN

Alquila Pro centraliza la operación de apartamentos y parqueaderos que normalmente queda dispersa en WhatsApp, correo, hojas de cálculo, memoria y carpetas.

Debe permitir saber rápidamente:

- disponibilidad/ocupación;
- candidato/inquilino asociado;
- saldo y vencimientos;
- pagos por conciliar;
- contratos y pólizas por vencer;
- reclamaciones que requieren revisión;
- solicitudes y visitas;
- inventarios/documentos;
- obligaciones de terminación;
- auditoría de quién hizo cada acción.

No es un ERP contable, un SaaS multiempresa ni una plataforma inmobiliaria masiva en V1.

---

# 3. STACK

- PHP 8.3 base, compatible 8.4 y 8.5.
- Slim 4.
- MariaDB/MySQL.
- PDO.
- Alpine.js.
- HTML server-side.
- CSS mobile-first.
- PHPMailer/SMTP.
- Composer local con `vendor/` en paquete de despliegue.
- PHPUnit.
- PHPStan solo si aporta sin sobrearquitectura.
- PDF mediante librería validada en spike corto.

Producción NO requiere Node, npm, Composer, Redis, Docker, SSH obligatorio, workers o colas externas.

---

# 4. BRANDING E INSTALACIÓN

Nombre inicial: **Alquila Pro**.

Configurable desde administración:

- nombre;
- logo;
- favicon;
- colores;
- nombre empresa/administración;
- correo/teléfono/dirección;
- pie documental;
- textos de contacto.

El instalador debe funcionar en subdominio o subcarpeta HTTPS y detectar la base URL real.

Pasos: diagnóstico → DB → conexión → migraciones → propietario → branding → SMTP → medio de pago inicial → cron → finalizar → bloquear installer.

---

# 5. UX / DISEÑO

Dirección: **Residential OS / PropTech premium**.

Características:

- moderna;
- limpia;
- humana;
- cálida;
- mobile-first;
- tarjetas útiles;
- estados claros;
- fotos reales;
- touch targets cómodos;
- cero gráficas decorativas;
- cero tablas si una tarjeta resuelve mejor;
- progressive disclosure.

## Admin

Barra inferior:

**Inicio · Cartera · [APPS] · Solicitudes · Cuenta**

`APPS` abre launcher con funciones secundarias.

## Inquilino

**Inicio · Pagos · [APPS] · Solicitudes · Cuenta**

La interfaz es contextual: si solo tiene parqueadero se ocultan módulos de vivienda que no aplican.

---

# 6. USUARIOS Y PERMISOS

## Propietario

Control total. Crea/desactiva administradores, configura permisos, contratos, candidatos, pagos, pólizas, branding, auditoría y liquidaciones.

No puede eliminarse el último propietario activo.

## Administrador

Permisos simples/configurables para cartera, pagos, candidatos, contratos, solicitudes, mantenimiento, pólizas, propiedades y documentos.

## Gestor

Operación limitada: cobros, conciliación si se autoriza, agenda, seguimiento y contacto.

## Inquilino

Portal privado: contrato, propiedad/parqueadero, fechas, saldo, pagos, documentos, póliza, servicios, solicitudes, visitas, manual y perfil.

## Candidato

Portal previo: datos, documentos, referencias, autorización de datos y estudio de aseguradora.

Autenticación del inquilino: documento como usuario y contraseña inicial igual al documento, con cambio obligatorio en primer acceso. La contraseña real se guarda hasheada.

---

# 7. PROPIEDADES Y UNIDADES

## Propiedad / conjunto

Nombre, dirección, ciudad/sector, administración, contacto, manual vigente, notas, estado.

## Unidad

Entidad genérica para apartamento o parqueadero: código, propiedad, ubicación, estado, fotos, descripción y observaciones.

## Apartamento

Número, torre/piso, área opcional, habitaciones/baños, parqueaderos asociados, canon sugerido, administración, matrícula opcional, inventario/póliza configurables.

## Parqueadero

Código, nivel/sótano, ubicación interna, fotos, dimensiones opcionales, tipo de vehículo, canon sugerido, observaciones.

Estados: Disponible · En proceso · Ocupada · Inactiva · Mantenimiento.

---

# 8. CANDIDATOS

Datos básicos: nombre, documento, fecha nacimiento, celular/WhatsApp, correo, dirección actual.

Información laboral configurable: empresa, cargo, antigüedad, vínculo, ingreso aproximado, contacto.

Referencias configurables:

- laboral;
- 2 familiares;
- 2 personales;
- arrendador anterior.

Cada referencia: nombre, relación, teléfono, correo opcional, resultado, fecha, administrador y observaciones.

Ocupantes: nombre, documento, relación, mayoría de edad, vehículo y mascota declarada cuando aplique.

Documentos configurables: cédula, certificado laboral, extractos, renta, RUT u otros. No pedir información financiera si no se necesita.

Estados: Nuevo → Incompleto → Verificación → Pendiente estudio → En estudio → Aprobado/No aprobado aseguradora → Decisión propietario → Contrato/Desistió.

La decisión final es humana. No hay score moral automático.

---

# 9. ESTUDIO DE ASEGURADORA

Modelo genérico `screening_process`; SURA es proveedor inicial.

Guardar: proveedor, producto, estado, inicio, correo, URL externa personalizada, fecha invitación, fecha resultado, resultado, observaciones, soporte, responsable.

El candidato puede abrir un enlace individual de SURA si existe. V1 no automatiza procesos de la aseguradora sin API oficial.

---

# 10. CONTRATOS

Módulo P0.

Tipos iniciales:

- vivienda urbana;
- parqueadero independiente;
- otrosí;
- renovación;
- acta de entrega;
- acta de terminación;
- aviso de reajuste;
- aviso de terminación.

## Creación

Candidato aprobado → traer datos existentes → inmueble → condiciones económicas → duración → cláusulas → anexos → preview → snapshot → PDF → firma/notaría opcional → subir final → activar arriendo.

## Variables

Ejemplos:

- `{{inquilino.nombre}}`
- `{{inquilino.documento}}`
- `{{arrendador.nombre}}`
- `{{arrendador.documento}}`
- `{{inmueble.direccion}}`
- `{{contrato.canon}}`
- `{{contrato.canon_letras}}`
- `{{contrato.fecha_inicio}}`
- `{{contrato.fecha_fin}}`

La UI tiene selector “Insertar variable”.

## Editor

Por cláusulas/bloques; no construir un Word web.

Cada cláusula: título, texto, orden, variables, activa/opcional, fuente jurídica, versión.

## Versionado

Modificar una plantilla publicada crea nueva versión. Los contratos generados son snapshots inmutables.

Estados: Borrador → Revisión → Listo para firma → Firmado → Notaría opcional → Vigente → Renovación/Terminación → Terminado → Liquidado.

Checklist: imprimir, firmas, notaría, subir final, entregar copia, póliza, inventario, activar.

PDF: número, versión, generación, partes, cláusulas, anexos, firmas y hash documental. El hash no sustituye firma digital.

No usar un switch simplista “renovable/no renovable” como regla jurídica automática; controlar intención, preavisos, causal, documentos y fechas.

---

# 11. INVENTARIOS Y DAÑOS

Inventario inicial/final por zonas y elementos con estado, descripción, fotos y observaciones.

Comparación inicial vs final.

Clasificación de hallazgos:

- Sin determinar;
- Imputable al inquilino;
- Deterioro normal;
- Propietario/estructural;
- Tercero;
- Pendiente diagnóstico.

Nunca generar cargo por daño automáticamente. Requiere decisión explícita y evidencia.

---

# 12. ARRENDAMIENTO Y PORTAL DEL INQUILINO

Vivienda: inmueble, fechas, canon, administración, saldo, medios de pago, contrato, póliza, inventario, documentos, servicios, solicitudes, visitas, convivencia y orientación.

Parqueadero: ubicación/fotos, canon, saldo, pagos, documentos y solicitudes pertinentes.

Módulos no aplicables quedan ocultos.

---

# 13. MOTOR FINANCIERO

Un solo motor.

Cargos:

- canon;
- administración;
- membresía/servicio adicional;
- servicio público;
- multa PH;
- reparación atribuible;
- otro.

Estados: Pendiente · Parcial · Reportado · Comprobante recibido · Pagado · Vencido · En reclamación · Anulado con justificación.

Pagos parciales permitidos. Un pago puede aplicarse a varios cargos.

---

# 14. MEDIOS DE PAGO

V1:

- cuenta bancaria;
- Llave Bre-B;
- QR Bre-B;
- billetera/depósito electrónico;
- otro manual.

Campos: nombre, entidad, titular, documento titular, tipo/número de cuenta, tipo/valor de llave, teléfono, QR, instrucciones, orden, activo y alcance.

Bre-B soportará llaves documento, celular, correo, identificador `@` y código de comercio cuando aplique.

Mostrar titular esperado, copiar llave/número, mostrar/descargar QR.

El QR se carga desde la entidad; no se genera un QR bancario interoperable inventado por Alquila Pro.

PSE queda como extensión futura del motor financiero, sin módulo muerto en V1.

---

# 15. REPORTAR PAGO Y CONCILIACIÓN

Con comprobante: monto, fecha, medio, archivo, observación.

Sin comprobante: monto, fecha, hora/rango aproximado, medio destino, banco origen opcional, observación y evidencia opcional.

Estado inicial: **Pendiente de conciliación**.

Admin ve esperado vs reportado, fecha/hora, destino, evidencia y observación.

Acciones: conciliar, parcial, no encontrado, requiere información, WhatsApp.

Advertir duplicados probables sin impedir un segundo reporte legítimo.

---

# 16. RECORDATORIOS / EMAIL / WHATSAPP

Un solo motor de reglas: evento + offset + destinatarios + canal + plantilla + activo.

Casos: pago, contrato, póliza, visita, servicio, tarea.

Cron idempotente: mismo evento/regla/destinatario no se envía dos veces.

Si el pago está reportado pendiente, no seguir enviando “no has pagado”; mostrar/usar estado pendiente de validación.

Email automático por SMTP.

WhatsApp manual asistido: abre WhatsApp/Business con mensaje personalizado; no afirmar enviado/entregado/leído sin API.

---

# 17. SERVICIOS, MULTAS Y PÓLIZAS

Servicios por contrato: energía, agua/alcantarillado, gas, aseo u otros. Seguimiento configurable: no controlar, solicitar soporte, registrar, recordar.

Multas PH: fecha, concepto, valor, soporte, responsable, estado y notificación. No marcarlas automáticamente como cubiertas por aseguradora.

Póliza: aseguradora, producto, número, fechas, coberturas configurables, límites, PDF, notas y estado.

Reclamación: fecha, motivo, monto, soporte, radicado, estado y notas.

Si existe reclamación y luego se recibe pago, generar alerta para revisar la situación antes de conciliar definitivamente.

No codificar condiciones SURA como verdades eternas: son configurables/versionadas según póliza/producto vigente.

---

# 18. SOLICITUDES, MANTENIMIENTO Y VISITAS

Ticket: categoría, prioridad, descripción, archivos, estado, responsable.

Estados: Nueva → Revisando → Programar visita → Programada → En ejecución → Resuelta → Cerrada/Cancelada.

Visitas: varias franjas propuestas, el inquilino selecciona una; técnico, contacto, fecha/hora, duración, costo estimado, resultado y recordatorios.

V1 prioriza imágenes/PDF; video se evalúa según hosting antes de incluirlo.

---

# 19. CONVIVENCIA Y CONTENIDO LEGAL

Manual de convivencia versionado por propiedad. Nueva versión conserva anteriores.

Incidentes: ruido, fiesta, mascota, zona común, administración, seguridad u otro. Registrar hechos/evidencia, no etiquetas subjetivas.

Mascotas: registrar información declarada; no imponer prohibiciones generales automáticas desde la app.

Sustancias: registrar hechos objetivos/reportes/actuaciones, no perfiles de la persona.

Orientación legal al inquilino: daño imputable vs deterioro normal, pagos, servicios, convivencia, manual. Contenido con versión, fecha, fuente y aviso de que prevalecen ley, contrato y autoridad competente.

---

# 20. MEMBRESÍA / SERVICIO DIGITAL

Concepto comercial separado con nombre, descripción real del servicio, precio, inicio/fin, duración N meses o ligada a contrato, renovación, aceptación y comprobante.

No es depósito, garantía ni saldo retenido/reembolsable condicionado al estado del inmueble.

El software no certifica legalidad por nombre o porcentaje.

---

# 21. BONO POTENCIAL DE BUEN CUMPLIMIENTO

Entidad separada de cargos, pagos y membresía.

Estados: Potencial → En evaluación → Aprobado/No causado → Pagado/Cancelado con justificación.

Condiciones configurables: canon, administración, servicios, obligaciones, inventario final, entrega, llaves, daños imputables.

Puede existir un límite comercial configurable; no presentarlo como regla legal si no lo es.

Viene desactivado por defecto hasta existir redacción contractual revisada.

---

# 22. LIQUIDACIÓN DE TERMINACIÓN

Debe consolidar: fecha entrega, inventario final, llaves, cargos, servicios, administración, multas, daños, reclamaciones, bono, resultado y documentos finales.

Generar resumen exportable.

---

# 23. ADMINISTRADORES Y NOTIFICACIONES

Cada evento puede notificar propietario, administrador responsable o administradores seleccionados.

Preferencias por evento: pago, mora, candidato, solicitud, póliza, contrato, reclamación.

Auditoría mínima: actor, fecha/hora, acción, entidad/ID, resumen, IP cuando aplique y metadatos mínimos.

El historial no se borra desde operación normal.

---

# 24. SEGURIDAD / PRIVACIDAD

- HTTPS.
- password hashing seguro.
- CSRF.
- escape XSS.
- PDO prepared statements.
- sesiones seguras.
- validación MIME/tamaño.
- nombres aleatorios.
- impedir ejecución PHP en uploads.
- archivos sensibles privados.
- tokens criptográficos.
- rate limiting básico.
- headers adecuados.
- logs sin secretos.
- autorización/tratamiento de datos y acceso restringido.

No almacenar PIN, claves bancarias, claves dinámicas ni tokens de banca.

---

# 25. MODELO DE DATOS — REGLA KISS

No hay obligación de crear una tabla por cada pantalla.

Candidatos de persistencia principales: users, properties, units, candidates, candidate_references, candidate_occupants, tenants, screening_processes, leases, lease_units, contract_templates/versiones, contracts, contract_tasks, documents, inventories/items, insurance_policies/claims, memberships, charges, payments, payment_allocations, payment_methods, payment_reports, utility_periods, tickets, appointments, reminder_rules, notifications, compliance_bonuses, community_incidents, legal_content_versions, audit_log, settings, schema_migrations.

Antes de una tabla nueva:

1. ¿puede usarse una existente sin ambigüedad?
2. ¿puede unirse/converger?
3. ¿tiene ciclo de vida independiente?
4. ¿qué consulta real necesita?
5. ¿qué integridad aporta?
6. ¿cuánto ruido añade?

Objetivo: reducir y converger durante implementación.

---

# 26. MARCO LEGAL / FUENTES BASE

Referencias principales a revisar/versionar:

- Ley 820 de 2003 — arrendamiento de vivienda urbana.
- Ley 1581 de 2012 — protección de datos.
- Ley 1801 de 2016 y normas posteriores de convivencia/ruido.
- Reglamento/manual de cada propiedad horizontal.
- Sentencias aplicables en materia de mascotas/propiedad horizontal.
- Condicionado vigente de SURA del producto contratado.
- Información oficial Bre-B del Banco de la República.

Alquila Pro ayuda a organizar y documentar. No reemplaza asesoría legal, aseguradora ni autoridad.

Antes de usar una plantilla de vivienda con dinero real, revisión profesional del modelo maestro.

---

# 27. P0 / P1 / P2 / REJECT

## P0 V1

Installer, auth, roles, UX móvil, propiedades/unidades, candidatos, referencias, estudio aseguradora, contratos, portal, pólizas, cargos, pagos, conciliación, Bre-B/QR, reporte sin comprobante, servicios, documentos, inventarios, tickets, visitas, email, cron, WhatsApp asistido, convivencia, contenido legal, membresía, bono, liquidación, auditoría y seguridad.

## P1

Informes adicionales, exportaciones, plantillas más avanzadas, proveedores ampliados, importaciones.

## P2

PSE, WhatsApp API, firma electrónica, integración bancaria, app nativa, OCR, analítica avanzada.

## REJECT V1

Microservicios, Redis, SPA completa, ERP contable, IA redactando cláusulas legales libremente, PSE vacío, integraciones falsas, dashboards decorativos.

---

# 28. CRITERIOS DE ACEPTACIÓN V1

V1 debe instalarse desde navegador, operar PHP 8.3–8.5, funcionar en móvil, completar candidato→estudio→contrato→inquilino, generar PDF versionado, manejar cartera/pagos/conciliación, Bre-B/QR, pagos sin comprobante, cron idempotente, email, WhatsApp asistido, solicitudes/visitas, documentos/inventarios, pólizas/reclamaciones, convivencia, membresía/bono separados, liquidación, auditoría y seguridad.

Además:

- no módulos muertos;
- no PSE falso;
- installer bloqueado al terminar;
- suite/regresión/seguridad pasan;
- todas las fases con ambas leyes en PASS;
- paquete `SERVER_READY` final.

---

# 29. DECISIONES CONGELADAS F0

- Alquila Pro.
- `alquilapro.sierraglobalcompany.com`.
- branding configurable.
- mobile-first.
- bottom navigation + Apps central.
- PHP 8.3–8.5.
- Slim 4.
- MariaDB/PDO.
- Alpine.js.
- PHPMailer.
- una app / una BD / un repo.
- sin Laravel.
- sin Vue SPA.
- sin Redis/workers.
- sin PSE activo.
- sin WhatsApp API.
- contratos por plantillas/versiones/snapshot/PDF.
- SURA como proveedor inicial dentro de modelo genérico.
- pagos manuales/Bre-B y conciliación humana.
- motor genérico de recordatorios.
- auditoría.
- Ley 1 KISS + Ley 2 Cero Ruido obligatorias.

---

# 30. PRÓXIMO GATE

F0 queda documentada. Antes de F1 debe verificarse que esta SPEC y el PLAN sean la fuente vigente, registrar el estado real del repositorio y arrancar Foundation con TDD/local-first.
