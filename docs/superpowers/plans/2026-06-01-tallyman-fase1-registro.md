# Registro de Actividad Tallyman — Fase 1 (Registro) · Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El coordinador tallyman registra, por turno, actividades de Muelle (Berth) y Patio (Yard) e incidencias; el sistema hereda `planned` de la nave (Operaciones) y calcula `accumulated`/`pending`, guardando todo en la BD `operaciones`.

**Architecture:** Tablas nuevas en BD `operaciones` (migración `007_tallyman.sql`). Módulo Node nuevo `modules/tallyman/` (routes + controller + model + validator) montado en `app.js`, espejando el patrón de `modules/operaciones/`. El allow-list del proxy PHP `api/operaciones_proxy.php` se amplía para aceptar `tallyman`. Frontend nuevo `pages/tallyman.php` con JS que llama vía `OP.opApi`. El turno (fecha+jornada) lo resuelve PHP (`obtener_turno_actual`) y se envía al backend en el cuerpo de cada POST.

**Tech Stack:** Node 18+ (ESM, Express 4, mysql2/promise), `node --test` para pruebas, MySQL/InnoDB utf8mb4, PHP 8.2 (proxy + página), JS vanilla (patrón `window.OP`), DM Sans + paleta navy de marca.

**Spec:** `docs/superpowers/specs/2026-06-01-registro-actividad-tallyman-design.md`

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `operaciones-api/sql/007_tallyman.sql` (crear) | DDL de `tallyman_actividades`, `tallyman_registros`, `tallyman_incidencias` + seed de 21 actividades |
| `operaciones-api/src/modules/tallyman/tallyman.model.js` (crear) | Todo el SQL del módulo (catálogo, CRUD registros, acumulados, incidencias) |
| `operaciones-api/src/modules/tallyman/tallyman.validator.js` (crear) | Validación pura de payloads (sin BD), testeable en aislamiento |
| `operaciones-api/src/modules/tallyman/tallyman.controller.js` (crear) | Orquesta validación + modelo, arma respuesta, calcula pending/accumulated |
| `operaciones-api/src/modules/tallyman/tallyman.routes.js` (crear) | Rutas Express + `requireRole` |
| `operaciones-api/src/app.js` (modificar) | Montar `tallymanRoutes` bajo `/api/operaciones` |
| `operaciones-api/test/tallyman.validator.test.js` (crear) | Pruebas unitarias del validador con `node --test` |
| `api/operaciones_proxy.php` (modificar :29) | Ampliar allow-list regex para incluir `tallyman` |
| `pages/tallyman.php` (crear) | Página de registro (HTML+CSS+JS inline, patrón de la app) |
| `includes/tallyman_turno.php` (crear) | Endpoint PHP que devuelve el turno vigente al front (usa `obtener_turno_actual`) |

---

## Task 1: Migración SQL del esquema tallyman

**Files:**
- Create: `operaciones-api/sql/007_tallyman.sql`

- [ ] **Step 1: Escribir el archivo de migración completo**

Crear `operaciones-api/sql/007_tallyman.sql` con este contenido exacto:

```sql
-- ============================================================
-- Operaciones · Registro de Actividad Tallyman · Migración 007
-- Ejecutar sobre la BD `operaciones` (ya existente).
-- ============================================================
USE operaciones;

-- ---------- Catálogo de actividades (21 del formulario HANDOVER) ----------
CREATE TABLE IF NOT EXISTS tallyman_actividades (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80)  NOT NULL,
  activo TINYINT(1)   NOT NULL DEFAULT 1,
  orden  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tallyman_act_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tallyman_actividades (nombre, orden) VALUES
  ('Containers Loading/Discharge', 1),
  ('Corn Loading/Discharge', 2),
  ('Salt Loading/Discharge', 3),
  ('Soybean Unloading/Loading', 4),
  ('Bulk Carrier Loading/Discharge', 5),
  ('Big Bags Loading/Discharge', 6),
  ('General Cargo Loading/Discharge', 7),
  ('Car Loading/Discharge', 8),
  ('Minerals', 9),
  ('Fishmeals', 10),
  ('Container deconsolidation', 11),
  ('Car deconsolidation', 12),
  ('Containers Dispatch', 13),
  ('Corn Dispatch', 14),
  ('Salt Dispatch', 15),
  ('Soybean Dispatch', 16),
  ('Bulk Carrier Dispatch', 17),
  ('Big Bags Dispatch', 18),
  ('General Cargo Dispatch', 19),
  ('Car Dispatch', 20),
  ('Reception of Salt', 21)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ---------- Registros de actividad por turno ----------
CREATE TABLE IF NOT EXISTS tallyman_registros (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE          NOT NULL,
  turno          VARCHAR(20)   NOT NULL,
  ubicacion_tipo ENUM('BERTH','YARD') NOT NULL,
  ubicacion      VARCHAR(40)   NOT NULL,
  nave_id        INT UNSIGNED  NULL,
  actividad_id   INT UNSIGNED  NOT NULL,
  estado_pos     ENUM('ACTIVE','INACTIVE','FINISH') NOT NULL DEFAULT 'ACTIVE',
  status_act     ENUM('Inicio','En Proceso','Culminado') NOT NULL DEFAULT 'Inicio',
  planned        DECIMAL(14,2) NULL,
  executed       DECIMAL(14,2) NOT NULL DEFAULT 0,
  productivity   DECIMAL(12,2) NULL,
  details        TEXT          NULL,
  coord_entrante VARCHAR(120)  NULL,
  coord_saliente VARCHAR(120)  NULL,
  registrado_por VARCHAR(120)  NOT NULL,
  fecha_registro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_tr_nave FOREIGN KEY (nave_id) REFERENCES naves(id) ON DELETE SET NULL,
  CONSTRAINT fk_tr_act  FOREIGN KEY (actividad_id) REFERENCES tallyman_actividades(id),
  KEY idx_tr_turno (fecha_turno, turno),
  KEY idx_tr_nave (nave_id),
  KEY idx_tr_acum (nave_id, actividad_id, ubicacion, fecha_turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Incidencias del turno ----------
CREATE TABLE IF NOT EXISTS tallyman_incidencias (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE         NOT NULL,
  turno          VARCHAR(20)  NOT NULL,
  hubo           TINYINT(1)   NOT NULL DEFAULT 0,
  detalle        TEXT         NULL,
  registrado_por VARCHAR(120) NOT NULL,
  fecha_registro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ti_turno (fecha_turno, turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Ejecutar la migración contra la BD operaciones**

Run (PowerShell, ruta de XAMPP MySQL):
```
& "C:\xampp2026\mysql\bin\mysql.exe" -u root operaciones -e "source C:/xampp2026/htdocs/Estiba_Turno/operaciones-api/sql/007_tallyman.sql"
```
Expected: sin errores (prompt vuelve limpio).

- [ ] **Step 3: Verificar que las tablas existen y el seed cargó**

Run:
```
& "C:\xampp2026\mysql\bin\mysql.exe" -u root operaciones -e "SELECT COUNT(*) AS n FROM tallyman_actividades; SHOW TABLES LIKE 'tallyman_%';"
```
Expected: `n = 21` y tres tablas listadas (`tallyman_actividades`, `tallyman_incidencias`, `tallyman_registros`).

- [ ] **Step 4: Commit**

```bash
git add operaciones-api/sql/007_tallyman.sql
git commit -m "feat(tallyman): migración 007 — catálogo, registros e incidencias"
```

---

## Task 2: Validador del módulo tallyman (TDD, sin BD)

**Files:**
- Create: `operaciones-api/src/modules/tallyman/tallyman.validator.js`
- Test: `operaciones-api/test/tallyman.validator.test.js`

El validador es función pura: recibe el cuerpo de un registro y devuelve un objeto normalizado, o lanza `ApiError`. Aislarlo permite probarlo sin BD.

- [ ] **Step 1: Escribir el test que falla**

Crear `operaciones-api/test/tallyman.validator.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseRegistro } from '../src/modules/tallyman/tallyman.validator.js';

