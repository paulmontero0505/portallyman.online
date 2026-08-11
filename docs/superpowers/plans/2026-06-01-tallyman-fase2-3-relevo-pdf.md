# Registro Tallyman — Fases 2 y 3 (Relevo + PDF ejecutivo) · Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Una vista de "Relevo de turno" (`pages/tallyman_relevo.php`) que muestra los registros del turno con KPIs y dos gráficos (barras apiladas + dona), y un botón "Exportar PDF" que genera un reporte ejecutivo estilo HANDOVER que auto-oculta lo vacío.

**Architecture:** Endpoint nuevo Node `GET /api/operaciones/tallyman/relevo?fecha=&turno=` que devuelve registros (con accumulated/pending/%), incidencia y `totales` en una llamada. Página PHP que consume ese payload vía el proxy, renderiza KPIs + tabla + gráficos con **Chart.js** (CDN cdnjs), y exporta un PDF **nativo** con **jsPDF + jspdf-autotable** (CDN cdnjs — ya usados en `index.php`), insertando los canvases de los gráficos como imágenes. La omisión de vacíos ocurre al construir el payload/DOM: berths/yards sin actividad no vienen; columnas vacías en todo el turno no se pintan; incidencias solo si `hubo`.

**Tech Stack:** Node/Express (módulo `tallyman` existente), MySQL, PHP 8.2, JS vanilla (patrón `window.OP`), Chart.js 4.x (CDN), jsPDF 2.5.1 + jspdf-autotable 3.8.0 (CDN, mismas versiones que `index.php`). `node --test` para el backend.

**Spec:** `docs/superpowers/specs/2026-06-01-registro-actividad-tallyman-design.md` (§5, §6.2, §6.3)

**Precedentes del código a imitar:**
- `index.php:585-586` carga jsPDF UMD + autotable por CDN; `js/estiba.js:1031-1036` arma un PDF con `const { jsPDF } = window.jspdf; new jsPDF({unit:'pt',format:'a4',...})`. Reusar ese patrón exacto.
- `operaciones-api/src/modules/tallyman/tallyman.controller.js` ya tiene `conResumen(reg, prev)` y `listarRegistros`; el endpoint relevo reutiliza la misma lógica de acumulado.
- `js/operaciones.js` expone `OP.opApi/esc/toast/$`.

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `operaciones-api/src/modules/tallyman/tallyman.controller.js` (modificar) | Añadir `relevoTurno` (arma registros+incidencia+totales). Extraer un helper `resumenLista(regs)` reutilizado por `listarRegistros` y `relevoTurno` (DRY). |
| `operaciones-api/src/modules/tallyman/tallyman.routes.js` (modificar) | Añadir `GET /tallyman/relevo`. |
| `operaciones-api/test/tallyman.relevo.test.js` (crear) | Test unitario de la función pura de totales (`calcularTotales`). |
| `operaciones-api/src/modules/tallyman/tallyman.totales.js` (crear) | Función pura `calcularTotales(registrosConResumen)` → {planned, executed, pending, porcentaje, n_actividades}. Aislada para testear sin BD. |
| `pages/tallyman_relevo.php` (crear) | Vista de relevo: cabecera ejecutiva, KPIs, tabla, 2 gráficos Chart.js, incidencias, botón Exportar PDF. |
| `js/tallyman_relevo.js` (crear) | Lógica de la vista: carga el relevo, construye tabla/KPIs ocultando vacíos, dibuja los gráficos, y genera el PDF ejecutivo. (Externo, no inline, por su tamaño y por el precedente `js/operaciones.js`.) |
| `includes/sidebar.php` (modificar) | Enlace "Relevo de turno". |

---

## Task 1: Función pura de totales (TDD)

**Files:**
- Create: `operaciones-api/src/modules/tallyman/tallyman.totales.js`
- Test: `operaciones-api/test/tallyman.relevo.test.js`

- [ ] **Step 1: Escribir el test que falla**

