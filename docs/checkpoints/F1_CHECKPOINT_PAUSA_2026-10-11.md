# Alquila Pro — F1 CHECKPOINT DE PAUSA

**Fecha:** 2026-10-11  
**Estado:** PAUSADO INTENCIONALMENTE  
**Fase:** F1 — Foundation + Installer  
**F1:** OPEN / NO CERRADA  
**Main:** NO TOCADO / NO MERGE  

---

## 1. REPOSITORIO Y PUNTO EXACTO

- Repositorio: `sierraglobalcompany-rgb/AlquilaPro`
- Rama base: `main`
- Base SHA de la rama: `76f3802dc26f0fb9021bae240ee833c22296dc57`
- Rama de trabajo: `impl/f1-foundation-installer-20261011`
- PR: `#1 — F1: Foundation + web installer`
- PR estado: **OPEN + DRAFT**
- HEAD al crear este checkpoint: `e9bc5ccadb6e9515d5664dfa7cb4653a12655bd3`
- Último HEAD totalmente GREEN antes del RED web actual: `71749a0daa80e52b793174fc197ec0393e604fe6`

**Regla:** no fusionar a `main` mientras F1 no cierre con verificación completa y las dos leyes en PASS.

---

## 2. LEYES SUPREMAS

### LEY 1 — KISS / ARQUITECTURA SIMPLE

Todo debe mantenerse simple, optimizado, eficiente, eficaz, rápido, auditable, actualizable y fácil de mejorar.

Prohibido introducir arquitectura compleja sin necesidad demostrada.

### LEY 2 — CERO RUIDO / CONVERGENCIA

Orden obligatorio ante ruido o potencial ruido:

1. ELIMINAR
2. UNIR / CONVERGER
3. SIMPLIFICAR
4. MEJORAR
5. AGREGAR solo cuando sea estrictamente necesario

Estas dos leyes deben volver a auditarse antes de cerrar F1.

---

## 3. PLAN EJECUTABLE F1

Documento rector de implementación:

`docs/plans/F1_FOUNDATION_INSTALLER_IMPLEMENTATION_PLAN.md`

Alcance F1: foundation PHP mínima + instalador web funcional, sin abrir módulos de negocio futuros.

---

## 4. TRABAJO YA IMPLEMENTADO Y VERIFICADO

Hasta el commit `71749a0daa80e52b793174fc197ec0393e604fe6`, el CI de GitHub quedó GREEN en la matriz:

- PHP 8.3
- PHP 8.4
- PHP 8.5
- MariaDB real en GitHub Actions para integración
- `composer validate --strict`
- instalación de dependencias
- suite PHPUnit completa existente en ese punto

### 4.1 Base URL

Implementado:

- `app/Foundation/BaseUrlDetector.php`
- `tests/Unit/Foundation/BaseUrlDetectorTest.php`

Cubre:

- subdominio HTTPS;
- instalación en subcarpeta sin duplicar segmentos;
- HTTP local cuando HTTPS no está indicado.

No se agregó soporte especulativo de `X-Forwarded-*`.

### 4.2 Requisitos de runtime

Implementado:

- `app/Foundation/RuntimeRequirements.php`
- `tests/Unit/Foundation/RuntimeRequirementsTest.php`

Valida:

- PHP >= 8.3 y < 8.6;
- `pdo`;
- `pdo_mysql`;
- `mbstring`;
- almacenamiento escribible.

### 4.3 Configuración y conexión de base de datos

Implementado:

- `app/Foundation/DatabaseConfig.php`
- `app/Foundation/DatabaseConnector.php`
- tests unitarios e integración.

Decisiones:

- PDO directo;
- sin ORM;
- sin repository pattern por convención;
- errores de conexión sanitizados;
- contraseña/DSN no se exponen en el error entregado al usuario.

### 4.4 Migraciones

Implementado:

- `app/Foundation/MigrationRunner.php`
- `database/migrations/001_create_users.sql`
- `database/migrations/002_create_settings.sql`
- `tests/Integration/Foundation/MigrationRunnerTest.php`

Decisión de convergencia KISS:

- `schema_migrations` NO es una migración que dependa de sí misma;
- la tabla de tracking la crea internamente `MigrationRunner`;
- no se creó una clase `MigrationResult`; el runner retorna un array mínimo.

Verificado:

- primera ejecución aplica las migraciones;
- segunda ejecución aplica cero;
- migración fallida no queda registrada como aplicada.

### 4.5 Installation Lock

Implementado:

- `app/Foundation/InstallationLock.php`
- `tests/Unit/Foundation/InstallationLockTest.php`

Decisión clave:

- la **existencia del archivo** es la única señal de que la instalación está cerrada;
- un lock corrupto sigue fallando cerrado porque el archivo existe;
- el JSON es solo metadato auditable;
- secretos adicionales se descartan al escribir el lock;
- escritura mediante temporal + rename.

### 4.6 Servicio de instalación

Implementado:

- `app/Foundation/Installer.php`
- `tests/Integration/Foundation/InstallerTest.php`

Verificado en PHP 8.3 / 8.4 / 8.5 antes de abrir la tarea web.

Comportamiento cubierto:

- rechaza propietario inválido;
- error de BD no crea lock ni config;
- ejecuta migraciones;
- crea o actualiza exactamente un propietario inicial en reintento incompleto;
- usa `password_hash()`;
- guarda `app.name` y `app.base_url`;
- guarda configuración local de BD fuera de rutas web;
- escribe el lock al final;
- segundo intento después del lock es rechazado.

---

## 5. ESTADO ACTUAL: TASK 8 — WEB INSTALLER

