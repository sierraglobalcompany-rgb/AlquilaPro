# Alquila Pro

Aplicación web mobile-first y autoinstalable para administrar arrendamientos de vivienda y parqueaderos, con **superficie pública de catálogo** y operación privada para administración/inquilinos.

**Dominio objetivo:** `https://alquilapro.sierraglobalcompany.com`  
**Estado:** F0–F3 **CLOSED / VERIFIED / MERGED**  
**Fase siguiente:** F4 — Propiedades + unidades + landing + catálogo público  
**Stack:** PHP 8.3–8.5 + Slim 4 + MariaDB/MySQL + PDO + HTML server-side + CSS/JS mínimo  
**Destino:** Hostinger; subdominio o subcarpeta HTTPS.

---

# 1. PUNTO DE ENTRADA OBLIGATORIO

Toda persona, ChatGPT, Codex, Antigravity u otra IA debe leer en este orden antes de tocar código:

1. [`README.md`](README.md)
2. [`docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md`](docs/specs/ALQUILA_PRO_SPEC_MAESTRO_V1.2_AUDITADO.md)
3. [`docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md`](docs/plans/ALQUILA_PRO_PLAN_DESARROLLO_V1.2_AUDITADO.md)
4. [`docs/checkpoints/ARCH_REAUDIT_V1_2_PUBLIC_CATALOG_2026-10-11.md`](docs/checkpoints/ARCH_REAUDIT_V1_2_PUBLIC_CATALOG_2026-10-11.md)
5. checkpoint más reciente de la fase cerrada/abierta
6. SPEC/plan específico de fase, si existe
7. tests y CI reales
8. código real del repositorio

Orden de autoridad:

`SPEC V1.2 → PLAN V1.2 → README/decisiones canónicas → SPEC/PLAN de fase → checkpoint → tests/CI → código`

V1.1 queda histórica y no debe usarse como autoridad para trabajo nuevo.

---

# 2. LAS DOS LEYES SUPREMAS

## LEY 1 — KISS

Elegir la solución correcta con menos piezas, tablas, estados, dependencias y capas, conservando seguridad, trazabilidad e integridad.

## LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio:

> **ELIMINAR → UNIR / CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR**

Ruido incluye:

- código muerto;
- duplicación;
- tablas equivalentes;
- dos fuentes de precio/disponibilidad;
- segundo auth;
- CMS de catálogo paralelo;
- lead/application paralelo al candidato;
- rutas/botones futuros;
- dependencias no usadas;
- checkpoints/docs obsoletos.

Toda fase cierra con:

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS
```

---

# 3. ESTADO REAL DEL REPOSITORIO

```text
PROJECT: Alquila Pro
REPO: sierraglobalcompany-rgb/AlquilaPro
TARGET: alquilapro.sierraglobalcompany.com
SPEC: V1.2 AUDITADA (rama documental hasta aprobar/mergear)
PLAN: V1.2 AUDITADO (rama documental hasta aprobar/mergear)

F0: CLOSED
F1: CLOSED / VERIFIED / MERGED
F1_MERGE_SHA: a5225151358aaeca4698adf8cb85d815752a5ac4

F2: CLOSED / VERIFIED / MERGED
F2_MERGE_SHA: 96a4fa59ca82604d821061c6aed7d9edb3849ae1
F2_QA: PHP 8.3 / 8.4 / 8.5 — 60 tests / 254 assertions

F3: CLOSED / VERIFIED / MERGED
F3_MERGE_SHA: b35c32b9f8c99646879e22b1af92e6a54690ceca
F3_QA: PHP 8.3 / 8.4 / 8.5 — 94 tests / 458 assertions / 0 warnings
F3_VISUAL_QA: PASS
F3_MERGED_CHECKPOINT: docs/checkpoints/F3_MERGED_2026-10-11.md