Crear `operaciones-api/test/tallyman.relevo.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calcularTotales } from '../src/modules/tallyman/tallyman.totales.js';

test('suma planned/executed/pending y % global', () => {
  const regs = [
    { planned: '1954.00', executed: '831.00', accumulated: 831, pending: 1123 },
    { planned: '500.00', executed: '200.00', accumulated: 200, pending: 300 },
  ];
  const t = calcularTotales(regs);
  assert.equal(t.planned, 2454);
  assert.equal(t.executed, 1031);
  assert.equal(t.pending, 1423);
  assert.equal(t.n_actividades, 2);
  // % global = executed acumulado / planned total → 1031/2454*100 = 42.0
  assert.equal(t.porcentaje, 42);
});

test('ignora planned nulo en el % pero cuenta la actividad', () => {
  const regs = [
    { planned: null, executed: '50.00', accumulated: 50, pending: null },
    { planned: '100.00', executed: '40.00', accumulated: 40, pending: 60 },
  ];
  const t = calcularTotales(regs);
  assert.equal(t.planned, 100);     // solo el que tiene planned
  assert.equal(t.executed, 90);     // ambos executed
  assert.equal(t.pending, 60);      // solo el que tiene pending
  assert.equal(t.n_actividades, 2);
  assert.equal(t.porcentaje, 40);   // 40/100 (el executed del que tiene planned)
});

test('lista vacía → ceros y porcentaje null', () => {
  const t = calcularTotales([]);
  assert.equal(t.planned, 0);
  assert.equal(t.executed, 0);
  assert.equal(t.pending, 0);
  assert.equal(t.n_actividades, 0);
  assert.equal(t.porcentaje, null);
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

Run (desde `operaciones-api/`): `npm test`
Expected: FAIL — `Cannot find module '.../tallyman.totales.js'`.

- [ ] **Step 3: Implementar la función**

Crear `operaciones-api/src/modules/tallyman/tallyman.totales.js`:

```js
// Totales del turno para los KPIs del relevo. Función pura (sin BD).
// El % global se calcula solo sobre las filas que tienen planned > 0:
// executed_de_esas_filas / planned_total * 100. Las filas sin planned
// cuentan para executed y n_actividades pero no distorsionan el %.
export function calcularTotales(regs) {
  let planned = 0, executed = 0, pending = 0, executedConPlan = 0;
  for (const r of regs) {
    const p = r.planned != null ? Number(r.planned) : null;
    const e = Number(r.executed) || 0;
    executed += e;
    if (p != null && p > 0) {
      planned += p;
      executedConPlan += Number(r.accumulated != null ? r.accumulated : e);
      if (r.pending != null) pending += Number(r.pending);
    }
  }
  const porcentaje = planned > 0
    ? Math.min(Math.round((executedConPlan / planned) * 1000) / 10, 100)
    : null;
  return {
    planned: Math.round(planned * 100) / 100,
    executed: Math.round(executed * 100) / 100,
    pending: Math.round(pending * 100) / 100,
    porcentaje,
    n_actividades: regs.length,
  };
}
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

Run: `npm test`
Expected: PASS — los 3 tests nuevos + los 24 existentes (27 total).

- [ ] **Step 5: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add operaciones-api/src/modules/tallyman/tallyman.totales.js operaciones-api/test/tallyman.relevo.test.js && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): función pura calcularTotales para KPIs del relevo (TDD)"
```

---

## Task 2: Endpoint relevo en el controlador + ruta

**Files:**
- Modify: `operaciones-api/src/modules/tallyman/tallyman.controller.js`
- Modify: `operaciones-api/src/modules/tallyman/tallyman.routes.js`

- [ ] **Step 1: Refactor DRY + nuevo handler en el controlador**

En `operaciones-api/src/modules/tallyman/tallyman.controller.js`:

(a) Añadir el import al inicio (junto a los otros imports):
```js
import { calcularTotales } from './tallyman.totales.js';
```

(b) Extraer la lógica de "lista con resumen" en un helper reutilizable. Añadir esta función (después de `conResumen`):
```js
// Convierte una lista de registros crudos en registros con accumulated/pending/%.
async function resumenLista(regs) {
  const out = [];
  for (const r of regs) {
    const prev = await TallymanModel.executedPrevio({
      nave_id: r.nave_id, actividad_id: r.actividad_id, ubicacion: r.ubicacion,
      fecha_turno: r.fecha_turno, turno: r.turno,
    });
    out.push(conResumen(r, prev));
  }
  return out;
}
```

(c) Reemplazar el cuerpo de `listarRegistros` para que use el helper (DRY). Buscar el bloque actual:
```js
  const regs = await TallymanModel.listarPorTurno(fecha, turno);
  const out = [];
  for (const r of regs) {
    const prev = await TallymanModel.executedPrevio({
      nave_id: r.nave_id, actividad_id: r.actividad_id, ubicacion: r.ubicacion,
      fecha_turno: r.fecha_turno, turno: r.turno,
    });
    out.push(conResumen(r, prev));
  }
  res.json({ success: true, count: out.length, data: out });
