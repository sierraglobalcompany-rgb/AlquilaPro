# Runbook manual Hostinger — AlquilaPro V1

`DEPLOYMENT_STATUS: DEPLOYMENT_HANDOFF_READY`  
`PRODUCTION_SMOKE: NOT RUN`  
`DEPLOYMENT_AUTHORIZED: NO`

## Alcance

Destino previsto: **https://alquilapro.sierraglobalcompany.com**.

Este documento prepara una operación manual futura. No se subió ningún archivo, ni se cambió Hostinger, DNS, SSL, base de datos, SMTP, cron o producción. La ejecución requiere autorización explícita y la confirmación de las compuertas de este runbook.

## Artefacto autorizado por F25

Usar solo el archivo `ALQUILA_PRO_V1_SERVER_READY.zip` de versión `1.0.0`, con tamaño `4,729,967 bytes` y SHA-256 `3eb9376fee8d45dac0211e3a9fcd14e6f93069b248c6537c98ab2a85fedf34b3`. Su SHA de origen es `647a02d2bd8a06cb034e8cb553151c3b471289da`. El manifiesto adyacente es `ALQUILA_PRO_V1_SERVER_READY.manifest.json`.

Antes de una futura transferencia, recalcular el SHA-256 y compararlo byte por byte con este checkpoint. Si no coincide, detenerse: no extraer ni instalar ese candidato. No desplegar un checkout Git ni reconstruir el ZIP en Hostinger.

## Compuertas previas obligatorias

1. Confirmar que el subdominio y su document root apuntan intencionalmente a esta instalación. Cambios DNS quedan fuera de este runbook y requieren autorización aparte.
2. Confirmar si el destino contiene un sitio o datos. Si existe, generar y verificar respaldo de archivos y base antes de reemplazar nada. No ejecutar el instalador sobre una base existente sin autorización y respaldo comprobable.
3. Confirmar en hPanel PHP `8.3`, `8.4` o `8.5` y las extensiones que exige el ZIP: `pdo_mysql`, `mbstring`, `dom`, `gd` y `fileinfo`. Corregir extensiones en PHP Configuration; no omitir los chequeos del instalador.
4. Crear una base MySQL nueva y su usuario desde hPanel. Mantener el password fuera del repositorio, de este documento, del chat y de URLs.
5. **Bloqueador de configuración:** confirmar con el plan vigente una forma segura, por sitio, de entregar variables de entorno a PHP web. El código consulta mediante `getenv()` en cada petición las variables `ALQUILAPRO_SMTP_HOST`, `ALQUILAPRO_SMTP_PORT`, `ALQUILAPRO_SMTP_USERNAME`, `ALQUILAPRO_SMTP_PASSWORD`, `ALQUILAPRO_SMTP_ENCRYPTION`, `ALQUILAPRO_SMTP_FROM_EMAIL`, `ALQUILAPRO_SMTP_FROM_NAME` y `ALQUILAPRO_CRON_SECRET`. El soporte documentado de Hostinger consultado describe configuración de PHP y variables para aplicaciones Node.js; no confirma una interfaz segura equivalente para PHP compartido. No avanzar hasta confirmar el mecanismo en el plan concreto. Nunca colocar secretos en Git, ZIP público, `.htaccess` expuesto, `phpinfo()` ni query strings.
6. El secreto de cron debe ser aleatorio y tener al menos 32 caracteres. Confirmar que el scheduler disponible puede hacer POST al endpoint e incluir el header `X-AlquilaPro-Cron-Secret` sin imprimir el secreto en logs o en una URL. La configuración estándar de Cron Jobs para comandos PHP no demuestra por sí sola esta capacidad.
7. Elegir y probar el proveedor SMTP, puerto y cifrado compatibles. Para Hostinger Email, su guía documenta `smtp.hostinger.com`, SSL 465 o TLS/STARTTLS 587; verificar primero que la cuenta contratada sea Hostinger Email, pues Titan u otro proveedor usa datos distintos.
8. Definir la ubicación escribible para documentos privados, fuera del acceso HTTP directo. El valor opcional `ALQUILAPRO_DOCUMENT_DIRECTORY` permite indicar ruta absoluta. Si no se define, la app usa una carpeta privada hermana de la raíz del proyecto; confirmar permisos del usuario PHP.
9. Acordar ventana de mantenimiento, responsable de respaldo y procedimiento de rollback antes de cargar archivos.

## Transferencia e instalación

1. En hPanel, revisar el dominio, la raíz web y PHP antes de transferir. Hostinger File Manager permite cargar y extraer el ZIP; extraer en una carpeta limpia, no combinar con archivos históricos desconocidos.
2. Comparar tamaño y, cuando sea posible, SHA-256 en destino. Confirmar después de extraer la presencia de los archivos del manifiesto y la ausencia de `.git`, tests, secretos, archivos temporales y datos de usuario.
3. Dejar el ZIP fuera del acceso público o retirarlo después de verificar la extracción. No renombrar `config/database.php.example`; es solo ejemplo.
4. Preparar directorios de escritura requeridos bajo `storage/` con permisos mínimos. Crear la carpeta privada de documentos fuera del document root y asignar propiedad/permisos al usuario PHP.
5. Habilitar SSL para el subdominio y abrir únicamente por HTTPS. Confirmar certificado válido y Force HTTPS en hPanel después de instalar SSL.
6. Abrir el instalador y seguir los pasos: requisitos; credenciales DB y botón **Test connection**; empresa/admin; owner; resumen redactado; instalación/migraciones; éxito/login. No continuar si un chequeo falla.
7. Confirmar que el resumen no revela password DB, que el login funciona, que el instalador queda bloqueado tras instalar y que `config/database.php`, el lock y los directorios privados no se descargan desde HTTP.
8. Configurar las variables SMTP/cron por el mecanismo PHP seguro confirmado en la compuerta 5. Reiniciar/revalidar PHP si el plan lo requiere y probar valores sin publicar secretos.
9. Configurar un único scheduler aprobado para el POST `/cron/notifications` con su header secreto. Probar que falta de header y secreto incorrecto son rechazados; validar la ejecución correcta y sus logs sin exponer secretos.
10. Mantener timezone de producto `America/Bogota`; la zona UTC de la programación hPanel debe convertirse explícitamente al horario de operación deseado.

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

- [Seleccionar la versión PHP del sitio](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hosting-plan/)
- [Administrar extensiones y opciones PHP](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/)
- [Crear una base de datos MySQL](https://www.hostinger.com/support/1583542-how-to-create-a-new-mysql-database-in-hostinger/)
- [Configurar cron jobs](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/)
- [Habilitar o deshabilitar HTTPS](https://www.hostinger.com/support/1583201-how-to-enable-or-disable-https-for-your-website-at-hostinger/)
- [Acciones de Hostinger File Manager](https://support.hostinger.com/en/articles/4548688-basic-actions-in-the-file-manager)
- [Parámetros SMTP de Hostinger Email](https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/)

Estas guías explican funciones del panel, pero no acreditan que estén disponibles en un plan o sitio concreto. Confirmar en el hPanel activo las opciones de PHP y cron antes de ejecutar cualquier paso.
