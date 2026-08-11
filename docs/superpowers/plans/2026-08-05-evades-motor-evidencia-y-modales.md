# EVADES Motor Integral and Hybrid Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the quarterly EVADES scoring engine with every evidence source and cross-competency rule from the approved guide, then implement the approved hybrid generation, evaluation, and read-only modals.

**Architecture:** Keep `evades_engine.php` as the calculation orchestrator and move source-specific database reads into a focused `evades_evidence.php`. Declare all mappings and quantitative thresholds in `evades_catalogo.php`, persist coordinator appreciations in an additive table, and expose one normalized evidence contract to the existing block domain and UI.

**Tech Stack:** PHP 8, MySQL/MariaDB (`mysqli`), vanilla JavaScript, CSS, jsPDF, existing PHP and Node assertion tests.

## Global Constraints

- Preserve the existing blue EVADES palette.
- Keep base score 6, positive increment limited to 0/+2/+4, final score clamped to 0-10, and total to 0-100.
- Use only evidence dated inside the selected `YYYY-T#` period.
- Never recalculate or edit a closed block.
- Treat `punto_mejorar` as the canonical incident mapping field.
- Do not score individual Productividad from team-level indicators.
- Preserve legacy evaluations with `bloque_id IS NULL`.
- Do not stage or rewrite unrelated dirty-worktree changes.

---

### Task 1: Canonical evidence catalog and cross-competency rules

**Files:**
- Modify: `includes/evades_catalogo.php`
- Modify: `includes/incidencias_catalogo.php`
- Modify: `tests/evades_catalogo_test.php`

**Interfaces:**
- Produces: `evades_reglas_evidencia(): array`, `evades_umbrales_ept(): array`, `evades_claves_afectadas_por_punto(string): array`.
- Consumes: `inc_puntos_competencia(): array`.

- [ ] **Step 1: Write failing catalog tests**

Add assertions equivalent to:

```php
$reglas = evades_reglas_evidencia();
eq($reglas['eficiencia']['cruzadas'], ['dominio_solido'], 'Eficiencia cruza con Dominio');
eq($reglas['dominio_solido']['puntos_incidencia'], [
    'Errores de pedeteo', 'Error de registro en balanzas', 'Registro de USR',
    'Registro de CDR', 'Trabajo en PS',
], 'Dominio usa los cinco procedimientos técnicos');
eq(evades_claves_afectadas_por_punto('Errores de pedeteo'),
   ['dominio_solido', 'eficiencia'], 'el error técnico afecta primaria y cruzada');
eq(evades_umbrales_ept(), ['minimo' => 3, 'nivel_2' => 4.0, 'nivel_4' => 4.5],
   'umbrales EPT explícitos');
eq(evades_incrementos_validos(), [0, 2, 4], 'la guía no permite +6');
```

- [ ] **Step 2: Run the test and confirm RED**

Run: `php tests/evades_catalogo_test.php`

Expected: FAIL because the new functions do not exist and +6 is still accepted.

- [ ] **Step 3: Implement the canonical mapping**

Add explicit rules for all ten keys. The essential structure is:

