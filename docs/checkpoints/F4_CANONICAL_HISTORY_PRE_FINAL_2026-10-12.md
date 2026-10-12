# F4 — CANONICAL HISTORY PRE-FINAL

**Fecha:** 2026-10-12  
**Fase:** F4 — Properties + Units + Landing + Public Catalog  
**Estado:** FUNCIONALMENTE GREEN / CIERRE FINAL PENDIENTE  
**Branch:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 OPEN / DRAFT / NOT MERGED  
**Base main:** `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`  
**Merge autorizado:** NO

Este archivo consolida la historia útil de recuperación F4 antes de eliminar checkpoints WIP ya sustituidos. No declara F4 CLOSED; eso corresponde a F4.8D.

---

# 1. RESULTADO ARQUITECTÓNICO

F4 termina con:

- +3 tablas: `properties`, `units`, `unit_media`;
- +2 permisos: `properties.view`, `properties.manage`;
- 1 entidad `units` para apartment|parking;
- 1 `PropertyService`;
- 1 `UnitMediaService`;
- 1 archivo `app/Properties/routes.php`;
- 1 regla viva de publicabilidad;
- 1 identificador público `public_key` de 24 hex;
- 0 nuevas dependencias runtime;
- 0 npm;
- 0 segundo auth;
- 0 tabla catálogo;
- 0 CTA Aplicar antes de F5.

---

# 2. TASKS 1–3

## Task 1 — Schema + Permissions

CLOSED / GREEN.

- migration `004_create_properties_units_media.sql`;
- exactamente 3 tablas;
- exactamente 2 permisos;
- `public_key CHAR(24)`.

## Task 2 — Property + Unit Domain

CLOSED / GREEN.

- property CRUD/inactivation;
- unit apartment|parking;
- estados canónicos;
- parking→apartment same property;
- server public_key;
- type immutable tras auditoría.

## Task 3 — Media + Publication

CLOSED / GREEN.

- MIME real JPEG/PNG/WebP;
- máximo 10 MiB;
- random stored name;
- fuera webroot;
- rollback;
- HTTPS video links;
- cover/order;
- publicabilidad viva.

Hitos de auditoría especialmente relevantes:

- live-publicability RED `9d44dfc6690c1bcb5e3edcb7c711a273a29b2028`;
- live-publicability GREEN `ad1712dab9c30909221ad7a65f62b1320d4e04ac`;
- obsolete skeleton convergence `051ee401b8350850b0060ab7e3f6718b20680822`;
- public-media drift RED `52a0fe04f31d745de93bbb4b4f34017399479764`;
- public-media drift GREEN `a852c7628633a7b4ed9d12e072f09df2644d4afc`;
- inactive-property RED `e2987d223954d8be0805a66ec8dcf44b32932987`;
- inactive-property GREEN `c53f15f7706b0b8229ed038094b5f21f6ed817f5`.

---

# 3. TASK 4 — PRIVATE ADMIN

CLOSED / VERIFIED.

Auditoría final:

- type immutability GREEN `46f25bdf...`;
- public allowlist GREEN `1907929e...`;
- linked apartment/propertyUnits GREEN `5a084f03...`;
- launcher convergence GREEN `c1fe5141...`;
- CSRF characterization `fc359a92...`;
- contextual UI GREEN `0f8e11db...`;
- property counts GREEN `a398b16f...`;
- Task 4 closure `06d68724...`.

Resultado:

- rutas privadas reales;
- permissions/owner bypass;
- CSRF global;
- fields contextual;
- linked apartment same property;
- counts by status;
- launcher Propiedades.

---

# 4. TASK 5 — LANDING + PUBLIC CATALOG

CLOSED / VERIFIED.

Resultado:

- installed visitor `/` → landing;
- authenticated `/` → F3 shell;
- `/catalog` + type/city/sector;
- invalid type 422;
- `/catalog/{publicKey}`;
- privacy allowlist;
- HTTPS video links;
- no Aplicar;
- live availability summary;
- subfolder.

Hitos relevantes:

- landing GREEN `3a0a6a8f...` + contract convergence `83adb9eb...`;
- catalog RED `e9edfb2c...`;
- catalog GREEN `0555edad...`;
- detail RED `c9a9eafd...`;
- detail GREEN `44c6ea95...`;
- landing-summary RED `8e1fd60e...`;
- final root-call fix `54151e6c...`.

