# F25 — Ensayo del ZIP SERVER_READY

**Fecha:** 2026-10-15  
**Resultado:** `REHEARSAL_RESULT: PASS`  
**Artefacto:** `ARTIFACT: ACCEPTED`  
**Preparación local:** `SERVER_READY: YES`

## Identidad inmutable del artefacto

| Campo | Valor |
|---|---|
| Archivo | `ALQUILA_PRO_V1_SERVER_READY.zip` |
| Versión | `1.0.0` |
| SHA de origen | `647a02d2bd8a06cb034e8cb553151c3b471289da` |
| Tamaño | `4,729,967 bytes` |
| SHA-256 | `3eb9376fee8d45dac0211e3a9fcd14e6f93069b248c6537c98ab2a85fedf34b3` |
| Manifiesto | `ALQUILA_PRO_V1_SERVER_READY.manifest.json` |
| Contenido del manifiesto | 700 archivos y 139 directorios |

El SHA-256 se registró y verificó antes de extraer el ZIP. Se utilizó ese mismo archivo durante toda la secuencia; no se reconstruyó ni modificó. La auditoría estática del checkpoint T3 confirmó la allowlist, ausencia de archivos prohibidos y secretos, ausencia de dependencias de desarrollo, integridad de las huellas, origen/versión y carga del autoloader de producción desde una extracción nueva. El test focal terminó con 1 test y 23,979 assertions, sin fallos.

## Entorno aislado

- Extracción nueva: `C:\codex\F25-rehearsal-2026-10-15-r4`.
- Base MariaDB desechable `alquila_f25_rehearsal_r4`; antes del instalador tenía cero tablas.
- PHP 8.5.11 con Apache 2.4.68; MariaDB 11.4.12.
- SMTP dirigido a Mailpit local; destinatarios de prueba usan el dominio reservado `.invalid`.
- Sin credenciales de producción, DNS, Hostinger ni servicios externos de entrega.
- Documentos privados fuera del document root en `/srv/alquilapro-private/documents`, propiedad de `www-data`, permisos `0700`.

## Secuencia y evidencia

| Paso | Resultado | Evidencia observada |
|---|---|---|
| Extraer ZIP exacto | PASS | 700 archivos; SHA-256 coincidente antes de extraer |
| Requisitos e instalador | PASS | Requisitos runtime presentes; conexión DB probada; empresa y owner ingresados; resumen sin passwords; instalación y migraciones finalizaron |
| Login | PASS | Inicio de sesión del owner después de instalación |
| Inmueble, unidad y catálogo | PASS | Inmueble ficticio, APTO-101 y foto sintética; listado y detalle públicos mostraron datos publicados |
| Wizard y revisión del candidato | PASS | Siete pasos completados; candidato ficticio revisado en administración; aprobación de aseguradora marcada explícitamente como simulación |
| Contrato y arrendamiento | PASS | Plantilla/versionado y preview; contrato urbano representativo finalizado y lease activado |
| Portal y PDF | PASS | Tenant inició sesión y abrió detalle del lease; ruta PDF respondió 200, `application/pdf`, 10,487 bytes y firma `%PDF-` |
| Upload y descarga privada | PASS | PDF adjunto al lease; descargas autenticadas de admin y tenant respondieron 200; URL directa sin sesión redirigió a login |
| Aislamiento de rutas | PASS | Acceso directo a `/vendor/autoload.php`, configuración de ejemplo, `/storage/app/`, `/storage/contracts/` y el PDF generado fue denegado (403); directorio privado externo no era publicable |
| Método de pago y QR | PASS | Método ficticio y PNG de ensayo visible en el portal; QR autenticado respondió 200 y firma PNG válida; URL sin sesión redirigió a login |
| Email local y cron protegido | PASS | POST de cron sin header respondió 403; con secreto solo de prueba respondió 200, generó 1 y envió 3 mensajes locales, 0 fallidos; Mailpit mostró exclusivamente destinatarios `.invalid` |

El PDF usado fue sintético. El método de pago y su QR indicaban que eran de ensayo y no servían para pagar. No se transmitieron mensajes reales ni se usaron datos personales reales.

## Ajustes del entorno de prueba

Una tentativa descartable anterior (`r3`) no quedó certificada por una configuración incompleta del sink SMTP. La certificación se reinició desde un ZIP idéntico y una base nueva, con Mailpit local listo; solo la ejecución limpia `r4` descrita arriba determina el PASS.

En `r4`, el primer upload privado mostró que el directorio externo aún no existía y el usuario Apache no podía crearlo bajo `/srv`. Se clasificó como configuración del harness local: se creó el directorio fuera del document root, se asignó a `www-data` con modo `0700` y se repitió el upload correctamente. No se editó el ZIP, el código ni la base de datos para corregirlo.

## Cierre

- `STATIC_ARTIFACT_AUDIT: PASS`
- `CLEAN_EXTRACTION: PASS`
- `EMPTY_DATABASE_BEFORE_INSTALL: PASS`
- `EXACT_ZIP_REHEARSAL: PASS`
- `ARTIFACT: ACCEPTED`
- `SERVER_READY: YES`
- Hostinger, producción y DNS no fueron tocados.

No se guardan aquí passwords, secretos, tokens de continuación ni datos privados.
