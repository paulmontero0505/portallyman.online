# EVADES Block Evaluation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir el EVADES existente de altas individuales a evaluaciones trimestrales generadas y cerradas por bloque de coordinador y puesto.

**Architecture:** Añadir un bloque maestro persistente que agrupa las evaluaciones individuales actuales, con máquina de estados, auditoría y concurrencia optimista. Mantener el motor individual como unidad de cálculo y ampliar las APIs y `pages/evades.php` para operar sobre la nómina congelada del bloque sin perder históricos ni exportación PDF.

**Tech Stack:** PHP 7+/8 procedural, MySQL/MariaDB con MySQLi, JavaScript nativo, CSS existente del módulo, pruebas PHP por CLI y `node:test` para el modelo de interfaz.

## Global Constraints

- Trabajar sobre el módulo EVADES existente; no crear un módulo alternativo.
- Periodos válidos: `YYYY-T1`, `YYYY-T2`, `YYYY-T3`, `YYYY-T4`.
- Puestos válidos: `ASISTENTE DE ESTIBA` y `ANALISTA DE TROUBLE DESK`.
- Ambos puestos usan las mismas diez competencias y reglas EVADES.
- Estados: `generado -> revisado -> modificado -> cerrado`.
- `cerrado` es inmutable y no se reabre desde el flujo ordinario.
- La nómina queda congelada al generar el bloque.
- Conservar la paleta esmeralda y las mejoras locales no confirmadas de evidencia y PDF.
- No incluir en commits cambios ajenos que ya existen en el worktree.

## File Map

- Create: `sql/032_evades_bloques.sql` — tablas, relaciones y versión de bloque.
- Create: `includes/evades_bloques.php` — dominio, permisos, consultas y transacciones de bloques.
- Modify: `includes/evades_catalogo.php` — puestos, estados y reglas puras compartidas.
- Modify: `api/get_evades.php` — compatibilidad individual más contexto de bloque.
- Create: `api/get_evades_bloques.php` — lista y detalle de bloques.
- Create: `api/preview_evades_bloque.php` — previsualización de la nómina elegible.
- Create: `api/generar_evades_bloque.php` — generación masiva transaccional.
- Create: `api/abrir_evades_bloque.php` — transición idempotente a revisado.
- Create: `api/cerrar_evades_bloque.php` — validación y cierre irreversible.
- Modify: `api/save_evades.php` — guardado hijo, auditoría y versión optimista.
- Modify: `api/delete_evades.php` — impedir borrar evaluaciones de bloques.
- Modify: `api/calcular_evades.php` — aceptar ambos puestos sin romper cálculo individual.
- Create: `assets/js/evades-bloques-model.js` — estado de interfaz sin dependencias DOM.
- Modify: `pages/evades.php` — lista por bloques, generador y espacio de trabajo.
- Modify: `tests/evades_catalogo_test.php` — contratos de puesto y estado.
- Create: `tests/evades_bloques_db_test.php` — integración transaccional del dominio.
- Create: `tests/evades_bloques_model.test.js` — progreso, estados y cambios pendientes.

---

### Task 1: Domain contracts for jobs and block states

**Files:**
- Modify: `tests/evades_catalogo_test.php`
- Modify: `includes/evades_catalogo.php`

**Interfaces:**
- Produces: `evades_puestos_validos(): array`
- Produces: `evades_normalizar_puesto(string): ?string`
- Produces: `evades_estados_bloque(): array`
- Produces: `evades_bloque_editable(string): bool`
- Produces: `evades_transicion_bloque_valida(string, string): bool`

- [ ] **Step 1: Write failing domain tests**

Add literal assertions:

```php
eq(evades_normalizar_puesto('Analista de Trouble Desk'), 'ANALISTA DE TROUBLE DESK', 'normaliza Analista');
eq(evades_normalizar_puesto('asistente de estiba'), 'ASISTENTE DE ESTIBA', 'normaliza Asistente');
eq(evades_normalizar_puesto('Coordinador'), null, 'rechaza puesto fuera de EVADES');
ok(evades_transicion_bloque_valida('generado', 'revisado'), 'primera apertura permitida');
ok(evades_transicion_bloque_valida('revisado', 'modificado'), 'primer cambio permitido');
ok(evades_transicion_bloque_valida('modificado', 'cerrado'), 'cierre permitido');
ok(!evades_transicion_bloque_valida('cerrado', 'modificado'), 'cerrado no se reabre');
ok(!evades_bloque_editable('cerrado'), 'cerrado es inmutable');
```

- [ ] **Step 2: Run tests and observe RED**