```php
function evades_reglas_evidencia() {
    return [
        'autonomia' => [
            'puntos_incidencia' => ['Supervisión constante'],
            'cruzadas' => [],
            'criterios_ept' => ['apoyo_equipo'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
        'organizacion_tiempo' => [
            'puntos_incidencia' => ['Tardanza o incumplimiento de charla', 'Incumplimiento de refrigerio'],
            'cruzadas' => ['disciplina_profesional'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'asistencia', 'apreciacion'],
        ],
        'adaptabilidad' => [
            'puntos_incidencia' => ['Resistencia al cambio'],
            'cruzadas' => ['iniciativa_compromiso'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'propuesta', 'apreciacion'],
        ],
        'productividad' => [
            'puntos_incidencia' => [],
            'cruzadas' => [],
            'criterios_ept' => [],
            'fuentes_positivas' => ['apreciacion'],
        ],
        'eficiencia' => [
            'puntos_incidencia' => ['Errores de pedeteo', 'Error de registro en balanzas', 'Registro de USR', 'Registro de CDR', 'Trabajo en PS'],
            'cruzadas' => ['dominio_solido'],
            'criterios_ept' => ['procedimientos', 'registro_preciso'],
            'fuentes_positivas' => ['ept_sin_incidencias', 'apreciacion'],
        ],
        'dominio_solido' => [
            'puntos_incidencia' => ['Errores de pedeteo', 'Error de registro en balanzas', 'Registro de USR', 'Registro de CDR', 'Trabajo en PS'],
            'cruzadas' => ['eficiencia'],
            'criterios_ept' => ['procedimientos', 'registro_preciso'],
            'fuentes_positivas' => ['ept_sin_incidencias', 'apreciacion'],
        ],
        'comunicacion_colaboracion' => [
            'puntos_incidencia' => ['Continuidad operativa', 'Relevo o radio deficiente'],
            'cruzadas' => [],
            'criterios_ept' => ['trato_colaborativo', 'comunicacion_novedades', 'apoyo_equipo'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
        'iniciativa_compromiso' => [
            'puntos_incidencia' => ['Proyección operativa', 'Falta de recursos o información'],
            'cruzadas' => ['adaptabilidad'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'propuesta', 'apreciacion'],
        ],
        'disciplina_profesional' => [
            'puntos_incidencia' => ['Responsabilidad en funciones', 'Asistencia a capacitaciones', 'Abandono o desacato'],
            'cruzadas' => ['organizacion_tiempo'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'asistencia', 'apreciacion'],
        ],
        'seguridad_trabajo' => [
            'puntos_incidencia' => ['Seguridad y salud en el trabajo'],
            'cruzadas' => [],
            'criterios_ept' => ['uso_epp', 'zona_segura', 'reporte_riesgos'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
    ];
}
```

Expand `inc_puntos_competencia()` with the guide events for supervision,
punctuality/refrigerio, resistance to change, deficient handover/radio, missing
resources, abandonment/disobedience, and safety.

- [ ] **Step 4: Run catalog tests and regression checks**

Run: `php tests/evades_catalogo_test.php`

Expected: all assertions PASS and no accepted increment exceeds +4.

- [ ] **Step 5: Commit**

```bash
git add includes/evades_catalogo.php includes/incidencias_catalogo.php tests/evades_catalogo_test.php
git commit -m "feat(evades): define fuentes y cruces de evidencia"
```

### Task 2: Normalize operational evidence sources

**Files:**
- Create: `includes/evades_evidence.php`
- Create: `tests/evades_evidence_db_test.php`

**Interfaces:**
- Produces:
  - `evades_evidencia_incidencias(mysqli $conn, int $colaboradorId, string $competenciaKey, array $rango): array`
  - `evades_evidencia_reconocimientos(...): array`
  - `evades_evidencia_ept(...): array`
  - `evades_evidencia_asistencia(...): array`
  - `evades_evidencia_propuestas(...): array`
- Returns normalized rows with `tipo`, `fuente`, `id`, `fecha`, `competencia_origen`, `competencia_destino`, `es_cruce`, `valor`, `impacto`, and `descripcion`.

- [ ] **Step 1: Write transaction-backed failing tests**

Create fixtures inside a transaction for one collaborator and `2097-T3`:

```php
$incidencias = evades_evidencia_incidencias($conn, $colaboradorId, 'eficiencia', $rango);
eq(count($incidencias), 1, 'error de pedeteo cruza hacia Eficiencia');
eq($incidencias[0]['es_cruce'], true, 'marca la relación cruzada');

$ept = evades_evidencia_ept($conn, $colaboradorId, 'seguridad_trabajo', $rango);
eq($ept['n'], 3, 'cuenta únicamente EPT del trimestre');
eq($ept['promedio'], 4.5, 'promedia solo los tres ítems de seguridad');

$propuestas = evades_evidencia_propuestas($conn, $colaboradorId, $rango);
eq($propuestas['nivel'], 4, 'propuesta revisada de impacto alto aporta +4');
```

