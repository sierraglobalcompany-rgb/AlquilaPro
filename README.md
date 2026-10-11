# Alquila Pro

Sistema privado, mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos.

**Dominio previsto:** `https://alquilapro.sierraglobalcompany.com`  
**Estado actual:** F1 — Foundation + Installer **CLOSED / VERIFIED / MERGED**.  
**Siguiente fase permitida:** **F2 — Autenticación, usuarios y permisos**.  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + Alpine.js + PHPMailer.  
**Destino:** Hostinger, instalación portable en subdominio o subcarpeta HTTPS.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Toda persona, ChatGPT, Codex, Antigravity u otra IA que retome este proyecto debe leer en este orden antes de tocar código:

1. [`README.md`](README.md)
2. [`docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md`](docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.1_AUDITADO.md)
3. [`docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md`](docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.1_AUDITADO.md)
4. checkpoint más reciente en [`docs/checkpoints/`](docs/checkpoints/)
5. tests y CI reales
6. código real del repositorio

La SPEC y el PLAN son los documentos canónicos completos. Este README es el **hub de continuidad** y no debe duplicarlos íntegramente.

Si existe contradicción entre documentación y repositorio, no se adivina: se audita el estado real y se corrige la fuente correspondiente.

---

# 2. LAS DOS LEYES SUPREMAS

## LEY 1 — KISS / ARQUITECTURA SIMPLE

Toda solución debe ser:

- simple;
- sencilla;
- optimizada;
- eficiente;
- eficaz;
- rápida;
- fácil de auditar;
- fácil de actualizar;
- fácil de mejorar;
- fácil de desplegar.

> Entre dos soluciones correctas gana la que use menos piezas, menos estados, menos dependencias, menos capas y menos conocimiento implícito, siempre que conserve seguridad, trazabilidad y requisitos funcionales.

Arquitectura base:

- 1 aplicación PHP;
- 1 base de datos MariaDB/MySQL;
- 1 repositorio;
- HTML renderizado en servidor;
- Alpine.js solo donde aporte valor;
- sin microservicios;
- sin Redis;
- sin workers;
- sin event bus;
- sin SPA completa;
- sin capas genéricas por costumbre.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Se considera ruido, entre otros:

- código muerto;
- duplicación;
- rutas repetidas;
- tablas sin ciclo de vida propio;
- estados equivalentes;
- capas que solo delegan;
- dependencias sin uso;
- documentación obsoleta;
- flags temporales permanentes;
- módulos, botones o integraciones futuras que aún no funcionan.

Cada fase debe cerrar con:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
```

Y demostrar qué agregó, eliminó, unió/convergió, qué cambió en dependencias, esquema y rutas, y qué pruebas lo verifican.

---

# 3. QUÉ RESUELVE ALQUILA PRO

Alquila Pro centraliza:

- propiedades, apartamentos y parqueaderos;
- candidatos y referencias;
- estudio de aseguradora/SURA;
- contratos y documentos;
- inquilinos;
- cartera, pagos y conciliación;
- medios de pago, Bre-B y QR;
- servicios públicos y cargos;
- pólizas y reclamaciones;
- solicitudes, mantenimiento y visitas;
- recordatorios y comunicaciones;
- convivencia y manuales;
- inventarios;
- membresías/servicios configurables;
- bono potencial;
- liquidación de terminación;
- administradores, permisos y auditoría.

La V1 no pretende ser un ERP contable, un SaaS multiempresa ni una plataforma masiva de inmobiliarias.

---

# 4. FLUJO MAESTRO

```text
CANDIDATO
  ↓
Datos + referencias + documentos
  ↓
Estudio aseguradora
  ↓
Decisión propietario
  ↓
Contrato + firma + anexos
  ↓
Inventario inicial
  ↓
ARRENDAMIENTO ACTIVO
  ↓
Cartera + pagos + póliza + servicios + solicitudes + convivencia
  ↓
Renovación o terminación
  ↓
Inventario final + liquidación + bono si aplica
  ↓
CERRADO
```

---

# 5. UX

Dirección visual: **Residential OS / PropTech premium**.

Administrador:

**Inicio · Cartera · [APPS] · Solicitudes · Cuenta**

Inquilino:

**Inicio · Pagos · [APPS] · Solicitudes · Cuenta**

Principios:

- mobile-first;
- navegación inferior;
- botón central Apps;
- tarjetas con jerarquía clara;
- touch targets cómodos;
- progressive disclosure;
- cero gráficas decorativas;
- cero tablas cuando una tarjeta resuelve mejor.

---

# 6. GUARDRAILS CLAVE

- no disfrazar depósitos o garantías;
- no ligar técnicamente membresía y bono;
- no afirmar cobertura SURA sin revisar póliza;
- no convertir daños automáticamente en deuda;
- no automatizar terminación contractual;
- no modificar contratos históricos;
- no registrar WhatsApp como entregado sin API;
- no fabricar QR bancario interoperable;
- no crear módulo PSE vacío;
- no exponer documentos privados por URL pública;
- no almacenar credenciales bancarias;
- contenido legal versionado y actualizable.

---

# 7. ESTADO REAL DEL REPOSITORIO

```text
PROJECT: Alquila Pro
REPO: sierraglobalcompany-rgb/AlquilaPro
TARGET: alquilapro.sierraglobalcompany.com
SPEC: V1.1 AUDITADA
PLAN: V1.1 AUDITADO
F0: CLOSED
F1: CLOSED / VERIFIED / MERGED
F1_MERGE_SHA: a5225151358aaeca4698adf8cb85d815752a5ac4
F1_QA: PHP 8.3 / 8.4 / 8.5 — 32 tests / 110 assertions
LEY_1_KISS_F1: PASS
LEY_2_CERO_RUIDO_F1: PASS
LATEST_MERGED_CHECKPOINT: docs/checkpoints/F1_MERGED_2026-10-11.md
NEXT: F2 — AUTH / USERS / PERMISSIONS
```

F1 quedó integrada mediante PR #1.

---

# 8. ROADMAP

- F0 — Spec + plan
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos
- F3 — Shell móvil + sistema visual
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

Cada fase sigue:

`SCOPE → PRE-KISS → RED → GREEN → CONVERGE/REFACTOR → QA → TWO LAWS GATE → CHECKPOINT`

---

# 9. PROTOCOLO DE RECUPERACIÓN

Una nueva sesión debe:

1. leer README;
2. leer SPEC;
3. leer PLAN;
4. leer checkpoint más reciente;
5. revisar `main`, ramas y PR reales;
6. revisar CI y tests;
7. identificar el primer gate abierto;
8. confirmar ambas leyes del último checkpoint;
9. no repetir fases cerradas sin evidencia de regresión;
10. no abrir una fase futura si la anterior está abierta;
11. no hacer merge sin gate y aprobación.

Prompt mínimo:

> Audita `sierraglobalcompany-rgb/AlquilaPro`. Lee README, SPEC, PLAN y el checkpoint más reciente. Recupera el estado real desde Git/tests/CI y continúa únicamente desde el primer gate abierto. Respeta Ley 1 KISS y Ley 2 Cero Ruido/Convergencia. No introduzcas sobrearquitectura ni código futuro sin uso real.

---

# 10. SIGUIENTE PASO

Crear rama nueva desde `main` y ejecutar **F2 — Autenticación, usuarios y permisos** con mini-plan propio y TDD.
