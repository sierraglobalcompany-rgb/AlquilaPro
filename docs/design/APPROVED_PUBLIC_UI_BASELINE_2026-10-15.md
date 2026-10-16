# AlquilaPro — Baseline visual público aprobado

Fecha: 2026-10-15
Estado: APPROVED_VISUAL_BASELINE
Owner UAT previo: REJECTED
F26: FROZEN
DEPLOYMENT_AUTHORIZED: NO

## Autoridad visual

Los mockups aprobados se preservan en la Library persistente del proyecto bajo:

`/AlquilaPro/Approved UI 2026-10-15/`

Archivos canónicos:

- `ALQUILAPRO_UI_BASELINE_MOBILE_APPROVED_2026-10-15.png`
- `ALQUILAPRO_UI_BASELINE_DESKTOP_APPROVED_2026-10-15.png`
- `ALQUILAPRO_UI_BASELINE_RESPONSIVE_APPROVED_2026-10-15.png`

La implementación debe respetar estas referencias y esta especificación. Ante duda visual, prevalece esta spec + los PNG aprobados.

## Mobile-first

Orden de prioridad de revisión:

1. 375x812
2. 768x1024
3. 1440x900

Desktop adapta el espacio; no redefine la jerarquía móvil.

## Landing pública móvil aprobada

- Logo AlquilaPro arriba a la izquierda.
- Sin navegación inferior pública antes de login.
- Sin hero de marketing tipo “Encuentra tu próximo hogar”.
- Título `Propiedades disponibles`.
- `Ver todas` a la derecha.
- Carrusel protagonista inmediatamente visible.
- Una propiedad/unidad principal por slide.
- Flechas laterales.
- Foto protagonista.
- Galería/thumbnails cuando aplique.
- Estado visible, por ejemplo `Disponible`.
- Nombre, ubicación, precio y atributos relevantes.
- `Ver detalle`.
- Indicadores/paginación.
- Debajo: módulo fijo `Iniciar sesión`.
- El módulo de login NO tiene botón X/cerrar.
- Sin Google login.
- Sin segunda sección `Más propiedades`.
- Sin scroll obligatorio para comprender carrusel + acceso.

## Landing pública desktop aprobada

- Logo AlquilaPro.
- `Propiedades disponibles` arriba.
- `Ver todas` a la derecha.
- Carrusel protagonista en la zona superior.
- Una propiedad principal centrada.
- Se ven parcialmente anterior/siguiente para comunicar carrusel.
- Tarjeta horizontal principal:
  - fotografías a la izquierda;
  - información a la derecha.
- Galería/thumbnails integrados.
- Precio, ubicación, habitaciones, baños, área, parqueadero/piso cuando existan.
- CTA `Ver detalle`.
- Login fijo debajo del carrusel.
- Sin marketing redundante.
- Sin Google login.
- Sin segunda grilla de propiedades.

## Regla de implementación

No crear un segundo catálogo. El carrusel usa el dominio público existente de propiedades/unidades.

No convertir a SPA. Mantener PHP SSR + CSS/JS existentes.

No inventar datos analíticos ni métricas.

No implementar estilos aislados por pantalla cuando una regla puede converger en la foundation visual compartida.

## Gates

La experiencia pública no se considera cerrada hasta que existan capturas reales en:

- 375x812
- 768x1024
- 1440x900

y sean comparadas contra el baseline aprobado.

Cualquier desviación deliberada debe documentarse y justificarse antes de continuar con el siguiente lote.