```
y reemplazarlo por:
```js
  const regs = await TallymanModel.listarPorTurno(fecha, turno);
  const out = await resumenLista(regs);
  res.json({ success: true, count: out.length, data: out });
```

(d) Añadir el nuevo handler `relevoTurno` al final del archivo (antes de cualquier cosa, como los demás exports):
```js
// GET /tallyman/relevo?fecha=&turno=  → payload completo para la vista de relevo
export const relevoTurno = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!esFechaISO(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
  const registros = await resumenLista(await TallymanModel.listarPorTurno(fecha, turno));
  const incidencia = await TallymanModel.obtenerIncidencia(fecha, turno);
  const totales = calcularTotales(registros);
  // coordinadores: tomados del primer registro que los tenga (se guardan por registro)
  const coord = registros.find((r) => r.coord_entrante || r.coord_saliente) || {};
  res.json({
    success: true,
    data: {
      fecha, turno,
      coord_entrante: coord.coord_entrante || null,
      coord_saliente: coord.coord_saliente || null,
      registros, incidencia, totales,
    },
  });
});
```

- [ ] **Step 2: Verificar sintaxis del controlador**

Run (desde `operaciones-api/`): `node --check src/modules/tallyman/tallyman.controller.js`
Expected: exit 0, sin salida.

- [ ] **Step 3: Añadir la ruta**

En `operaciones-api/src/modules/tallyman/tallyman.routes.js`:

(a) Añadir `relevoTurno` a la lista de imports del controlador (en el bloque `import { ... } from './tallyman.controller.js';`).

(b) Añadir la ruta junto a las otras GET (después de la línea de `/tallyman/registros` GET):
```js
// Relevo del turno (payload completo para la vista): roles operativos
router.get('/tallyman/relevo', requireRole(...OPERATIVOS), relevoTurno);
```

- [ ] **Step 4: Verificar que la app carga**

Run (desde `operaciones-api/`):
```
node --check src/modules/tallyman/tallyman.routes.js
node -e "import('./src/app.js').then(()=>console.log('APP_OK')).catch(e=>{console.error(e.message);process.exit(1)})"
```
Expected: `APP_OK`.

- [ ] **Step 5: Prueba de humo del endpoint (API arriba)**

Con la API corriendo (`npm start` en otra terminal), crear un registro de prueba y pedir el relevo:
```
curl -s -X POST -H "x-user-role: Coordinador" -H "x-user-name: JP" -H "Content-Type: application/json" -d "{\"fecha_turno\":\"2026-06-09\",\"turno\":\"U\",\"ubicacion_tipo\":\"BERTH\",\"ubicacion\":\"Berth 04\",\"actividad_id\":1,\"planned\":1000,\"executed\":400,\"status_act\":\"Inicio\"}" http://127.0.0.1:4000/api/operaciones/tallyman/registros
curl -s -H "x-user-role: Coordinador" -H "x-user-name: JP" "http://127.0.0.1:4000/api/operaciones/tallyman/relevo?fecha=2026-06-09&turno=U"
```
Expected: el relevo trae `registros` (1), `totales` con planned 1000 / executed 400 / pending 600 / porcentaje 40 / n_actividades 1, e `incidencia` null.
Luego limpiar: `"/c/xampp2026/mysql/bin/mysql.exe" -u root operaciones -e "DELETE FROM tallyman_registros WHERE fecha_turno='2026-06-09';"`

- [ ] **Step 6: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add operaciones-api/src/modules/tallyman/tallyman.controller.js operaciones-api/src/modules/tallyman/tallyman.routes.js && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): endpoint GET /tallyman/relevo con totales (DRY resumenLista)"
```

---

## Task 3: Ampliar allow-list del proxy para `tallyman/relevo`

**Files:**
- Verify only: `api/operaciones_proxy.php`

