# Módulo de Colaboradores · Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear el módulo de Colaboradores con CRUD persistente en MySQL e importación masiva desde Excel, reemplazando el seed JS actual.

**Architecture:** Página dedicada `pages/colaboradores.php` (estilo `usuarios.php`), 4 endpoints REST-like en `/api/` con `mysqli` y prepared statements, parseo de .xlsx en navegador con SheetJS, UPSERT por DNI en transacción. La pestaña Plantilla del `index.php` migra a consumir la nueva API sin cambiar su UI.

**Tech Stack:** PHP 8 + mysqli, vanilla JS, SheetJS (`xlsx.full.min.js`) standalone, MySQL 8 con trigger `AFTER INSERT`.

**Spec de referencia:** `docs/superpowers/specs/2026-05-27-colaboradores-module-design.md`

---

## Notas de implementación

**Sin framework de tests.** Este proyecto no tiene PHPUnit / Jest / etc. La verificación de cada task es **manual via navegador y consultas SQL**. Cada task incluye su "Verificación" con pasos concretos.

**Sin git (proyecto local).** No hay control de versiones activo. Si más adelante se versiona, los pasos de commit pueden agregarse fácilmente al final de cada task.

**Convenciones del proyecto que se respetan:**
- APIs viven en `/api/` y devuelven JSON `{ success, data?, error? }`.
- Páginas administrativas usan `require_admin()`; endpoints JSON usan `api_require_admin()`.
- Estilos por página usan prefijos (`.usr-*`, `.est-*`). Usaremos `.col-*` para Colaboradores.
- mysqli con prepared statements (`mysqli_prepare`, `mysqli_stmt_bind_param`).

---

## Task 1: Schema, trigger y seed inicial

**Files:**
- Create: `sql/002_colaboradores.sql`

- [ ] **Step 1: Crear el archivo SQL completo**

```sql
-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 002 · Tabla colaboradores
-- Catálogo maestro de personal. Reemplaza el seed hardcoded
-- de js/data-source.js (array `plantilla`).
-- Ejecutar con: mysql -uroot estiba_turno < sql/002_colaboradores.sql
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

CREATE TABLE IF NOT EXISTS colaboradores (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(20)  NULL,
  nombre             VARCHAR(150) NOT NULL,
  dni                VARCHAR(8)   NOT NULL,
  funcion_principal  VARCHAR(60)  NOT NULL,
  cuadrilla          VARCHAR(20)  NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dni (dni),
  UNIQUE KEY uq_codigo (codigo),
  KEY ix_cuadrilla (cuadrilla),
  KEY ix_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTA: en versiones previas de este diseño había un trigger que
-- generaba `codigo` automáticamente, pero los enfoques que funcionan
-- en MariaDB 10.4 (AFTER INSERT UPDATE de la misma tabla → error 1442;
-- BEFORE INSERT leyendo information_schema → desync en multi-row
-- INSERT) tienen edge cases. Se eliminó el trigger; la generación
-- de `codigo` se hace en PHP en los endpoints `save_colaborador.php`
-- e `import_colaboradores.php` (patrón: INSERT, luego UPDATE codigo
-- con el id recién obtenido vía LAST_INSERT_ID).
DROP TRIGGER IF EXISTS trg_colaboradores_codigo;

-- Seed: los 12 colaboradores actuales de js/data-source.js.
-- codigo explícito para preservar los IDs ST-001..ST-012 que ya
-- referencian otros lugares (personal en data-source.js).
INSERT IGNORE INTO colaboradores (codigo, nombre, dni, funcion_principal, cuadrilla, activo) VALUES
('ST-001', 'Juan Pérez Quispe',        '45123678', 'Winchero',     'A', 1),
('ST-002', 'Carlos Mendoza Lévano',    '47893201', 'Estibador',    'A', 1),
('ST-003', 'Luis Ramírez Saldaña',     '41234567', 'Señalero',     'A', 1),
('ST-004', 'Pedro Huamán Castro',      '43456789', 'Tractorista',  'A', 1),
('ST-005', 'Miguel Ángel Torres',      '46789012', 'Capataz',      'A', 1),
('ST-006', 'Jorge Salazar Núñez',      '48901234', 'Lashing',      'A', 1),
('ST-007', 'Andrés Cárdenas Yupanqui', '44567890', 'Apoyo Bodega', 'A', 1),
('ST-008', 'Fernando Quiroz Bravo',    '42345678', 'Estibador',    'A', 1),
('ST-009', 'Ricardo Villanueva Poma',  '49012345', 'Winchero',     'B', 1),
('ST-010', 'Héctor Zapata Quispe',     '45678901', 'Estibador',    'B', 1),
('ST-011', 'Óscar Llerena Matos',      '43210987', 'Señalero',     'B', 1),
('ST-012', 'Raúl Condori Flores',      '47654321', 'Tractorista',  'B', 0);

-- Asegura que AUTO_INCREMENT arranca después del seed.
ALTER TABLE colaboradores AUTO_INCREMENT = 13;
```

- [ ] **Step 2: Ejecutar el script en MySQL**

Abrir terminal en XAMPP y ejecutar:

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root estiba_turno < c:/xampp2026/htdocs/Estiba_Turno/sql/002_colaboradores.sql
```

Si pide contraseña y no se ha configurado: `-u root` sin `-p` (config por defecto de XAMPP).

- [ ] **Step 3: Verificar en MySQL**

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; SELECT id, codigo, nombre, dni, funcion_principal, cuadrilla, activo FROM colaboradores ORDER BY id;"
```

**Resultado esperado:** 12 filas con codigos `ST-001` a `ST-012`, DNIs únicos, ST-012 con `activo=0`.

- [ ] **Step 4: Verificar que el trigger fue eliminado**

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; SHOW TRIGGERS LIKE 'colaboradores';"
```

**Resultado esperado:** salida vacía (sin triggers en la tabla). La generación de `codigo` queda delegada al PHP en los endpoints siguientes.

---

## Task 2: API `get_colaboradores.php`

**Files:**
- Create: `api/get_colaboradores.php`

- [ ] **Step 1: Crear el endpoint**

```php
<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_login();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT id, codigo, nombre, dni, funcion_principal, cuadrilla, activo,
            created_at, updated_at
       FROM colaboradores
       ORDER BY cuadrilla ASC, nombre ASC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['activo'] = (int)$row['activo'];   // 0/1 como int, no string
    $out[] = $row;
}

echo json_encode([
    'success' => true,
    'data'    => $out,
]);
```

**Notas de diseño:**
- Se usa `api_require_login()` (no `api_require_admin()`) porque la pestaña Plantilla del index.php — visible a todos los roles — también consume este endpoint.
- Se castea `activo` a int para que en JS `!!c.activo` funcione correctamente.

- [ ] **Step 2: Verificación con login**

Iniciar sesión en `http://localhost/Estiba_Turno/login.php` como admin (`admin@estiba.local` / `admin123`), luego abrir en otra pestaña:

```
http://localhost/Estiba_Turno/api/get_colaboradores.php
```

**Resultado esperado:** JSON con `success: true` y `data` conteniendo 12 colaboradores ordenados por cuadrilla (A primero, luego B) y dentro de cada cuadrilla por nombre alfabético.

- [ ] **Step 3: Verificar protección sin sesión**

Cerrar sesión y abrir la misma URL.

**Resultado esperado:** JSON `{ "success": false, "error": "No autenticado." }` con HTTP 401.

---

## Task 3: Página `pages/colaboradores.php` · scaffold + listado

**Files:**
- Create: `pages/colaboradores.php`

- [ ] **Step 1: Crear el archivo con estructura completa (sigue patrón de `pages/usuarios.php`)**

