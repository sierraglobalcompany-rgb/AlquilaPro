# AGENTS.md — AlquilaPro

Este archivo define reglas durables de trabajo para cualquier agente/IA. **No es un tablero de estado de fases**. Para estado actual leer `README.md`, el checkpoint canónico de la fase activa y la PR/CI del SHA exacto.

## Autoridad

Si hay contradicción, usar este orden:

1. GitHub real: código, migraciones, tests, diff y SHA.
2. CI del SHA exacto.
3. Checkpoint canónico de fase.
4. SPEC / PLAN aprobados.
5. README y documentación narrativa.
6. Chat / memoria.

Nunca declarar trabajo hecho porque un handoff lo diga si GitHub no lo demuestra.

## Ley 1 — KISS

Todo cambio debe ser simple, directo, eficiente, optimizado y eficaz.

- Reutilizar owners existentes antes de crear nuevas autoridades.
- No agregar tabla, clase, servicio, permiso, ruta, scheduler, dependencia o framework sin necesidad demostrada.
- Ir de A a B; evitar abstracciones que solo redistribuyen complejidad.
- El presupuesto arquitectónico de cada fase es un límite, no un objetivo a consumir.

## Ley 2 — Cero Ruido / Convergencia

```text
ELIMINAR
→ UNIR / CONVERGER
→ SIMPLIFICAR
→ MEJORAR / OPTIMIZAR
→ AGREGAR
```

Antes de eliminar, demostrar que el elemento está supersedido y que no contiene autoridad, evidencia o recuperación única. No arrastrar checkpoints transitorios dentro del HEAD final solo por precaución; preservar recuperación en ramas `checkpoint/...` cuando sea suficiente.

## Ley 3 — Anti-parche

```text
REPRODUCE
→ ROOT CAUSE
→ OWNING AUTHORITY
→ SEARCH DUPLICATION
→ RED
→ MINIMAL GREEN
→ REMOVE SUPERSEDED LOGIC
→ REGRESSION
```

No aplicar arreglos especulativos. Un cambio de producto necesita un RED que reproduzca el problema, salvo cambios puramente documentales/tooling de cierre que no alteren runtime.

## Método obligatorio

1. Confirmar base, branch, HEAD, PR y scope.
2. PRE-KISS: owner canónico, presupuesto y prohibiciones.
3. Confirmar SPEC/DESIGN y PLAN antes de producto cuando la fase lo requiera.
4. Auditar diez pasadas antes de implementar y antes de cerrar.
5. TDD por tarea: RED verificado → GREEN mínimo → regresión.
6. CI del SHA exacto; no reutilizar CI de un commit anterior.
7. Checkpoint antes de abrir el siguiente gate.
8. Merge solo con base/head/mergeability y CI nuevamente verificados.

## Política de checkpoints

- Crear checkpoints frecuentes para evitar pérdida de continuidad.
- Preferir ramas `checkpoint/<fase>-<gate>-<fecha>` para estados transitorios.
- Un checkpoint RED preserva el RED y no mezcla GREEN.
- Un GREEN candidate se guarda antes de CI; un GREEN certificado solo después de CI completa.
- El HEAD final de una fase converge a un checkpoint documental canónico; los checkpoints intermedios pueden vivir solo en ramas.

## QA / CI

- PHP soportado: 8.3, 8.4 y 8.5.
- `composer validate --strict` obligatorio.
- Lint PHP obligatorio dentro del CI canónico.
- PHPUnit completo en la matriz soportada.
- No declarar PASS mientras un job esté queued/in_progress.
- Distinguir Product Head de Closure Head cuando un commit posterior sea solo docs/tooling.
- Evitar reruns rituales: mismo test + mismo código + mismo entorno no agrega evidencia.

## Contratos durables

- Tenant ownership se resuelve antes de lookups hijos.
- CSRF global se mantiene para mutaciones autenticadas; excepciones deben ser exactas y protegidas.
- Archivos privados validan contención, tamaño, MIME por bytes e integridad según owner; fallan cerrados.
- `FinanceService` conserva la autoridad financiera compartida.
- `NotificationService` conserva la autoridad de cola/proceso/retención automática; no crear segundo scheduler.
- WhatsApp sigue siendo salida asistida, no proveedor de estado de entrega paralelo.
- Auditoría F22 usa un solo `AuditService` / `audit_log`; no event bus, segunda pila de logs o framework de seguridad.
- Nunca registrar passwords, raw tokens, cookies/sesiones, documentos, receipts/payment bodies, SQL o exception traces en auditoría.
- `REMOTE_ADDR` validado es la fuente F22 de IP; no confiar ciegamente en forwarding headers.

## Roles

### ChatGPT / Sol

- producto, SPEC, arquitectura, UX, planes, auditoría, GitHub, QA y continuidad;
- contrastar siempre estado documental con GitHub real;
- buscar huecos, duplicaciones, ruido y parches;
- mantener checkpoints y PR actualizados por gate.

### Codex

Usarlo cuando la implementación local/TDD o tooling del PC aporte valor. Cuando se delegue:

- un Markdown autocontenido por lote aprobado;
- hard stops y presupuesto arquitectónico explícitos;
- TDD focal y checkpoint recuperable;
- no rediseñar decisiones congeladas;
- no merge sin autorización inequívoca;
- no releer todo el repo si checkpoint/SPEC/PLAN ya fijan la fase.

## Roadmap guardrail

No implementar una fase futura antes de que la precedente esté cerrada y mergeada.

```text
F21 Bono + liquidación
F22 Seguridad + privacidad + audit log
F23 Dashboard + Adaptive UX Final
F24 Release Readiness + installer/candidate wizard + QA
F25 SERVER_READY ZIP + exact-artifact rehearsal
F26 Manual Hostinger deploy + smoke
```

No existe F27 en el roadmap aprobado.

Para F23–F26 usar `docs/specs/F23_F26_UX_RELEASE_SPEC_V1_2026-10-14.md` y el checkpoint de la fase vigente; no adelantar módulos futuros para resolver una fase anterior.

## Recovery

```text
GitHub real + CI del SHA exacto
→ README
→ AGENTS
→ checkpoint canónico de fase
→ SPEC / PLAN de la fase
→ NEXT_EXACT_ACTION
```

No reimplementar trabajo certificado sin evidencia real de regresión o una nueva SPEC aprobada.