Include an incidence whose redundant `competencia` is intentionally wrong but
whose `punto_mejorar` is canonical.

- [ ] **Step 2: Run the focused test and confirm RED**

Run: `php tests/evades_evidence_db_test.php`

Expected: FAIL because `evades_evidence.php` is absent.

- [ ] **Step 3: Implement source readers**

Use prepared statements and the period range for every query. Decode
`evaluacion_desempeno.criterios`, normalize criterion labels to stable semantic
keys, skip invalid JSON, and never use `puntaje_total` as a substitute for a
mapped criterion average.

Recognition queries must require `estado='aprobado'`. Proposal queries must
require `colaborador_id`, `canal='sugerencia'`, and `puntaje_at IS NOT NULL`.
Attendance queries must use participant state and the event date.

- [ ] **Step 4: Run the evidence test**

Run: `php tests/evades_evidence_db_test.php`

Expected: PASS with transaction rollback leaving no fixture rows.

- [ ] **Step 5: Commit**

```bash
git add includes/evades_evidence.php tests/evades_evidence_db_test.php
git commit -m "feat(evades): normaliza fuentes operativas de evidencia"
```

### Task 3: Structured coordinator appreciations

**Files:**
- Create: `sql/033_evades_evidencia.sql`
- Create: `includes/evades_apreciaciones.php`
- Create: `api/save_evades_apreciacion.php`
- Create: `tests/evades_apreciaciones_db_test.php`

**Interfaces:**
- Produces: `evades_guardar_apreciacion(mysqli $conn, array $payload, int $actorId): array` and `evades_listar_apreciaciones(...): array`.
- Consumes: block ownership/editability helpers from `includes/evades_bloques.php` and competency keys from the catalog.

- [ ] **Step 1: Write failing schema and domain tests**

Assert the table columns and these behaviors:

```php
$saved = evades_guardar_apreciacion($conn, [
    'evaluacion_id' => $evaluacionId,
    'competencia_key' => 'productividad',
    'direccion' => 'positiva',
    'nivel' => 2,
    'descripcion' => 'Superó el promedio documentado del turno.',
], $actorId);
eq($saved['nivel'], 2, 'guarda apreciación positiva estructurada');
```

Reject an empty description, invalid +6, foreign block, and closed block.

- [ ] **Step 2: Run the test and confirm RED**

Run: `php tests/evades_apreciaciones_db_test.php`

Expected: FAIL because the migration/domain do not exist.

- [ ] **Step 3: Add the additive migration and domain**

Create `evades_apreciaciones` with evaluation/block/colaborador, competency,
direction, positive level or negative impact, description, actor, active flag,
timestamps, and indexes. Apply the migration locally through the project DB.

- [ ] **Step 4: Add the authenticated API and run tests**

The API accepts JSON, derives actor from session, and returns stable validation
errors. Run:

```bash
php tests/evades_apreciaciones_db_test.php
php tests/evades_bloques_db_test.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add sql/033_evades_evidencia.sql includes/evades_apreciaciones.php api/save_evades_apreciacion.php tests/evades_apreciaciones_db_test.php
git commit -m "feat(evades): agrega apreciaciones estructuradas"
```

### Task 4: Replace the partial scoring engine

**Files:**
- Modify: `includes/evades_engine.php`
- Modify: `includes/evades_bloques.php`
- Modify: `api/save_evades.php`
- Modify: `tests/evades_evidence_db_test.php`
- Modify: `tests/evades_bloques_db_test.php`

**Interfaces:**
- `evades_calcular_sugerencias()` retains its existing signature.
- Each competency adds `cobertura`, `regla`, `resumen_calculo`, and normalized `evidencia` while preserving the existing scoring fields.

- [ ] **Step 1: Add failing engine assertions**

Test that:

```php
eq($porKey['dominio_solido']['auto_descuento'], 4, 'F2 moderado descuenta 4');
eq($porKey['eficiencia']['auto_descuento'], 4, 'el cruce recibe el mismo cálculo explicable');
eq($porKey['seguridad_trabajo']['auto_incremento'], 4, 'tres EPT con promedio 4.5 aportan +4');
eq($porKey['autonomia']['auto_incremento'], 4, 'múltiples fuentes positivas toman máximo, no suman +6');
eq($porKey['productividad']['cobertura'], 'sin_fuente', 'no usa KPI de team');
```