```php
<?php
require_once('../includes/auth.php');
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Colaboradores · Estiba Shift Command Deck</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">

  <style>
    /* ════════════════════════════════════════════════════════════════
       COLABORADORES · CRUD + IMPORT (prefijo .col-*)
    ════════════════════════════════════════════════════════════════ */
    .col-wrap {
      --co-navy:#002b5c; --co-navy-700:#013a78; --co-red:#e60012;
      --co-deck:#f1f5f9; --co-line:#e2e8f0; --co-line-bold:#cbd5e1;
      --co-ink:#0b1f3a; --co-mute:#64748b; --co-faint:#94a3b8;
      --st-ok-bg:#ECFDF3; --st-ok-fg:#057A55; --st-ok-dot:#12B76A;
      --st-er-bg:#FEF3F2; --st-er-fg:#B42318; --st-er-dot:#F04438;
      --st-wn-bg:#FFFAEB; --st-wn-fg:#B54708; --st-wn-dot:#F79009;
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .col-wrap *, .col-wrap *::before, .col-wrap *::after { box-sizing:border-box; }

    .col-hero {
      background: linear-gradient(155deg, #001b3a 0%, #002b5c 45%, #013a78 100%);
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
    }
    .col-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .col-hero p  { margin:0; color:rgba(255,255,255,.7); font-size:13px; max-width:560px; }
    .col-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
    }
    .col-hero-actions { display:flex; gap:8px; }

    .col-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer;
      font-family:inherit; font-size:13px; font-weight:600; color:var(--co-ink);
      transition:all .15s;
    }
    .col-btn:hover { border-color:var(--co-navy); color:var(--co-navy); }
    .col-btn.primary { background:var(--co-navy); color:#fff; border-color:var(--co-navy); }
    .col-btn.primary:hover { background:var(--co-navy-700); }
    .col-btn.ghost-light { background:rgba(255,255,255,.10); color:#fff; border-color:rgba(255,255,255,.25); }
    .col-btn.ghost-light:hover { background:rgba(255,255,255,.18); }
    .col-btn.danger { background:#fff; border-color:#FCA5A5; color:var(--st-er-fg); }
    .col-btn.danger:hover { background:var(--st-er-bg); }
    .col-btn svg { width:14px; height:14px; }

    .col-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .col-kpi {
      flex:1; min-width:140px;
      background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px;
    }
    .col-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .col-kpi .val { font-size:22px; font-weight:700; color:var(--co-navy); margin-top:4px; }

    .col-toolbar {
      display:flex; gap:10px; align-items:center;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
    }
    .col-search {
      flex:1; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid transparent; border-radius:10px; padding:8px 12px;
    }
    .col-search:focus-within { border-color:var(--co-navy); background:#fff; }
    .col-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .col-search svg { width:15px; height:15px; color:var(--co-mute); }
    .col-filter {
      display:flex; gap:4px; background:var(--co-deck); border-radius:10px; padding:3px;
    }
    .col-filter button {
      padding:6px 12px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }
    .col-filter button.active { background:#fff; color:var(--co-navy); box-shadow:0 1px 3px rgba(0,0,0,.06); }

    .col-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:hidden; }
    .col-table { width:100%; border-collapse:collapse; font-size:13px; }
    .col-table thead tr { background:var(--co-deck); border-bottom:1px solid var(--co-line-bold); }
    .col-table th {
      padding:11px 14px; text-align:left;
      font-size:10.5px; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-mute); font-weight:700; white-space:nowrap;
    }
    .col-table tbody tr { border-bottom:1px solid var(--co-line); transition:background .12s; }
    .col-table tbody tr:last-child { border-bottom:0; }
    .col-table tbody tr:hover { background:#f8fafc; }
    .col-table td { padding:11px 14px; vertical-align:middle; }
    .col-cell-name { display:flex; align-items:center; gap:10px; }
    .col-avatar {
      width:34px; height:34px; border-radius:10px; flex-shrink:0;
      background:var(--co-navy); color:#fff;
      display:grid; place-items:center; font-size:13px; font-weight:700;
    }
    .col-avatar.inact { background:var(--co-faint); }
    .col-name { font-weight:600; color:var(--co-ink); }
    .col-codigo { font-size:11px; color:var(--co-faint); font-family:var(--mono); }

    .col-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 8px; border-radius:6px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
    }
    .col-badge .dot { width:6px; height:6px; border-radius:50%; }
    .col-badge.is-active { background:var(--st-ok-bg); color:var(--st-ok-fg); }
    .col-badge.is-active .dot { background:var(--st-ok-dot); }
    .col-badge.is-inactive { background:var(--st-er-bg); color:var(--st-er-fg); }
    .col-badge.is-inactive .dot { background:var(--st-er-dot); }
    .col-badge.is-cuadrilla { background:var(--co-deck); color:var(--co-navy); }
    .col-badge.is-cuadrilla .dot { background:var(--co-navy); }

    .col-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-ink);
      transition:all .12s;
    }
    .col-act-btn:hover { border-color:var(--co-navy); color:var(--co-navy); }
    .col-act-btn.danger:hover { border-color:var(--co-red); color:var(--co-red); }
    .col-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }

    /* Toast (reusa estética de usuarios) */
    .col-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#001b3a; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px -8px rgba(0,0,0,.4);
      transform:translateY(120%); opacity:0; transition:all .25s;
    }
    .col-toast.show { transform:translateY(0); opacity:1; }
    .col-toast.is-error { background:var(--st-er-fg); }

    .content { padding:24px 28px 60px; overflow-y:auto; }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="col-wrap">

        <!-- HERO -->
        <section class="col-hero">
          <div>
            <span class="tag">ADMINISTRACIÓN · COLABORADORES</span>
            <h1>Catálogo maestro de colaboradores</h1>
            <p>Base maestra del personal de estiba. Alta manual o importación desde Excel. Los colaboradores aparecen luego en la Plantilla del turno.</p>
          </div>
          <div class="col-hero-actions">
            <button class="col-btn ghost-light" id="btnImport">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Importar Excel
            </button>
            <button class="col-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nuevo colaborador
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="col-kpis">
          <div class="col-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="col-kpi"><div class="lbl">Activos</div><div class="val" id="kpiActivos">0</div></div>
          <div class="col-kpi"><div class="lbl">Inactivos</div><div class="val" id="kpiInactivos">0</div></div>
          <div class="col-kpi"><div class="lbl">Cuadrillas</div><div class="val" id="kpiCuadrillas">0</div></div>
          <div class="col-kpi"><div class="lbl">Funciones</div><div class="val" id="kpiFunciones">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="col-toolbar">
          <div class="col-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="colSearch" type="text" placeholder="Buscar por nombre, DNI, función o cuadrilla…">
          </div>
          <div class="col-filter" id="colFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="activo">Activos</button>
            <button data-f="inactivo">Inactivos</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="col-table-wrap">
          <table class="col-table">
            <thead>
              <tr>
                <th>Colaborador</th>
                <th>DNI</th>
                <th>Función</th>
                <th>Cuadrilla</th>
                <th>Estado</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="colTbody">
              <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<div class="col-toast" id="colToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  let colaboradores = [];
  let query = '';
  let filtro = 'todos';

  function toast(msg, type) {
    const t = $('colToast');
    t.textContent = msg;
    t.className = 'col-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 2800);
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase();
  }

  function renderKpis() {
    $('kpiTotal').textContent     = colaboradores.length;
    $('kpiActivos').textContent   = colaboradores.filter(c => c.activo === 1).length;
    $('kpiInactivos').textContent = colaboradores.filter(c => c.activo === 0).length;
    $('kpiCuadrillas').textContent = new Set(colaboradores.map(c => c.cuadrilla)).size;
    $('kpiFunciones').textContent  = new Set(colaboradores.map(c => c.funcion_principal)).size;
  }

  function render() {
    const q = query.trim().toLowerCase();
    const list = colaboradores.filter(c => {
      if (filtro === 'activo'   && c.activo !== 1) return false;
      if (filtro === 'inactivo' && c.activo !== 0) return false;
      if (!q) return true;
      return [c.nombre, c.dni, c.funcion_principal, c.cuadrilla, c.codigo]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });

    const tbody = $('colTbody');
    tbody.innerHTML = '';
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--co-faint)">Sin resultados.</td></tr>`;
      return;
    }
    list.forEach(c => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="col-cell-name">
            <div class="col-avatar ${c.activo ? '' : 'inact'}">${esc(initials(c.nombre))}</div>
            <div>
              <div class="col-name">${esc(c.nombre)}</div>
              <div class="col-codigo">${esc(c.codigo)}</div>
            </div>
          </div>
        </td>
        <td><span style="font-family:var(--mono);font-size:12px">${esc(c.dni)}</span></td>
        <td>${esc(c.funcion_principal)}</td>
        <td><span class="col-badge is-cuadrilla"><span class="dot"></span>${esc(c.cuadrilla)}</span></td>
        <td>
          <span class="col-badge ${c.activo ? 'is-active' : 'is-inactive'}">
            <span class="dot"></span>${c.activo ? 'Activo' : 'Inactivo'}
          </span>
        </td>
        <td>
          <div class="col-cell-actions">
            <button class="col-act-btn" data-action="edit" data-id="${c.id}">Editar</button>
            <button class="col-act-btn danger" data-action="del" data-id="${c.id}">Eliminar</button>
          </div>
        </td>`;
      tbody.append(tr);
    });
  }

  async function cargar() {
    try {
      const res = await fetch('../api/get_colaboradores.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      colaboradores = data.data || [];
      renderKpis();
      render();
    } catch (e) {
      toast('Error de red', 'error');
    }
  }

  // Placeholders (se conectan en tasks 4–9)
  $('btnNew').addEventListener('click', () => toast('Modal de alta — pendiente Task 4'));
  $('btnImport').addEventListener('click', () => toast('Modal de import — pendiente Task 7'));
  $('colSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('colFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('colFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('colTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'edit') toast('Edición — pendiente Task 4');
    if (b.dataset.action === 'del')  toast('Eliminación — pendiente Task 5');
  });

  cargar();

  // Exponer para tasks siguientes
  window.__ColaboradoresPage = {
    reload: cargar,
    getList: () => colaboradores,
    toast,
  };
})();
</script>

</body>
</html>
```

- [ ] **Step 2: Verificación visual**

Abrir `http://localhost/Estiba_Turno/pages/colaboradores.php` (logueado como admin).

**Resultado esperado:**
- Hero navy con título y dos botones ("Importar Excel" y "Nuevo colaborador").
- KPIs: Total=12, Activos=11, Inactivos=1, Cuadrillas=2, Funciones=7 (Winchero, Estibador, Señalero, Tractorista, Capataz, Lashing, Apoyo Bodega).
- Tabla con 12 filas, ordenadas por cuadrilla (A primero) y luego nombre.
- ST-012 (Raúl Condori) aparece con avatar gris y badge "Inactivo".
- Buscador funciona (probar "wincher" → 2 resultados).
- Filtros funcionan (probar "Inactivos" → 1 resultado).
- Click en botones de acción muestra toasts placeholder.

- [ ] **Step 3: Verificación de protección de ruta**

Cerrar sesión y abrir `http://localhost/Estiba_Turno/pages/colaboradores.php`.

**Resultado esperado:** redirige a `login.php` (por `require_admin()` → `require_login()`).

---

## Task 4: API `save_colaborador.php` + modal de alta/edición

**Files:**
- Create: `api/save_colaborador.php`
- Modify: `pages/colaboradores.php` (añadir modal + handlers)

- [ ] **Step 1: Crear `api/save_colaborador.php`**

```php
<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$id        = isset($data['id']) ? (int)$data['id'] : 0;
$nombre    = trim($data['nombre'] ?? '');
$dni       = trim($data['dni'] ?? '');
$funcion   = trim($data['funcion_principal'] ?? '');
$cuadrilla = trim($data['cuadrilla'] ?? '');
$activo    = !empty($data['activo']) ? 1 : 0;

// Validaciones
if ($nombre === '' || mb_strlen($nombre) < 3) {
    echo json_encode(['success' => false, 'error' => 'Nombre requerido (mínimo 3 caracteres).']); exit;
}
if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'DNI debe tener exactamente 8 dígitos.']); exit;
}
if ($funcion === '')   { echo json_encode(['success' => false, 'error' => 'Función requerida.']); exit; }
if ($cuadrilla === '') { echo json_encode(['success' => false, 'error' => 'Cuadrilla requerida.']); exit; }

if ($id > 0) {
    // UPDATE
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE colaboradores
            SET nombre=?, dni=?, funcion_principal=?, cuadrilla=?, activo=?
          WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssii', $nombre, $dni, $funcion, $cuadrilla, $activo, $id);

    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        $msg = (strpos((string)$err, 'Duplicate entry') !== false && strpos((string)$err, 'uq_dni') !== false)
            ? 'Ese DNI ya está registrado.'
            : $err;
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    // Recuperar codigo existente
    $r = mysqli_query($conn, "SELECT codigo FROM colaboradores WHERE id = " . (int)$id);
    $codigo = '';
    if ($r && ($row = mysqli_fetch_assoc($r))) $codigo = $row['codigo'];

    echo json_encode(['success' => true, 'id' => (int)$id, 'codigo' => $codigo]);

} else {
    // INSERT + generación de codigo en PHP (sin trigger).
    // Se usa transacción para que INSERT + UPDATE codigo sean atómicos.
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO colaboradores (nombre, dni, funcion_principal, cuadrilla, activo)
                  VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'ssssi', $nombre, $dni, $funcion, $cuadrilla, $activo);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException($err);
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $codigo = 'ST-' . str_pad((string)$newId, 3, '0', STR_PAD_LEFT);
        $up = mysqli_prepare($conn, "UPDATE colaboradores SET codigo=? WHERE id=?");
        mysqli_stmt_bind_param($up, 'si', $codigo, $newId);
        if (!mysqli_stmt_execute($up)) {
            $err = mysqli_stmt_error($up);
            mysqli_stmt_close($up);
            throw new RuntimeException($err);
        }
        mysqli_stmt_close($up);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'id' => $newId, 'codigo' => $codigo]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'uq_dni') !== false)
            ? 'Ese DNI ya está registrado.'
            : $e->getMessage();
        echo json_encode(['success' => false, 'error' => $msg]);
    }
}
```

- [ ] **Step 2: Añadir modal HTML al final del `<body>` de `pages/colaboradores.php` (antes del toast)**

Localizar la línea `<div class="col-toast" id="colToast">—</div>` y JUSTO ANTES insertar:

```html
<!-- MODAL: alta / edición -->
<div class="col-modal-back" id="colModalBack">
  <div class="col-modal">
    <div class="col-modal-head">
      <div>
        <h3 id="colModalTitle">Nuevo colaborador</h3>
        <div class="sub">El código (ST-###) se genera automáticamente al guardar.</div>
      </div>
      <button class="col-modal-close" id="colModalClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="col-modal-body">
      <input type="hidden" id="cm-id">
      <div class="col-field">
        <label>Nombre completo</label>
        <input id="cm-nombre" type="text" placeholder="Apellidos y Nombres" maxlength="150">
      </div>
      <div class="col-row2">
        <div class="col-field">
          <label>DNI</label>
          <input id="cm-dni" type="text" placeholder="00000000" maxlength="8" inputmode="numeric">
        </div>
        <div class="col-field">
          <label>Cuadrilla</label>
          <input id="cm-cuadrilla" type="text" placeholder="A, B, Noche…" maxlength="20">
        </div>
      </div>
      <div class="col-row2">
        <div class="col-field">
          <label>Función principal</label>
          <input id="cm-funcion" type="text" placeholder="Winchero, Estibador…" maxlength="60" list="cm-funcion-suggestions">
          <datalist id="cm-funcion-suggestions">
            <option value="Winchero"></option>
            <option value="Estibador"></option>
            <option value="Señalero"></option>
            <option value="Tractorista"></option>
            <option value="Capataz"></option>
            <option value="Apoyo Bodega"></option>
            <option value="Apoyo Muelle"></option>
            <option value="Lashing"></option>
          </datalist>
        </div>
        <div class="col-field">
          <label>Estado</label>
          <select id="cm-activo">
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>
      </div>
    </div>
    <div class="col-modal-foot">
      <button class="col-btn" id="colModalCancel">Cancelar</button>
      <button class="col-btn primary" id="colModalSave">Guardar</button>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Añadir CSS del modal al bloque `<style>` (justo antes de `.col-toast`)**

```css
.col-modal-back {
  position:fixed; inset:0; background:rgba(11,31,58,.48);
  display:grid; place-items:center; z-index:995;
  opacity:0; pointer-events:none; transition:opacity .2s;
}
.col-modal-back.open { opacity:1; pointer-events:auto; }
.col-modal {
  background:#fff; border-radius:18px; width:520px; max-width:94vw;
  box-shadow:0 24px 64px -16px rgba(0,43,92,.35);
  transform:translateY(12px) scale(.97);
  transition:transform .22s cubic-bezier(.25,.46,.45,.94);
  max-height:90vh; display:flex; flex-direction:column; overflow:hidden;
}
.col-modal-back.open .col-modal { transform:translateY(0) scale(1); }
.col-modal-head {
  padding:18px 20px 14px; border-bottom:1px solid var(--co-line);
  display:flex; align-items:center; justify-content:space-between;
}
.col-modal-head h3 { margin:0; font-size:17px; font-weight:700; }
.col-modal-head .sub { font-size:12px; color:var(--co-mute); margin-top:2px; }
.col-modal-close {
  width:32px; height:32px; border-radius:8px; border:1px solid var(--co-line);
  background:#fff; cursor:pointer; display:grid; place-items:center; color:var(--co-mute);
}
.col-modal-close:hover { color:var(--co-red); border-color:var(--co-red); }
.col-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex:1; }
.col-modal-foot { padding:14px 20px; border-top:1px solid var(--co-line); display:flex; justify-content:flex-end; gap:8px; }
.col-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.col-field { display:flex; flex-direction:column; gap:5px; }
.col-field label {
  font-size:11px; font-weight:600; color:var(--co-mute);
  letter-spacing:.04em; text-transform:uppercase;
}
.col-field input, .col-field select {
  font:inherit; font-size:13.5px; color:var(--co-ink);
  background:#fff; border:1px solid var(--co-line-bold); border-radius:8px;
  padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s;
}
.col-field input:focus, .col-field select:focus {
  border-color:var(--co-navy); box-shadow:0 0 0 3px rgba(0,43,92,.08);
}
```

- [ ] **Step 4: Reemplazar el `<script>` IIFE de la página, añadiendo el código del modal**

Reemplazar el contenido completo del `<script>` con esta versión (incluye lo que ya había + el modal):

```javascript
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  let colaboradores = [];
  let query = '';
  let filtro = 'todos';
  let editingId = null;

  function toast(msg, type) {
    const t = $('colToast');
    t.textContent = msg;
    t.className = 'col-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 2800);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase();
  }

  function renderKpis() {
    $('kpiTotal').textContent      = colaboradores.length;
    $('kpiActivos').textContent    = colaboradores.filter(c => c.activo === 1).length;
    $('kpiInactivos').textContent  = colaboradores.filter(c => c.activo === 0).length;
    $('kpiCuadrillas').textContent = new Set(colaboradores.map(c => c.cuadrilla)).size;
    $('kpiFunciones').textContent  = new Set(colaboradores.map(c => c.funcion_principal)).size;
  }

  function render() {
    const q = query.trim().toLowerCase();
    const list = colaboradores.filter(c => {
      if (filtro === 'activo'   && c.activo !== 1) return false;
      if (filtro === 'inactivo' && c.activo !== 0) return false;
      if (!q) return true;
      return [c.nombre, c.dni, c.funcion_principal, c.cuadrilla, c.codigo]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tbody = $('colTbody');
    tbody.innerHTML = '';
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--co-faint)">Sin resultados.</td></tr>`;
      return;
    }
    list.forEach(c => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div class="col-cell-name">
            <div class="col-avatar ${c.activo ? '' : 'inact'}">${esc(initials(c.nombre))}</div>
            <div>
              <div class="col-name">${esc(c.nombre)}</div>
              <div class="col-codigo">${esc(c.codigo)}</div>
            </div>
          </div>
        </td>
        <td><span style="font-family:var(--mono);font-size:12px">${esc(c.dni)}</span></td>
        <td>${esc(c.funcion_principal)}</td>
        <td><span class="col-badge is-cuadrilla"><span class="dot"></span>${esc(c.cuadrilla)}</span></td>
        <td><span class="col-badge ${c.activo ? 'is-active' : 'is-inactive'}"><span class="dot"></span>${c.activo ? 'Activo' : 'Inactivo'}</span></td>
        <td>
          <div class="col-cell-actions">
            <button class="col-act-btn" data-action="edit" data-id="${c.id}">Editar</button>
            <button class="col-act-btn danger" data-action="del" data-id="${c.id}">Eliminar</button>
          </div>
        </td>`;
      tbody.append(tr);
    });
  }

  async function cargar() {
    try {
      const res = await fetch('../api/get_colaboradores.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      colaboradores = data.data || [];
      renderKpis(); render();
    } catch (e) {
      toast('Error de red', 'error');
    }
  }

  // ─── Modal Alta/Edición ───
  function openModal(id) {
    editingId = id ? Number(id) : null;
    const c = editingId ? colaboradores.find(x => Number(x.id) === editingId) : null;
    $('colModalTitle').textContent = c ? 'Editar colaborador' : 'Nuevo colaborador';
    $('cm-id').value         = c ? c.id : '';
    $('cm-nombre').value     = c ? c.nombre : '';
    $('cm-dni').value        = c ? c.dni : '';
    $('cm-funcion').value    = c ? c.funcion_principal : '';
    $('cm-cuadrilla').value  = c ? c.cuadrilla : '';
    $('cm-activo').value     = c ? String(c.activo) : '1';
    $('colModalBack').classList.add('open');
    setTimeout(() => $('cm-nombre').focus(), 80);
  }
  function closeModal() { $('colModalBack').classList.remove('open'); editingId = null; }

  async function guardar() {
    const payload = {
      id:                Number($('cm-id').value || 0),
      nombre:            $('cm-nombre').value.trim(),
      dni:               $('cm-dni').value.trim(),
      funcion_principal: $('cm-funcion').value.trim(),
      cuadrilla:         $('cm-cuadrilla').value.trim(),
      activo:            $('cm-activo').value === '1' ? 1 : 0,
    };
    if (payload.nombre.length < 3) { toast('Nombre requerido', 'error'); $('cm-nombre').focus(); return; }
    if (!/^\d{8}$/.test(payload.dni)) { toast('DNI debe tener 8 dígitos', 'error'); $('cm-dni').focus(); return; }
    if (!payload.funcion_principal) { toast('Función requerida', 'error'); $('cm-funcion').focus(); return; }
    if (!payload.cuadrilla) { toast('Cuadrilla requerida', 'error'); $('cm-cuadrilla').focus(); return; }

    const btn = $('colModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch('../api/save_colaborador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Colaborador actualizado' : `Colaborador creado · ${data.codigo}`);
        closeModal();
        cargar();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  $('btnImport').addEventListener('click', () => toast('Modal de import — pendiente Task 7'));
  $('colSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('colFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('colFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('colTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'del')  toast('Eliminación — pendiente Task 5');
  });
  $('colModalClose').addEventListener('click', closeModal);
  $('colModalCancel').addEventListener('click', closeModal);
  $('colModalBack').addEventListener('click', e => { if (e.target === $('colModalBack')) closeModal(); });
  $('colModalSave').addEventListener('click', guardar);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $('colModalBack').classList.contains('open')) closeModal();
  });

  cargar();

  window.__ColaboradoresPage = { reload: cargar, getList: () => colaboradores, toast };
})();
```

- [ ] **Step 5: Verificación — alta de colaborador**

Recargar `pages/colaboradores.php`. Click en "Nuevo colaborador":
- Modal se abre, foco en Nombre.
- Llenar: Nombre="Prueba Uno", DNI="11111111", Función="Estibador", Cuadrilla="C", Estado=Activo.
- Click "Guardar".

**Resultado esperado:** toast "Colaborador creado · ST-013" (o el siguiente número correspondiente). El listado se recarga y el nuevo colaborador aparece con código ST-### autogenerado.

- [ ] **Step 6: Verificación — validaciones**

Click "Nuevo", llenar Nombre vacío → toast "Nombre requerido".
Nombre OK, DNI="123" → toast "DNI debe tener 8 dígitos".
DNI duplicado (ej. "45123678" que ya existe en seed) → toast "Ese DNI ya está registrado.".

- [ ] **Step 7: Verificación — edición**

Click "Editar" en cualquier fila. Modal muestra datos pre-llenados. Cambiar el nombre, click "Guardar".

**Resultado esperado:** toast "Colaborador actualizado", la tabla refleja el cambio.

- [ ] **Step 8: Limpiar la fila de prueba via SQL**

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; DELETE FROM colaboradores WHERE dni='11111111';"
```

---

## Task 5: API `delete_colaborador.php` + botón Eliminar

**Files:**
- Create: `api/delete_colaborador.php`
- Modify: `pages/colaboradores.php` (handler del botón eliminar)

- [ ] **Step 1: Crear `api/delete_colaborador.php`**

```php
<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM colaboradores WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}
if ($affected === 0) {
    echo json_encode(['success' => false, 'error' => 'No se encontró el colaborador.']);
    exit;
}
echo json_encode(['success' => true]);
```

- [ ] **Step 2: En `pages/colaboradores.php`, reemplazar el handler de `data-action="del"`**

En el bloque de eventos del IIFE, buscar el listener del tbody y reemplazar la rama `'del'`:

```javascript
$('colTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
});
```

Y añadir la función `eliminar` cerca de `guardar`:

```javascript
async function eliminar(id) {
    const c = colaboradores.find(x => Number(x.id) === Number(id));
    if (!c) return;
    if (!confirm(`¿Eliminar al colaborador "${c.nombre}" (${c.codigo})?\nEsta acción no se puede deshacer.`)) return;
    try {
      const res = await fetch('../api/delete_colaborador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Colaborador eliminado'); cargar(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) {
      toast('Error de red', 'error');
    }
}
```

- [ ] **Step 3: Verificación — crear y eliminar**

Crear un colaborador de prueba (Nuevo → DNI 22222222 → Guardar).
Click "Eliminar" en su fila → confirmar.

**Resultado esperado:** toast "Colaborador eliminado", fila desaparece de la tabla, KPI Total decrece.

- [ ] **Step 4: Verificación — cancelar el confirm**

Click "Eliminar" en otra fila → cancelar el diálogo.

**Resultado esperado:** nada cambia.

---

## Task 6: Entrada "Colaboradores" en el sidebar (solo Admin)

**Files:**
- Modify: `includes/sidebar.php` (entre el bloque admin existente)

- [ ] **Step 1: Editar `includes/sidebar.php`**

Localizar el bloque `<?php if ($rol === 'Administrador'): ?>` (línea ~56). Justo antes del enlace a `pages/usuarios.php`, añadir el enlace a Colaboradores:

```php
    <?php if ($rol === 'Administrador'): ?>
    <!-- ADMINISTRACIÓN · solo Administrador -->
    <div class="nav-divider"></div>
    <span class="nav-sep">Administración</span>

    <a href="<?= $sb_base ?? '..' ?>/pages/colaboradores.php" class="nav-item">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </span>
      <span class="nav-label">Colaboradores</span>
      <span class="tip">Colaboradores</span>
    </a>

    <a href="<?= $sb_base ?? '..' ?>/pages/usuarios.php" class="nav-item">
```

(Solo se añade el `<a>` nuevo antes del existente de usuarios; el `if`/divider/sep ya existen, no se duplican.)

- [ ] **Step 2: Verificación**

Refrescar cualquier página estando logueado como admin.

**Resultado esperado:** el sidebar muestra "Colaboradores" justo encima de "Usuarios" en la sección Administración. Click navega a la página correctamente.

- [ ] **Step 3: Verificación con rol Operador**

Cerrar sesión, crear (vía Usuarios) o usar un usuario Operador, e iniciar sesión con él.

**Resultado esperado:** el sidebar NO muestra "Colaboradores" ni "Usuarios" (el bloque entero está envuelto en `if ($rol === 'Administrador')`).

---

## Task 7: SheetJS local + modal de importación · paso 1 (selección y parseo)

**Files:**
- Create: `js/vendor/xlsx.full.min.js` (descarga)
- Modify: `pages/colaboradores.php` (modal de import + carga de SheetJS)

- [ ] **Step 1: Descargar SheetJS a `js/vendor/`**

```bash
mkdir -p c:/xampp2026/htdocs/Estiba_Turno/js/vendor
curl -L -o c:/xampp2026/htdocs/Estiba_Turno/js/vendor/xlsx.full.min.js https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js
```

Verificar tamaño:

```bash
ls -la c:/xampp2026/htdocs/Estiba_Turno/js/vendor/xlsx.full.min.js
```

**Resultado esperado:** archivo de ~900 KB. Si la descarga falla, alternativa: descargar manualmente desde `https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js` y guardar en esa ruta.

- [ ] **Step 2: Incluir SheetJS en `pages/colaboradores.php`**

Localizar la línea `<link rel="stylesheet" href="../css/ui.css">` (en `<head>`). Justo antes del `</head>` (después de la `<style>` block), agregar:

```html
  <script src="../js/vendor/xlsx.full.min.js"></script>
```

- [ ] **Step 3: Añadir HTML del modal de import al final del `<body>` (antes del toast)**

```html
<!-- MODAL: importar Excel -->
<div class="col-modal-back" id="impModalBack">
  <div class="col-modal" id="impModal" style="width:880px">
    <div class="col-modal-head">
      <div>
        <h3 id="impModalTitle">Importar colaboradores desde Excel</h3>
        <div class="sub" id="impModalSub">Selecciona un archivo .xlsx con las columnas: Nombre, DNI, Función, Cuadrilla.</div>
      </div>
      <button class="col-modal-close" id="impModalClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="col-modal-body">

      <!-- PASO 1: SELECCIÓN -->
      <div id="impStep1">
        <div class="col-drop" id="colDrop">
          <input type="file" id="impFile" accept=".xlsx" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="col-drop-title">Arrastra tu archivo .xlsx aquí</div>
          <div class="col-drop-sub">o haz click para seleccionarlo</div>
        </div>
        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--co-mute)">
          <span>Encabezados aceptados: <code>Nombre · DNI · Función · Cuadrilla</code></span>
          <a href="#" id="impDownloadTpl" style="color:var(--co-navy);font-weight:600;text-decoration:none">↓ Descargar plantilla Excel</a>
        </div>
      </div>

      <!-- PASO 2: PREVIEW -->
      <div id="impStep2" style="display:none">
        <div class="col-imp-summary" id="impSummary"></div>
        <div class="col-imp-filter">
          <button class="active" data-imp-f="all">Todos</button>
          <button data-imp-f="new">Solo nuevos</button>
          <button data-imp-f="update">Solo actualizar</button>
          <button data-imp-f="error">Solo errores</button>
        </div>
        <div class="col-imp-table-wrap">
          <table class="col-imp-table">
            <thead>
              <tr><th>#</th><th>Estado</th><th>Nombre</th><th>DNI</th><th>Función</th><th>Cuadrilla</th></tr>
            </thead>
            <tbody id="impTbody"></tbody>
          </table>
        </div>
      </div>

    </div>
    <div class="col-modal-foot" id="impFoot">
      <button class="col-btn" id="impBack" style="display:none">← Volver</button>
      <button class="col-btn" id="impCancel">Cancelar</button>
      <button class="col-btn primary" id="impConfirm" style="display:none">Confirmar 0 filas</button>
    </div>
  </div>