F4: NOT STARTED
CURRENT_GATE: revisión/merge documentación V1.2 → SPEC específica F4
```

No reimplementar F1–F3 sin evidencia de regresión.

---

# 4. DECISIONES CANÓNICAS V1.2

## 4.1 Una sola autenticación

Propietario, administrador, gestor e inquilino usan el **mismo módulo de login, sesión, CSRF, cambio, recuperación y logout**.

No crear:

- tenant login;
- admin login;
- segundo AuthService;
- segunda tabla de sesiones.

### Hueco real auditado

El código F2 actual autentica por `users.email` únicamente.

Cuando F8 implemente inquilinos debe **evolucionar** el mismo auth, no duplicarlo.

Diseño preferido a congelar en SPEC F8:

- `users.login_identifier` UNIQUE;
- backfill de usuarios existentes con email normalizado;
- inquilino usa documento normalizado como `login_identifier`;
- email continúa como contacto;
- mismo AuthService/SessionGuard/CSRF/reset;
- login UI puede decir `Correo o documento`.

No implementar esto antes de F8 salvo necesidad real demostrada.

## 4.2 Landing pública

Una vez instalada:

- visitante sin sesión en `/` → landing pública;
- landing tiene **Iniciar sesión** y **Catálogo**;
- usuario autenticado en `/` conserva el shell privado;
- antes de instalar, `/` conserva installer.

No crear otro sitio/aplicación para catálogo.

## 4.3 Catálogo = unidades, no CMS paralelo

El catálogo público es una proyección de `units`.

No crear:

- `catalog_listings`;
- `apartment_catalog`;
- `parking_catalog`;
- copia de precio/disponibilidad.

Una unidad se publica solo si:

- `is_public = true`;
- estado = `Disponible`;
- propiedad activa;
- datos públicos mínimos válidos.

`En proceso`, `Ocupada`, `Inactiva` o `Mantenimiento` desaparecen del catálogo automáticamente.

## 4.4 Unidades

Una sola tabla/entidad `units` para:

- apartamento;
- parqueadero.

No CRUD/tablas paralelas mientras campos nullable por tipo sean claros.

Una sola fuente de precio comercial en unidad. El contrato posterior guarda el canon final como snapshot.

## 4.5 Media

- fotos de marketing: `unit_media`;
- JPEG/PNG/WebP;
- fuera del webroot;
- MIME real + tamaño + nombres aleatorios;
- solo se sirven públicamente si la unidad sigue publicable;
- video V1: URL HTTPS externa;
- video self-hosted/transcoding: P2.

## 4.6 Aplicar = candidato único

No tabla/CRM `lead` o `application` paralela.

F5 activa el CTA `Aplicar` y crea/continúa el mismo `candidate`, enlazado a la unidad.

El candidato puede continuar mediante token seguro sin segundo login tradicional.

F4 **no muestra botón Aplicar** hasta que F5 tenga ruta real.

## 4.7 Administración de catálogo

Publicación/precio/fotos/descripción viven dentro de Propiedades/Unidades.

Puede existir una vista UI “Catálogo”, pero comparte datos y permisos.

Permisos previstos mínimos:

- `properties.view`;
- `properties.manage`.

No `catalog.manage` salvo necesidad independiente futura.

---

# 5. ROADMAP V1.2

- F0 — SPEC + plan ✅
- F1 — Foundation + installer ✅
- F2 — Auth + usuarios + permisos ✅
- F3 — Shell móvil + sistema visual ✅
- **F4 — Propiedades + unidades + landing + catálogo público ← SIGUIENTE**
- F5 — Aplicaciones + candidatos + referencias
- F6 — Aseguradora/SURA
- F7 — Contratos
- F8 — Inquilino + lease + portal usando auth único
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
- F25 — SERVER_READY
- F26 — Hostinger + smoke test

---

# 6. F4 — ALCANCE MACRO, TODAVÍA NO IMPLEMENTADO

F4 debe incluir:

- properties;
- units;
- unit_media;
- apartment/parking;
- estados;
- precio comercial;
- publicación;
- fotos;
- video URL;
- requisitos públicos resumidos;
- UI administrativa;
- landing pública;
- `/catalog`;
- `/catalog/{slug}`;
- media pública controlada;
- permisos properties;
- subfolder/security/tests.

F4 NO incluye:

- candidate;
- CTA Aplicar;
- contratos;
- leases;
- documentos privados;
- video upload/transcoding;
- SEO avanzado;
- CMS paralelo.

Antes de código F4 se requiere SPEC específica + plan ejecutable aprobados.

---

# 7. PROTOCOLO DE RECUPERACIÓN

Una nueva IA debe:

1. leer este README;
2. leer SPEC V1.2;
3. leer PLAN V1.2;
4. leer checkpoint de reauditoría V1.2;
5. verificar `main`, ramas y PRs reales;
6. revisar CI/tests;
7. identificar primer gate abierto;
8. aplicar ambas leyes;
9. no reabrir fases cerradas sin evidencia;
10. no implementar F5 antes de cerrar/mergear F4;
11. no hacer merge sin aprobación explícita.

Prompt mínimo:

> Audita `sierraglobalcompany-rgb/AlquilaPro`. Lee `README.md`, SPEC V1.2, PLAN V1.2 y el checkpoint V1.2 más reciente. Recupera el estado real desde Git/tests/CI y continúa únicamente desde el primer gate abierto. Respeta Ley 1 KISS y Ley 2 Cero Ruido/Convergencia. Mantén un solo auth, un solo modelo de units, catálogo derivado de units y candidato único. No introduzcas módulos futuros ni fuentes paralelas.

---

# 8. GATE ACTUAL

La arquitectura V1.2 está documentada en la rama:

`spec/v1.2-public-catalog-reaudit-20261011`

Siguiente acción correcta:

1. revisar SPEC/PLAN V1.2;
2. integrar documentación a `main` tras aprobación;
3. crear SPEC específica F4;
4. revisar/aprobar SPEC F4;
5. escribir plan F4;
6. revisar/aprobar plan;
7. entonces TDD.

**No hay código F4 iniciado.**