- [ ] **Step 2: Run tests and confirm RED**

Run:

```bash
php tests/evades_evidence_db_test.php
php tests/evades_bloques_db_test.php
```

- [ ] **Step 3: Implement orchestration and snapshot persistence**

For each competency, collect positive candidates, choose `max(0, 2, 4)`,
collect negative events, calculate FI from count and worst impact, and build the
human-readable summary. Include active appreciations. Store the full normalized
evidence snapshot during generation/save.

Update all validation to `[0,2,4]`; closed blocks continue returning their
stored snapshot without recalculation.

- [ ] **Step 4: Run engine, block, and legacy tests**

Run:

```bash
php tests/evades_catalogo_test.php
php tests/evades_evidence_db_test.php
php tests/evades_bloques_db_test.php
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/evades_engine.php includes/evades_bloques.php api/save_evades.php tests/evades_evidence_db_test.php tests/evades_bloques_db_test.php
git commit -m "feat(evades): calcula puntajes desde evidencia completa"
```

### Task 5: Evidence coverage preview for block generation

**Files:**
- Modify: `api/preview_evades_bloque.php`
- Modify: `includes/evades_bloques.php`
- Modify: `tests/evades_bloques_db_test.php`

**Interfaces:**
- Preview response adds aggregate `cobertura` and per-person `fuentes`, `estado_cobertura`, and `motivo_revision` without removing existing keys.

- [ ] **Step 1: Add a failing preview-domain test**

Assert a fixture roster returns counts for incidents, EPT, approved
recognitions, attendance, and reviewed proposals, plus `lista`/`revisar`.

- [ ] **Step 2: Run and confirm RED**

Run: `php tests/evades_bloques_db_test.php`

- [ ] **Step 3: Implement one batched coverage query per source**

Avoid N+1 queries: aggregate each source by `colaborador_id`, merge results into
the frozen roster, and mark `revisar` when no automatic competency has
sufficient evidence.

- [ ] **Step 4: Run the DB suite**

Run: `php tests/evades_bloques_db_test.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/preview_evades_bloque.php includes/evades_bloques.php tests/evades_bloques_db_test.php
git commit -m "feat(evades): muestra cobertura antes de generar bloques"
```

### Task 6: Hybrid modal model and interaction tests

**Files:**
- Create: `js/evades_workspace.js`
- Modify: `tests/evades_bloques_model.test.js`

**Interfaces:**
- Produces pure functions `buildCoverageModel`, `buildCompetencyView`, `buildRosterModel`, and `isReadOnlyBlock` exported for Node and attached to `window.EvadesWorkspace` in browser.

- [ ] **Step 1: Write failing UI-model tests**

Cover:

```js
assert.equal(buildCompetencyView(row).formula, '6 + 0 - 4 = 2');
assert.equal(buildCompetencyView(row).crossLabel, 'Cruce desde Dominio sólido');
assert.equal(buildCoverageModel(person).status, 'revisar');
assert.equal(isReadOnlyBlock({ estado: 'cerrado' }), true);
```

- [ ] **Step 2: Run and confirm RED**

Run: `node tests/evades_bloques_model.test.js`

- [ ] **Step 3: Implement the smallest pure model**

No DOM access inside the four exported functions. Normalize null suggestions to
zero for arithmetic while preserving `cobertura` for copy.