Run: `C:\xampp2026\php\php.exe tests\evades_catalogo_test.php`

Expected: fatal error for missing `evades_normalizar_puesto()`.

- [ ] **Step 3: Implement minimal domain helpers**

Use canonical uppercase values and an explicit transition map:

```php
function evades_puestos_validos() {
    return ['ASISTENTE DE ESTIBA', 'ANALISTA DE TROUBLE DESK'];
}

function evades_normalizar_puesto($puesto) {
    $normalizado = strtoupper(trim((string)$puesto));
    return in_array($normalizado, evades_puestos_validos(), true) ? $normalizado : null;
}

function evades_estados_bloque() {
    return ['generado', 'revisado', 'modificado', 'cerrado'];
}

function evades_bloque_editable($estado) {
    return in_array($estado, ['generado', 'revisado', 'modificado'], true);
}

function evades_transicion_bloque_valida($desde, $hacia) {
    $mapa = [
        'generado' => ['revisado', 'modificado', 'cerrado'],
        'revisado' => ['modificado', 'cerrado'],
        'modificado' => ['cerrado'],
        'cerrado' => [],
    ];
    return in_array($hacia, $mapa[$desde] ?? [], true);
}
```

- [ ] **Step 4: Run catalog tests and observe GREEN**

Run: `C:\xampp2026\php\php.exe tests\evades_catalogo_test.php`

Expected: all assertions pass.

- [ ] **Step 5: Commit only Task 1 files**

```powershell
git add -- includes/evades_catalogo.php tests/evades_catalogo_test.php
git commit -m "feat(evades): define puestos y estados de bloques"
```

### Task 2: Database schema and legacy compatibility

**Files:**
- Create: `sql/032_evades_bloques.sql`
- Create: `tests/evades_bloques_db_test.php`

**Interfaces:**
- Produces tables: `evades_bloques`, `evades_bloques_estados`, `evades_modificaciones`
- Produces column: `evades_evaluaciones.bloque_id`
- Produces column: `evades_evaluaciones.version`

- [ ] **Step 1: Write a failing real-schema test**

The test must connect through `includes/db.php` and assert observable schema behavior:

```php
$tables = ['evades_bloques', 'evades_bloques_estados', 'evades_modificaciones'];
foreach ($tables as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    ok(mysqli_num_rows($res) === 1, "$table existe");
}
$res = mysqli_query($conn, "SHOW COLUMNS FROM evades_evaluaciones LIKE 'bloque_id'");
ok(mysqli_num_rows($res) === 1, 'evaluaciones tiene bloque_id');
```

- [ ] **Step 2: Run schema test and observe RED**

Run: `C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php`

Expected: fails because `evades_bloques` does not exist.

- [ ] **Step 3: Write idempotent migration**

Create the three tables with foreign keys, a unique key
`(coordinador_id, puesto, periodo)`, timestamps, `version INT NOT NULL DEFAULT 1`,
and nullable `bloque_id` on legacy evaluations. Use `information_schema` guards
for `ALTER TABLE` statements because MariaDB versions differ in support for
`ADD COLUMN IF NOT EXISTS`.

`evades_modificaciones` must store `antes_json`, `despues_json`,
`motivo`, `usuario_id`, and the affected evaluation. `evades_bloques_estados`
must store old/new states and actor.

- [ ] **Step 4: Apply migration to the configured local database**

Run the SQL through the project's configured MySQL client after resolving the
database name from `includes/db.php`. Do not import either full dump.

Expected: statements complete without data deletion.

- [ ] **Step 5: Run schema test and observe GREEN**

Run: `C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php`

Expected: all tables, columns, foreign keys and unique index are present; an
existing row with `bloque_id IS NULL` remains readable.

- [ ] **Step 6: Commit only schema and test**

```powershell
git add -- sql/032_evades_bloques.sql tests/evades_bloques_db_test.php
git commit -m "feat(evades): agrega esquema de evaluacion por bloques"
```

### Task 3: Block domain service and mass generation

**Files:**
- Create: `includes/evades_bloques.php`
- Modify: `tests/evades_bloques_db_test.php`
- Create: `api/preview_evades_bloque.php`
- Create: `api/generar_evades_bloque.php`
- Modify: `api/calcular_evades.php`

**Interfaces:**
- Consumes: `evades_normalizar_puesto()`, `evades_calcular_sugerencias()`
- Produces: `evades_resolver_coordinador_objetivo(array $session, int $requestedId): int`
- Produces: `evades_obtener_nomina(mysqli $conn, int $coordinadorId, string $puesto): array`
- Produces: `evades_generar_bloque(mysqli $conn, int $coordinadorId, string $puesto, string $periodo, int $actorId): array`

