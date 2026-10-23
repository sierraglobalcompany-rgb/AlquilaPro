# AlquilaPro — CURRENT CHECKPOINT

**Corte:** 2026-10-23, America/Bogota. Autoridad: SHA actual de GitHub → CI del SHA → esta nota → SPEC/plan aprobado → historial. El checkpoint canónico de esta fase vive en esta rama; `main` permanece protegido. No sustituir el estado observado por fechas o HEAD antiguos.

## Fuentes y estado recuperable
- **Repo** `sierraglobalcompany-rgb/AlquilaPro`.
- **Main** `414bcfeae0a3b4f8eea7262eefcb498e3f116307`: merge autorizado por squash del [PR #54](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/54); Work CAND-02 y Documentos con requisitos reales integrados. **No deploy**.
- **QA PR #54:** CI #1177 `SUCCESS` PHP 8.3/8.4/8.5 sobre fuente pre-merge `ba152597e2fd47792fc39e96c3906cce38dc00d9`; Browser QA focal fuente de código `ffddc415c57c98588ca5c895d9e56f4eab0e1945` `PASS_WITH_SCOPE`. Tree del squash = tree de fuente validada. Paridad global y WCAG completa `NOT_CERTIFIED`.
- **PR #52 y #53:** cerrados **sin merge** como `SUPERSEDED_BY_PR54`. Ramas/diffs/QA disponibles, pero NO fusionarlas otra vez.
- **CAND-03:** [PR #55](https://github.com/sierraglobalcompany-rgb/AlquilaPro/pull/55) Draft; rama `impl/cand-03-household-human-first-20261023`, base `main@414bcfeae0a3b4f8eea7262eefcb498e3f116307`. **SHA de código antes de este commit de documentación** `36d4161a6a442a943201ff6a5d5f9736d09db639`. Commit nuevo = solo Markdown; verificar HEAD/CI tras este commit.
- **CI de CAND-03:** `NOT_VERIFIED` al redactar, PHP 8.3/8.4/8.5 requeridos. **Browser QA de CAND-03:** `NOT_RUN`, paridad mockup `NOT_CERTIFIED`. No inferir PASS de PR54.

## Contrato CAND-03: Ocupantes y mascotas
- Autoridades: `docs/superpowers/specs/2026-10-17-candidates-human-first-redesign.md` §9.3, plan homónimo Task 3, `docs/design/CANDIDATES_VISUAL_BASELINE_2026-10-17.md` §03 y PNG `03_OCUPANTES_MASCOTAS.png`.
- Verdad existente de `candidates`: `occupants_count` (nulo admite no respuesta y 0 explícito), `occupants_notes`, `pets_declared`, `pets_notes`. **Riesgo conocido**: `pets_declared NOT NULL DEFAULT 0`, wizard «No» preseleccionado. Un cero en borrador no prueba una declaración. Sin migrar: diferenciar `submitted_at` para mostrar `Sin mascotas registradas en la solicitud` únicamente tras envío; en borrador vacío presentar estado vacío y con notas `Declaración de mascotas no confirmada`. No confundir «no registro» con «no tiene mascotas».
- Un `section=household` renderiza resumen factual y cards de ocupantes y mascotas; escapar notas con saltos de línea; sin expediente de personas, fotos, raza, vacunación, política de inmueble, indicadores o riesgo inventados.
- Únicos archivos de producto CAND-03: `resources/views/candidate_detail.php`, `assets/app.css` específico y `tests/Integration/Candidates/HumanFirstCandidateDetailViewTest.php`; RED creado antes de GREEN, pero no se ejecutó el RED local. No API ni base de datos ni servicios nuevos.
- `main` conserva Work, Documents, permisos, contratos y componentes. Ningún cambio de esos módulos permitido en esta fase.

## Cuatro leyes, gates y NEXT
1. **KISS:** PHP SSR/Slim/PDO; dos cards secundarias y un resumen usando datos reales, sin capas ni frontend paralelo.
2. **Antirruido:** cerrar PRs superados con prueba de solapamiento; un único checkpoint, no multiplicar reportes ni ramas alternativas.
3. **Antiparches:** tratar causa del falso «No mascotas» en visualización sin inferir una respuesta inexistente; una suite causal para null/0/default/XSS.
4. **UX/UI Pro:** usar PNG original solo para composición, sin fotos/acciones/scores falsos; comparar capturas con rol, contenido y viewport. Paridad total `NOT_CERTIFIED` hasta evidencia suficiente.

**NEXT ÚNICO:** revisar CI exacto de PR #55 y auditar contratos de `household`; si GREEN, ejecutar Browser QA focal solo de CAND-03 (1448,768,375 + borrador y scroll final) una vez. Si RED, reparar causa concreta y revalidar. **PR #55 Draft; NO MERGE, NO HOSTINGER/DEPLOY; no abrir CAND-04 antes del gate.**
