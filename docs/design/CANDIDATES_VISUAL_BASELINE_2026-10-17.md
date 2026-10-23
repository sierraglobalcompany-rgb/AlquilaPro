# AlquilaPro — Candidates Visual Baseline — 2026-10-17

**Estado:** `APPROVED_VISUAL_DIRECTION / CONTENT_REQUIRES_BACKEND_TRUTH`  
**Scope:** módulo administrativo `Candidatos`  
**Spec asociada:** `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md`

---

## 1. Autoridad

Este baseline define **arte visual, jerarquía, composición y lenguaje de interfaz**.

No redefine:

- rutas;
- permisos;
- estados;
- datos;
- métricas;
- acciones;
- capacidades del backend.

Regla:

```text
BACKEND TRUTH + SECURITY + ACCESSIBILITY
> HUMAN-FIRST MASTER V2.1
> CANDIDATES SPEC
> THESE MOCKUPS
```

Los mockups contienen ejemplos visuales deliberadamente más ricos que el backend actual. Todo contenido no soportado por la spec debe eliminarse o sustituirse por verdad real.

---

## 2. Pack visual persistente

Los ocho mockups originales aprobados están almacenados en ChatGPT Library:

```text
/AlquilaPro/Design/Candidatos/2026-10-17/
```

Archivos:

```text
01_SOLICITUD.png
02_INFORMACION_LABORAL.png
03_OCUPANTES_MASCOTAS.png
04_REFERENCIAS.png
05_DOCUMENTOS.png
06_ESTUDIO_ASEGURADORA.png
07_CONTRATOS.png
08_ADMINISTRAR.png
```

Estos nombres son canónicos para recuperar la referencia en chats futuros.

---

## 3. Elementos visuales aprobados

Mantener como lenguaje del módulo:

- sidebar clara con navegación principal;
- topbar limpia;
- área de trabajo con fondo neutro;
- header de candidato de alta jerarquía;
- avatar por iniciales;
- nombre grande;
- badge de estado;
- propiedad/unidad como contexto corto;
- navegación contextual de secciones;
- verde AlquilaPro como acento;
- cards con radio consistente;
- sombras/elevation discretas;
- tipografía con fuerte diferencia entre título, label y apoyo;
- iconografía simple;
- espacios amplios;
- superficies blancas con propósito claro;
- CTA primario claramente distinguible;
- estados de información no basados sólo en color;
- desktop moderno tipo SaaS, no “sistema contable viejo”.

---

## 4. Elementos que deben simplificarse

Aunque aparecen en los mockups, evitar:

- repetir candidato + teléfono + correo + dirección dentro de múltiples cards;
- convertir cada hecho en KPI;
- demasiados paneles simultáneos;
- “10 cards iguales”;
- dashboards decorativos;
- gráficas sin necesidad operativa;
- timelines sin truth owner;
- cajas de “recomendación” que inventen una evaluación;
- texto auxiliar redundante.

Objetivo de implementación:

```text
MENOS BLOQUES
+ MEJOR JERARQUÍA
+ CADA CARD CON UNA RAZÓN
+ CERO INFORMACIÓN DUPLICADA SIN JUSTIFICACIÓN
```

---

## 5. Divergencias por pantalla

### 01 — Solicitud

Conservar:
- estructura general;
- header;
- navegación;
- resumen dominante;
- estado destacado;
- agrupación visual.

Eliminar/no inventar:
- `Editar candidato`;
- `Enviar mensaje`;
- dirección del inmueble no entregada por la ruta;
- estado civil/nacionalidad/tipo de solicitud si no existen;
- progreso/“próximos pasos” no respaldados por reglas reales;
- datos del inmueble que el candidate route no entrega.

### 02 — Información laboral

Conservar:
- composición limpia;
- ingreso con jerarquía cuando exista;
- notas separadas.

Eliminar/no inventar:
- sector;
- área;
- tipo de contrato;
- fecha de ingreso;
- antigüedad;
- modalidad;
- otros ingresos;
- historial laboral;
- verificación de ingresos;
- documentos de soporte laborales especializados;
- “perfil sólido”, riesgo o recomendación.

### 03 — Ocupantes y mascotas — revisión funcional ratificada 2026-10-23

**Fuente visual permanece INTACTA**: `03_OCUPANTES_MASCOTAS.png`, 1448×1086. El owner rechazó la reducción a tres cards y autorizó completar el módulo con fichas de ocupantes, mascotas individuales y fotografías opcionales reales **en CAND-03**, sin diferirlas. La restricción histórica siguiente ("sin fichas ni fotos porque no hay backend") quedó **supersedida por la nueva SPEC §9.3 y plan Task 3**, no por cambio de imagen.

