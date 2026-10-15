# Runbook manual Hostinger — AlquilaPro V1

`DEPLOYMENT_STATUS: DEPLOYMENT_HANDOFF_READY`  
`F26_PREFLIGHT_PUBLIC_DOCS: PASS_WITH_ACCOUNT_PROBE_PENDING`  
`PRODUCTION_SMOKE: NOT RUN`  
`DEPLOYMENT_AUTHORIZED: NO`

## Alcance

Destino previsto: **https://alquilapro.sierraglobalcompany.com**.

Este documento prepara una operación manual futura. No se subió ningún archivo, ni se cambió Hostinger, DNS, SSL, base de datos, SMTP, cron o producción. La ejecución requiere autorización explícita y la confirmación de las compuertas de este runbook.

F23, F24 y F25 ya están integradas en `main`. F26 no modifica producto por defecto: primero valida el entorno real de Hostinger. Si una limitación del plan obliga a cambiar código, se detiene F26 y la corrección vuelve al repositorio, con nueva certificación y nuevo artefacto F25.

## Artefacto autorizado por F25

Usar solo el archivo `ALQUILA_PRO_V1_SERVER_READY.zip` de versión `1.0.0`, con tamaño `4,729,967 bytes` y SHA-256 `3eb9376fee8d45dac0211e3a9fcd14e6f93069b248c6537c98ab2a85fedf34b3`. Su SHA de origen es `647a02d2bd8a06cb034e8cb553151c3b471289da`. El manifiesto adyacente es `ALQUILA_PRO_V1_SERVER_READY.manifest.json`.

Antes de una futura transferencia, recalcular el SHA-256 y compararlo byte por byte con este checkpoint. Si no coincide, detenerse: no extraer ni instalar ese candidato. No desplegar un checkout Git ni reconstruir el ZIP en Hostinger.

## Resultado del preflight documental Hostinger

La documentación oficial revisada el 2026-10-15 confirma:

- Web/Cloud permite PHP soportado y configuración de extensiones/opciones desde hPanel.
- Hostinger usa LiteSpeed en Web/Cloud y soporta `.htaccess` para configuración por sitio.
- La documentación oficial muestra uso de `SetEnv` dentro de `.htaccess`, por lo que existe una vía candidata para exponer configuración a la ejecución web sin cambiar AlquilaPro.
- Cron Jobs ofrece tipo **Custom** para comandos/scripts.
- Si el comando necesita caracteres especiales, Hostinger recomienda guardarlo en un archivo `.sh` y ejecutar ese script desde el cron.
- Hostinger documenta `curl` como herramienta para probar ejecuciones por URL.
- El scheduler de hPanel trabaja en UTC.

Esto resuelve el diseño del cron, pero **no sustituye la prueba en el plan/hPanel concreto**. La documentación pública no confirma una pantalla de variables de entorno para PHP equivalente a la disponible para Node.js.

Referencias oficiales adicionales:

- https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/
- https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/
- https://www.hostinger.com/support/1583514-troubleshooting-cron-jobs-at-hostinger/
- https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/
- https://www.hostinger.com/support/4622479-how-to-change-values-of-php-parameters-in-hostinger/
- https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/

## Compuertas previas obligatorias

1. Confirmar que el subdominio y su document root apuntan intencionalmente a esta instalación. Cambios DNS quedan fuera de este runbook y requieren autorización aparte.
2. Confirmar si el destino contiene un sitio o datos. Si existe, generar y verificar respaldo de archivos y base antes de reemplazar nada. No ejecutar el instalador sobre una base existente sin autorización y respaldo comprobable.
3. Confirmar en hPanel PHP `8.3`, `8.4` o `8.5` y las extensiones que exige el ZIP: `pdo_mysql`, `mbstring`, `dom`, `gd` y `fileinfo`. Corregir extensiones en PHP Configuration; no omitir los chequeos del instalador.
4. Crear una base MySQL nueva y su usuario desde hPanel. Mantener el password fuera del repositorio, de este documento, del chat y de URLs.
5. Ejecutar el **probe de variables PHP** descrito abajo antes de introducir cualquier secreto.
6. Confirmar que el plan concreto muestra Cron Jobs y permite un cron tipo **Custom** que ejecute un `.sh`. No poner el secreto en el campo Command de hPanel.
7. Elegir y probar el proveedor SMTP, puerto y cifrado compatibles. Para Hostinger Email, su guía documenta `smtp.hostinger.com`, SSL 465 o TLS/STARTTLS 587; verificar primero que la cuenta contratada sea Hostinger Email, pues Titan u otro proveedor usa datos distintos.
8. Definir la ubicación escribible para documentos privados, fuera del acceso HTTP directo. El valor opcional `ALQUILAPRO_DOCUMENT_DIRECTORY` permite indicar ruta absoluta. Si no se define, la app usa una carpeta privada hermana de la raíz del proyecto; confirmar permisos del usuario PHP.
9. Acordar ventana de mantenimiento, responsable de respaldo y procedimiento de rollback antes de cargar archivos.

## Probe seguro de variables PHP — antes de secretos