---

# 5. TASK 6 — PUBLIC PHOTO BOUNDARY

CLOSED / VERIFIED.

Contrato final:

`GET /catalog/{publicKey}/photo/{position}`

No expone `unit_media.id`.

- RED `17058aba...`;
- service convergence `0d8f138f...`;
- route/gallery work `9e97be55...` + `dde65327...`;
- complete candidate GREEN `cd691027...`.

Headers:

- validated Content-Type;
- nosniff;
- no-store.

---

# 6. TASK 7 — SECURITY / REGRESSION

CLOSED / VERIFIED.

Caracterización `5b840f84...` confirmó sin runtime nuevo:

- private subfolder guard/redirect;
- private media no IDOR;
- CSRF F4;
- storage boundary;
- public-media fail closed.

---

# 7. TASK 8A — CONVERGENCE + VISUAL QA

CLOSED / VERIFIED.

## Route convergence

`c10ec1284d73a63c309dc059d6c6ec16b8602b2f`

`public_routes.php` fue absorbido por `routes.php`; bootstrap registra una sola pieza F4.

## Visual QA RED/GREEN

- RED `db37b376fcd55936cc28fff20d3479905a899600`: responsive images + missing F4 CSS primitives;
- GREEN `68a465534d25f29d8a23fd70609823cb9550c805`;
- RED `f4f47ba176836588466f7f790866ca7eedee0cfe`: long public links;
- final GREEN `54836fa6a3baae49f0f60085e39d7ccb1f4d8198`.

CI final product run `38211673768`:

- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- 151 tests / 873 assertions / 0 failures.

Visual:

- 360/390/412/768/1280 px;
- reflow equivalent 200%;
- no horizontal overflow;
- focus PASS;
- dialog PASS;
- bottom nav PASS.

---

# 8. TASK 8B — DOCUMENT CONVERGENCE

CLOSED / VERIFIED.

- README `5c2fe3be5749b96b27e4fc686d7a21340d2ca35b`;
- F4 SPEC final `981411d3426efaccb51e53f2d1e70f966a89545f`;
- F4 PLAN as-built `8ba2ad65bbaef360d85e557e3aa544a1e5b8620a`;
- public-contract decision `75a6769e2431c47edea5a1e3e258ea69ceb7347b`;
- checkpoint 8B `1aacadbc24a1949ea3a664500f46b959838ca5dd`;
- CI docs `38212621127`: PHP 8.3/8.4/8.5 PASS.

PR #7 body convergido al estado real.

---

# 9. CONTRATOS CANÓNICOS F4

```text
public_key: CHAR(24), bin2hex(random_bytes(12))
GET /catalog
GET /catalog/{publicKey}
GET /catalog/{publicKey}/photo/{position}
```

Publicable si y solo si:

```text
property.is_active
AND unit.status = available
AND unit.is_public = 1
AND monthly_price > 0
AND description nonblank
AND requirements nonblank
AND photo exists
```

---

# 10. DOCUMENTOS QUE REEMPLAZAN WIP

Autoridad de recuperación F4 desde ahora:

1. `README.md`;
2. `docs/specs/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_SPEC.md`;
3. `docs/plans/F4_PROPERTIES_UNITS_PUBLIC_CATALOG_IMPLEMENTATION_PLAN.md`;
4. `docs/decisions/F4_CONVERGENCIA_CONTRATOS_PUBLICOS_2026-10-12.md`;
5. este historial canónico;
6. `F4_CHECKPOINT_8B_DOC_CONVERGENCE_CLOSED_2026-10-12.md` hasta F4.8C/F4.8D;
7. tests/CI/código.

Checkpoints WIP anteriores de Task 1/2/3/4/5 y pausas de Task 8 quedan sustituidos cuando no contengan evidencia única adicional.

---

# 11. SIGUIENTE GATE

```text
F4.8C SAFE CHECKPOINT CLEANUP: IN PROGRESS
F4.8D FINAL TWO-LAWS GATE: PENDING
F4.8E PR REVIEW: PENDING
F4 CLOSED: NO
MERGE AUTHORIZED: NO
F5: BLOCKED
```
