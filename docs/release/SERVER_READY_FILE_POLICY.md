# SERVER_READY V1 — política de archivos

Versión congelada del paquete: `1.0.0` (ver `docs/release/VERSION`). Esta política define el contenido del ZIP `ALQUILA_PRO_V1_SERVER_READY.zip`; el builder crea el paquete desde el commit limpio de cierre F24 y añade únicamente dependencias Composer de producción.

## Contenido permitido

El ZIP contiene la aplicación necesaria para ejecutarse desde su raíz:

- `index.php` y `.htaccess`.
- Código de `app/`, `bootstrap/`, `assets/` y `resources/`.
- Migraciones de `database/migrations/`.
- `composer.json`, `composer.lock` y `vendor/` generado con `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
- `config/database.php.example`, un ejemplo sin credenciales reales. El instalador crea `config/database.php` con los valores privados introducidos durante la instalación.
- Directorios vacíos de escritura bajo `storage/`: `app/`, `branding/`, `unit-media/`, `candidate-documents/`, `contracts/`, `payment-method-qrs/`, `payment-receipts/`, `obligation-supports/`, `insurance-documents/` y `maintenance-photos/`. Se preservan vacíos; nunca se distribuye contenido de usuarios.
- `docs/release/SERVER_READY_FILE_POLICY.md`, `docs/release/RELEASE_NOTES_V1.md` y `docs/release/VERSION`.
- Manifiesto de contenido y huellas del paquete. El directorio de salida incluye además el ZIP, su archivo `.sha256` y el manifiesto `.manifest.json`.

`storage/` y la carpeta privada de documentos fuera de la raíz web deben pertenecer al usuario del servidor y permitir escritura solo donde la aplicación la necesita. Apache debe aplicar `.htaccess`; la configuración web deniega el acceso directo a `app/`, `bootstrap/`, `config/`, `database/`, `docs/`, `resources/`, `storage/`, `tests/` y `vendor/`. El instalador crea `config/database.php` y `storage/app/installed.lock`; protege el archivo de configuración con permisos privados.

`config/database.php.example` es documentación, no configuración activa. No lo renombres a `database.php` ni guardes credenciales en Git. El instalador es la autoridad que genera la configuración efectiva.

## Contenido prohibido

El paquete excluye explícitamente:

- `.git/`, `.github/`, `.codex-state/`, worktrees y metadatos locales de Git.
- `tests/`, `.phpunit.cache`, `.phpunit.result.cache`, reportes de prueba, logs de CI y artefactos temporales.
- `.env`, `.env.*`, `config/database.php`, `config/database.php.tmp`, cualquier secreto, cookie, token, password SMTP/cron, credencial local o credencial de prueba (incluidos `root/root`, `testdb` y `alquila_test`).
- `require-dev` y dependencias de desarrollo; `vendor/` se reconstruye exclusivamente sin dependencias de desarrollo.
- Logs (`logs/`), dumps o archivos locales de base de datos, cachés y temporales.
- Archivos de usuario, datos privados o de prueba dentro de `storage/` o de la carpeta privada externa: uploads, documentos, contratos, fotos, logos, recibos y códigos QR.
- `node_modules/`, Playwright y sus navegadores, `screenshots/`, capturas QA, referencias UI/UX, herramientas de desarrollo y dependencias locales.
- Cualquier archivo no aprobado por la allowlist del builder. La presencia accidental de un archivo no lo convierte en contenido permitido.

El checksum SHA-256 describe los bytes exactos del ZIP certificado. Una reconstrucción genera un candidato distinto y requiere un nuevo hash y un ensayo completo desde una extracción limpia.