- [ ] **Step 4: Run the Node test**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add js/evades_workspace.js tests/evades_bloques_model.test.js
git commit -m "feat(evades): agrega modelo visual del workspace híbrido"
```

### Task 7: Generation and evaluation modal redesign

**Files:**
- Modify: `pages/evades.php`
- Modify: `api/get_evades.php`
- Create: `tests/evades_modal_ui_test.php`

**Interfaces:**
- Consumes preview coverage and normalized competency evidence.
- Keeps existing element IDs needed by current API actions or updates all references atomically.

- [ ] **Step 1: Add failing structural UI tests**

The PHP text test must assert presence of:

```php
ok(strpos($html, 'ev-coverage-grid') !== false, 'generación muestra cobertura');
ok(strpos($html, 'ev-workspace-rail') !== false, 'workspace tiene nómina lateral');
ok(strpos($html, 'ev-competency-why') !== false, 'competencia explica el cálculo');
ok(strpos($html, 'ev-cross-source') !== false, 'cruces son visibles');
ok(strpos($html, 'ev-readonly-report') !== false, 'consulta tiene composición propia');
```

- [ ] **Step 2: Run UI tests and confirm RED**

Run: `php tests/evades_modal_ui_test.php`

- [ ] **Step 3: Implement the generation modal**

Build the approved context panel, aggregate coverage metrics, per-person source
row, period warning, disabled/duplicate states, and exact generate count.

- [ ] **Step 4: Implement the hybrid evaluation workspace**

Add searchable roster, sticky profile/score, Conductuales/Operativas/Feedback
tabs, compact competency cards, base/+/-/final formula, evidence chips, expanded
reason, and structured appreciation control. Preserve dirty-change warnings,
previous/next, save, close, and PDF actions.

- [ ] **Step 5: Implement responsive/accessibility behavior**

At <= 760px, use horizontal roster/select and stack score arithmetic. Add
visible focus styles, labels, `aria-expanded` on evidence details, and text for
every semantic color.

- [ ] **Step 6: Run structural and model tests**

Run:

```bash
php tests/evades_modal_ui_test.php
node tests/evades_bloques_model.test.js
```

Expected: PASS.

- [ ] **Step 7: Commit only EVADES UI files**

Because `pages/evades.php` and `api/get_evades.php` contain pre-existing local
changes, inspect their diff carefully and stage only after confirming no
unrelated hunks were introduced.

```bash
git add pages/evades.php api/get_evades.php tests/evades_modal_ui_test.php
git commit -m "feat(evades): rediseña modales con experiencia híbrida"
```

### Task 8: Read-only report, PDF, and end-to-end verification

**Files:**
- Modify: `pages/evades.php`
- Modify: `tests/evades_modal_ui_test.php`

**Interfaces:**
- The read-only modal and PDF consume the stored evidence snapshot, not live recalculation for closed blocks.

- [ ] **Step 1: Add failing read-only/PDF assertions**

Assert the view includes section subtotals, total, classification, competency
cause, evidence, state history, modifications, feedback, and no enabled edit
controls when closed.

- [ ] **Step 2: Run and confirm RED**

Run: `php tests/evades_modal_ui_test.php`

- [ ] **Step 3: Implement the report composition and PDF evidence summary**

Render the ten competency summaries, timeline, feedback, and plan. PDF rows must
include base, increment, discount, final, and one concise calculation reason.

- [ ] **Step 4: Run all automated verification**

```bash
php -l includes/evades_catalogo.php
php -l includes/evades_evidence.php
php -l includes/evades_engine.php
php -l includes/evades_bloques.php
php -l pages/evades.php
php tests/evades_catalogo_test.php
php tests/evades_evidence_db_test.php
php tests/evades_apreciaciones_db_test.php
php tests/evades_bloques_db_test.php
php tests/evades_modal_ui_test.php
node tests/evades_bloques_model.test.js
```

Expected: no syntax errors and every assertion PASS.

- [ ] **Step 5: Verify real T3 calculations without mutating closed data**

Run a read-only diagnostic for collaborators with July/August incidents and
confirm at least Domain/Efficiency vary from base when their T3 block is
calculated. Confirm existing T2 evaluations remain unchanged because those
incidents are outside T2.

- [ ] **Step 6: Browser QA**

Open local EVADES, inspect generation/evaluation/view modals at desktop and
mobile widths, test keyboard focus and evidence expansion, and capture no
console/runtime errors.

- [ ] **Step 7: Final commit**

```bash
git add pages/evades.php tests/evades_modal_ui_test.php
git commit -m "feat(evades): completa reporte trazable y verificación"
```
