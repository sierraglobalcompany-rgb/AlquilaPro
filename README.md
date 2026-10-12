# Alquila Pro

Sistema privado, mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos.

**Dominio previsto:** `https://alquilapro.sierraglobalcompany.com`  
**Estado actual:** F2 — Autenticación, usuarios y permisos **CLOSED / VERIFIED / MERGED**.  
**Siguiente fase:** F3 — Shell móvil + sistema visual, actualmente en gate de SPEC específica.  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + Alpine.js solo cuando aporte valor real.  
**Destino:** Hostinger, instalación portable en subdominio o subcarpeta HTTPS.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Toda persona, ChatGPT, Codex, Antigravity u otra IA que retome este proyecto debe leer, antes de tocar código:

1. [`README.md`](README.md)
2. [`docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`](docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md)
3. [`docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`](docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md)
4. checkpoint más reciente en [`docs/checkpoints/`](docs/checkpoints/)
5. SPEC/plan específico de la fase abierta, si existe
6. tests y CI reales
7. código real del repositorio

Orden de autoridad:

`SPEC MAESTRA → PLAN MAESTRO → SPEC DE FASE → PLAN DE FASE → CHECKPOINT → TESTS/CI → CÓDIGO REAL`

Si hay contradicción, no se adivina: se audita y se corrige la fuente correspondiente.

---

# 2. LAS DOS LEYES SUPREMAS

## LEY 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe ser simple, sencilla, optimizada, eficiente, eficaz, rápida, fácil de auditar, actualizar, mejorar y desplegar.

> Entre dos soluciones correctas gana la que use menos piezas, menos estados, menos dependencias y menos capas, siempre que mantenga seguridad, trazabilidad y requisitos.

Arquitectura base:

- 1 aplicación PHP;
- 1 base MariaDB/MySQL;
- 1 repositorio;
- HTML server-side;
- JS mínimo;
- Alpine.js solo por necesidad real;
- sin microservicios;
- sin Redis;
- sin workers;
- sin event bus;
- sin SPA completa;
- sin capas genéricas por costumbre.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Ruido incluye código muerto, duplicación, rutas repetidas, tablas sin ciclo de vida propio, estados equivalentes, capas que solo delegan, dependencias sin uso, documentación obsoleta, flags permanentes y módulos/botones futuros sin función real.

Cada fase debe cerrar con:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
```

Y demostrar added/removed/merged, delta de dependencias/esquema/rutas y pruebas.

---

# 3. VISIÓN Y UX

Alquila Pro centraliza propiedades, candidatos, estudios de aseguradora, contratos, inquilinos, cartera, pagos, pólizas, servicios, solicitudes, visitas, convivencia, inventarios, documentos y cierre contractual.

Dirección visual: **Residential OS / PropTech premium**.

Administrador objetivo:

**Inicio · Cartera · [APPS] · Solicitudes · Cuenta**

Inquilino objetivo:

**Inicio · Pagos · [APPS] · Solicitudes · Cuenta**

Principios:

- mobile-first;
- navegación inferior;
- Apps central;
- tarjetas con jerarquía clara;
- touch targets cómodos;
- progressive disclosure;
- cero gráficas decorativas;
- cero tablas cuando una tarjeta resuelve mejor;
- no mostrar botones de módulos que todavía no existen.

---

# 4. ESTADO REAL DEL REPOSITORIO

```text
PROJECT: Alquila Pro
REPO: sierraglobalcompany-rgb/AlquilaPro
TARGET: alquilapro.sierraglobalcompany.com
SPEC: V1.1 AUDITADA
PLAN: V1.1 AUDITADO

F0: CLOSED
F1: CLOSED / VERIFIED / MERGED
F1_MERGE_SHA: a5225151358aaeca4698adf8cb85d815752a5ac4

F2: CLOSED / VERIFIED / MERGED
F2_IMPLEMENTATION_SHA: f4ac9e8b386aab0e0b83226f5012a5e7e86d102b
F2_DOCUMENTATION_HEAD: 94489483879da7ffa433538b4cb4dbd702990e39
F2_MERGE_SHA: 96a4fa59ca82604d821061c6aed7d9edb3849ae1
F2_QA: PHP 8.3 / 8.4 / 8.5 — 60 tests / 254 assertions
LEY_1_KISS_F2: PASS
LEY_2_CERO_RUIDO_F2: PASS
LATEST_MERGED_CHECKPOINT: docs/checkpoints/F2_MERGED_2026-10-11.md

F3: DESIGN/SPEC GATE
NEXT: aprobar SPEC específica F3 antes de plan/implementación
```

---

# 5. ROADMAP

- F0 — Spec + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- **F3 — Shell móvil + sistema visual ← ACTUAL**
- F4 — Propiedades + unidades
- F5 — Candidatos + referencias
- F6 — Aseguradora/SURA
- F7 — Contratos
- F8 — Inquilino + portal
- F9 — Cargos + cartera
- F10 — Medios de pago
- F11 — Reporte + conciliación
- F12 — Servicios + obligaciones
- F13 — Pólizas + reclamaciones
- F14 — Solicitudes + mantenimiento
- F15 — Agenda + visitas
- F16 — Recordatorios + cron + email
- F17 — WhatsApp asistido
- F18 — Documentos + manual + inventarios
- F19 — Convivencia + contenido legal
- F20 — Membresías/servicios
- F21 — Bono + liquidación
- F22 — Seguridad + privacidad
- F23 — Dashboard final con datos reales
- F24 — QA PHP 8.3/8.4/8.5
- F25 — `SERVER_READY`
- F26 — Hostinger + smoke test

Ciclo por fase:

`SCOPE → PRE-KISS → SPEC/DISEÑO → PLAN → RED → GREEN → CONVERGE → QA → TWO LAWS GATE → CHECKPOINT`

---

# 6. GUARDRAILS CLAVE

- no introducir funcionalidades futuras como botones muertos;
- no crear PSE, WhatsApp API o módulos incompletos por anticipación;
- no modificar contratos históricos;
- no exponer documentos privados;
- no automatizar decisiones jurídicas;
- no duplicar fuentes de verdad;
- no agregar dependencia si CSS/PHP/HTML/JS mínimo resuelven mejor.

---

# 7. PROTOCOLO DE RECUPERACIÓN

Una nueva sesión debe:

1. leer README;
2. leer SPEC y PLAN maestros;
3. leer checkpoint más reciente;
4. revisar rama/PR de la fase actual;
5. revisar SPEC/plan específico de fase;
6. revisar CI/tests;
7. identificar el primer gate abierto;
8. confirmar ambas leyes;
9. no repetir fases cerradas sin evidencia de regresión;
10. no abrir una fase futura mientras la actual esté abierta;
11. no hacer merge sin gate y aprobación.

Prompt mínimo:

> Audita `sierraglobalcompany-rgb/AlquilaPro`. Lee README, SPEC, PLAN y el checkpoint más reciente. Recupera el estado real desde Git/tests/CI y continúa únicamente desde el primer gate abierto. Respeta Ley 1 KISS y Ley 2 Cero Ruido/Convergencia. No introduzcas sobrearquitectura ni código futuro sin uso real.

---

# 8. SIGUIENTE PASO

Completar y revisar la **SPEC específica de F3 — Shell móvil + sistema visual**. Solo después de su aprobación se puede crear el mini-plan de implementación y comenzar TDD.