const base = {
  fecha_turno: '2026-06-01',
  turno: 'Noche',
  ubicacion_tipo: 'BERTH',
  ubicacion: 'Berth 04',
  actividad_id: 1,
  estado_pos: 'ACTIVE',
  status_act: 'Inicio',
  executed: 831,
};

test('acepta un registro válido y normaliza números', () => {
  const r = parseRegistro({ ...base, planned: '1954', productivity: '120.5' });
  assert.equal(r.executed, 831);
  assert.equal(r.planned, 1954);
  assert.equal(r.productivity, 120.5);
  assert.equal(r.ubicacion_tipo, 'BERTH');
  assert.equal(r.actividad_id, 1);
});

test('rechaza fecha_turno con formato inválido', () => {
  assert.throws(() => parseRegistro({ ...base, fecha_turno: '01/06/2026' }), /YYYY-MM-DD/);
});

test('rechaza ubicacion_tipo fuera del enum', () => {
  assert.throws(() => parseRegistro({ ...base, ubicacion_tipo: 'DOCK' }), /ubicacion_tipo/);
});

test('rechaza executed negativo', () => {
  assert.throws(() => parseRegistro({ ...base, executed: -5 }), /Executed/);
});

test('rechaza actividad_id no entero', () => {
  assert.throws(() => parseRegistro({ ...base, actividad_id: 'abc' }), /actividad/);
});

test('rechaza estado_pos inválido', () => {
  assert.throws(() => parseRegistro({ ...base, estado_pos: 'ON' }), /estado_pos/);
});

test('rechaza status_act inválido', () => {
  assert.throws(() => parseRegistro({ ...base, status_act: 'Empezado' }), /status_act/);
});

test('rechaza ubicacion vacía', () => {
  assert.throws(() => parseRegistro({ ...base, ubicacion: '  ' }), /ubicacion/);
});

test('planned y productivity son opcionales (null si ausentes)', () => {
  const r = parseRegistro(base);
  assert.equal(r.planned, null);
  assert.equal(r.productivity, null);
});

test('nave_id se normaliza a número o null', () => {
  assert.equal(parseRegistro({ ...base, nave_id: '7' }).nave_id, 7);
  assert.equal(parseRegistro({ ...base, nave_id: '' }).nave_id, null);
});
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run (desde `operaciones-api/`):
```
npm test
```
Expected: FAIL — `Cannot find module '.../tallyman.validator.js'`.

- [ ] **Step 3: Implementar el validador mínimo**

Crear `operaciones-api/src/modules/tallyman/tallyman.validator.js`:

```js
import { ApiError } from '../../utils/ApiError.js';

const UBIC_TIPOS = ['BERTH', 'YARD'];
const ESTADOS_POS = ['ACTIVE', 'INACTIVE', 'FINISH'];
const STATUS_ACT = ['Inicio', 'En Proceso', 'Culminado'];

// número >= 0 o null (acepta strings numéricos). Lanza si es inválido.
function numOpt(v, etq) {
  if (v === undefined || v === null || v === '') return null;
  const n = Number(v);
  if (!Number.isFinite(n) || n < 0) throw new ApiError(400, `${etq} debe ser un número ≥ 0.`);
  return n;
}

function entOpt(v) {
  if (v === undefined || v === null || v === '') return null;
  const n = Number(v);
  if (!Number.isInteger(n) || n <= 0) return null;
  return n;
}

// Valida y normaliza el cuerpo de un registro de actividad tallyman.
export function parseRegistro(body) {
  const fecha_turno = String(body.fecha_turno ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha_turno)) {
    throw new ApiError(400, 'fecha_turno debe ser YYYY-MM-DD.');
  }
  const turno = String(body.turno ?? '').trim();
  if (!turno) throw new ApiError(400, 'turno es obligatorio.');

  const ubicacion_tipo = String(body.ubicacion_tipo ?? '').trim().toUpperCase();
  if (!UBIC_TIPOS.includes(ubicacion_tipo)) {
    throw new ApiError(400, `ubicacion_tipo inválido. Use: ${UBIC_TIPOS.join(' o ')}.`);
  }
  const ubicacion = String(body.ubicacion ?? '').trim();
  if (!ubicacion) throw new ApiError(400, 'ubicacion es obligatoria.');

  const actividad_id = Number(body.actividad_id);
  if (!Number.isInteger(actividad_id) || actividad_id <= 0) {
    throw new ApiError(400, 'actividad_id inválido.');
  }

  const estado_pos = String(body.estado_pos ?? 'ACTIVE').trim().toUpperCase();
  if (!ESTADOS_POS.includes(estado_pos)) {
    throw new ApiError(400, `estado_pos inválido. Use: ${ESTADOS_POS.join(', ')}.`);
  }
  const status_act = String(body.status_act ?? 'Inicio').trim();
  if (!STATUS_ACT.includes(status_act)) {
    throw new ApiError(400, `status_act inválido. Use: ${STATUS_ACT.join(', ')}.`);
  }

  const executed = numOpt(body.executed, 'Executed');
  if (executed === null) throw new ApiError(400, 'Executed es obligatorio.');

  return {
    fecha_turno,
    turno,
    ubicacion_tipo,
    ubicacion,
    nave_id: entOpt(body.nave_id),
    actividad_id,
    estado_pos,
    status_act,
    planned: numOpt(body.planned, 'Planned'),
    executed,
    productivity: numOpt(body.productivity, 'Productivity'),
    details: String(body.details ?? '').trim() || null,
    coord_entrante: String(body.coord_entrante ?? '').trim() || null,
    coord_saliente: String(body.coord_saliente ?? '').trim() || null,
  };
}
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Run (desde `operaciones-api/`):
```
npm test
```
Expected: PASS — 10 tests ok.

- [ ] **Step 5: Commit**

```bash
git add operaciones-api/src/modules/tallyman/tallyman.validator.js operaciones-api/test/tallyman.validator.test.js
git commit -m "feat(tallyman): validador de registros con pruebas unitarias"
```

---

## Task 3: Modelo de datos (acceso a BD)

**Files:**
- Create: `operaciones-api/src/modules/tallyman/tallyman.model.js`

Sin test unitario propio (requiere BD; se cubre por la prueba de humo de Task 6). Todo el SQL parametrizado vive aquí, igual que `naves.model.js`.

- [ ] **Step 1: Implementar el modelo completo**

Crear `operaciones-api/src/modules/tallyman/tallyman.model.js`:

```js
import { pool } from '../../config/db.js';

