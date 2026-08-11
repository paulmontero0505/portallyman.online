# Task Modals Visual Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the create/edit and assigned-task detail modals as a coherent, accessible operational interface while preserving every current workflow and API payload.

**Architecture:** Keep implementation local to `pages/tareas.php`, extending its existing scoped CSS and dynamic HTML without dependencies or broad refactoring. Add small presentation helpers for priority, validation and the detail summary; current APIs, permissions and domain values remain authoritative.

**Tech Stack:** PHP 8, HTML5, scoped CSS, vanilla JavaScript, DM Sans and existing task APIs.

## Global Constraints

- Preserve institutional greens `#00875A` and `#005C3D` as orientation and action colors.
- Do not change endpoints, payload fields, task states, permissions, lot behavior, delivery rules or review rules.
- Keep `DM Sans` and existing semantic success, warning and error colors.
- Support desktop, 720 px, 520 px and 375 px widths.
- Dialogs require accessible names, visible focus, 44 px close targets and reduced-motion support.
- Preserve unrelated local modifications in `pages/tareas.php` and the repository.

---

### Task 1: Add structural regression coverage

**Files:**
- Create: `tests/tareas_modal_ui_test.php`
- Test: `tests/tareas_modal_ui_test.php`

**Interfaces:**
- Consumes: static source from `pages/tareas.php`.
- Produces: a CLI regression test for modal structure, accessibility and responsive CSS.

- [ ] **Step 1: Write the failing test**

Load `pages/tareas.php` as text and require these fragments:

```php
$required = [
    'class="tk-modal tk-compose"',
    'class="tk-modal wide tk-detail"',
    'role="dialog"',
    'aria-modal="true"',
    'class="tk-modal-kicker"',
    'class="tk-form-section"',
    'id="tm-priority-options"',
    'class="tk-detail-summary"',
    '@media (max-width:720px)',
    '@media (prefers-reduced-motion:reduce)',
];
```

Print the missing fragment to `STDERR` and exit `1`; otherwise print `OK tareas_modal_ui_test` and exit `0`.

- [ ] **Step 2: Verify the test fails**

Run: `php tests/tareas_modal_ui_test.php`

Expected: FAIL naming the first absent redesigned fragment.

---

### Task 2: Build the shared shell and create/edit experience

**Files:**
- Modify: `pages/tareas.php:140-221`
- Modify: `pages/tareas.php:361-419`
- Modify: `pages/tareas.php:723-835`
- Test: `tests/tareas_modal_ui_test.php`

**Interfaces:**
- Consumes: current IDs `tm-id`, `tm-titulo`, `tm-desc`, `tm-prioridad`, `tm-fecha`, `tm-dest`, `tm-lote`, `tkModalSave` and `guardar()` payload.
- Produces: `setPriority(value)`, `clearFieldErrors()`, `markFieldError(id)` and visual controls under `#tm-priority-options` synchronized to `#tm-prioridad`.

- [ ] **Step 1: Implement the shared shell CSS**

Upgrade the scrim, modal border, safe viewport height, fixed header/footer, scrollable body and entrance motion. Add reusable heading, form-section, priority and invalid-state classes:

```css
.tk-modal-headmark { width:44px; height:44px; display:grid; place-items:center; }
.tk-modal-kicker { font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
.tk-modal-heading { display:flex; align-items:center; gap:14px; min-width:0; }
.tk-form-section { border:1px solid var(--co-line); border-radius:16px; padding:16px; }
.tk-priority-options { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.tk-priority-option[aria-pressed="true"] { border-color:var(--priority-color); }
.tk-field.is-invalid input,
.tk-field.is-invalid textarea,
.tk-field.is-invalid .tk-dest { border-color:var(--er); }
```

Add `:focus-visible`, disabled states, subtle scrollbars, a 720 px single-column breakpoint, a 520 px near-fullscreen treatment and reduced-motion overrides.

- [ ] **Step 2: Reorganize create/edit markup**

Add `tk-compose`, `role="dialog"`, `aria-modal="true"` and `aria-labelledby="tkModalTitle"`. Use the institutional headmark, kicker and labeled close control. Group the same fields into information, planning and assignment sections; retain every existing field ID.

Retain the native priority select as the data source and add three synchronized buttons under `#tm-priority-options` with `data-priority="baja|media|alta"` and `aria-pressed`.

- [ ] **Step 3: Synchronize priority and validation**

Implement:

```js
function setPriority(value) {
  $('tm-prioridad').value = value;
  $('tm-priority-options').querySelectorAll('[data-priority]').forEach(button => {
    button.setAttribute('aria-pressed', String(button.dataset.priority === value));
  });
}
function clearFieldErrors() {
  $('tkModalBack').querySelectorAll('.tk-field.is-invalid').forEach(field => field.classList.remove('is-invalid'));
}
function markFieldError(id) {
  $(id).closest('.tk-field')?.classList.add('is-invalid');
}
```

Call `setPriority()` and clear errors on open. Mark title, date or recipients before the current toast/focus validation. Change primary copy to `Crear tarea` or `Guardar cambios` while preserving the payload.

- [ ] **Step 4: Run interim checks**

Run: `php tests/tareas_modal_ui_test.php`

Expected: only detail-specific assertions remain failing.

Run: `php -l pages/tareas.php`

Expected: `No syntax errors detected in pages/tareas.php`.

---

### Task 3: Redesign detail and assigned-task delivery

**Files:**
- Modify: `pages/tareas.php:176-288`
- Modify: `pages/tareas.php:421-435`
- Modify: `pages/tareas.php:840-1190`
- Test: `tests/tareas_modal_ui_test.php`

**Interfaces:**
- Consumes: `T`, `window.tkBadgeEstado(T)`, `window.tkFmt()`, `T.permisos` and current detail IDs.
- Produces: `detailSummaryHtml(t)` plus `.tk-detail-summary`, improved file/drop-zone presentation and grouped footer actions.

- [ ] **Step 1: Upgrade the detail shell**

Add `tk-detail`, `role="dialog"`, `aria-modal="true"` and `aria-labelledby="tkDetTitle"`. Use the shared headmark with kicker `Detalle de tarea` and label the close button.

- [ ] **Step 2: Add the operational summary**

Add `detailSummaryHtml(t)` before `pintar()`:

```js
function detailSummaryHtml(t) {
  const deadlineClass = t.atrasada ? 'is-danger' : '';
  return `<div class="tk-detail-summary">
    <div class="tk-summary-status"><span class="tk-summary-label">Estado</span>${window.tkBadgeEstado(t)}</div>
    <div><span class="tk-summary-label">Prioridad</span><strong>${esc(t.prioridad)}</strong></div>
    <div class="${deadlineClass}"><span class="tk-summary-label">Plazo vigente</span><strong>${fmt(t.plazo_vigente)}</strong></div>
    <div><span class="tk-summary-label">Envíos</span><strong>${t.entregas_count}</strong></div>
  </div>`;
}
```

Insert it before observations and assignment content. Present four columns on desktop and two columns on small screens.

- [ ] **Step 3: Improve section, file and delivery hierarchy**

Use the existing outline SVG style for section and upload icons. Turn `#tkDrop` into a clear upload action with action phrase and limit text. Style `.tk-file` with an icon tile, filename, metadata and a 44 px delete target without changing URLs or deletion behavior.

Wrap the editable upload and `#tkComent` areas in a `.tk-delivery-panel` so they read as the primary assigned-user action.

- [ ] **Step 4: Group contextual footer actions**

Keep every current button ID/listener. Generate `.tk-footer-secondary` for edit/delete/close and `.tk-footer-primary` for submit. Stack safely at 520 px.

- [ ] **Step 5: Run complete acceptance checks**

Run: `php tests/tareas_modal_ui_test.php`

Expected: `OK tareas_modal_ui_test`.

Run: `php -l pages/tareas.php`

Expected: `No syntax errors detected in pages/tareas.php`.

---

### Task 4: Regression and visual verification

**Files:**
- Verify: `pages/tareas.php`
- Verify: `tests/tareas_modal_ui_test.php`
- Verify: `tests/tareas_catalogo_test.php`

**Interfaces:**
- Consumes: completed modal implementation.
- Produces: syntax, regression and visual evidence.

- [ ] **Step 1: Run focused automated checks**

Run `php -l pages/tareas.php`, `php tests/tareas_modal_ui_test.php` and `php tests/tareas_catalogo_test.php`.

Expected: syntax passes and both scripts exit `0`.

- [ ] **Step 2: Review exact diff**

Run `git diff --check -- pages/tareas.php tests/tareas_modal_ui_test.php` and then the scoped diff.

Expected: no whitespace errors and only modal UI, accessibility and test changes.

- [ ] **Step 3: Perform browser QA when an authenticated session is available**

Verify new/edit/detail at desktop, 720 px, 520 px and 375 px. Check focus, scrolling, fixed actions, priority synchronization, recipient counter, drag state, delivery actions and reduced motion. If authentication blocks browser QA, report it explicitly.

- [ ] **Step 4: Commit only implementation files**

Stage `pages/tareas.php` and `tests/tareas_modal_ui_test.php`, then commit with `feat: refresh task modal experience`.