**Preservar** seis regiones, jerarquía, disposición y densidad visual: Resumen (ancho), Estado de la información (lateral), Ocupantes con ficha del solicitante y personas adicionales, Mascotas con fotos reales/placeholder y manual contextual, Reglas/observaciones, Próximos pasos útiles. Misma experiencia en responsive sin ocultar regiones.

**No inventar**: perfiles, edad, parentesco, raza, foto, vacunas, «riesgo bajo», «hogar estable», «sin alertas», política de mascotas, envío de mensajes ni aprobaciones automáticas. Los nuevos campos capturados voluntariamente alimentan solo las fichas que realmente existen. Vacunación, cuando se capture, significa **declaración no verificada**. Para ausencia mostrar un estado vacío humano en la región aprobada, **no borrar la región**.

**Acciones** Agregar/Editar reales para gestor con `candidates.manage`; lectura `candidates.view`; privados de inmueble exigen también `properties.view` y `documents.view`. Manual real `manual_convivencia` JSON/PDF según tipo/versionado del producto; no adjudicar permisos jurídicos ni calcular riesgo. Fotos privadas por persona adulta/mascota opcionales, menores con iniciales por defecto.

**Regla de aceptación:** el PNG es autoridad visual, no datos de prueba; comparar cada una de las seis regiones contra captura real a 1448/1100/768/375 y gates funcionales de formulario, fotos, permisos, manual y navegación. QA PHP verde por sí solo no certifica fidelidad visual.

### 04 — Referencias

Conservar:
- cards individuales;
- jerarquía nombre/tipo/contacto.

Eliminar/no inventar:
- 80% confiabilidad;
- calificación 4.5/5;
- contactada;
- resultado;
- recomendado;
- llamadas;
- notas del evaluador;
- agregar referencia desde admin.

### 05 — Documentos

Conservar:
- cards/filas visuales claras;
- nombre del requisito;
- nombre del archivo;
- acción abrir documento;
- empty state humano.

Eliminar/no inventar en primer bloque:
- porcentajes de completitud;
- en revisión;
- aprobado;
- solicitar documentos;
- checklist del revisor;
- notas del revisor;
- subir documento desde admin;
- faltantes, salvo que posteriormente se amplíe el truth contract con requisitos de unidad.

### 06 — Estudio de aseguradora

Conservar:
- estado prominente;
- campos reales agrupados;
- resultado/observaciones cuando existen;
- formulario real cuando puede editarse.

Eliminar/no inventar:
- score 82/100;
- riesgo bajo;
- capacidad de pago;
- historial crediticio;
- estabilidad calculada;
- ETA prometida;
- “todo va por buen camino”.

### 07 — Contratos

Conservar:
- cards modernas;
- estado real;
- plantilla/unidad;
- abrir contrato;
- crear contrato sólo con capability real.

Eliminar/no inventar:
- firmas requeridas;
- avance de firmas;
- historial;
- previsualizador nuevo;
- recordatorios;
- reenviar;
- cancelar proceso;
- aprobaciones no soportadas.

### 08 — Administrar

Conservar:
- zona visualmente diferenciada para acciones sensibles;
- estado actual;
- una decisión/acción principal clara.

Eliminar/no inventar:
- pre-aprobar;
- aprobar;
- rechazar;
- comentario de decisión;
- notas internas generales;
- historial de actividad;
- informe del candidato;
- enviar actualización;
- archivar.

Usar únicamente transiciones reales y regeneración de enlace.

---

## 6. Responsive baseline

La identidad visual debe sobrevivir a:

```text
1440×900
768×1024
375×812
```

Desktop puede usar tabs contextuales horizontales.
Mobile debe usar navegación compacta sin convertir ocho tabs en scroll horizontal infinito.

---

## 7. Regla de futuro

Una vez el módulo `Candidatos` quede implementado y certificado, este lenguaje visual se podrá **extraer como dirección del ERP** y adaptar a Contratos, Propiedades y otros módulos.

No hacerlo antes de cerrar Candidatos para evitar propagar errores visuales o falsas capacidades.

---

```text
CANDIDATES_VISUAL_DIRECTION=APPROVED
MOCKUPS_PERSISTED=YES
MOCKUPS_ARE_FUNCTIONAL_TRUTH=NO
CANDIDATES_SPEC_REQUIRED=YES
```
