# F24 — Cierre local de Release Readiness

Fecha: 2026-10-15
Rama: `impl/f24-release-readiness-20261015`
Base F24: `097c257170cf2c1cef9b5fe90b70e91705c79d32`
Product Head antes del cierre documental: `d072a88f29292e92be45e4352114191646a55844`

## Resultado

`F24_STATUS: GREEN_LOCAL`

Candidate wizard, installer wizard, limpieza/upgrade de migraciones, flujos críticos de release y matriz responsive/accesibilidad completados. El avance del candidato se deriva de sus datos existentes; el instalador escribe configuración y migra únicamente en la confirmación final. No se añadieron datos de muestra.

`NEW_PRODUCT_TABLES: 0`
`NEW_DOMAIN_SERVICES: 0`
`NEW_RUNTIME_DEPS: 0`
`MERGE_AUTHORIZED: NO`

## Gate de cierre

| Verificación | Entorno | Resultado |
| --- | --- | --- |
| `composer validate --strict` | Composer 2.10.2, Windows, PHP 8.5.8 | PASS (`composer.json` válido) |
| PHPUnit completo | PHP 8.3.33 + GD, MariaDB 11.4.12, DB aislada | PASS — 624 tests, 7.293 assertions, 0 failures |
| PHPUnit completo | PHP 8.4.25 + GD, MariaDB 11.4.12, DB aislada | PASS — 624 tests, 7.294 assertions, 0 failures |
| PHPUnit completo | PHP 8.5.10 + GD, MariaDB 11.4.12, DB aislada | PASS — 624 tests, 7.292 assertions, 0 failures |
| PHP parse lint | PHP 8.5.10; app, bootstrap, database, resources, tests e `index.php` | PASS — 248 archivos |
| `git diff --check` | Rama F24 | PASS |

La matriz local usa `TEST_DB_HOST=127.0.0.1`, `TEST_DB_PORT=3306`, `TEST_DB_NAME=alquila_test`, usuario root de una base de pruebas desechable por runtime y `TEST_DB_PASSWORD=root`. Cada base fue independiente; no se usaron datos de producción. Las imágenes PHP locales incluyen `pdo_mysql`, `mbstring`, `dom`, `gd` y `fileinfo`.

CI remota: `NOT RUN`. El contrato F23–F25 establece cierre local, push de la rama de cierre y ningún PR F24/F25.

## QA funcional y visual

- `ReleaseCriticalFlowsTest`: 1 test / 69 assertions PASS bajo zona horaria por defecto y UTC. Cubre instalador, login, inmueble/unidad, catálogo público, candidato, contrato/arrendamiento, portal, PDF, email local, cron protegido, archivos privados, QR de pago y permisos.
- Regresiones focales previas: Auth 30/168; Properties 54/395; Candidates 89/943; Contracts 38/526; Leases 66/903; Documents 14/123 (1 skip de symlink en Windows); Payments 24/459; Notifications 34/310. PASS salvo el skip de entorno indicado; el gate completo de cierre pasó en Linux/PHP Docker.
- Responsive Chromium real: 36 combinaciones de ruta y viewport en 375×812, 768×1024, 1024×768 y 1440×900; cuatro estados Apps; siete pasos del instalador en los cuatro viewports. Sin overflow horizontal ni controles interactivos visibles sin nombre accesible; foco de teclado, Escape, retorno de foco, tamaño de controles y `prefers-reduced-motion` verificados.
- F24-T5 corrigió un defecto reproducido: las etiquetas del diálogo Apps se partían en móvil de 375 px. Se redujo el padding/gap/icono/título y se restableció el salto normal de palabras en el breakpoint móvil.
- Contraste calculado para la paleta base: todos los pares comprobados superan 4.5:1. El portal `/tenant` se verificó en estado vacío; el flujo con arrendamiento está cubierto por `ReleaseCriticalFlowsTest`.

La inspección de contraste se limita a la paleta base probada y la matriz no representa una auditoría WCAG completa ni verifica cada combinación de marca personalizada. No se encontraron otros defectos reproducibles en la matriz.

## Checkpoints y límites

- `checkpoint/f24-task1-green-local-20261015` — `9df16bd30c6d50643662307e22c7a1a026ce72b0`
- `checkpoint/f24-task3-green-local-20261015` — `5409f34b469bb7d539c1773728b72afb1952c8a9`
- `checkpoint/f24-task4-green-local-20261015` — `c47cb2ab2eb047ad6c418237d85183da2f5f5538`
- `checkpoint/f24-task5-green-local-20261015` — `d072a88f29292e92be45e4352114191646a55844`
- T2 commit: `38ceef877eba2bd2b99ec5523d8b5da729b15526`.
- T6 full-suite closure se guarda en el commit que contiene este checkpoint. La rama de cierre se subirá sin PR y sin merge.
- F26 queda limitado al runbook manual; no se tocarán Hostinger, producción, DNS, base real, SMTP/cron de producción ni despliegue.