- [ ] **Step 1: Extend the integration test with roster and transaction cases**

Within a database transaction, create or reuse controlled fixture users and
collaborators, then assert:

```php
$nomina = evades_obtener_nomina($conn, $coordId, 'ANALISTA DE TROUBLE DESK');
eq(array_column($nomina, 'id'), [$analistaId], 'solo incluye activos del puesto y coordinador');

$bloque = evades_generar_bloque($conn, $coordId, 'ANALISTA DE TROUBLE DESK', '2098-T1', $coordId);
eq($bloque['estado'], 'generado', 'nace generado');
eq($bloque['total_colaboradores'], 1, 'congela una persona');
eq(count($bloque['evaluaciones']), 1, 'crea una evaluación hija');
eq(count($bloque['evaluaciones'][0]['competencias']), 10, 'guarda las diez competencias');
```

Add a second call and assert the duplicate is rejected without adding rows.
Rollback all fixtures in `finally`.

- [ ] **Step 2: Run integration test and observe RED**

Run: `C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php`

Expected: missing `includes/evades_bloques.php` or missing generation function.

- [ ] **Step 3: Implement the service transaction**

The service must:

```php
mysqli_begin_transaction($conn);
try {
    // validate role, period, normalized job, unique block and non-empty roster
    // insert evades_bloques
    // run evades_calcular_sugerencias for each frozen collaborator
    // insert evades_evaluaciones and exactly 10 evades_competencias rows
    // insert initial evades_bloques_estados row
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    throw $e;
}
```

Extract the existing score/previous-score persistence logic from
`save_evades.php` into focused service helpers instead of copying it.

- [ ] **Step 4: Implement preview and generation API contracts**

`preview_evades_bloque.php` accepts:

```json
{"puesto":"ANALISTA DE TROUBLE DESK","periodo":"2026-T3","coordinador_id":22}
```

and returns canonical puesto, periodo, coordinator and the eligible roster.
`generar_evades_bloque.php` accepts the same payload and returns the generated
block id, status and total. Coordinators cannot override their session id;
administrator/supervisor override is validated explicitly.

- [ ] **Step 5: Accept both EVADES jobs in individual calculation**

Replace the hard-coded Assistant-only check in `calcular_evades.php` with
`evades_normalizar_puesto($col['funcion_principal']) !== null` and preserve the
coordinator ownership check.

- [ ] **Step 6: Run integration and catalog tests and observe GREEN**

```powershell
C:\xampp2026\php\php.exe tests\evades_catalogo_test.php
C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php
```

- [ ] **Step 7: Commit Task 3 files only**

```powershell
git add -- includes/evades_bloques.php tests/evades_bloques_db_test.php api/preview_evades_bloque.php api/generar_evades_bloque.php api/calcular_evades.php
git commit -m "feat(evades): genera evaluaciones por bloque"
```

### Task 4: Listing, opening, saving, audit and irreversible closing

**Files:**
- Modify: `tests/evades_bloques_db_test.php`
- Create: `api/get_evades_bloques.php`
- Create: `api/abrir_evades_bloque.php`
- Create: `api/cerrar_evades_bloque.php`
- Modify: `api/get_evades.php`
- Modify: `api/save_evades.php`
- Modify: `api/delete_evades.php`
- Modify: `includes/evades_bloques.php`

**Interfaces:**
- Produces: `evades_obtener_bloque(mysqli $conn, int $id, int $actorId, string $rol): ?array`
- Produces: `evades_marcar_revisado(mysqli $conn, int $bloqueId, int $actorId): array`
- Produces: `evades_guardar_evaluacion_bloque(mysqli $conn, array $payload, int $actorId): array`
- Produces: `evades_cerrar_bloque(mysqli $conn, int $bloqueId, int $version, int $actorId): array`

- [ ] **Step 1: Write failing state, audit, conflict and close tests**

Exercise real persisted behavior:

```php
$opened = evades_marcar_revisado($conn, $bloqueId, $coordId);
eq($opened['estado'], 'revisado', 'primera apertura cambia estado');
$openedAgain = evades_marcar_revisado($conn, $bloqueId, $coordId);
eq($openedAgain['version'], $opened['version'], 'segunda apertura es idempotente');

$saved = evades_guardar_evaluacion_bloque($conn, $payload, $coordId);
eq($saved['estado'], 'modificado', 'primer cambio marca modificado');
eq($saved['modificaciones_creadas'], 1, 'cambio real queda auditado');
```

