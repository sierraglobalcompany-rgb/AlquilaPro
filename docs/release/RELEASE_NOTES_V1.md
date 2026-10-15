# AlquilaPro SERVER_READY V1 — 1.0.0

Este paquete reúne la aplicación AlquilaPro para instalación en un servidor PHP compatible. La versión `1.0.0` identifica el formato de release V1 y no cambia la versión de runtime de Composer.

El artefacto distribuible se llama `ALQUILA_PRO_V1_SERVER_READY.zip` y se acompaña de su checksum SHA-256 y manifiesto JSON.

Incluye:

- Aplicación PHP SSR, assets locales, migraciones y dependencias de producción fijadas por `composer.lock`.
- Instalador guiado que comprueba requisitos, prueba la conexión a base de datos y escribe la configuración al confirmar la instalación.
- Flujo guiado de solicitud de arrendamiento y rutas privadas protegidas por las autoridades existentes de permisos, CSRF y ownership.
- Política de contenido y ejemplo de configuración sin credenciales.

Requisitos de PHP, extensiones, base de datos, almacenamiento y permisos se verifican durante la instalación y se detallan en `SERVER_READY_FILE_POLICY.md`. El ZIP solo se declara `SERVER_READY` cuando el hash exacto supera la auditoría estática y el ensayo local desde una extracción limpia y una base vacía.

No incluye bases locales, usuarios de prueba, secretos, archivos de clientes ni dependencias de desarrollo. Esta entrega no ejecuta cambios en Hostinger ni en producción.