</div>
```

- [ ] **Step 4: Añadir CSS del modal de import al bloque `<style>`**

```css
.col-drop {
  border:2px dashed var(--co-line-bold); border-radius:14px;
  padding:36px; text-align:center; cursor:pointer;
  color:var(--co-mute); transition:all .15s; background:var(--co-deck);
}
.col-drop:hover, .col-drop.over { border-color:var(--co-navy); background:#fff; color:var(--co-navy); }
.col-drop svg { display:block; margin:0 auto 10px; opacity:.7; }
.col-drop-title { font-size:14px; font-weight:600; color:var(--co-ink); }
.col-drop-sub   { font-size:12px; margin-top:2px; }

.col-imp-summary {
  display:flex; gap:14px; padding:12px 16px;
  background:var(--co-deck); border-radius:10px; margin-bottom:12px;
  font-size:13px; font-weight:600;
}
.col-imp-summary span { display:inline-flex; align-items:center; gap:6px; }
.col-imp-summary .dot { width:8px; height:8px; border-radius:50%; }
.col-imp-summary .new    { color:var(--st-ok-fg); }
.col-imp-summary .new .dot    { background:var(--st-ok-dot); }
.col-imp-summary .upd    { color:var(--st-wn-fg); }
.col-imp-summary .upd .dot    { background:var(--st-wn-dot); }
.col-imp-summary .err    { color:var(--st-er-fg); }
.col-imp-summary .err .dot    { background:var(--st-er-dot); }

.col-imp-filter {
  display:flex; gap:4px; background:var(--co-deck); border-radius:10px;
  padding:3px; margin-bottom:8px; width:fit-content;
}
.col-imp-filter button {
  padding:6px 12px; border:0; background:transparent; border-radius:7px;
  font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
}
.col-imp-filter button.active { background:#fff; color:var(--co-navy); box-shadow:0 1px 3px rgba(0,0,0,.06); }

.col-imp-table-wrap {
  max-height:340px; overflow:auto;
  border:1px solid var(--co-line); border-radius:10px;
}
.col-imp-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.col-imp-table thead { position:sticky; top:0; background:var(--co-deck); z-index:1; }
.col-imp-table th, .col-imp-table td { padding:8px 12px; text-align:left; border-bottom:1px solid var(--co-line); }
.col-imp-table tr[data-status="new"]    td { background:rgba(18,183,106,.05); }
.col-imp-table tr[data-status="update"] td { background:rgba(247,144,9,.06); }
.col-imp-table tr[data-status="error"]  td { background:rgba(240,68,56,.06); }
.col-imp-table .col-imp-status {
  display:inline-flex; align-items:center; gap:5px;
  padding:2px 7px; border-radius:5px;
  font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
}
.col-imp-table .col-imp-status.new    { background:var(--st-ok-bg); color:var(--st-ok-fg); }
.col-imp-table .col-imp-status.update { background:var(--st-wn-bg); color:var(--st-wn-fg); }
.col-imp-table .col-imp-status.error  { background:var(--st-er-bg); color:var(--st-er-fg); }
.col-imp-table .col-imp-errmsg {
  display:block; font-size:11px; color:var(--st-er-fg); font-weight:500; margin-top:2px;
}
```

- [ ] **Step 5: Añadir lógica del modal y parseo de Excel al IIFE de la página**

Dentro del IIFE (justo antes de `cargar();` final), agregar este bloque:

```javascript
  // ════════════════════════════════════════════════════════════════════
  // MÓDULO DE IMPORTACIÓN
  // ════════════════════════════════════════════════════════════════════

  const HEADER_MAP = {
    nombre:    ['Nombre','NOMBRE','Nombres','Nombre completo','Apellidos y Nombres','APELLIDOS Y NOMBRES'],
    dni:       ['DNI','D.N.I.','Documento','DOCUMENTO','Cédula','CEDULA','Cedula'],
    funcion:   ['Función','Funcion','FUNCION','Función Principal','Cargo','CARGO','Puesto'],
    cuadrilla: ['Cuadrilla','CUADRILLA','Turno','Grupo','Equipo']
  };

  let impRows = [];        // filas con status calculado
  let impFilter = 'all';

  function impOpen() {
    impRows = [];
    impFilter = 'all';
    $('impStep1').style.display = '';
    $('impStep2').style.display = 'none';
    $('impBack').style.display = 'none';
    $('impConfirm').style.display = 'none';
    $('impFile').value = '';
    $('impModalBack').classList.add('open');
  }
  function impClose() { $('impModalBack').classList.remove('open'); }

  function normalizeRow(raw) {
    const out = { nombre:'', dni:'', funcion:'', cuadrilla:'' };
    for (const key in out) {
      const candidates = HEADER_MAP[key];
      for (const c of candidates) {
        if (raw[c] !== undefined && raw[c] !== null && String(raw[c]).trim() !== '') {
          out[key] = String(raw[c]).trim();
          break;
        }
      }
    }
    // DNI a veces viene como número desde Excel
    if (out.dni) out.dni = out.dni.replace(/\D/g, '').padStart(8, '0').slice(-8);
    return out;
  }

  function validateRows(rawRows) {
    const existingDniSet = new Set(colaboradores.map(c => c.dni));
    const seenInFile = new Set();
    return rawRows.map((raw, idx) => {
      const row = normalizeRow(raw);
      const errors = [];

      if (!row.nombre || row.nombre.length < 3) errors.push('Nombre requerido (≥3 caracteres)');
      if (!/^\d{8}$/.test(row.dni))             errors.push('DNI inválido (8 dígitos)');
      if (!row.funcion)                          errors.push('Función requerida');
      if (!row.cuadrilla)                        errors.push('Cuadrilla requerida');

      // Duplicado dentro del archivo
      if (row.dni && seenInFile.has(row.dni)) errors.push('DNI duplicado dentro del archivo');
      else if (row.dni) seenInFile.add(row.dni);

      const status = errors.length ? 'error'
                   : existingDniSet.has(row.dni) ? 'update'
                   : 'new';
      return { rowNumber: idx + 2, ...row, status, errors }; // +2 porque hoja Excel parte en 1 y tiene encabezado
    });
  }

  async function impHandleFile(file) {
    if (!file) return;
    if (!/\.xlsx$/i.test(file.name)) { toast('Solo se aceptan archivos .xlsx', 'error'); return; }
    try {
      const buf = await file.arrayBuffer();
      const wb = XLSX.read(buf, { type: 'array' });
      if (!wb.SheetNames.length) { toast('El archivo no tiene hojas', 'error'); return; }
      const sheet = wb.Sheets[wb.SheetNames[0]];
      const raw = XLSX.utils.sheet_to_json(sheet, { defval: '' });
      if (!raw.length) { toast('La hoja está vacía', 'error'); return; }

      // Validar que el archivo tiene al menos columnas Nombre o DNI reconocibles
      const headerKeys = Object.keys(raw[0]);
      const hasName = HEADER_MAP.nombre.some(h => headerKeys.includes(h));
      const hasDni  = HEADER_MAP.dni.some(h => headerKeys.includes(h));
      if (!hasName || !hasDni) {
        toast('No se reconocen las columnas Nombre / DNI. Descarga la plantilla.', 'error');
        return;
      }
      if (raw.length > 1000) {
        toast('Máximo 1000 filas por archivo. Divide la planilla.', 'error');
        return;
      }

      impRows = validateRows(raw);
      impFilter = 'all';
      impRenderPreview();
      $('impStep1').style.display = 'none';
      $('impStep2').style.display = '';
      $('impBack').style.display = '';
      $('impConfirm').style.display = '';
    } catch (e) {
      console.error(e);
      toast('No se pudo leer el archivo (¿formato válido?)', 'error');
    }
  }

  function impRenderPreview() {
    const counts = { new:0, update:0, error:0 };
    impRows.forEach(r => counts[r.status]++);
    $('impSummary').innerHTML = `
      <span class="new"><span class="dot"></span>${counts.new} nuevos</span>
      <span class="upd"><span class="dot"></span>${counts.update} actualizar</span>
      <span class="err"><span class="dot"></span>${counts.error} con errores</span>
    `;
    const processable = counts.new + counts.update;
    $('impConfirm').textContent = `Confirmar ${processable} filas`;
    $('impConfirm').disabled = processable === 0;

    const visible = impRows.filter(r => impFilter === 'all' ? true : r.status === impFilter);
    const tb = $('impTbody'); tb.innerHTML = '';
    if (!visible.length) {
      tb.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--co-faint)">Sin filas para mostrar.</td></tr>`;
      return;
    }
    visible.forEach(r => {
      const tr = document.createElement('tr');
      tr.dataset.status = r.status;
      const label = r.status === 'new' ? '✓ Nuevo' : r.status === 'update' ? '↻ Actualizar' : '⚠ Error';
      tr.innerHTML = `
        <td style="font-family:var(--mono);color:var(--co-faint)">${r.rowNumber}</td>
        <td><span class="col-imp-status ${r.status}">${label}</span>${r.errors.length ? `<span class="col-imp-errmsg">${esc(r.errors.join(' · '))}</span>` : ''}</td>
        <td>${esc(r.nombre || '—')}</td>
        <td style="font-family:var(--mono)">${esc(r.dni || '—')}</td>
        <td>${esc(r.funcion || '—')}</td>
        <td>${esc(r.cuadrilla || '—')}</td>`;
      tb.append(tr);
    });
  }

  function impDownloadTemplate(ev) {
    ev.preventDefault();
    const ws = XLSX.utils.aoa_to_sheet([
      ['Nombre','DNI','Función','Cuadrilla'],
      ['Ejemplo Colaborador Uno','11111111','Estibador','A'],
      ['Ejemplo Colaborador Dos','22222222','Winchero','B'],
    ]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Colaboradores');
    XLSX.writeFile(wb, 'plantilla_colaboradores.xlsx');
  }

  // Wiring del modal de import
  $('btnImport').addEventListener('click', impOpen);
  $('impModalClose').addEventListener('click', impClose);
  $('impCancel').addEventListener('click', impClose);
  $('impBack').addEventListener('click', () => {
    $('impStep1').style.display = '';
    $('impStep2').style.display = 'none';
    $('impBack').style.display = 'none';
    $('impConfirm').style.display = 'none';
    $('impFile').value = '';
  });
  $('impModalBack').addEventListener('click', e => { if (e.target === $('impModalBack')) impClose(); });
  $('colDrop').addEventListener('click', () => $('impFile').click());
  $('colDrop').addEventListener('dragover', e => { e.preventDefault(); $('colDrop').classList.add('over'); });
  $('colDrop').addEventListener('dragleave', () => $('colDrop').classList.remove('over'));
  $('colDrop').addEventListener('drop', e => {
    e.preventDefault();
    $('colDrop').classList.remove('over');
    impHandleFile(e.dataTransfer.files[0]);
  });
  $('impFile').addEventListener('change', e => impHandleFile(e.target.files[0]));
  document.querySelector('.col-imp-filter')?.addEventListener('click', e => {
    const b = e.target.closest('button[data-imp-f]'); if (!b) return;
    impFilter = b.dataset.impF;
    document.querySelectorAll('.col-imp-filter button').forEach(x => x.classList.toggle('active', x === b));
    impRenderPreview();
  });
  $('impDownloadTpl').addEventListener('click', impDownloadTemplate);
  $('impConfirm').addEventListener('click', () => toast('Confirmación — pendiente Task 9'));
```

**IMPORTANTE:** quitar la línea placeholder `$('btnImport').addEventListener('click', () => toast('Modal de import — pendiente Task 7'));` que se añadió en Task 4 — fue reemplazada por el wiring real arriba.

- [ ] **Step 6: Verificación — descargar plantilla**

Recargar la página, click "Importar Excel" → modal abre con drop zone. Click en "↓ Descargar plantilla Excel".

**Resultado esperado:** descarga `plantilla_colaboradores.xlsx` con 4 columnas y 2 filas de ejemplo. Abrir en Excel para confirmar.

- [ ] **Step 7: Verificación — preview con filas válidas**

Abrir la plantilla descargada, dejarla como está (los 2 ejemplos), guardarla, y subirla al modal (click en drop zone → seleccionar).

**Resultado esperado:**
- Pasa al paso 2 (preview).
- Resumen: "2 nuevos, 0 actualizar, 0 con errores".
- Tabla con 2 filas verdes.
- Botón "Confirmar 2 filas" habilitado.

- [ ] **Step 8: Verificación — duplicados detectados**

Editar la plantilla y cambiar el DNI de la primera fila a `45123678` (que ya existe en BD como Juan Pérez). Volver a importar.

**Resultado esperado:** primera fila aparece ámbar con badge "↻ Actualizar". Resumen "1 nuevo, 1 actualizar, 0 errores".

- [ ] **Step 9: Verificación — errores de validación**

Editar plantilla: dejar la primera fila con DNI vacío y la segunda con DNI "abc". Importar.

**Resultado esperado:** ambas filas en rojo con badges "⚠ Error" y mensajes específicos ("DNI inválido"). Botón "Confirmar 0 filas" deshabilitado.

- [ ] **Step 10: Verificación — DNI duplicado intra-archivo**

Crear una plantilla con 2 filas con el MISMO DNI (ej. 33333333). Importar.

**Resultado esperado:** primera fila verde, segunda fila roja con error "DNI duplicado dentro del archivo".

---

## Task 8: API `import_colaboradores.php` (UPSERT transaccional)

**Files:**
- Create: `api/import_colaboradores.php`

- [ ] **Step 1: Crear el endpoint**

```php
<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !is_array($data['rows'] ?? null)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$rows = $data['rows'];
if (count($rows) === 0) {
    echo json_encode(['success' => false, 'error' => 'No hay filas para importar.']);
    exit;
}
if (count($rows) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Máximo 1000 filas por importación.']);
    exit;
}

mysqli_begin_transaction($conn);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO colaboradores (nombre, dni, funcion_principal, cuadrilla, activo)
          VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       nombre = VALUES(nombre),
       funcion_principal = VALUES(funcion_principal),
       cuadrilla = VALUES(cuadrilla),
       updated_at = CURRENT_TIMESTAMP"
);

$inserted = 0;
$updated  = 0;

$stmtCodigo = mysqli_prepare($conn, "UPDATE colaboradores SET codigo=? WHERE id=? AND codigo IS NULL");

try {
    foreach ($rows as $i => $r) {
        $nombre    = trim($r['nombre']    ?? '');
        $dni       = trim($r['dni']       ?? '');
        $funcion   = trim($r['funcion']   ?? '');
        $cuadrilla = trim($r['cuadrilla'] ?? '');

        // Re-validación defensiva (cliente ya validó)
        if (mb_strlen($nombre) < 3)                throw new RuntimeException("Fila " . ($i+1) . ": nombre inválido.");
        if (!preg_match('/^\d{8}$/', $dni))        throw new RuntimeException("Fila " . ($i+1) . ": DNI inválido.");
        if ($funcion === '')                       throw new RuntimeException("Fila " . ($i+1) . ": función vacía.");
        if ($cuadrilla === '')                     throw new RuntimeException("Fila " . ($i+1) . ": cuadrilla vacía.");

        mysqli_stmt_bind_param($stmt, 'ssss', $nombre, $dni, $funcion, $cuadrilla);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException("Fila " . ($i+1) . ": " . mysqli_stmt_error($stmt));
        }

        // Semántica MySQL: ON DUPLICATE KEY UPDATE → affected_rows = 1 si insert, 2 si update, 0 si update sin cambios.
        // mysqli_insert_id() devuelve el id de la fila insertada O actualizada (0 cuando no es INSERT puro).
        $aff = mysqli_stmt_affected_rows($stmt);
        if ($aff === 1) {
            // INSERT real → generar codigo en PHP (no hay trigger).
            $newId  = (int)mysqli_insert_id($conn);
            $codigo = 'ST-' . str_pad((string)$newId, 3, '0', STR_PAD_LEFT);
            mysqli_stmt_bind_param($stmtCodigo, 'si', $codigo, $newId);
            if (!mysqli_stmt_execute($stmtCodigo)) {
                throw new RuntimeException("Fila " . ($i+1) . ": falló asignación de codigo: " . mysqli_stmt_error($stmtCodigo));
            }
            $inserted++;
        } elseif ($aff === 2 || $aff === 0) {
            // UPDATE (DNI existente). codigo se conserva, no se toca.
            $updated++;
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmtCodigo);
    mysqli_commit($conn);
    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'total'    => $inserted + $updated,
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    if (isset($stmt)       && $stmt)       @mysqli_stmt_close($stmt);
    if (isset($stmtCodigo) && $stmtCodigo) @mysqli_stmt_close($stmtCodigo);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

**Notas:**
- `affected_rows`: 1 = INSERT efectivo, 2 = UPDATE con cambios, 0 = UPDATE sin cambios (datos idénticos). Trato 0 y 2 como "actualizado" para no perder cuenta.
- Cualquier excepción → `rollback` completo. No hay commits parciales.

- [ ] **Step 2: Test directo con `curl` o herramienta similar (opcional)**

Si no quieres esperar a integrar el frontend, puedes probar con `curl` desde otra terminal teniendo cookie de sesión válida (usar el navegador con DevTools → Network → copy as cURL para obtener el comando con sesión).

Saltar este paso y verificar en Task 9 vía UI es aceptable.

---

## Task 9: Conectar el botón "Confirmar" al endpoint de import

**Files:**
- Modify: `pages/colaboradores.php` (handler de `impConfirm`)

- [ ] **Step 1: Reemplazar el handler placeholder de `impConfirm`**

En el IIFE de la página, localizar:

```javascript
$('impConfirm').addEventListener('click', () => toast('Confirmación — pendiente Task 9'));
```

Reemplazar por:

```javascript
$('impConfirm').addEventListener('click', async () => {
    const payload = impRows
      .filter(r => r.status === 'new' || r.status === 'update')
      .map(r => ({ nombre: r.nombre, dni: r.dni, funcion: r.funcion, cuadrilla: r.cuadrilla }));

    if (!payload.length) { toast('No hay filas válidas para importar', 'error'); return; }

    const btn = $('impConfirm');
    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = 'Importando…';
    try {
      const res = await fetch('../api/import_colaboradores.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rows: payload }),
      });
      const data = await res.json();
      if (data.success) {
        toast(`Importación completada · ${data.inserted} nuevos, ${data.updated} actualizados`);
        impClose();
        cargar();
      } else {
        toast(data.error || 'Error en la importación', 'error');
      }
    } catch (e) {
      toast('Error de red — ningún cambio guardado', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
});
```

- [ ] **Step 2: Verificación end-to-end — import exitoso**

1. Crear un nuevo archivo Excel con 4 filas válidas (todos DNIs nuevos, ej. 51111111, 52222222, 53333333, 54444444).
2. Click "Importar Excel" → seleccionar archivo → preview muestra "4 nuevos".
3. Click "Confirmar 4 filas".

**Resultado esperado:** toast "Importación completada · 4 nuevos, 0 actualizados". Modal cierra. Tabla muestra los 4 nuevos colaboradores con códigos ST-### consecutivos. KPI Total subió en 4.

- [ ] **Step 3: Verificación — import con UPSERT**

Crear un Excel con 3 filas: 2 DNIs nuevos + 1 DNI que ya existe (cambiando solo el nombre). Importar.

**Resultado esperado:** toast "Importación completada · 2 nuevos, 1 actualizados". El colaborador con DNI duplicado tiene ahora el nombre nuevo en la tabla.

- [ ] **Step 4: Verificación — rollback en error**

Forzar un error: modificar temporalmente la BD para que algo falle (ej. `mysql_query("ALTER TABLE colaboradores MODIFY nombre VARCHAR(5)")` para forzar truncamiento). Importar un archivo con nombres largos.

**Resultado esperado:** toast con error específico, ninguna fila insertada (verificar con `SELECT COUNT(*)`).

Después restaurar: `ALTER TABLE colaboradores MODIFY nombre VARCHAR(150) NOT NULL`.

(Este paso es opcional. Si es complicado de simular, basta con confiar en que la lógica de transacción está correcta.)

- [ ] **Step 5: Limpiar las filas de prueba**

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; DELETE FROM colaboradores WHERE dni IN ('51111111','52222222','53333333','54444444');"
```

---

## Task 10: Migrar el tab Plantilla del `index.php` a usar la API

**Files:**
- Modify: `js/data-source.js` (eliminar array `plantilla`)
- Modify: `js/estiba.js` (hacer `boot()` async y consumir API)

- [ ] **Step 1: En `js/data-source.js`, eliminar el bloque `plantilla`**

Eliminar las líneas 82-98 completas (el comentario `/* Catálogo completo... */` y todo el array `plantilla: [...]`). La última propiedad del objeto debe ser `personal: [...]` cerrado con `]` (sin la coma que precedía a `plantilla`).

El final del archivo queda así (a partir de `personal`):

```javascript
  personal: [
    { id: "ST-001", nombre: "Juan Pérez Quispe", /* … igual que antes … */ },
    /* … resto del array personal sin cambios … */
    { id: "ST-008", nombre: "Fernando Quiroz Bravo", funcion: "Estibador", ubicacion: "Muelle Sur", estado: "activo", bitacora: [] }
  ]
};
```

- [ ] **Step 2: En `js/estiba.js`, hacer `boot()` async y fetch a la API**

Localizar la función `boot()` (línea ~576). Reemplazar la función completa por esta versión:

```javascript
  async function boot() {
    const src = window.__EstibaDataSource || { personal: [], funcionesDisponibles: [], ubicacionesDisponibles: [], turnoLabel: '' };
    state.personal    = src.personal.map(p => ({ ...p, bitacora: [...(p.bitacora || [])] }));
    state.funciones   = [...(src.funcionesDisponibles   || [])];
    state.ubicaciones = [...(src.ubicacionesDisponibles || [])];
    state.turnoLabel  = src.turnoLabel || '';
    const lim = (src.limitesMin || {});
    state.limites = {
      refrigerio: lim.refrigerio ?? 30,
      permiso:    lim.permiso    ?? 60
    };

    // Cargar plantilla desde la BD (reemplaza al seed JS anterior)
    try {
      const res = await fetch('api/get_colaboradores.php', { cache: 'no-store' });
      const data = await res.json();
      if (data && data.success && Array.isArray(data.data)) {
        state.plantilla = data.data.map(c => ({
          id:               c.codigo,
          nombre:           c.nombre,
          dni:              c.dni,
          funcionPrincipal: c.funcion_principal,
          cuadrilla:        c.cuadrilla,
          activo:           !!c.activo
        }));
      } else {
        state.plantilla = [];
        console.warn('[Estiba] No se pudo cargar la plantilla:', data?.error);
      }
    } catch (e) {
      state.plantilla = [];
      console.warn('[Estiba] Error de red al cargar plantilla:', e);
    }

    state.notified.clear();
    renderKpis();
    renderGrid();
    renderPlantilla();
    state.personal.forEach(p => {
      const al = computeAlert(p);
      if (al) state.notified.add(`${p.id}:${al.level}`);
    });
    clearInterval(state._tick);
    state._tick = setInterval(tickAlerts, 30000);
  }
