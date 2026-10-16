# AlquilaPro — R2 Foundation preflight checkpoint

**Fecha:** 2026-10-16  
**Task:** R2-FOUNDATION-PREFLIGHT  
**Estado:** READ-ONLY AUDIT COMPLETE / DESIGN APPROVAL PENDING  
**Base canónica:** `main@71445785f39caec7f4e7059a2a80ffbd75468b2d`  
**R1:** MERGED / CLOSED GREEN  
**R3:** NOT STARTED  
**DEPLOYMENT_AUTHORIZED:** NO

## Propósito de R2

R2 debe establecer la foundation visual compartida sobre la arquitectura SSR existente sin construir todavía landing/carrusel/login final de R3 ni introducir un segundo design system.

## Owners actuales confirmados

- `assets/app.css` — única hoja visual principal.
- `app/UI/View.php` — único renderer para auth/public/app.
- `resources/views/layouts/auth.php` — shell de autenticación.
- `resources/views/layouts/public.php` — shell público.
- `resources/views/layouts/app.php` — shell autenticado.
- `app/UI/Branding.php` — fuente de branding ya existente.
- `assets/app.js` — JS nativo mínimo.

No crear otra capa View/Theme/DesignSystem/NavigationService.

## Hallazgos

1. `assets/app.css` ya tiene tokens semánticos básicos, spacing implícito, radios, shadow, estados, focus, safe-area y breakpoints 420/768/1200.
2. `auth.php`, `public.php` y `app.php` consumen el mismo CSS y `--brand-primary`.
3. `public.php` reutiliza `auth-page`, `auth-shell` y `auth-card`. Esta dependencia estructural mezcla dos superficies que el baseline aprobado trata como experiencias distintas.
4. `public_landing.php` sigue siendo la landing histórica (`Encuentra tu próximo espacio`, CTA catálogo + login) y contradice el baseline R0. Eso pertenece a R3, no debe corregirse dentro de R2.
5. El baseline exige una foundation compartida y prohíbe estilos aislados por pantalla; también mantiene SSR, CSS/JS existentes y prohíbe un segundo catálogo.
6. `AssetContractTest` ya protege: sin imports/fonts remotos, safe areas, reduced motion, focus visible, touch targets, responsive images y primitives privadas existentes.
7. F3 histórico confirma la arquitectura que ya existe: una hoja CSS, View único, layouts SSR, sin framework CSS, sin build frontend, sin design-system framework.

## Recomendación KISS para R2

Converger, no reconstruir:

- mantener un solo `assets/app.css`;
- mantener `View.php` y los tres layouts;
- mantener `Branding` existente;
- crear una identidad estructural real para el shell público usando clases públicas propias dentro de `public.php` y `app.css`, sin implementar todavía contenido R3;
- consolidar solo tokens/primitives compartidos que ya tienen consumidores reales;
- no convertir CSS en utility framework;
- no crear componentes PHP genéricos ni nuevas clases de UI;
- no tocar módulos de negocio;
- no resolver todavía X/copy/carrusel/login final del baseline R3.

## Diseños candidatos

### A — Convergencia mínima recomendada

R2 cambia solo foundation existente:
- tokens/primitives compartidos en `app.css`;
- `public.php` deja de fingir ser auth shell y usa wrappers públicos propios;
- auth/app preservan comportamiento;
- tests estructurales fijan un solo CSS owner y separación auth/public;
- cero contenido/product flow nuevo.

### B — Refactor visual amplio

Reorganizar gran parte de `app.css`, renombrar selectores de múltiples módulos y migrar vistas actuales a nuevos primitives.

Rechazado como primera opción: demasiado churn/regresión para R2; mezcla foundation con limpieza masiva no demostrada.

### C — Dejar public shell para R3

R2 solo añade tokens y pospone separación public/auth.

No recomendado: obliga a R3 a cambiar foundation y pantalla pública al mismo tiempo, favoreciendo parches y hace más difícil separar causa raíz visual.

## Diseño propuesto para aprobación

**Arquitectura:** opción A.

**Alcance R2:**
1. Foundation tokens/primitives mínima en `assets/app.css`.
2. Shell público semánticamente independiente en `resources/views/layouts/public.php` usando el mismo CSS/branding.
3. Contratos estructurales TDD en tests UI/layout existentes o un único test de foundation si no existe owner adecuado.
4. Mantener auth/app sin rediseño de producto.
5. Ningún cambio de datos, rutas de dominio, DB, Composer, Node o framework.

**Fuera de R2:** carrusel, catálogo visual final, detalle, login final, X/cerrar, copy final de login, assets demo, dashboard redesign, módulos.

## Secuencia propuesta por tasks

- `R2.1 FOUNDATION CONTRACT RED`: tests estructurales que prueben separación public/auth + tokens mínimos/owner único.
- `R2.2 FOUNDATION GREEN`: cambio mínimo a `app.css` + `public.php`.
- `R2.3 REGRESSION`: suites UI/Auth/Foundation + full CI 8.3/8.4/8.5.
- `R2.4 AUDIT/CHECKPOINT`: KISS/Cero Ruido/Anti-parche, cierre y merge gate.

## Gate

No escribir código R2 hasta aprobación explícita del diseño arquitectónico presentado en chat. Un `continua` posterior a este diseño se toma como aprobación del gate, conforme a la regla del owner.