El allow-list actual es `^(naves|tipos-nave|tallyman)(/[A-Za-z0-9_\-]+)*$`. El segmento `relevo` solo tiene letras → ya pasa. **No requiere cambios.** Esta tarea es una verificación explícita para no asumir.

- [ ] **Step 1: Verificar que la ruta pasa el regex**

Run:
```
node -e "console.log(/^(naves|tipos-nave|tallyman)(\/[A-Za-z0-9_\-]+)*$/.test('tallyman/relevo'))"
```
Expected: `true`. (Si fuera `false`, añadir el segmento; pero será `true`.)

- [ ] **Step 2: Sin commit** (no hubo cambios). Continuar.

---

## Task 4: Vendor de librerías + vista de relevo (HTML/CSS) sin datos

**Files:**
- Create: `pages/tallyman_relevo.php`

Esta tarea crea el esqueleto de la página (shell + cabecera + contenedores de KPIs/tabla/gráficos/incidencias + botón Exportar) y carga las librerías por CDN (mismas versiones que `index.php`). La lógica JS va en Task 5.

- [ ] **Step 1: Crear la página**

Crear `pages/tallyman_relevo.php`:

```php
<?php
require_once('../includes/auth.php');
require_operaciones();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Relevo de turno · Tallyman</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <style>
    .rv-wrap { --navy:#002b5c; --navy7:#013a78; --deck:#f1f5f9; --line:#e2e8f0; --lineb:#cbd5e1;
      --ink:#0b1f3a; --mute:#64748b; --faint:#94a3b8; --mono:ui-monospace,Consolas,monospace;
      display:flex; flex-direction:column; gap:18px; font-family:'DM Sans',system-ui,sans-serif; color:var(--ink); }
    .rv-wrap *,.rv-wrap *::before,.rv-wrap *::after{box-sizing:border-box;}
    .rv-hero{background:linear-gradient(155deg,#001b3a,#002b5c 45%,#013a78);color:#fff;border-radius:20px;padding:22px 28px;display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;}
    .rv-hero h1{margin:6px 0 4px;font-size:22px;font-weight:700;}
    .rv-hero p{margin:0;color:rgba(255,255,255,.72);font-size:13px;}
    .rv-meta{margin-top:10px;display:flex;gap:18px;flex-wrap:wrap;font-size:12.5px;color:rgba(255,255,255,.85);}
    .rv-meta b{color:#fff;}
    .rv-btn{display:inline-flex;align-items:center;gap:7px;cursor:pointer;padding:10px 16px;border-radius:10px;border:1px solid var(--lineb);background:#fff;color:var(--ink);font:inherit;font-size:13px;font-weight:600;}
    .rv-btn:hover{background:var(--deck);}
    .rv-btn.primary{background:#fff;color:var(--navy);border-color:#fff;}
    .rv-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;}
    .rv-kpi{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px 18px;}
    .rv-kpi .lbl{font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--mute);}
    .rv-kpi .val{font-size:24px;font-weight:800;font-family:var(--mono);color:var(--navy);margin-top:4px;}
    .rv-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 20px;}
    .rv-card h2{margin:0 0 14px;font-size:15px;font-weight:700;color:var(--navy);}
    .rv-charts{display:grid;grid-template-columns:2fr 1fr;gap:16px;}
    @media(max-width:880px){.rv-charts{grid-template-columns:1fr;}}
    .rv-table{width:100%;border-collapse:collapse;font-size:13px;}
    .rv-table th{padding:9px 11px;text-align:left;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--mute);font-weight:700;border-bottom:1px solid var(--lineb);}
    .rv-table td{padding:9px 11px;border-bottom:1px solid var(--line);}
    .rv-table .num{font-family:var(--mono);text-align:right;}
    .rv-empty{padding:30px;text-align:center;color:var(--faint);}
    .content{padding:24px 28px 60px;overflow-y:auto;}
  </style>
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="shell">
  <?php $sb_base='..'; include('../includes/sidebar.php'); ?>
  <div class="main-area">
    <?php include('../includes/header.php'); ?>
    <main class="content">
      <div class="rv-wrap" id="rvWrap">
        <section class="rv-hero">
          <div>
            <h1>Relevo de turno</h1>
            <p>Resumen ejecutivo de la actividad registrada en el turno.</p>
            <div class="rv-meta" id="rvMeta">Cargando…</div>
          </div>
          <button class="rv-btn primary" id="rvExport" disabled>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exportar PDF
          </button>
        </section>

        <section class="rv-kpis" id="rvKpis"></section>

        <section class="rv-card">
          <h2>Avance por actividad</h2>
          <div class="rv-charts">
            <div><canvas id="rvBar" height="220"></canvas></div>
            <div><canvas id="rvDona" height="220"></canvas></div>
          </div>
        </section>

        <section class="rv-card">
          <h2>Detalle de actividades</h2>
          <div id="rvTablaWrap"><div class="rv-empty">Cargando…</div></div>
        </section>

        <section class="rv-card" id="rvIncCard" style="display:none;">
          <h2>Incidencias del turno</h2>
          <div id="rvInc"></div>
        </section>
      </div>
    </main>
  </div>
</div>

<!-- Librerías (mismas versiones/CDN que index.php) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
<script src="../js/operaciones.js"></script>
<script src="../js/tallyman_relevo.js"></script>
</body>
</html>
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run: `& "C:\xampp2026\php\php.exe" -l "C:\xampp2026\htdocs\Estiba_Turno\pages\tallyman_relevo.php"`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add pages/tallyman_relevo.php && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): esqueleto de la vista de relevo (HTML/CSS + libs CDN)"
```