Then submit the stale version and assert a domain exception with code
`EVADES_VERSION_CONFLICT`. Close a complete block and assert every save/delete
attempt returns `EVADES_BLOQUE_CERRADO` and no row changes.

- [ ] **Step 2: Run integration test and observe RED**

Run: `C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php`

Expected: missing state/audit service functions.

- [ ] **Step 3: Implement listing and idempotent opening**

The list query must aggregate child count, average score, completed count and
classification counts, while applying visibility against the block's frozen
`coordinador_id`. Detail returns the frozen roster ordered by name and each
evaluation's current score/classification.

Opening uses `UPDATE ... WHERE estado='generado'`, writes one transition and
increments `version`; otherwise it returns the current block unchanged.

- [ ] **Step 4: Refactor save into an atomic block-aware operation**

Require `bloque_id`, `id` and `version` for block children. Lock the block with
`SELECT ... FOR UPDATE`, compare versions, reject closed, recalculate automatic
evidence, validate ten rows, compare old/new snapshots, and only then update.

Store a single modification record per save with JSON containing only changed
fields. Change block status to `modificado` and increment version in the same
transaction. Preserve legacy `bloque_id IS NULL` reading and the pre-existing
PDF evidence enhancements.

- [ ] **Step 5: Implement strict closing**

Validate non-empty block, exactly ten competence rows per evaluation,
consistent totals, required adjustment motives, and non-empty
`fortalezas`, `aspectos_mejora`, and `plan_accion`. Then update
`estado='cerrado'`, set `cerrado_at/cerrado_por`, increment version and append
the state history atomically.

- [ ] **Step 6: Protect legacy mutation endpoints**

`delete_evades.php` must reject any evaluation with non-null `bloque_id`.
`get_evades.php?id=` must include `bloque_id`, block state and version so the
workspace can render read-only mode. Keep unblocked historical detail working.

- [ ] **Step 7: Run all PHP EVADES tests and observe GREEN**

```powershell
C:\xampp2026\php\php.exe tests\evades_catalogo_test.php
C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php
```

- [ ] **Step 8: Commit Task 4 files only**

```powershell
git add -- includes/evades_bloques.php tests/evades_bloques_db_test.php api/get_evades_bloques.php api/abrir_evades_bloque.php api/cerrar_evades_bloque.php api/get_evades.php api/save_evades.php api/delete_evades.php
git commit -m "feat(evades): agrega revision auditoria y cierre de bloques"
```

### Task 5: Testable client model and block workspace UI

**Files:**
- Create: `tests/evades_bloques_model.test.js`
- Create: `assets/js/evades-bloques-model.js`
- Modify: `pages/evades.php`

**Interfaces:**
- Produces global/module: `EvadesBloquesModel`
- Produces: `createWorkspace(block): WorkspaceState`
- Produces: `selectEvaluation(state, id): WorkspaceState`
- Produces: `markDirty(state): WorkspaceState`
- Produces: `blockProgress(evaluations): {complete:number,total:number,percent:number}`

- [ ] **Step 1: Write failing UI model tests**

Use Node's built-in runner and literal expectations:

```js
test('calculates completion progress from frozen roster', () => {
  assert.deepEqual(blockProgress([
    { id: 1, completa: true },
    { id: 2, completa: false },
  ]), { complete: 1, total: 2, percent: 50 });
});

test('refuses silent navigation when current evaluation is dirty', () => {
  const state = markDirty(createWorkspace({ estado: 'modificado', evaluaciones: [{id: 1}, {id: 2}] }));
  assert.equal(selectEvaluation(state, 2).needsConfirmation, true);
  assert.equal(selectEvaluation(state, 2).selectedId, 1);
});

test('closed workspace is never editable', () => {
  assert.equal(createWorkspace({ estado: 'cerrado', evaluaciones: [{id: 1}] }).editable, false);
});
```

- [ ] **Step 2: Run model test and observe RED**

Run: `node --test tests\evades_bloques_model.test.js`

Expected: module not found.

- [ ] **Step 3: Implement minimal immutable UI model**

Export for CommonJS tests and attach to `window` in browser. Do not include DOM
operations in this file.

- [ ] **Step 4: Run model tests and observe GREEN**

Run: `node --test tests\evades_bloques_model.test.js`

Expected: all tests pass.

- [ ] **Step 5: Replace individual-first list with block-first interface**

Keep the module's existing hero and PDF functions, but render block rows/cards
with puesto, trimestre, status, coordinator, roster count, completion, average
and last update. Move legacy rows to a `Históricos individuales` section.

New-evaluation modal fields are puesto, trimestre and coordinator only for
admin/supervisor. Preview the frozen roster before enabling `Generar bloque`.