AlquilaPro lee configuración runtime mediante `getenv()`. No cambiar código ni reconstruir el ZIP antes de comprobar el comportamiento real del hosting.

### Objetivo

Demostrar únicamente:

```text
SetEnv en configuración del sitio
→ PHP web
→ getenv()
```

con un valor **ficticio**, nunca con password, token SMTP ni secreto de cron.

### Procedimiento

1. En una ventana de mantenimiento/autorización F26, añadir temporalmente en el `.htaccess` activo del sitio:

```apache
SetEnv ALQUILAPRO_ENV_PROBE f26-probe
```

2. Crear temporalmente un archivo de prueba que revele **solo PASS/FAIL**, nunca el contenido de variables ni `phpinfo()`:

```php
<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
echo hash_equals('f26-probe', (string) getenv('ALQUILAPRO_ENV_PROBE')) ? 'PASS' : 'FAIL';
```

3. Solicitarlo una sola vez por HTTPS.
4. Resultado requerido: `PASS`.
5. Eliminar inmediatamente el archivo de prueba y la variable ficticia.
6. Verificar que el archivo temporal ya responde 404/403.

### Decisión

- `PASS`: el mecanismo `SetEnv → getenv()` queda demostrado para ese sitio y puede usarse como configuración de despliegue sin cambiar el producto.
- `FAIL`: marcar `DEPLOYMENT_STATUS: HOLD`. No crear fallbacks, `.env` loaders, bootstrap paralelo ni parches en producción. Volver al owner de configuración y decidir si hace falta una solución de producto; si el código cambia, generar nuevo ZIP, nuevo hash, auditoría F25 y rehearsal desde cero.

### Manejo de secretos si el probe pasa

Preferir primero cualquier mecanismo nativo seguro por sitio que el hPanel concreto ofrezca para PHP. Si no existe y se usa `SetEnv`:

- editarlo solo como configuración del servidor después de extraer el ZIP; nunca commitearlo ni incorporarlo al ZIP;
- comprobar antes que solicitudes HTTP directas a `/.htaccess` son denegadas;
- restringir acceso al hPanel/FTP/SFTP a operadores autorizados;
- no mostrar `phpinfo()` en producción;
- no copiar secretos a tickets, chat, screenshots o logs;
- rotar credenciales si se sospecha exposición.

Variables requeridas actualmente:

```text
ALQUILAPRO_SMTP_HOST
ALQUILAPRO_SMTP_PORT
ALQUILAPRO_SMTP_USERNAME
ALQUILAPRO_SMTP_PASSWORD
ALQUILAPRO_SMTP_ENCRYPTION
ALQUILAPRO_SMTP_FROM_EMAIL
ALQUILAPRO_SMTP_FROM_NAME
ALQUILAPRO_CRON_SECRET
```

Opcional según instalación:

```text
ALQUILAPRO_DOCUMENT_DIRECTORY
ALQUILAPRO_GOOD_CONDUCT_BONUS_ENABLED
```

## Cron protegido — diseño confirmado por documentación pública

Hostinger permite cron tipo **Custom** y recomienda `.sh` para comandos con caracteres especiales. El secreto no debe aparecer en la URL ni en el campo del cron de hPanel.

### Diseño KISS

1. Crear un directorio privado fuera del document root si el plan lo permite.
2. Guardar un archivo de secretos privado, por ejemplo `alquilapro-cron.env`, con permisos mínimos y sin acceso HTTP.
3. Guardar un script `alquilapro-cron.sh`, también fuera del webroot.
4. El cron de hPanel ejecuta **solo la ruta del script**.
5. El script carga el secreto localmente y hace el POST HTTPS.

Ejemplo conceptual — reemplazar rutas reales solo durante F26:

```sh
#!/bin/sh
set -eu
. /home/USER/domains/DOMAIN/private/alquilapro-cron.env

curl --fail --silent --show-error \
  --request POST \
  --header "X-AlquilaPro-Cron-Secret: ${ALQUILAPRO_CRON_SECRET}" \
  "https://alquilapro.sierraglobalcompany.com/cron/notifications"
```

Archivo privado de entorno conceptual:

```sh
ALQUILAPRO_CRON_SECRET='GENERAR_UN_SECRETO_ALEATORIO_DE_AL_MENOS_32_CARACTERES'
export ALQUILAPRO_CRON_SECRET
```

Reglas:

- nunca usar `curl -v` en el cron;
- nunca poner el secreto en query string;
- nunca escribir el secreto con `echo`;
- no guardar el secreto dentro de `public_html` si el plan permite directorio privado;
- permisos recomendados: secreto `0600`, script `0700`, sujetos a capacidades reales del hosting;
- el output del cron debe contener solo resultado operativo seguro;
- probar primero que request sin header y con header incorrecto reciben 403;
- después probar una ejecución autorizada y revisar que los logs no contienen el secreto.

Si el hPanel concreto no permite ejecutar el `.sh`/ruta privada, marcar HOLD; no degradar a secreto en URL.

## Transferencia e instalación