```

**Cambios respecto al original:**
- `function boot()` → `async function boot()`.
- Se eliminó el `if (src.plantilla && src.plantilla.length) { ... } else { ... }`.
- Se agregó el `fetch` a `api/get_colaboradores.php` y el mapeo a la forma que ya espera el resto del JS (`id` = `codigo`, `funcionPrincipal` = `funcion_principal`, `activo` = bool).

- [ ] **Step 3: Verificación — index.php pestaña Plantilla**

Abrir `http://localhost/Estiba_Turno/index.php?tab=plantilla` logueado como cualquier rol.

**Resultado esperado:**
- La pestaña Plantilla muestra los mismos 12 colaboradores (ahora desde la BD), con códigos ST-001 a ST-012.
- El badge "Plantilla" en las tabs muestra "12".
- Buscador del tab funciona (probar "wincher").
- Si añades un colaborador desde `pages/colaboradores.php` y recargas el index, aparece en la pestaña Plantilla.

- [ ] **Step 4: Verificación — pestaña Turno actual**

Abrir `http://localhost/Estiba_Turno/index.php` (tab por defecto: Turno actual).

**Resultado esperado:** las 8 cards del personal en turno aparecen como siempre (este flujo NO cambió). Los KPIs superiores funcionan.

---

## Task 11: Redirigir botón "Nuevo colaborador" del tab Plantilla a la página dedicada

