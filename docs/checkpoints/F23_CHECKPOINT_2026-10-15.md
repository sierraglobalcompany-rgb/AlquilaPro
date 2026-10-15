# F23 checkpoint — Adaptive UX dashboard

**Fecha:** 2026-10-15  
**Fase:** F23 — cierre local; push autorizado pendiente  
**Rama:** `impl/f23-adaptive-ux-dashboard-20261015`  
**Base de fase:** `b2f859c37346b003d3db450705919612cab8db46`

## Estado del trabajo

| Hito | SHA del commit local | Resultado |
|---|---|---|
| F23-T2 — shell adaptativo y controles accesibles | `b61806c2b7a296f46ca08afeb4dc7c62c9521c11` | Completo |
| F23-T3 — acciones del dashboard | `d70875f827e23a7855bf8f3064ddf70edf4fb4d9` | Completo |
| F23-T4 — convergencia responsive | `7afb9c1e0fb7a593a3de44f44d79e7b4e2dcde3d` | Completo |
| F23-T5 — QA visual y de interacción | HEAD de fase `7afb9c1e0fb7a593a3de44f44d79e7b4e2dcde3d` | Completo |

El alcance implementa navegación de escritorio y táctil, accesos rápidos sujetos a permisos existentes y estilos adaptables para formularios, tablas y controles. No introduce tablas de producto, servicios ni dependencias de runtime.

Los SHA de la tabla identifican los commits locales de auditoría. La publicación mediante la conexión GitHub autenticada crea commits remotos equivalentes con SHA propios.

## Verificación local

- `composer validate --strict`: PASS.
- `git diff --check`: PASS.
- Suite completa: `php -d extension=gd vendor/bin/phpunit` — **610 tests, 6.826 assertions, 1 skipped; 0 failures/errors** (PHP 8.5.8). La extensión GD se habilitó solo para este proceso; no se modificó el `php.ini` compartido.
- El único skip es `DocumentStorageSecurityTest::testSymlinkedStoredFileFailsClosed`: el host Windows no permite crear el symlink requerido. La prueba está cubierta en el entorno CI Linux según la guía del repositorio.

## QA de navegador

La aplicación local se revisó en 375×812, 768×1024, 1024×768 y 1440×900. Se recorrieron login, dashboard de propietario, propiedades, unidad, candidatos, finanzas, mantenimiento, inicio de inquilino y contrato del inquilino. En las rutas y tamaños revisados no hubo desbordamiento horizontal. Los formularios mantuvieron entradas de 16 px y controles táctiles de al menos 48 px; el shell cambió a navegación inferior en tamaños táctiles y a sidebar agrupado en escritorio.

El diálogo de aplicaciones se comprobó en los tres tamaños táctiles: contenido desplazable dentro del diálogo cuando hace falta, destinos táctiles amplios, cierre con Escape y devolución del foco al botón que lo abrió. En escritorio se comprobaron grupos y enlaces visibles de navegación.

La hoja incluye reglas `prefers-reduced-motion` y `safe-area-inset`; el navegador de QA no emuló movimiento reducido y reportó insets seguros de cero. No se afirma validación física en dispositivo con notch.

## Cierre remoto y límites

- **CI remoto:** pendiente; ejecutar tras el único push F23 autorizado y registrar el resultado del SHA publicado.
- **PR:** actualizar PR draft #28; no fusionar.
- **Merge autorizado:** NO.
- **Deploy/producción:** NO.
- No se guardaron capturas ni credenciales temporales en el repositorio.

## Continuación

Tras el push F23 y la revisión del CI remoto, iniciar F24 en una rama apilada desde el cierre F23. Mantener F24/F25 en local-first según el prompt maestro; F26 es runbook solamente. No comenzar F27.