---

## Task 5: Lógica de la vista de relevo (carga, KPIs, tabla, gráficos) — auto-oculta vacíos

**Files:**
- Create: `js/tallyman_relevo.js`

- [ ] **Step 1: Crear el JS de la vista**

Crear `js/tallyman_relevo.js`:

```js
/* Relevo de turno (Fase 2) + Export PDF ejecutivo (Fase 3).
   Carga el payload del relevo, pinta KPIs/tabla/gráficos ocultando vacíos,
   y exporta un PDF nativo con jsPDF + autotable. Patrón window.OP. */
(function () {
  'use strict';
  var OP = window.OP, $ = OP.$;
  var rv = null;          // payload del relevo
  var barChart = null, donaChart = null;

  function n(v) { return v == null ? null : Number(v); }
  function fmt(v) { return v == null ? '—' : (Math.round(Number(v) * 100) / 100).toLocaleString('es-PE'); }

  // ¿alguna fila trae productividad? (para decidir si la columna se muestra)
  function hayCol(regs, key) { return regs.some(function (r) { return r[key] != null && r[key] !== ''; }); }

  async function cargar() {
    // turno vigente desde PHP
    var tr = await fetch('../includes/tallyman_turno.php', { cache: 'no-store' });
    var td = await tr.json();
    if (!td.success) throw new Error(td.error || 'Sin turno');
    var turno = td.data;
    var r = await OP.opApi('tallyman/relevo', { query: { fecha: turno.fecha, turno: turno.turno } });
    rv = r.data;
    rv._label = turno.label;
    return rv;
  }

  function pintarMeta() {
    var m = [];
    m.push('Fecha: <b>' + OP.esc(rv.fecha) + '</b>');
    m.push('Turno: <b>' + OP.esc(rv._label || rv.turno) + '</b>');
    if (rv.coord_entrante) m.push('Entrante: <b>' + OP.esc(rv.coord_entrante) + '</b>');
    if (rv.coord_saliente) m.push('Saliente: <b>' + OP.esc(rv.coord_saliente) + '</b>');
    $('rvMeta').innerHTML = m.join('');
  }

  function pintarKpis() {
    var t = rv.totales, k = [];
    k.push(kpi('Actividades', t.n_actividades));
    k.push(kpi('Planned total', fmt(t.planned)));
    k.push(kpi('Executed (turno)', fmt(t.executed)));
    if (t.porcentaje != null) k.push(kpi('Avance global', t.porcentaje + '%'));
    $('rvKpis').innerHTML = k.join('');
  }
  function kpi(lbl, val) {
    return '<div class="rv-kpi"><div class="lbl">' + OP.esc(lbl) + '</div><div class="val">' + OP.esc(String(val)) + '</div></div>';
  }

  function pintarTabla() {
    var regs = rv.registros;
    if (!regs.length) { $('rvTablaWrap').innerHTML = '<div class="rv-empty">Sin actividades registradas en este turno.</div>'; return; }
    var showProd = hayCol(regs, 'productivity');  // columna se omite si está vacía en todo
    var head = '<tr><th>Ubicación</th><th>Nave</th><th>Actividad</th><th>Status</th>' +
      '<th class="num">Planned</th><th class="num">Executed</th><th class="num">Acum.</th><th class="num">Pend.</th><th class="num">%</th>' +
      (showProd ? '<th class="num">Prod.</th>' : '') + '</tr>';
    var body = regs.map(function (r) {
      return '<tr>' +
        '<td>' + OP.esc(r.ubicacion) + '</td>' +
        '<td>' + OP.esc(r.nave || '—') + '</td>' +
        '<td>' + OP.esc(r.actividad) + '</td>' +
        '<td>' + OP.esc(r.status_act) + '</td>' +
        '<td class="num">' + fmt(r.planned) + '</td>' +
        '<td class="num">' + fmt(r.executed) + '</td>' +
        '<td class="num">' + fmt(r.accumulated) + '</td>' +
        '<td class="num">' + fmt(r.pending) + '</td>' +
        '<td class="num">' + (r.porcentaje == null ? '—' : r.porcentaje + '%') + '</td>' +
        (showProd ? '<td class="num">' + fmt(r.productivity) + '</td>' : '') +
        '</tr>';
    }).join('');
    $('rvTablaWrap').innerHTML = '<table class="rv-table"><thead>' + head + '</thead><tbody>' + body + '</tbody></table>';
  }

  function pintarIncidencia() {
    var inc = rv.incidencia;
    if (!inc || !inc.hubo) return;  // solo si hubo
    $('rvIncCard').style.display = '';
    $('rvInc').innerHTML = '<p style="margin:0;font-size:13.5px;white-space:pre-wrap;">' + OP.esc(inc.detalle || '') + '</p>';
  }

  function pintarGraficos() {
    var regs = rv.registros.filter(function (r) { return r.planned != null && Number(r.planned) > 0; });
    var labels = regs.map(function (r) { return r.actividad + ' · ' + r.ubicacion; });
    var ejec = regs.map(function (r) { return Number(r.executed) || 0; });
    var pend = regs.map(function (r) { return r.pending == null ? 0 : Number(r.pending); });

    if (window.Chart && regs.length) {
      barChart = new Chart($('rvBar'), {
        type: 'bar',
        data: { labels: labels, datasets: [
          { label: 'Executed (turno)', data: ejec, backgroundColor: '#013a78' },
          { label: 'Pendiente', data: pend, backgroundColor: '#cbd5e1' },
        ] },
        options: { responsive: true, animation: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } },
      });
      var tExec = rv.totales.executed, tPend = rv.totales.pending;
      donaChart = new Chart($('rvDona'), {
        type: 'doughnut',
        data: { labels: ['Executed', 'Pendiente'], datasets: [{ data: [tExec, tPend], backgroundColor: ['#013a78', '#cbd5e1'] }] },
        options: { responsive: true, animation: false, plugins: { legend: { position: 'bottom' }, title: { display: rv.totales.porcentaje != null, text: 'Avance global ' + (rv.totales.porcentaje || 0) + '%' } } },
      });
    }
  }

  async function init() {
    try {
      await cargar();
    } catch (e) {
      $('rvMeta').textContent = 'No se pudo cargar el relevo — ¿servicio de Operaciones activo?';
      OP.toast(e.message, 'error');
      return;
    }
    pintarMeta(); pintarKpis(); pintarTabla(); pintarIncidencia(); pintarGraficos();
    $('rvExport').disabled = false;
    $('rvExport').addEventListener('click', function () { window.TallymanPDF.exportar(rv, barChart, donaChart); });
  }
  init();
})();
```

