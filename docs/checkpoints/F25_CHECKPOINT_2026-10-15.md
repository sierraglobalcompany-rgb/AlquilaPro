# F25 — Cierre SERVER_READY local

**Fecha:** 2026-10-15  
**Rama:** `impl/f25-server-ready-20261015`  
**Base F25:** `4c1ce38af2814340e0dfecb0700e823c33836af8`  
**Código de origen del artefacto:** `647a02d2bd8a06cb034e8cb553151c3b471289da`

## Decisión de release

`SERVER_READY: YES`

El candidato exacto tiene SHA-256, sidecar y manifiesto coincidentes; la auditoría estática y el ensayo limpio desde extracción nueva y base vacía pasaron. La identidad certificada es:

| Campo | Valor |
|---|---|
| Archivo | `ALQUILA_PRO_V1_SERVER_READY.zip` |
| Versión | `1.0.0` |
| Tamaño | `4,729,967 bytes` |
| SHA-256 | `3eb9376fee8d45dac0211e3a9fcd14e6f93069b248c6537c98ab2a85fedf34b3` |
| Manifiesto | `ALQUILA_PRO_V1_SERVER_READY.manifest.json` |
| Archivos/directory entries | 700 archivos, 139 directorios |
| SHA de origen del ZIP | `647a02d2bd8a06cb034e8cb553151c3b471289da` |

No reconstruir este ZIP para instalarlo: cualquier cambio de bytes exige otra auditoría, hash y ensayo desde cero.

## Verificaciones

- F25-T1 política de archivos: 3 tests / 55 assertions PASS.
- F25-T2 builder: 3 tests / 224 assertions PASS.
- F25-T3 auditoría estática: 1 test / 23,979 assertions PASS; extracción nueva y autoloader de producción PASS.
- F25-T4 ensayo del artefacto exacto: PASS; informe en `docs/qa/F25_SERVER_READY_REHEARSAL_2026-10-15.md`.
- Total de pruebas focales F25 reportadas: 7 tests / 24,258 assertions PASS.
- Entorno de build disponible: Windows, PHP 8.5.8 CLI, Composer 2.10.2.
- Ensayo: PHP 8.5.11 / Apache 2.4.68 / MariaDB 11.4.12; DB vacía antes del instalador.
- Composer strict, matriz completa y PHP parse lint están certificados en F23/F24. No se repitió la suite completa de producto para F25.
- `git diff --check`: requerido y ejecutado al preparar el cierre.

## Resultado arquitectónico y límites

F25 no cambió comportamiento de producto ni añadió dependencias runtime. La primera versión del ZIP fue rechazada por incluir fuentes de pruebas dentro de `vendor/slim/psr7`; la allowlist del builder se corrigió y la prueba de auditoría detecta esa clase de regresión. El ZIP indicado arriba es el candidato posterior a esa corrección.

| Control | Estado |
|---|---|
| Artefacto único con hash y manifiesto | PASS |
| Auditoría estática | PASS |
| Rehearsal limpio del ZIP exacto | PASS |
| Merge autorizado | NO |
| Despliegue autorizado | NO |
| Hostinger, DNS y producción modificados | NO |

## Continuación

F26 queda limitado al runbook manual en `docs/deployment/HOSTINGER_DEPLOYMENT_RUNBOOK.md`. El método seguro para inyectar variables de entorno PHP por sitio en el plan Hostinger activo y la llamada programada POST con header secreto deben confirmarse antes de cualquier despliegue. Esto no afecta el PASS local de F25.
