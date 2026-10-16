# AlquilaPro

AlquilaPro es un sistema web SSR en PHP para administrar alquileres residenciales: propiedades y unidades, candidatos, contratos, arrendamientos, cartera, pagos, mantenimiento, documentos, seguros, comunidad, membresías, notificaciones y auditoría.

## Estado vivo

El README no es un snapshot por SHA ni por PR. El estado operativo vigente se mantiene en:

`docs/CURRENT_CHECKPOINT.md`

Para recuperar el proyecto después de un cambio de chat, contexto o sesión, leer primero ese archivo y después contrastar sus refs contra GitHub + CI.

Situación durable post-UAT:

- Pre-F26 owner UAT: **REJECTED**.
- F26: **FROZEN**.
- Deployment: **NO autorizado**.
- El artefacto F25 anterior es evidencia histórica; no es candidato de despliegue después de los cambios post-UAT.
- R0 baseline visual público aprobado: `docs/design/APPROVED_PUBLIC_UI_BASELINE_2026-10-15.md`.

## Roadmap post-UAT vigente

```text
R0  baseline visual
R1  auth
R2  design foundation
R3  landing + catálogo + detalle + login
R4  imágenes/assets demo
R5  dashboard + shell
R6  propiedades + unidades + media
R7  candidatos + wizard
R8  contratos + tenant
R9  finanzas
R10 módulos restantes
R11 demo completa
R12 human owner UAT
R13 reconstrucción F25
R14 Hostinger / F26
```

No abrir una fase nueva antes de cerrar la anterior conforme al checkpoint y CI. Merge y despliegue son gates separados; despliegue no se infiere de un merge.

## Leyes de desarrollo

### 1. KISS

La solución debe ser simple, directa, eficiente, optimizada y eficaz. Preferir A -> B. No crear nuevas tablas, servicios, providers, middlewares o capas si el owner actual puede resolver el contrato de forma clara.

### 2. Cero ruido

Orden obligatorio antes de agregar algo:

`ELIMINAR -> UNIR/CONVERGER -> SIMPLIFICAR -> MEJORAR/OPTIMIZAR -> AGREGAR`

No mantener autoridades duplicadas ni estados documentales supersedidos como estado vivo. La historia queda en Git; el puntero operativo queda en `docs/CURRENT_CHECKPOINT.md`.

### 3. Anti-parche

Para bugs o gaps:

`REPRODUCE -> ROOT CAUSE -> OWNER -> BUSCAR DUPLICACIÓN -> RED -> VERIFICAR RED -> MINIMAL GREEN -> RETIRAR SUPERSEDED -> REGRESSION`

No resolver fallos con excepciones laterales o parches sobre parches.

## Human-First UI/UX

La recuperación visual y de experiencia sigue el programa Human-First aprobado. Su auditoría viva y prioridades están enlazadas desde `docs/CURRENT_CHECKPOINT.md`.

Reglas durables:

- preservar verdad de negocio y permisos;
- traducir estados/datos técnicos a lenguaje humano sin crear una segunda autoridad;
- reducir densidad mediante progressive disclosure, no SPA ni frameworks paralelos;
- conservar un solo `assets/app.css` y un solo `assets/app.js`;
- no inventar KPIs para el dashboard;
- Browser real es gate obligatorio para cerrar QA visual cuando aplique.

## Método de trabajo

1. Contrato pequeño y verificable.
2. Para cambios funcionales: RED causal primero.
3. GREEN mínimo dentro del owner existente.
4. Suite focal.
5. Suite completa.
6. Matriz CI PHP 8.3 / 8.4 / 8.5 sobre SHA exacto cuando aplique.
7. Auditoría KISS / ruido / parches.
8. Actualizar `docs/CURRENT_CHECKPOINT.md` y checkpoint ref en gates relevantes.
9. Merge sólo en su gate autorizado.
10. Deployment sólo con autorización explícita independiente.

## Stack validado

- PHP soportado/validado: 8.3–8.5.
- MariaDB/MySQL mediante PDO.
- Slim SSR.
- Composer.
- PHPUnit.
- Sin SPA ni framework frontend paralelo.

## Fuente de verdad

Orden operativo:

1. GitHub del repositorio canónico.
2. CI del SHA exacto.
3. `docs/CURRENT_CHECKPOINT.md` y checkpoint ref canónico de la fase activa.
4. SPEC / PLAN aprobado aplicable.
5. `AGENTS.md` y README para metodología, identidad y guardrails durables.
6. Handoffs/chats sólo como recuperación cuando no contradicen las fuentes superiores.

Los documentos y artefactos históricos no sustituyen el estado vivo del repositorio, CI y checkpoint canónico.