**Files:**
- Modify: `js/estiba.js` (handler de `plntNewBtn`)

- [ ] **Step 1: Localizar la función `wirePlantilla` en `js/estiba.js` (~línea 516)**

Encontrar la línea:

```javascript
    $('plntNewBtn').addEventListener('click', () => openCollabModal(null));
```

Y reemplazarla por:

```javascript
    $('plntNewBtn').addEventListener('click', () => {
      // El alta de colaboradores ahora se centraliza en la página dedicada.
      window.location.href = 'pages/colaboradores.php';
    });
```

**Nota:** dejar las funciones `openCollabModal`, `saveCollab`, `closeCollabModal` intactas — quedan inertes pero el spec las preserva para minimizar superficie de cambio. Los botones "Editar" / "✕" en la tabla del tab Plantilla siguen llamando `openCollabModal(id)` / `deleteCollab(id)` que solo mutan memoria; en una iteración futura se deciden qué hacer con ellas (este task NO las toca).

- [ ] **Step 2: Verificación**

Abrir `http://localhost/Estiba_Turno/index.php?tab=plantilla`. Click en "Nuevo colaborador" (en el toolbar de la pestaña).

**Resultado esperado:** navega a `pages/colaboradores.php`. Si el usuario es Operador, `require_admin()` lo bloquea con un mensaje 403.