- [ ] **Step 6: Build the block workspace**

Use this content structure:

```text
+--------------------------------------------------------------+
| Puesto · Trimestre     GENERADO > REVISADO > MODIFICADO > CERRADO |
| 8 personas · 63 promedio · 5/8 completas                     |
+--------------------+-----------------------------------------+
| Buscar persona     | Persona seleccionada · puntaje          |
| [nómina congelada] | Evidencia automática y 10 competencias  |
| estado / puntaje   | Fortalezas · mejoras · plan             |
|                    | [Anterior] [Guardar cambios] [Siguiente] |
+--------------------+-----------------------------------------+
```

The status route is the signature element: a restrained operational progress
line, not a decorative stepper. It communicates which actions already
happened and why the record is editable or locked.

- [ ] **Step 7: Apply the approved visual system**

Use existing typography and these tokens:

```css
--ev-emerald-700:#006b49;
--ev-emerald-600:#00875a;
--ev-emerald-050:#e8f5ef;
--ev-ink:#0f2940;
--ev-muted:#64748b;
--ev-amber:#f5a524;
--ev-surface:#ffffff;
```

Use blue-gray only for `generado`, emerald wash for `revisado`, amber for
`modificado`, and deep emerald for `cerrado`. Every status also includes text
and icon. Keep animation to one workspace entrance, disable it under
`prefers-reduced-motion`, preserve visible keyboard focus and collapse the
roster to a select below 760px.

- [ ] **Step 8: Wire API actions and unsaved-change protection**

On open, call the idempotent open endpoint, load detail, then select the first
person. Save submits block/evaluation/version and refreshes the returned
version. Close requires explicit confirmation and changes the entire workspace
to read-only. Navigation and modal close use the tested dirty-state model.

- [ ] **Step 9: Run automated UI model and PHP tests**

```powershell
node --test tests\evades_bloques_model.test.js
C:\xampp2026\php\php.exe tests\evades_catalogo_test.php
C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php
```

- [ ] **Step 10: Commit Task 5 files only**

```powershell
git add -- assets/js/evades-bloques-model.js tests/evades_bloques_model.test.js pages/evades.php
git commit -m "feat(evades): implementa espacio de trabajo por bloques"
```

### Task 6: Browser QA, regression verification and handoff

**Files:**
- Modify only files implicated by failing tests or visual defects.

**Interfaces:**
- Consumes all previous tasks.
- Produces a verified end-to-end EVADES block workflow.

- [ ] **Step 1: Read the browser-control skill before browser actions**

Load `browser:control-in-app-browser` and use the signed-in local application
if available.

- [ ] **Step 2: Run complete automated verification**

```powershell
C:\xampp2026\php\php.exe -l includes\evades_catalogo.php
C:\xampp2026\php\php.exe -l includes\evades_bloques.php
C:\xampp2026\php\php.exe -l api\get_evades_bloques.php
C:\xampp2026\php\php.exe -l api\preview_evades_bloque.php
C:\xampp2026\php\php.exe -l api\generar_evades_bloque.php
C:\xampp2026\php\php.exe -l api\abrir_evades_bloque.php
C:\xampp2026\php\php.exe -l api\cerrar_evades_bloque.php
C:\xampp2026\php\php.exe -l api\save_evades.php
C:\xampp2026\php\php.exe tests\evades_catalogo_test.php
C:\xampp2026\php\php.exe tests\evades_bloques_db_test.php
node --test tests\evades_bloques_model.test.js
```

Expected: zero syntax errors and all assertions pass.

- [ ] **Step 3: Exercise the coordinator journey in the browser**

Verify preview, generation for each job, first-open state, roster navigation,
automatic incident evidence, manual adjustment reason, save/audit, stale
version conflict, close confirmation and read-only behavior.

- [ ] **Step 4: Exercise compatibility and responsive behavior**

Verify an old individual evaluation, individual PDF export, desktop layout,
mobile roster selector, keyboard focus and reduced-motion preference.

- [ ] **Step 5: Critique and refine the visual result**

Capture screenshots of list, new-block modal and workspace. Check that the
status route is the only visually assertive device, remove ornamental elements
that do not communicate state or evidence, and rerun all tests after CSS/JS
changes.

- [ ] **Step 6: Inspect final diff and working tree boundaries**

Run `git diff --check` and `git status --short`. Confirm unrelated pre-existing
changes were not staged or overwritten.

- [ ] **Step 7: Use verification-before-completion before claiming success**

Load the required skill, rerun its prescribed evidence checks, and report the
actual commands and outcomes.

