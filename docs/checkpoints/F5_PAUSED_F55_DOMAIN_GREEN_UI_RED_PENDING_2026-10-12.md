# AlquilaPro — F5 checkpoint detallado

Fecha: 2026-10-12
Estado: PAUSADO POR SOLICITUD HUMANA
Fase: F5 — Applications + Candidates + References
Subfase activa: F5.5 — Structured Requirements + Private Candidate Documents

## 1. Estado ejecutivo

```text
F5_SPEC: APPROVED
F5_PLAN: APPROVED
F5.1_SCHEMA_PERMISSIONS: CLOSED / VERIFIED
F5.2_CANDIDATE_CORE_TOKEN: CLOSED / VERIFIED
F5.3_PUBLIC_START_CONTINUE: CLOSED / VERIFIED
F5.4_PROFILE_REFERENCES_UNIT_CHANGE: CLOSED / VERIFIED
F5.5_DOMAIN_REQUIREMENTS_DOCUMENTS: GREEN / VERIFIED
F5.5_BOOTSTRAP_ROUTES_UI: PENDING
F5.5_OVERALL: OPEN
F5.6_ADMIN_CANDIDATES: BLOCKED
PR_READY: NO
MERGE_ALLOWED: NO
```

F5.5 NO está cerrada. Se detuvo deliberadamente después de dejar GREEN y certificado el núcleo de dominio de requisitos/documentos y la regla de completitud del submit, pero ANTES de abrir el segundo RED de bootstrap/rutas/UI.

## 2. Repositorio / branch / PR

```text
repository: sierraglobalcompany-rgb/AlquilaPro
base: main
base_sha: 648401a4927bfeb7525421a4edd2b5f2c60f06c4
branch: impl/f5-candidates-20261012
product_test_head_before_checkpoint: 72fe306d3c9206946c1aa4af9a5acd6bc1b522a4
pr: #8 — F5: Applications, candidates and references
pr_state: OPEN
pr_draft: YES
pr_merged: NO
pr_mergeable: YES
```

PR #8 conserva cuerpo documental de F5.4 y por tanto está desactualizado respecto a F5.5. No actualizarlo hasta que F5.5 alcance un cierre verificable o se haga una convergencia documental explícita.

## 3. Leyes de trabajo que siguen vinculantes

### LEY 1 — KISS

Mantener solución simple, optimizada, eficiente y efectiva. No introducir arquitectura anticipada.

Para F5.5 el PRE-KISS fijado es:

```text
+1 servicio legítimo: CandidateDocumentService
+0 tablas nuevas
+0 dependencias Composer
+0 npm
+0 segundo auth
+0 segundo modelo de aplicación
+0 route files nuevos
```

No reutilizar `UnitMediaService` para documentos privados de candidatos.

### LEY 2 — Cero Ruido / Convergencia

Orden obligatorio:

```text
ELIMINAR → UNIR/CONVERGER → SIMPLIFICAR → MEJORAR → AGREGAR
```

No parche sobre parche. No agregar repositorios/DTO/factories/interfaces/colas/tablas compensadoras sin necesidad demostrada.

### TDD

```text
SCOPE → PRE-KISS → SPEC/DESIGN → PLAN → RED → VERIFY RED
→ MINIMAL GREEN → VERIFY GREEN → CONVERGE → QA → TWO LAWS GATE
→ CHECKPOINT → REVIEW → MERGE
```

No merge sin aprobación humana explícita.

## 4. Autoridad documental F5.5

Documentos canónicos:

- `docs/specs/F5_APPLICATIONS_CANDIDATES_REFERENCES_SPEC.md`
- `docs/plans/F5_APPLICATIONS_CANDIDATES_REFERENCES_IMPLEMENTATION_PLAN.md`
- este checkpoint para continuidad operativa del punto exacto de pausa.

Interfaces previstas por PLAN:

```php
final class CandidateDocumentService
{
    public function __construct(PDO $pdo, string $documentDirectory);

    public function listRequirementsForUnit(int $unitId, bool $activeOnly = false): array;
    public function createRequirement(int $unitId, array $input): array;
    public function updateRequirement(int $unitId, int $requirementId, array $input): array;
    public function reorderRequirements(int $unitId, array $orderedRequirementIds): void;

    public function listDocumentsForCandidate(int $candidateId): array;
    public function addOrReplaceDocument(int $candidateId, string $requirementCode, UploadedFileInterface $upload): array;
    public function privateDocument(int $candidateId, int $documentId): ?array;
}
```

Rutas F5.5 previstas por PLAN, todavía NO implementadas al pausar:

```text
POST /admin/units/{id}/application-requirements
POST /admin/units/{id}/application-requirements/{requirementId}
POST /admin/units/{id}/application-requirements/order
POST /apply/continue/{token}/documents/{requirementCode}
POST /apply/continue/{token}/submit
```

## 5. Trabajo realizado en esta sesión

### 5.1 RED de dominio F5.5

Commit:

```text
71bcf59ae746a0e46fe60e1af6401e7016e30271
```

Archivo creado:

```text
tests/Integration/Candidates/CandidateDocumentsTest.php
```

El RED fijó seis contratos:

1. requisitos por unidad, `code` inmutable, activeOnly y orden exacto;
2. PDF/JPEG/PNG reales, nombres generados y reemplazo seguro;
3. rechazo de vacío/SVG/PHP/fake MIME/oversize preservando documento previo;
4. candidato existente + requirementCode activo de la unidad actual;
5. lectura privada sin IDOR cross-candidate y rechazo de archivo manipulado;
6. submit exige únicamente requisitos actuales `required + active` y no revalida publicabilidad de una candidatura ya creada.

CI RED:

```text
run: 38275744898
PHP 8.3/8.4/8.5: FAIL esperado
183 tests / 1127 assertions / 6 failures
```

Los seis fallos correspondieron exactamente a `CandidateDocumentService` ausente. La regresión previa F1–F5.4 permaneció verde.

### 5.2 GREEN inicial — CandidateDocumentService

Commit:

```text
30486b3e7ceca8f8df9acaf3eead47071803ae8e
```

Archivo creado:

```text
app/Candidates/CandidateDocumentService.php
```

Contratos implementados:

#### Requisitos

- requisito pertenece a una unidad existente;
- `code` requerido, máximo 80, único por constraint de DB y tratado como inmutable;
- `label`, `description`, `is_required`, `is_active`, `sort_order` editables;
- `activeOnly=true` devuelve solo activos conservando orden;
- reorder exige conjunto exacto de IDs propios, sin faltantes, duplicados ni IDs ajenos;
- no se implementó borrado físico de requisitos.

#### Documentos privados

- candidato debe existir;
- candidatura debe seguir editable (`new|incomplete`);
- requirementCode debe ser activo y pertenecer a la unidad actual del candidato;
- formatos admitidos: PDF, JPEG, PNG;
- límite: 10 MiB;
- MIME verificado por bytes con `finfo`, no por nombre ni Content-Type cliente;
- nombre almacenado generado: `document-<32 hex>.<ext>`;
- nombre original nunca se usa como path;
- un documento por candidato+requisito;
- replacement crea/confirma nuevo archivo+fila antes de retirar archivo anterior;
- validación fallida no reemplaza ni elimina documento válido previo;
- `privateDocument(candidateId, documentId)` exige ownership exacto candidato+documento;
- lectura privada revalida MIME/tamaño del archivo físico y falla cerrado si fue manipulado;
- directorio/archivo intenta permisos restrictivos (`0700` / `0600`).

CI intermedio:

```text
run: 38276064992
183 tests / 1189 assertions / 1 error
```

Los primeros cinco contratos documentales quedaron GREEN. El único error restante era el esperado: `submitByToken()` todavía permitía enviar sin documento obligatorio.

### 5.3 GREEN submit completeness

Commit:

```text
cb39ab8cd0e141bb24a7614d86e8d74561770212
```

Archivo modificado:

```text
app/Candidates/CandidateService.php
```

Cambio mínimo:

```text
submitByToken()
  -> assertBasicCompleteness()
  -> assertLaborCompleteness()
  -> assertDocumentCompleteness()
  -> verification
```

`assertDocumentCompleteness()` considera únicamente:

```text
unit_application_requirements.unit_id = candidate.unit_id
AND is_required = 1
AND is_active = 1
AND no candidate_documents row for candidate+requirement
```

Reglas confirmadas:

- requisito opcional no bloquea;
- requisito inactivo no bloquea;
- requisito activo+obligatorio sin documento bloquea;
- unidad que deja de estar publicable NO bloquea el submit de un candidato ya creado;
- no se añadió segunda fuente de estado/publicabilidad.

### 5.4 Regresión encontrada y clasificación correcta

CI del commit anterior:

```text
run: 38276609968
183 tests / 1190 assertions / 1 error
```

No fue defecto del nuevo guard de producción.

El test histórico F5.4 `CandidateReferencesTest::testUnitChangeKeepsSingleCandidateAndEnforcesAllGuards` creaba un requirement implícitamente obligatorio solo para probar que la existencia de cualquier documento impide cambiar de unidad y después reutilizaba la misma unidad para un submit sin documentos.

Con F5.5 ese fixture quedó semánticamente obsoleto: un requisito obligatorio debe bloquear ese submit.

Corrección aplicada SOLO al fixture:

```text
requirement de la prueba de bloqueo de cambio de unidad -> is_required = 0
```

Esto conserva ambos contratos:

- cualquier documento sigue bloqueando cambio de unidad;
- solo requisitos activos+obligatorios bloquean submit.

Commit:

```text
72fe306d3c9206946c1aa4af9a5acd6bc1b522a4
```

## 6. Última certificación CI antes del checkpoint

Run:

```text
38277046297
```

Resultado:

```text
PHP 8.3: PASS
PHP 8.4: PASS
PHP 8.5: PASS
Composer validate --strict: PASS
PHP 8.3 exacto: OK (183 tests, 1191 assertions)
0 failures
0 errors
```

Por tanto el dominio implementado hasta `72fe306d...` queda certificado GREEN en PHP 8.3/8.4/8.5.

Esto NO cierra F5.5 porque faltan bootstrap, rutas y vistas.

## 7. Archivos F5.5 tocados hasta la pausa

Nuevos:

```text
app/Candidates/CandidateDocumentService.php
tests/Integration/Candidates/CandidateDocumentsTest.php
```

Modificados:

```text
app/Candidates/CandidateService.php
tests/Integration/Candidates/CandidateReferencesTest.php
```

NO tocados todavía para F5.5:

```text
app/Candidates/routes.php
bootstrap/app.php
resources/views/unit_detail.php
resources/views/public_candidate_continue.php
app/Properties/routes.php
```

No se creó ningún route file nuevo.

## 8. Trabajo pendiente exacto de F5.5

### 8.1 Segundo RED — todavía NO abierto

Debe fijar, como mínimo:

#### Admin requirements

- mutations requieren autenticación, no `must_change_password`, CSRF y `candidates.manage`;
- owner bypass existente;
- crear/editar/reordenar requisitos dentro de contexto de unidad;
- no exponer CRUD físico de delete;
- UI escapa label/description/code;
- basePath correcto.

#### Public candidate documents

- GET continue muestra requisitos activos y estado documental sin IDs internos públicos;
- upload resuelve candidato exclusivamente por token server-side;
- ruta usa `requirementCode`, nunca `requirementId` público;
- CSRF obligatorio;
- throttle reutiliza el bucket continue/submit ya definido, no middleware nuevo;
- upload inválido no muta fila/archivo previo;
- redirect conserva basePath;
- no se filtra candidate_id, requirement_id, document_id ni path físico.

#### Final submit route

- `POST /apply/continue/{token}/submit` reutiliza `CandidateService::submitByToken()`;
- missing required document -> fallo controlado sin transición;
- complete candidate -> `verification`;
- submitted candidate deja de ser editable públicamente.

### 8.2 GREEN mínimo después del segundo RED

PLAN exige modificar/reutilizar:

```text
app/Candidates/routes.php
bootstrap/app.php
resources/views/unit_detail.php
resources/views/public_candidate_continue.php
```

Bootstrap pendiente:

```text
candidate_document_directory default = storage/candidate-documents
instanciar CandidateDocumentService($pdo, $candidateDocumentDirectory)
registrarlo sin contenedor DI ni nueva capa
```

### 8.3 Tensión documental detectada, todavía sin resolver

El PLAN F5.5 exige modificar `resources/views/unit_detail.php` para administrar requisitos en contexto de unidad, pero su lista de archivos NO incluye `app/Properties/routes.php`.

La ruta privada F4 que renderiza `unit_detail.php` actualmente no entrega requisitos F5.5. No meter SQL ni instanciar servicios dentro de la vista.

Al retomar, antes de escribir GREEN de UI:

1. comprobar de nuevo el render exacto de `unit_detail.php`;
2. intentar resolver con el mínimo wiring ya existente;
3. si es inevitable modificar `app/Properties/routes.php` para pasar `applicationRequirements`, documentarlo como desviación mínima justificada del PLAN;
4. NO crear pantalla paralela ni segundo route file para evitar esa modificación.

## 9. Lo que NO debe hacerse al retomar

- NO abrir F5.6.
- NO marcar F5.5 CLOSED todavía.
- NO mover PR #8 a Ready.
- NO mergear.
- NO crear tablas nuevas.
- NO reutilizar UnitMediaService.
- NO crear CandidateDocumentRepository/DTO/factory/interface.
- NO crear nueva capa de auth/CSRF/throttle.
- NO hacer SQL desde las vistas.
- NO crear ruta pública por documentId/requirementId.
- NO exponer candidate_id/document_id/requirement_id/path físico.
- NO revalidar publicabilidad de unidad al continuar/subir/enviar una candidatura ya creada, salvo contratos específicos ya definidos para cambio de unidad.
- NO cambiar CSS/JS salvo defecto reproducido.

## 10. Orden exacto para reanudar

```text
1. Releer este checkpoint.
2. Reconfirmar PR #8, branch HEAD y CI 38277046297.
3. Confirmar que product/test HEAD certificado es 72fe306d3c9206946c1aa4af9a5acd6bc1b522a4.
4. Releer F5.5 del PLAN; NO abrir F5.6.
5. Escribir segundo RED únicamente para bootstrap/rutas/UI F5.5.
6. Ejecutar RED y demostrar fallos por rutas/UI ausentes, sin regresiones inesperadas.
7. Implementar bootstrap + Candidate routes + public_candidate_continue + requirement UI mínima.
8. Resolver de forma KISS la inyección de requirements a unit_detail; modificar Properties/routes solo si se demuestra necesario.
9. Ejecutar focal GREEN:
   - CandidateDocumentsTest
   - CandidateServiceTest
   - pruebas nuevas de rutas/UI
   - SecurityBoundaryTest
10. Ejecutar full CI PHP 8.3/8.4/8.5.
11. Cero Ruido / Two Laws gate.
12. Actualizar F5_CURRENT_CHECKPOINT y cuerpo del PR con estado real.
13. Solo entonces declarar F5.5 CLOSED / VERIFIED.
14. Mantener PR Draft y esperar siguiente instrucción antes de F5.6 si así se ordena.
```

## 11. Punto de recuperación corto

```text
RESUME_FROM: F5.5 second RED — bootstrap/routes/UI
CERTIFIED_HEAD: 72fe306d3c9206946c1aa4af9a5acd6bc1b522a4
CERTIFIED_CI: 38277046297
CERTIFIED_MATRIX: PHP 8.3/8.4/8.5 PASS
CERTIFIED_TESTS: 183
CERTIFIED_ASSERTIONS: 1191
F5.5_DOMAIN: GREEN
F5.5_UI_ROUTES: NOT STARTED
F5.5_CLOSED: NO
F5.6_ALLOWED: NO
PR_8_DRAFT: YES
MERGE_ALLOWED: NO
```

Trabajo detenido aquí por solicitud expresa del usuario.