La siguiente tarea ya fue iniciada **solo en RED**.

Commit RED actual:

`e9bc5ccadb6e9515d5664dfa7cb4653a12655bd3`

Archivo creado:

`tests/Integration/Foundation/AppBootstrapTest.php`

El CI asociado al commit actual terminó en **FAILURE**, que es el estado RED esperado porque todavía NO existe la implementación web que satisfaga la prueba.

### Comportamientos RED ya definidos

- `GET /` redirige a `/install` antes de instalar;
- `GET /install` muestra diagnóstico + formulario;
- fallo en `POST /install` no reimprime contraseñas;
- `POST /install` exitoso redirige a `/` y bloquea el instalador;
- después del lock, `GET /install` responde 404;
- `GET /health` retorna solo estado no secreto.

### IMPORTANTE

Todavía NO se han creado para Task 8:

- `bootstrap/app.php`
- `index.php`
- vistas mínimas del instalador
- `.htaccess` raíz
- GREEN de `AppBootstrapTest`

Por tanto, **no interpretar el CI rojo actual como regresión**. Es el RED TDD intencional del siguiente bloque.

---

## 6. DECISIONES ARQUITECTÓNICAS VIGENTES

Mantener:

- una sola aplicación PHP;
- Slim 4;
- PDO;
- HTML server-rendered;
- una sola BD;
- un solo repositorio;
- sin SPA;
- sin Node en producción;
- sin Redis;
- sin workers;
- sin event bus;
- sin contenedor DI complejo;
- sin abstracciones preventivas;
- sin servicios vacíos de módulos futuros.

### Diferidos deliberadamente por Cero Ruido

No implementar todavía:

- SMTP real;
- medios de pago;
- PSE;
- autenticación completa/sesiones/permisos de F2;
- inmuebles;
- candidatos;
- SURA;
- contratos;
- PDFs de negocio;
- dashboards de negocio;
- WhatsApp;
- cron/reminders funcionales.

El propietario inicial sí existe en F1 porque el instalador necesita terminar con una identidad administrativa válida; F2 añadirá login/sesión/permisos.

---

## 7. AUDITORÍA INTERMEDIA DE LAS DOS LEYES

### LEY 1 — KISS

**Estado intermedio hasta último GREEN:** PASS.

Razones:

- clases pequeñas y responsabilidades directas;
- PDO sin ORM;
- migraciones SQL simples;
- sin contenedor DI;
- sin framework adicional de testing HTTP;
- sin código de negocio futuro.

### LEY 2 — CERO RUIDO

**Estado intermedio hasta último GREEN:** PASS.

Convergencias/eliminaciones realizadas:

- eliminada la idea de `MigrationResult` dedicado;
- tracking de migraciones convergido dentro de `MigrationRunner`;
- lock reducido a existencia de archivo como única fuente de verdad;
- reintento del propietario converge por update/insert, sin duplicar registros;
- SMTP/pagos diferidos hasta tener módulos reales.

### Gate final de F1

**PENDIENTE.**

No marcar F1 cerrada ni las leyes como PASS final hasta terminar Task 8, protección de storage, suite final y auditoría completa.

---

## 8. SIGUIENTE PASO ÚNICO AL REANUDAR

Retomar exactamente desde:

**Task 8 — convertir `AppBootstrapTest.php` de RED a GREEN.**

Implementación mínima prevista:

1. crear `bootstrap/app.php` como factory callable de Slim;
2. crear rutas `/`, `/install`, `/health`;
3. crear HTML mínimo server-rendered del instalador;
4. no reflejar contraseñas después de POST;
5. crear `index.php` como front controller;
6. agregar `.htaccess` solo con lo necesario para Hostinger/Apache/subcarpeta;
7. ejecutar matriz completa PHP 8.3/8.4/8.5;
8. no avanzar a Task 9 hasta GREEN completo.

Después:

- Task 9: protección de storage + manejo de fallos;
- Task 10: verificación integral F1 + auditoría KISS/Cero Ruido + checkpoint de cierre.

---

## 9. GUARDRAILS PARA LA REANUDACIÓN

- No tocar `main`.
- No hacer merge del PR #1.
- No abrir F2.
- No saltar el RED web actual.
- No borrar ni debilitar pruebas para obtener GREEN.
- No agregar dependencias sin justificar primero reducción de complejidad total.
- No crear módulos fuera de F1.
- Cada bloque debe cerrar RED → GREEN → suite completa → auditoría de las dos leyes.

---

## 10. RESUMEN DE RECUPERACIÓN

```text
PROJECT=AlquilaPro
PHASE=F1_FOUNDATION_INSTALLER
STATUS=PAUSED_OPEN
BRANCH=impl/f1-foundation-installer-20261011
PR=1_DRAFT_OPEN
BASE_SHA=76f3802dc26f0fb9021bae240ee833c22296dc57
LAST_GREEN_SHA=71749a0daa80e52b793174fc197ec0393e604fe6
CURRENT_HEAD_BEFORE_CHECKPOINT=e9bc5ccadb6e9515d5664dfa7cb4653a12655bd3
CURRENT_TASK=TASK_8_WEB_INSTALLER_RED
CURRENT_CI=EXPECTED_RED_FAILURE
NEXT=IMPLEMENT_BOOTSTRAP_APP_AND_MINIMAL_WEB_INSTALLER_GREEN
MAIN_MERGED=NO
F1_CLOSED=NO
F2_OPENED=NO
LAW_1_KISS_INTERIM=PASS_TO_LAST_GREEN
LAW_2_ZERO_NOISE_INTERIM=PASS_TO_LAST_GREEN
FINAL_LAWS_GATE=PENDING
```