---

## Task 12: Verificación end-to-end

- [ ] **Step 1: Flujo completo desde cero**

1. Cerrar sesión y volver a entrar como admin.
2. Sidebar muestra "Colaboradores" bajo "Administración". Click → abre `pages/colaboradores.php`.
3. Lista 12 colaboradores con avatares, códigos ST-001 a ST-012, badges, etc.
4. Click "Nuevo colaborador" → modal → llenar y guardar → toast verde, aparece en lista.
5. Editar la fila recién creada → modal abre con datos pre-llenados → cambiar nombre → guardar.
6. Eliminar la fila → confirmar → desaparece.
7. Click "Importar Excel" → descargar plantilla → editar (agregar 3 filas válidas) → subir → preview muestra "3 nuevos" → confirmar → toast verde, aparecen en lista.
8. Importar el mismo archivo otra vez → preview muestra "0 nuevos, 3 actualizar" → confirmar → toast "0 nuevos, 3 actualizados".

- [ ] **Step 2: Coherencia entre página y pestaña Plantilla del index**

1. En `pages/colaboradores.php`, anotar el total (KPI).
2. Ir a `index.php?tab=plantilla`. El badge "Plantilla" en el tab debe coincidir con ese total.
3. Crear un colaborador nuevo en la página.
4. Recargar el index → el badge sube en 1 y el nuevo aparece en la lista del tab.

