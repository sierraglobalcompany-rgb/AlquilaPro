# F4 CHECKPOINT — 8B DOCUMENT CONVERGENCE CLOSED

**Fecha:** 2026-10-12  
**Proyecto:** Alquila Pro  
**Branch:** `impl/f4-properties-catalog-20261011`  
**PR:** #7 OPEN / DRAFT / NOT MERGED  
**Base main:** `940ebfae25a05d1589fe1ab5ceeaf667c2d08345`  
**Merge autorizado:** NO

---

# 1. MICROFASE CERRADA

`F4.8B — CONVERGENCIA DOCUMENTAL`

Estado: **CLOSED / VERIFIED**.

---

# 2. DOCUMENTOS CONVERGIDOS

## README

Commit: `5c2fe3be5749b96b27e4fc686d7a21340d2ca35b`

Cambios:

- elimina `F4 NOT STARTED`;
- fija estado real de F4;
- fija `public_key` de 24 hex;
- fija detalle `/catalog/{publicKey}`;
- fija foto `/catalog/{publicKey}/photo/{position}`;
- fija roadmap/gate actual;
- mantiene F5 bloqueada.

## SPEC F4 final

Commit: `981411d3426efaccb51e53f2d1e70f966a89545f`

Ahora es SPEC as-built e incluye:

- modelo final;
- publicabilidad viva;
- type immutable;
- allowlist pública;
- media pública sin ID interno;
- cache `no-store`;
- seguridad;
- QA visual;
- evidencia CI.

## PLAN F4 as-built

Commit: `8ba2ad65bbaef360d85e557e3aa544a1e5b8620a`

Registra Tasks 1–7 CLOSED y Task 8 en cierre. Elimina el contrato preliminar de 32 hex y `publicPhoto(int mediaId)`.

## Decisión de convergencia pública F4

Commit: `75a6769e2431c47edea5a1e3e258ea69ceb7347b`

Archivo:

`docs/decisions/F4_CONVERGENCIA_CONTRATOS_PUBLICOS_2026-10-12.md`

Decide explícitamente:

- `public_key = random_bytes(12)` / 24 hex;
- no slug adicional F4;
- media pública por `publicKey + position`;
- no ID interno;
- no-store;
- maestros V1.2 conservan roadmap futuro sin ser reescritos destructivamente.

## PR #7

Descripción actualizada desde “Task 1” al cierre real F4.

Permanece:

- OPEN;
- DRAFT;
- NOT MERGED.

---

# 3. EVIDENCIA

HEAD documental verificado previo a este checkpoint:

`75a6769e2431c47edea5a1e3e258ea69ceb7347b`

CI:

`38212621127`

Resultado:

- PHP 8.3 PASS;
- PHP 8.4 PASS;
- PHP 8.5 PASS;
- Composer validate PASS;
- suite completa PASS.

Último producto funcional GREEN permanece:

`54836fa6a3baae49f0f60085e39d7ccb1f4d8198`

Producto no fue modificado en F4.8B.

---

# 4. LEYES

```text
LEY_1_KISS: PASS
LEY_2_CERO_RUIDO: PASS PARA 8B
PRODUCT_CODE_CHANGED: NO
ROADMAP_MASTER_DESTROYED: NO
PUBLIC_CONTRACT_AMBIGUITY: RESOLVED
```

---

# 5. SIGUIENTE MICROFASE

`F4.8C — SAFE CHECKPOINT CLEANUP`

Secuencia obligatoria antes de borrar:

`REFERENCIAS → AUTORIDAD → REEMPLAZO → TEST → ELIMINAR → CI`

Conservar checkpoints históricos F1/F2/F3.

Eliminar solo WIP F4 totalmente sustituido por documentos canónicos y checkpoints posteriores.

Después:

- F4.8D gate final;
- F4.8E review PR;
- no merge sin autorización;
- no abrir F5.