Nota: `window.TallymanPDF.exportar` se define en Task 6 (mismo archivo, se añade abajo). Hasta entonces el botón existe pero su handler fallaría — por eso Task 6 va seguida y se prueba junta.

- [ ] **Step 2: Verificar sintaxis JS**

Run (desde la raíz): `node --check js/tallyman_relevo.js`
Expected: exit 0.

- [ ] **Step 3: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add js/tallyman_relevo.js && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): vista de relevo — KPIs, tabla y gráficos (oculta vacíos)"
```

---

## Task 6: Export PDF ejecutivo (jsPDF + autotable, oculta vacíos)

**Files:**
- Modify: `js/tallyman_relevo.js` (añadir el módulo `TallymanPDF` al final, dentro del archivo pero en su propio IIFE)

- [ ] **Step 1: Añadir el generador de PDF**

Al final de `js/tallyman_relevo.js` (después del IIFE existente), añadir:

```js
/* Generador de PDF ejecutivo. Reusa el patrón de js/estiba.js:
   const { jsPDF } = window.jspdf; doc.autoTable(...). Inserta los gráficos
   (canvas de Chart.js) como imágenes. Omite secciones/columnas vacías. */
(function () {
  'use strict';
  function fmt(v) { return v == null ? '—' : (Math.round(Number(v) * 100) / 100).toLocaleString('es-PE'); }

  function exportar(rv, barChart, donaChart) {
    if (!window.jspdf || !window.jspdf.jsPDF) { (window.OP ? OP.toast('jsPDF no cargó', 'error') : alert('jsPDF no cargó')); return; }
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
    var W = doc.internal.pageSize.getWidth();
    var M = 40, y = 48;

    // Cabecera ejecutiva (banda navy)
    doc.setFillColor(0, 43, 92); doc.rect(0, 0, W, 70, 'F');
    doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(16);
    doc.text('Relevo de turno · Reporte ejecutivo', M, 34);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(10);
    var meta = 'Fecha: ' + rv.fecha + '    Turno: ' + (rv._label || rv.turno);
    if (rv.coord_entrante) meta += '    Entrante: ' + rv.coord_entrante;
    if (rv.coord_saliente) meta += '    Saliente: ' + rv.coord_saliente;
    doc.text(meta, M, 54);
    y = 92;

    // KPIs
    doc.setTextColor(11, 31, 58); doc.setFont('helvetica', 'bold'); doc.setFontSize(11);
    doc.text('Indicadores del turno', M, y); y += 8;
    var t = rv.totales;
    var kpis = [['Actividades', String(t.n_actividades)], ['Planned total', fmt(t.planned)], ['Executed (turno)', fmt(t.executed)]];
    if (t.porcentaje != null) kpis.push(['Avance global', t.porcentaje + '%']);
    doc.autoTable({ startY: y + 4, margin: { left: M, right: M }, theme: 'grid',
      head: [kpis.map(function (k) { return k[0]; })], body: [kpis.map(function (k) { return k[1]; })],
      headStyles: { fillColor: [1, 58, 120] }, styles: { fontSize: 9, halign: 'center' } });
    y = doc.lastAutoTable.finalY + 18;

    // Gráficos (canvas → imagen). Solo si existen.
    var imgW = (W - M * 2 - 16) / 2;
    if (barChart) { try { doc.addImage(barChart.toBase64Image(), 'PNG', M, y, imgW, imgW * 0.7); } catch (e) {} }
    if (donaChart) { try { doc.addImage(donaChart.toBase64Image(), 'PNG', M + imgW + 16, y, imgW, imgW * 0.7); } catch (e) {} }
    if (barChart || donaChart) y += imgW * 0.7 + 18;

    // Tabla de actividades (oculta la columna Prod. si vacía en todo)
    var regs = rv.registros;
    var showProd = regs.some(function (r) { return r.productivity != null && r.productivity !== ''; });
    if (regs.length) {
      var head = ['Ubicación', 'Nave', 'Actividad', 'Status', 'Planned', 'Executed', 'Acum.', 'Pend.', '%'];
      if (showProd) head.push('Prod.');
      var body = regs.map(function (r) {
        var row = [r.ubicacion, r.nave || '—', r.actividad, r.status_act,
          fmt(r.planned), fmt(r.executed), fmt(r.accumulated), fmt(r.pending),
          (r.porcentaje == null ? '—' : r.porcentaje + '%')];
        if (showProd) row.push(fmt(r.productivity));
        return row;
      });
      doc.autoTable({ startY: y, margin: { left: M, right: M }, theme: 'striped',
        head: [head], body: body, headStyles: { fillColor: [1, 58, 120] }, styles: { fontSize: 8 } });
      y = doc.lastAutoTable.finalY + 16;
    }

    // Incidencias (solo si hubo)
    if (rv.incidencia && rv.incidencia.hubo) {
      if (y > doc.internal.pageSize.getHeight() - 80) { doc.addPage(); y = 48; }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(180, 35, 24);
      doc.text('Incidencias del turno', M, y); y += 14;
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(11, 31, 58);
      var lines = doc.splitTextToSize(rv.incidencia.detalle || '', W - M * 2);
      doc.text(lines, M, y);
    }

    doc.save('relevo_' + rv.fecha + '_' + rv.turno + '.pdf');
  }

  window.TallymanPDF = { exportar: exportar };
})();
```

- [ ] **Step 2: Verificar sintaxis JS**

Run: `node --check js/tallyman_relevo.js`
Expected: exit 0.

- [ ] **Step 3: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add js/tallyman_relevo.js && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): export PDF ejecutivo (jsPDF+autotable, oculta vacíos)"
```