export const TallymanModel = {
  // Catálogo de actividades activas.
  async listarActividades() {
    const [rows] = await pool.query(
      'SELECT id, nombre FROM tallyman_actividades WHERE activo = 1 ORDER BY orden, nombre',
    );
    return rows;
  },

  async actividadExiste(id) {
    const [rows] = await pool.query(
      'SELECT id FROM tallyman_actividades WHERE id = ? AND activo = 1 LIMIT 1',
      [id],
    );
    return rows.length > 0;
  },

  // Suma de executed de turnos ANTERIORES (estrictamente) para una combinación
  // nave+actividad+ubicación. Se usa para el acumulado previo (sin el actual).
  async executedPrevio({ nave_id, actividad_id, ubicacion, fecha_turno, turno }) {
    const [rows] = await pool.query(
      `SELECT COALESCE(SUM(executed), 0) AS prev
         FROM tallyman_registros
        WHERE actividad_id = ? AND ubicacion = ?
          AND ((nave_id <=> ?))
          AND (fecha_turno < ? OR (fecha_turno = ? AND turno <> ?))`,
      [actividad_id, ubicacion, nave_id ?? null, fecha_turno, fecha_turno, turno],
    );
    return Number(rows[0]?.prev) || 0;
  },

  async crearRegistro(r) {
    const [res] = await pool.query(
      `INSERT INTO tallyman_registros
         (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, actividad_id,
          estado_pos, status_act, planned, executed, productivity, details,
          coord_entrante, coord_saliente, registrado_por)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        r.fecha_turno, r.turno, r.ubicacion_tipo, r.ubicacion, r.nave_id ?? null,
        r.actividad_id, r.estado_pos, r.status_act, r.planned ?? null, r.executed,
        r.productivity ?? null, r.details ?? null, r.coord_entrante ?? null,
        r.coord_saliente ?? null, r.registrado_por,
      ],
    );
    return this.obtenerRegistro(res.insertId);
  },

  async obtenerRegistro(id) {
    const [rows] = await pool.query(
      `SELECT r.*, a.nombre AS actividad, n.nombre AS nave
         FROM tallyman_registros r
         JOIN tallyman_actividades a ON a.id = r.actividad_id
         LEFT JOIN naves n ON n.id = r.nave_id
        WHERE r.id = ?`,
      [id],
    );
    return rows[0] || null;
  },

  async listarPorTurno(fecha_turno, turno) {
    const [rows] = await pool.query(
      `SELECT r.*, a.nombre AS actividad, n.nombre AS nave
         FROM tallyman_registros r
         JOIN tallyman_actividades a ON a.id = r.actividad_id
         LEFT JOIN naves n ON n.id = r.nave_id
        WHERE r.fecha_turno = ? AND r.turno = ?
        ORDER BY r.ubicacion_tipo, r.ubicacion, r.id`,
      [fecha_turno, turno],
    );
    return rows;
  },

  async editarRegistro(id, r) {
    await pool.query(
      `UPDATE tallyman_registros
          SET ubicacion_tipo = ?, ubicacion = ?, nave_id = ?, actividad_id = ?,
              estado_pos = ?, status_act = ?, planned = ?, executed = ?,
              productivity = ?, details = ?, coord_entrante = ?, coord_saliente = ?
        WHERE id = ?`,
      [
        r.ubicacion_tipo, r.ubicacion, r.nave_id ?? null, r.actividad_id,
        r.estado_pos, r.status_act, r.planned ?? null, r.executed,
        r.productivity ?? null, r.details ?? null, r.coord_entrante ?? null,
        r.coord_saliente ?? null, id,
      ],
    );
    return this.obtenerRegistro(id);
  },

  async eliminarRegistro(id) {
    await pool.query('DELETE FROM tallyman_registros WHERE id = ?', [id]);
  },

  // Incidencia del turno: upsert lógico (una por fecha+turno; reemplaza la previa).
  async guardarIncidencia({ fecha_turno, turno, hubo, detalle, registrado_por }) {
    await pool.query(
      'DELETE FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ?',
      [fecha_turno, turno],
    );
    const [res] = await pool.query(
      `INSERT INTO tallyman_incidencias (fecha_turno, turno, hubo, detalle, registrado_por)
       VALUES (?, ?, ?, ?, ?)`,
      [fecha_turno, turno, hubo ? 1 : 0, detalle ?? null, registrado_por],
    );
    const [rows] = await pool.query('SELECT * FROM tallyman_incidencias WHERE id = ?', [res.insertId]);
    return rows[0] || null;
  },

  async obtenerIncidencia(fecha_turno, turno) {
    const [rows] = await pool.query(
      'SELECT * FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ? LIMIT 1',
      [fecha_turno, turno],
    );
    return rows[0] || null;
  },
};
```

Nota: `nave_id <=> ?` es el operador null-seguro de MySQL (compara NULL = NULL como verdadero), necesario porque una actividad de Yard puede no tener nave.

- [ ] **Step 2: Verificar que el archivo carga sin error de sintaxis**

Run (desde `operaciones-api/`):
```
node --check src/modules/tallyman/tallyman.model.js
```
Expected: sin salida (exit 0).

- [ ] **Step 3: Commit**

```bash
git add operaciones-api/src/modules/tallyman/tallyman.model.js
git commit -m "feat(tallyman): modelo de acceso a datos (registros, acumulado, incidencias)"
```

---

## Task 4: Controlador

**Files:**
- Create: `operaciones-api/src/modules/tallyman/tallyman.controller.js`

- [ ] **Step 1: Implementar el controlador**

Crear `operaciones-api/src/modules/tallyman/tallyman.controller.js`:

```js
import { asyncHandler } from '../../utils/asyncHandler.js';
import { ApiError } from '../../utils/ApiError.js';
import { TallymanModel } from './tallyman.model.js';
import { parseRegistro } from './tallyman.validator.js';

// Calcula acumulado y pendiente de un registro a partir del executed previo.
function conResumen(reg, prev) {
  const planned = reg.planned != null ? Number(reg.planned) : null;
  const accumulated = prev + Number(reg.executed);
  const pending = planned != null ? Math.max(planned - accumulated, 0) : null;
  const porcentaje = planned && planned > 0
    ? Math.min(Math.round((accumulated / planned) * 1000) / 10, 100)
    : null;
  return { ...reg, accumulated, pending, porcentaje };
}

// GET /tallyman/actividades
export const listarActividades = asyncHandler(async (_req, res) => {
  const data = await TallymanModel.listarActividades();
  res.json({ success: true, count: data.length, data });
});

// GET /tallyman/registros?fecha=&turno=
export const listarRegistros = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
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
});

// POST /tallyman/registros   (Coordinador)
export const crearRegistro = asyncHandler(async (req, res) => {
  const r = parseRegistro(req.body);
  if (!(await TallymanModel.actividadExiste(r.actividad_id))) {
    throw new ApiError(400, 'La actividad no existe o está inactiva.');
  }
  const creado = await TallymanModel.crearRegistro({ ...r, registrado_por: req.user.name });
  const prev = await TallymanModel.executedPrevio({
    nave_id: creado.nave_id, actividad_id: creado.actividad_id, ubicacion: creado.ubicacion,
    fecha_turno: creado.fecha_turno, turno: creado.turno,
  });
  res.status(201).json({ success: true, data: conResumen(creado, prev) });
});

// PUT /tallyman/registros/:id
export const editarRegistro = asyncHandler(async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isInteger(id) || id <= 0) throw new ApiError(400, 'ID inválido.');
  const existe = await TallymanModel.obtenerRegistro(id);
  if (!existe) throw new ApiError(404, 'Registro no encontrado.');
  const r = parseRegistro(req.body);
  if (!(await TallymanModel.actividadExiste(r.actividad_id))) {
    throw new ApiError(400, 'La actividad no existe o está inactiva.');
  }
  const upd = await TallymanModel.editarRegistro(id, r);
  const prev = await TallymanModel.executedPrevio({
    nave_id: upd.nave_id, actividad_id: upd.actividad_id, ubicacion: upd.ubicacion,
    fecha_turno: upd.fecha_turno, turno: upd.turno,
  });
  res.json({ success: true, data: conResumen(upd, prev) });
});

// DELETE /tallyman/registros/:id
export const eliminarRegistro = asyncHandler(async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isInteger(id) || id <= 0) throw new ApiError(400, 'ID inválido.');
  const existe = await TallymanModel.obtenerRegistro(id);
  if (!existe) throw new ApiError(404, 'Registro no encontrado.');
  await TallymanModel.eliminarRegistro(id);
  res.json({ success: true });
});

// POST /tallyman/incidencias   (Coordinador)
export const guardarIncidencia = asyncHandler(async (req, res) => {
  const fecha_turno = String(req.body.fecha_turno ?? '').trim();
  const turno = String(req.body.turno ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha_turno) || !turno) {
    throw new ApiError(400, 'fecha_turno (YYYY-MM-DD) y turno son obligatorios.');
  }
  const hubo = !!req.body.hubo;
  const detalle = String(req.body.detalle ?? '').trim() || null;
  if (hubo && !detalle) throw new ApiError(400, 'Si hubo incidente, el detalle es obligatorio.');
  const data = await TallymanModel.guardarIncidencia({
    fecha_turno, turno, hubo, detalle, registrado_por: req.user.name,
  });
  res.status(201).json({ success: true, data });
});

// GET /tallyman/incidencias?fecha=&turno=
export const obtenerIncidencia = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
  const data = await TallymanModel.obtenerIncidencia(fecha, turno);
  res.json({ success: true, data });
});
```

- [ ] **Step 2: Verificar sintaxis**

Run (desde `operaciones-api/`):
```
node --check src/modules/tallyman/tallyman.controller.js
```
Expected: sin salida (exit 0).

- [ ] **Step 3: Commit**

```bash
git add operaciones-api/src/modules/tallyman/tallyman.controller.js
git commit -m "feat(tallyman): controlador con cálculo de acumulado/pendiente"
```

---

## Task 5: Rutas + montaje en la app

**Files:**
- Create: `operaciones-api/src/modules/tallyman/tallyman.routes.js`
- Modify: `operaciones-api/src/app.js`

- [ ] **Step 1: Crear las rutas**

Crear `operaciones-api/src/modules/tallyman/tallyman.routes.js`:

```js
import { Router } from 'express';
import { requireRole } from '../../middlewares/auth.js';
import {
  listarActividades, listarRegistros, crearRegistro, editarRegistro,
  eliminarRegistro, guardarIncidencia, obtenerIncidencia,
} from './tallyman.controller.js';

const router = Router();
const OPERATIVOS = ['Administrador', 'Supervisor', 'Coordinador'];

// Catálogo de actividades (para selects del front)
router.get('/tallyman/actividades', requireRole(...OPERATIVOS), listarActividades);

// Registros del turno (lectura): roles operativos
router.get('/tallyman/registros', requireRole(...OPERATIVOS), listarRegistros);

// Crear registro: Coordinador
router.post('/tallyman/registros', requireRole('Coordinador'), crearRegistro);

// Editar/eliminar: Coordinador (su turno) o Admin/Supervisor (corrección)
router.put('/tallyman/registros/:id', requireRole(...OPERATIVOS), editarRegistro);
router.delete('/tallyman/registros/:id', requireRole(...OPERATIVOS), eliminarRegistro);

// Incidencias del turno
router.get('/tallyman/incidencias', requireRole(...OPERATIVOS), obtenerIncidencia);
router.post('/tallyman/incidencias', requireRole('Coordinador'), guardarIncidencia);

export default router;
```

- [ ] **Step 2: Montar en app.js**

Modificar `operaciones-api/src/app.js`. Tras la línea `import camposRoutes ...`, añadir:

```js
import tallymanRoutes from './modules/tallyman/tallyman.routes.js';
```

Y tras `app.use('/api/operaciones', camposRoutes);`, añadir:

```js
  app.use('/api/operaciones', tallymanRoutes);
```

- [ ] **Step 3: Verificar que la app arranca sin error**

Run (desde `operaciones-api/`):
```
node --check src/app.js
```
Expected: sin salida (exit 0).

- [ ] **Step 4: Commit**

```bash
git add operaciones-api/src/modules/tallyman/tallyman.routes.js operaciones-api/src/app.js
git commit -m "feat(tallyman): rutas y montaje en la app Express"
```

---

## Task 6: Ampliar el allow-list del proxy + prueba de humo end-to-end

**Files:**
- Modify: `api/operaciones_proxy.php` (línea 29)

- [ ] **Step 1: Ampliar el regex del proxy**

En `api/operaciones_proxy.php`, reemplazar exactamente:

```php
    if (!preg_match('#^(naves|tipos-nave)(/[A-Za-z0-9_\-]+)*$#', $path)) {
```

por:

```php
    if (!preg_match('#^(naves|tipos-nave|tallyman)(/[A-Za-z0-9_\-]+)*$#', $path)) {
```

- [ ] **Step 2: Arrancar la API Node en segundo plano**

Run (desde `operaciones-api/`, requiere `.env` con DB_NAME=operaciones ya configurado):
```
npm start
```
(Dejar corriendo; usar otra terminal para los curl.)
Expected: log de arranque en `:4000` sin errores de conexión a BD.

- [ ] **Step 3: Probar el catálogo de actividades (directo a Node, con headers simulados)**

Run:
```
curl -s -H "x-user-role: Coordinador" -H "x-user-name: Test" http://127.0.0.1:4000/api/operaciones/tallyman/actividades
```
Expected: JSON `{"success":true,"count":21,"data":[...]}`.

- [ ] **Step 4: Probar la creación de un registro y su acumulado**

Run:
```
curl -s -X POST -H "x-user-role: Coordinador" -H "x-user-name: JP" -H "Content-Type: application/json" -d "{\"fecha_turno\":\"2026-06-01\",\"turno\":\"Noche\",\"ubicacion_tipo\":\"BERTH\",\"ubicacion\":\"Berth 04\",\"actividad_id\":1,\"planned\":1954,\"executed\":831,\"status_act\":\"Inicio\"}" http://127.0.0.1:4000/api/operaciones/tallyman/registros
```
Expected: JSON con `"executed":831`, `"accumulated":831`, `"pending":1123`, `"porcentaje":42.5`.

- [ ] **Step 5: Verificar el filtro por turno**

Run:
```
curl -s -H "x-user-role: Coordinador" -H "x-user-name: JP" "http://127.0.0.1:4000/api/operaciones/tallyman/registros?fecha=2026-06-01&turno=Noche"
```
Expected: JSON con `count >= 1` incluyendo el registro creado.

- [ ] **Step 6: Limpiar el registro de prueba**

Run (sustituir `<ID>` por el id devuelto en Step 4):
```
curl -s -X DELETE -H "x-user-role: Supervisor" -H "x-user-name: Test" http://127.0.0.1:4000/api/operaciones/tallyman/registros/<ID>
```
Expected: `{"success":true}`.

- [ ] **Step 7: Commit**

```bash
git add api/operaciones_proxy.php
git commit -m "feat(tallyman): habilitar ruta tallyman en el proxy de Operaciones"
```

---

## Task 7: Endpoint PHP del turno vigente

**Files:**
- Create: `includes/tallyman_turno.php`

El backend Node no recalcula el turno; lo resuelve PHP (que tiene `obtener_turno_actual`). Este endpoint pequeño lo expone al front.

- [ ] **Step 1: Crear el endpoint**

Crear `includes/tallyman_turno.php`:

```php
<?php
/* Devuelve el turno vigente (fecha + jornada) para el front del tallyman.
   Reutiliza la lógica central de includes/turno.php. */
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/db.php');
require_once(__DIR__ . '/turno.php');

header('Content-Type: application/json; charset=utf-8');
api_require_operaciones(); // 401/403 según sesión y rol

$t = obtener_turno_actual($conn);
if (!$t) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No hay jornada vigente configurada.']);
    exit;
}
echo json_encode([
    'success' => true,
    'data' => [
        'fecha'   => $t['fecha'],
        'turno'   => $t['codigo'],     // código de jornada (ej. D/N/U)
        'nombre'  => $t['nombre'],
        'label'   => $t['label'],
    ],
]);
```

Nota: verificar que `$conn` es el nombre de la conexión mysqli que expone `includes/db.php` (es el usado por `turno.php`). Si difiere, ajustar.

- [ ] **Step 2: Verificar sintaxis PHP**

Run:
```
& "C:\xampp2026\php\php.exe" -l "C:\xampp2026\htdocs\Estiba_Turno\includes\tallyman_turno.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/tallyman_turno.php
git commit -m "feat(tallyman): endpoint PHP del turno vigente"
```

---

## Task 8: Página de registro (frontend)

**Files:**
- Create: `pages/tallyman.php`

Página con el patrón de la app (sidebar+header, hero navy, `window.OP`). Lista dinámica de actividades de Muelle y Patio, incidencias, y cálculo en vivo de pending. Guarda vía proxy.

- [ ] **Step 1: Crear la página**

Crear `pages/tallyman.php` con esta estructura (HTML+CSS+JS inline). El JS usa `OP.opApi` (de `js/operaciones.js`) y carga el turno desde `includes/tallyman_turno.php`.

```php
<?php
require_once('../includes/auth.php');
require_operaciones();
$rol = $_SESSION['user_rol'] ?? '';
$canRegistrar = ($rol === 'Coordinador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registro de actividad · Tallyman</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <style>
    .tm-wrap { --navy:#002b5c; --navy7:#013a78; --deck:#f1f5f9; --line:#e2e8f0; --lineb:#cbd5e1;
      --ink:#0b1f3a; --mute:#64748b; --faint:#94a3b8; --mono:ui-monospace,Consolas,monospace;
      display:flex; flex-direction:column; gap:18px; font-family:'DM Sans',system-ui,sans-serif; color:var(--ink); }
    .tm-wrap *,.tm-wrap *::before,.tm-wrap *::after{box-sizing:border-box;}
    .tm-hero{background:linear-gradient(155deg,#001b3a,#002b5c 45%,#013a78);color:#fff;border-radius:20px;padding:22px 28px;}
    .tm-hero h1{margin:6px 0 4px;font-size:22px;font-weight:700;}
    .tm-hero p{margin:0;color:rgba(255,255,255,.72);font-size:13px;}
    .tm-turno{display:inline-flex;gap:8px;align-items:center;margin-top:10px;padding:6px 12px;border-radius:999px;
      background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);font-size:12px;font-weight:600;}
    .tm-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 20px;}
    .tm-card h2{margin:0 0 14px;font-size:15px;font-weight:700;color:var(--navy);display:flex;justify-content:space-between;align-items:center;}
    .tm-btn{display:inline-flex;align-items:center;gap:7px;cursor:pointer;padding:9px 15px;border-radius:10px;
      border:1px solid var(--lineb);background:#fff;color:var(--ink);font:inherit;font-size:13px;font-weight:600;}
    .tm-btn:hover{background:var(--deck);}
    .tm-btn.primary{background:var(--navy);color:#fff;border-color:var(--navy);}
    .tm-btn.primary:hover{background:var(--navy7);}
    .tm-row{border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px;background:#fcfdfe;}
    .tm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;}
    .tm-f label{display:block;font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--mute);margin-bottom:5px;}
    .tm-f input,.tm-f select,.tm-f textarea{width:100%;padding:9px 11px;border:1.5px solid var(--lineb);border-radius:9px;font:inherit;font-size:13.5px;color:var(--ink);background:#fff;outline:none;}
    .tm-f input:focus,.tm-f select:focus,.tm-f textarea:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(0,43,92,.12);}
    .tm-calc{display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;padding:10px 12px;border-radius:10px;background:#eef4fb;border:1px solid var(--lineb);font-size:12.5px;}
    .tm-calc b{font-family:var(--mono);color:var(--navy);}
    .tm-del{cursor:pointer;border:1px solid #fecdca;background:#fef3f2;color:#b42318;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:600;}
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
      <div class="tm-wrap">
        <section class="tm-hero">
          <h1>Registro de actividad de turno</h1>
          <p>Registra lo realizado en tu turno por muelle y patio. El total planeado se hereda de la nave; tú solo ingresas lo ejecutado.</p>
          <span class="tm-turno" id="tmTurno">Cargando turno…</span>
        </section>

        <section class="tm-card">
          <h2>Actividad en Muelle (Berth)
            <button class="tm-btn primary" id="addBerth" <?= $canRegistrar?'':'disabled' ?>>+ Añadir muelle</button>
          </h2>
          <div id="berthList"></div>
        </section>

        <section class="tm-card">
          <h2>Actividad en Patio (Yard)
            <button class="tm-btn primary" id="addYard" <?= $canRegistrar?'':'disabled' ?>>+ Añadir patio</button>
          </h2>
          <div id="yardList"></div>
        </section>

        <section class="tm-card">
          <h2>Incidencias</h2>
          <div class="tm-f"><label>¿Hubo incidente, demora o problema?</label>
            <select id="incHubo"><option value="0">No</option><option value="1">Sí</option></select>
          </div>
          <div class="tm-f" id="incDetWrap" style="margin-top:12px;display:none;">
            <label>Detalle del incidente</label><textarea id="incDet" rows="3"></textarea>
          </div>
          <div style="margin-top:14px;text-align:right;">
            <button class="tm-btn primary" id="saveInc" <?= $canRegistrar?'':'disabled' ?>>Guardar incidencias</button>
          </div>
        </section>
      </div>
    </main>
  </div>
</div>

<script src="../js/operaciones.js"></script>
<script>
(function(){
  'use strict';
  var OP = window.OP, $ = OP.$;
  var turno = null;          // {fecha, turno, label}
  var actividades = [];      // catálogo
  var naves = [];            // naves activas de Operaciones

  var UBICS_BERTH = ['Berth 01','Berth 02','Berth 03','Berth 04','Berth 3.5','Berth 4.5'];
  var ESTADOS_POS = ['ACTIVE','INACTIVE','FINISH'];
  var STATUS_ACT  = ['Inicio','En Proceso','Culminado'];

  function plannedDeNave(nave){
    if(!nave || !nave.datos_adicionales) return '';
    var d = nave.datos_adicionales;
    return d.cantidad_total || d.teus || d.vehiculos || '';
  }
  function opt(v, label, sel){ return '<option value="'+OP.esc(v)+'"'+(sel===v?' selected':'')+'>'+OP.esc(label||v)+'</option>'; }

  // Construye una fila de registro (tipo = 'BERTH' | 'YARD').
  function fila(tipo){
    var wrap = document.createElement('div');
    wrap.className = 'tm-row';
    var ubicSel = tipo==='BERTH'
      ? '<select data-k="ubicacion">'+UBICS_BERTH.map(function(u){return opt(u);}).join('')+'</select>'
      : '<input data-k="ubicacion" value="Yard" />';
    wrap.innerHTML =
      '<div class="tm-grid">'+
        '<div class="tm-f"><label>Ubicación</label>'+ubicSel+'</div>'+
        '<div class="tm-f"><label>Nave</label><select data-k="nave_id"><option value="">—</option>'+
           naves.map(function(n){return opt(String(n.id), n.nombre);}).join('')+'</select></div>'+
        '<div class="tm-f"><label>Actividad</label><select data-k="actividad_id">'+
           actividades.map(function(a){return opt(String(a.id), a.nombre);}).join('')+'</select></div>'+
        '<div class="tm-f"><label>Estado posición</label><select data-k="estado_pos">'+
           ESTADOS_POS.map(function(s){return opt(s);}).join('')+'</select></div>'+
        '<div class="tm-f"><label>Status</label><select data-k="status_act">'+
           STATUS_ACT.map(function(s){return opt(s);}).join('')+'</select></div>'+
        '<div class="tm-f"><label>Planned (heredado)</label><input data-k="planned" readonly placeholder="—"/></div>'+
        '<div class="tm-f"><label>Executed (tu turno)</label><input data-k="executed" type="number" min="0" step="0.01"/></div>'+
        (tipo==='BERTH'?'<div class="tm-f"><label>Productividad (mov/h)</label><input data-k="productivity" type="number" min="0" step="0.01"/></div>':'')+
        '<div class="tm-f" style="grid-column:1/-1;"><label>Detalles</label><textarea data-k="details" rows="2"></textarea></div>'+
      '</div>'+
      '<div class="tm-calc"><span>Pendiente: <b data-calc="pending">—</b></span><span>Avance: <b data-calc="pct">—</b></span></div>'+
      '<div style="margin-top:10px;text-align:right;">'+
        '<button class="tm-del">Quitar</button> '+
        '<button class="tm-btn primary tm-save">Guardar actividad</button>'+
      '</div>';

    var naveSel = wrap.querySelector('[data-k=nave_id]');
    var plannedIn = wrap.querySelector('[data-k=planned]');
    var execIn = wrap.querySelector('[data-k=executed]');
    function recalc(){
      var planned = Number(plannedIn.value)||0, exec = Number(execIn.value)||0;
      var pend = planned>0 ? Math.max(planned-exec,0) : null;
      wrap.querySelector('[data-calc=pending]').textContent = pend==null?'—':pend.toFixed(2);
      wrap.querySelector('[data-calc=pct]').textContent = planned>0?Math.min(exec/planned*100,100).toFixed(1)+'%':'—';
    }
    naveSel.addEventListener('change', function(){
      var n = naves.find(function(x){return String(x.id)===naveSel.value;});
      plannedIn.value = n ? plannedDeNave(n) : '';
      recalc();
    });
    execIn.addEventListener('input', recalc);
    wrap.querySelector('.tm-del').addEventListener('click', function(){ wrap.remove(); });
    wrap.querySelector('.tm-save').addEventListener('click', function(){ guardarFila(wrap, tipo); });
    return wrap;
  }

  function leerFila(wrap, tipo){
    var get = function(k){ var el=wrap.querySelector('[data-k='+k+']'); return el?el.value:''; };
    return {
      fecha_turno: turno.fecha, turno: turno.turno,
      ubicacion_tipo: tipo, ubicacion: get('ubicacion'),
      nave_id: get('nave_id') || null, actividad_id: Number(get('actividad_id')),
      estado_pos: get('estado_pos'), status_act: get('status_act'),
      planned: get('planned') || null, executed: Number(get('executed')||0),
      productivity: get('productivity') || null, details: get('details') || null
    };
  }

  async function guardarFila(wrap, tipo){
    if(!turno){ OP.toast('Turno no disponible','error'); return; }
    var body = leerFila(wrap, tipo);
    if(!body.executed && body.executed!==0){ OP.toast('Ingresa el ejecutado','error'); return; }
    try{
      var r = await OP.opApi('tallyman/registros', {method:'POST', body:body});
      wrap.querySelector('[data-calc=pending]').textContent = r.data.pending==null?'—':Number(r.data.pending).toFixed(2);
      wrap.querySelector('[data-calc=pct]').textContent = r.data.porcentaje==null?'—':r.data.porcentaje+'%';
      OP.toast('Actividad guardada','success');
    }catch(e){ OP.toast(e.message,'error'); }
  }

  async function guardarInc(){
    if(!turno) return;
    try{
      await OP.opApi('tallyman/incidencias', {method:'POST', body:{
        fecha_turno:turno.fecha, turno:turno.turno,
        hubo: $('incHubo').value==='1', detalle: $('incDet').value.trim()
      }});
      OP.toast('Incidencias guardadas','success');
    }catch(e){ OP.toast(e.message,'error'); }
  }

  async function cargarTurno(){
    var r = await fetch('../includes/tallyman_turno.php', {cache:'no-store'});
    var d = await r.json();
    if(!d.success) throw new Error(d.error||'Sin turno');
    turno = d.data;
    $('tmTurno').textContent = 'Turno: '+turno.label+' · '+turno.fecha;
  }

  async function init(){
    try{
      await cargarTurno();
      var a = await OP.opApi('tallyman/actividades'); actividades = a.data;
      var n = await OP.opApi('naves'); naves = n.data;
    }catch(e){ OP.toast(e.message,'error'); return; }
    $('addBerth').addEventListener('click', function(){ $('berthList').appendChild(fila('BERTH')); });
    $('addYard').addEventListener('click', function(){ $('yardList').appendChild(fila('YARD')); });
    $('incHubo').addEventListener('change', function(){ $('incDetWrap').style.display = this.value==='1'?'block':'none'; });
    $('saveInc').addEventListener('click', guardarInc);
  }
  init();
})();
</script>
</body>
</html>
```

- [ ] **Step 2: Verificar sintaxis PHP**

Run:
```
& "C:\xampp2026\php\php.exe" -l "C:\xampp2026\htdocs\Estiba_Turno\pages\tallyman.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verificación manual en navegador**

Con la API Node corriendo (`npm start` en `operaciones-api/`) y sesión iniciada como Coordinador:
1. Abrir `http://localhost/Estiba_Turno/pages/tallyman.php`.
2. Verificar que el chip de turno muestra fecha+jornada vigentes.
3. Pulsar "+ Añadir muelle" → aparece una fila; elegir una nave → el campo Planned se autocompleta.
4. Escribir Executed → "Pendiente" y "Avance" se recalculan en vivo.
5. "Guardar actividad" → toast "Actividad guardada".
6. En Incidencias, elegir "Sí" → aparece el textarea; "Guardar incidencias" → toast de éxito.

Expected: todos los pasos funcionan sin errores en consola.

- [ ] **Step 4: Commit**

```bash
git add pages/tallyman.php
git commit -m "feat(tallyman): página de registro de actividad de turno"
```

---

## Task 9: Enlace en el sidebar

**Files:**
- Modify: `includes/sidebar.php`

- [ ] **Step 1: Localizar el patrón de enlaces del sidebar**

Run:
```
Grep pattern="operaciones" path="includes/sidebar.php" output_mode="content" -n=true
```
Expected: ver cómo se construyen los `<a>` del menú (clase, uso de `$sb_base`, control por rol).

- [ ] **Step 2: Añadir el enlace siguiendo ese patrón**

Añadir un enlace a `pages/tallyman.php` en la sección de Operaciones del menú, visible para roles operativos (Administrador/Supervisor/Coordinador), replicando exactamente la estructura `<a>` existente (clase activa, icono SVG inline si los demás lo usan, `$sb_base`). Texto del enlace: "Registro tallyman".

- [ ] **Step 3: Verificar sintaxis PHP**

Run:
```
& "C:\xampp2026\php\php.exe" -l "C:\xampp2026\htdocs\Estiba_Turno\includes\sidebar.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add includes/sidebar.php
git commit -m "feat(tallyman): enlace de registro en el sidebar"
```

---

## Cierre de Fase 1

Al terminar las 9 tareas:
- La BD `operaciones` tiene las 3 tablas tallyman con el catálogo sembrado.
- La API Node expone `/api/operaciones/tallyman/*` con cálculo de acumulado/pendiente, probado por curl y por el validador unitario.
- El proxy permite la ruta `tallyman`.
- `pages/tallyman.php` permite al coordinador registrar actividades de muelle/patio e incidencias, con planned heredado y pending en vivo.

**Siguiente:** Fase 2 (vista de relevo con gráficos) — su propio spec ya está esbozado en el documento de diseño; se planificará por separado.
