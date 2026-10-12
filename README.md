# Alquila Pro

Sistema privado, mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos.

**Dominio previsto:** `https://alquilapro.sierraglobalcompany.com`  
**Estado actual:** F3 — Shell móvil + sistema visual **CLOSED / VERIFIED / MERGED**.  
**Siguiente fase:** F4 — Propiedades + unidades.  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + JS mínimo. Alpine.js solo cuando aporte valor real.  
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

`SPEC MAESTRA → PLAN MAESTRO → DECISIONES/ACLARACIONES CANÓNICAS DEL README → SPEC DE FASE → PLAN DE FASE → CHECKPOINT → TESTS/CI → CÓDIGO REAL`

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

# 4. DECISIÓN CANÓNICA — AUTENTICACIÓN ÚNICA

Alquila Pro usa **un solo módulo de autenticación** para todos los usuarios humanos de la aplicación.

Esto significa:

- propietario, administrador, gestor e inquilino usan el mismo endpoint/pantalla de login;
- no se crea un `tenant login`, `admin login` ni segundo sistema de sesiones;
- no se duplica recuperación de contraseña, CSRF, rate limiting ni cierre de sesión;
- la diferencia ocurre **después de autenticar**, según rol, permisos, estado y contexto del usuario;
- F8 puede crear/activar credenciales del inquilino y su portal, pero debe reutilizar el `AuthService`, `SessionGuard`, CSRF, recuperación y vistas/base auth existentes;
- las credenciales iniciales del inquilino siguen la SPEC: documento como usuario/identificador y contraseña inicial igual al documento, con cambio obligatorio en primer acceso, salvo revisión posterior explícita de producto/seguridad;
- cualquier propuesta futura que requiera un segundo módulo de login debe tratarse como conflicto con Ley 1 KISS y Ley 2 Cero Ruido hasta demostrar necesidad real.

Esta decisión **aclara y hace explícito** el presupuesto ya definido en SPEC/PLAN: `1 auth`.

---

# 5. ESTADO REAL DEL REPOSITORIO

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
F2_MERGE_SHA: 96a4fa59ca82604d821061c6aed7d9edb3849ae1
F2_QA: PHP 8.3 / 8.4 / 8.5 — 60 tests / 254 assertions

F3: CLOSED / VERIFIED / MERGED
F3_MERGE_SHA: b35c32b9f8c99646879e22b1af92e6a54690ceca
F3_VERIFIED_IMPLEMENTATION_HEAD: 76574f2c4e37bc9386bc00e81aabc9a75c3205a0
F3_DOCUMENTATION_HEAD: cfc07f9e750f80771b7b14fdb237ea38e57fbee6
F3_QA: PHP 8.3 / 8.4 / 8.5 — 94 tests / 458 assertions / 0 warnings
F3_VISUAL_QA: PASS — 360x800 / 390x844 / 412x915 / 768x1024 / 1440x1000 + 200% equivalent
LEY_1_KISS_F3: PASS
LEY_2_CERO_RUIDO_F3: PASS
LATEST_CLOSED_CHECKPOINT: docs/checkpoints/F3_CLOSED_2026-10-11.md

CURRENT_PHASE: F4 — Propiedades + unidades
NEXT_GATE: recuperar alcance F4 → PRE-KISS → SPEC/mini-plan de fase → TDD
```

---

# 6. F3 — QUÉ QUEDÓ ENTREGADO

F3 cerró el sistema visual real sin abrir módulos futuros:

- shell autenticado mobile-first;
- topbar y bottom navigation `Inicio · Apps · Cuenta`;
- launcher Apps mediante `<dialog>` nativo;
- Inicio y Cuenta reales;
- administración de usuarios F2 convergida al mismo shell;
- branding configurable con nombre, un color primario y logo opcional;
- logo almacenado fuera del webroot y servido por ruta controlada;
- auth e installer convergidos sobre una única hoja CSS;
- `resources/` protegido contra acceso HTTP directo;
- safe-area, foco visible, touch targets y reduced-motion;
- soporte de subcarpeta;
- cero rutas/botones de Cartera, Solicitudes o Propiedades antes de sus fases.

Deltas arquitectónicos F3:

```text
COMPOSER_DEPENDENCIES_DELTA: +0
DB_MIGRATIONS_DELTA: +0
DB_TABLES_DELTA: +0
NODE_NPM_BUILD_DELTA: +0
NEW_REAL_ROUTES: /account, /admin/appearance, /brand/logo
INSTALLED_ROOT: plain text -> authenticated app shell
FUTURE_MODULE_ROUTES: +0
```

---

# 7. ROADMAP

- F0 — Spec + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- **F4 — Propiedades + unidades ← ACTUAL**
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

# 8. GUARDRAILS CLAVE

- no introducir funcionalidades futuras como botones muertos;
- no crear PSE, WhatsApp API o módulos incompletos por anticipación;
- no modificar contratos históricos;
- no exponer documentos privados;
- no automatizar decisiones jurídicas;
- no duplicar fuentes de verdad;
- no agregar dependencia si CSS/PHP/HTML/JS mínimo resuelven mejor;
- no crear un login separado para inquilinos, candidatos o administradores mientras el auth único pueda resolverlo;
- no abrir F5 mientras F4 permanezca abierta.

---

# 9. PROTOCOLO DE RECUPERACIÓN

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

# 10. SIGUIENTE PASO

Recuperar y congelar el alcance exacto de **F4 — Propiedades + unidades**, aplicar PRE-KISS y crear la SPEC/mini-plan de fase antes del primer RED.