1. En hPanel, revisar el dominio, la raíz web y PHP antes de transferir. Hostinger File Manager permite cargar y extraer el ZIP; extraer en una carpeta limpia, no combinar con archivos históricos desconocidos.
2. Comparar tamaño y, cuando sea posible, SHA-256 en destino. Confirmar después de extraer la presencia de los archivos del manifiesto y la ausencia de `.git`, tests, secretos, archivos temporales y datos de usuario.
3. Dejar el ZIP fuera del acceso público o retirarlo después de verificar la extracción. No renombrar `config/database.php.example`; es solo ejemplo.
4. Preparar directorios de escritura requeridos bajo `storage/` con permisos mínimos. Crear la carpeta privada de documentos fuera del document root y asignar propiedad/permisos al usuario PHP.
5. Habilitar SSL para el subdominio y abrir únicamente por HTTPS. Confirmar certificado válido y Force HTTPS en hPanel después de instalar SSL.
6. Ejecutar el probe de variables PHP y eliminar todo artefacto temporal del probe.
7. Configurar las variables runtime por el mecanismo ya demostrado, sin publicarlas.
8. Abrir el instalador y seguir los pasos: requisitos; credenciales DB y botón **Test connection**; empresa/admin; owner; resumen redactado; instalación/migraciones; éxito/login. No continuar si un chequeo falla.
9. Confirmar que el resumen no revela password DB, que el login funciona, que el instalador queda bloqueado tras instalar y que `config/database.php`, el lock y los directorios privados no se descargan desde HTTP.
10. Configurar un único scheduler aprobado para el POST `/cron/notifications` usando el script privado. Probar que falta de header y secreto incorrecto son rechazados; validar la ejecución correcta y sus logs sin exponer secretos.
11. Mantener timezone de producto `America/Bogota`; la zona UTC de la programación hPanel debe convertirse explícitamente al horario de operación deseado.

## Smoke de producción acotado

Registrar hora y resultado sin PII ni secretos. No declarar éxito sin evidencia directa:

- HTTPS, certificado y página inicial.
- Catálogo público: solo unidades publicadas y campos públicos.
- Login/logout del owner y controles de cuenta.
- Un inmueble y unidad de prueba no sensibles; UI móvil y escritorio.
- Wizard público de candidato con identidad sintética claramente ficticia; revisión en administración.
- Contrato/lease representativo y portal tenant aislado.
- Upload de archivo inocuo, descarga autenticada y URL directa privada denegada.
- PDF válido.
- Email controlado a una cuenta de prueba aprobada; sin secretos en respuesta o logs.
- Cron protegido: falta/valor erróneo rechazados y POST autorizado aceptado.
- Método de pago/QR de ensayo; no iniciar una transferencia real.
- Permisos, aislamiento tenant y rutas de auditoría relevantes.
- F20 membresía sin cargo automático; F21/F22 liquidación/auditoría si están en el artefacto.
- Revisar logs de PHP/app sin stack traces, secretos ni tokens.
- No dejar registros de humo. Anular/eliminar solo registros de prueba si las reglas del producto lo permiten; nunca borrar historia directamente en SQL para ocultar evidencia.

Si falla una compuerta o smoke crítico: parar, preservar evidencia segura, marcar `DEPLOYMENT_STATUS: HOLD`, restaurar desde backup si el sitio fue afectado y volver al owner de código. No editar archivos de producción como parche canónico.

## Rollback

Antes de transferir, verificar respaldo de archivos y DB. Ante bloqueo del sitio o fallo crítico, detener cambios y restaurar el respaldo previo según el procedimiento aprobado por el operador. Cualquier corrección de producto vuelve al repositorio y a su fase propietaria; requiere pruebas, nuevo ZIP, nuevo hash y rehearsal F25 completo antes de un nuevo intento.

## Referencias oficiales Hostinger

- [Seleccionar la versión PHP del sitio](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
- [Administrar extensiones y opciones PHP](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/)
- [Cambiar parámetros PHP](https://www.hostinger.com/support/4622479-how-to-change-values-of-php-parameters-in-hostinger/)
- [Crear una base de datos MySQL](https://www.hostinger.com/support/1583542-how-to-create-a-new-mysql-database-in-hostinger/)
- [Configurar cron jobs](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/)
- [Cron con caracteres especiales / scripts .sh](https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/)
- [Troubleshooting de cron y curl](https://www.hostinger.com/support/1583514-troubleshooting-cron-jobs-at-hostinger/)
- [Acceso al servidor / LiteSpeed / .htaccess](https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/)
- [Habilitar o deshabilitar HTTPS](https://www.hostinger.com/support/1583201-how-to-enable-or-disable-https-for-your-website/)
- [Acciones de Hostinger File Manager](https://support.hostinger.com/en/articles/4548688-basic-actions-in-the-file-manager)
- [Parámetros SMTP de Hostinger Email](https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/)

Estas guías acreditan capacidades públicas generales, no que estén habilitadas en un plan/sitio concreto. La autorización de F26 exige verificar el hPanel activo con valores ficticios antes de introducir secretos o subir datos reales.