- [ ] **Step 3: Permisos**

1. Crear un usuario Operador desde `pages/usuarios.php` (si no existe).
2. Cerrar sesión y entrar como ese Operador.
3. Sidebar NO debe mostrar "Colaboradores" ni "Usuarios" ni la sección "Administración".
4. Intentar acceso directo a `http://localhost/Estiba_Turno/pages/colaboradores.php` → ver "403 · No tienes permisos…".
5. Intentar acceso directo a `http://localhost/Estiba_Turno/api/import_colaboradores.php` (POST con DevTools) → JSON con `{ success: false, error: "Solo Administrador." }` y HTTP 403.

- [ ] **Step 4: Limpieza de filas de prueba (si quedan)**

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; SELECT id, codigo, nombre, dni FROM colaboradores WHERE id > 12;"
```

Si hay filas de prueba que quieres eliminar:

```bash
c:/xampp2026/mysql/bin/mysql.exe -u root -e "USE estiba_turno; DELETE FROM colaboradores WHERE id > 12;"
```

---

## Resumen de archivos tocados al final

**Creados:**
- `sql/002_colaboradores.sql`
- `pages/colaboradores.php`
- `api/get_colaboradores.php`
- `api/save_colaborador.php`
- `api/delete_colaborador.php`
- `api/import_colaboradores.php`
- `js/vendor/xlsx.full.min.js`
- `docs/superpowers/specs/2026-05-27-colaboradores-module-design.md` (ya existente)
- `docs/superpowers/plans/2026-05-27-colaboradores-module.md` (este archivo)

**Modificados:**
- `includes/sidebar.php` (+1 entrada en bloque admin)
- `js/data-source.js` (–17 líneas del array `plantilla`)
- `js/estiba.js` (`boot()` async + redirección de `plntNewBtn`)