---

## Task 7: Enlace en el sidebar + verificación end-to-end en navegador

**Files:**
- Modify: `includes/sidebar.php`

- [ ] **Step 1: Añadir el enlace de relevo**

En `includes/sidebar.php`, justo después del bloque del enlace "Registro tallyman" (que está dentro del `if (in_array($rol, ['Administrador','Supervisor','Coordinador']...))`), añadir:

```php
    <a href="<?= $sb_base ?? '..' ?>/pages/tallyman_relevo.php" class="nav-item<?= str_starts_with($cur, 'tallyman_relevo') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>
        </svg>
      </span>
      <span class="nav-label">Relevo de turno</span>
      <span class="tip">Relevo de turno</span>
    </a>
```

Nota: el enlace "Registro tallyman" usa `str_starts_with($cur, 'tallyman')` para marcar activo. Como `tallyman_relevo.php` también empieza con "tallyman", ambos se marcarían activos a la vez. Para evitarlo, cambiar la condición del enlace de **Registro tallyman** de:
```php
class="nav-item<?= str_starts_with($cur, 'tallyman') ? ' active' : '' ?>"
```
a:
```php
class="nav-item<?= ($cur === 'tallyman.php') ? ' active' : '' ?>"
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run: `& "C:\xampp2026\php\php.exe" -l "C:\xampp2026\htdocs\Estiba_Turno\includes\sidebar.php"`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verificación manual en navegador**

Con la API Node corriendo y sesión de Coordinador:
1. Registrar 1-2 actividades en `pages/tallyman.php` (una con planned, otra sin nave para probar la omisión).
2. Abrir `pages/tallyman_relevo.php`.
3. Confirmar: meta (fecha/turno), KPIs (planned/executed/% si hay planned), tabla con las actividades, gráfico de barras + dona, y que la columna Prod. solo aparece si alguna fila la tiene.
4. Pulsar "Exportar PDF" → se descarga `relevo_<fecha>_<turno>.pdf` con cabecera ejecutiva, KPIs, gráficos, tabla e incidencias (si hubo).

Expected: todo se ve y el PDF descarga sin errores de consola.

- [ ] **Step 4: Commit**

```bash
cd "/c/xampp2026/htdocs/Estiba_Turno" && git add includes/sidebar.php && git -c user.name="Estiba Dev" -c user.email="dev@estiba.local" commit -m "feat(tallyman): enlace Relevo de turno en el sidebar"
```

---

## Cierre de Fases 2 y 3

Al terminar:
- Endpoint `GET /tallyman/relevo` devuelve registros+incidencia+totales en una llamada (con tests del cálculo de totales).
- `pages/tallyman_relevo.php` muestra cabecera ejecutiva, KPIs, tabla, barras+dona, e incidencias, ocultando lo vacío.
- "Exportar PDF" genera un reporte ejecutivo nativo (jsPDF+autotable) estilo HANDOVER que auto-oculta berths/yards vacíos, columnas vacías e incidencias inexistentes.
- Enlace en el sidebar.

**Notas de operación:** requiere la API Node en :4000 (igual que Registro). Las librerías Chart.js/jsPDF se cargan por CDN cdnjs (como `index.php`); requiere internet en el navegador del cliente. Si se necesita operación offline, una mejora futura es vendorizarlas en `js/vendor/` (como `xlsx.full.min.js`).
