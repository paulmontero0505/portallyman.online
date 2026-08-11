# Fase 2 · Campos dinámicos por tipo de nave — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir al Administrador definir campos adicionales por tipo de nave (catálogo) y que cada nave guarde sus valores en JSON validados contra ese catálogo.

**Architecture:** Catálogo `campos_tipo_nave` + columna `naves.datos_adicionales` (JSON). Validador puro `validarDatos()` aplica reglas del catálogo. Capas routes→controller→model como en Fase 1. Solo backend, dentro de `operaciones-api/`.

**Tech Stack:** Node ESM, Express, mysql2/promise, tests con `node --test` (built-in, sin deps nuevas).

> **Nota git:** el proyecto NO es repo git → los pasos de "commit" se reemplazan por checkpoints de verificación.

---

## File Structure

```
operaciones-api/
  sql/002_campos_dinamicos.sql                 (nuevo)
  src/modules/operaciones/
    campos.validator.js        (nuevo)  validarDatos() — núcleo
    campos.validator.test.js   (nuevo)  node --test
    campos.model.js            (nuevo)  SQL de campos_tipo_nave
    campos.controller.js       (nuevo)  CRUD definiciones (Admin)
    campos.routes.js           (nuevo)  /tipos-nave/:tipoId/campos
    naves.model.js             (mod)    datos_adicionales en selects + crear + setDatos()
    naves.controller.js        (mod)    crear acepta datos; obtenerNave; actualizarDatos
    naves.routes.js            (mod)    GET /naves/:id ; PUT /naves/:id/datos
  src/app.js                   (mod)    monta campos.routes
  package.json                 (mod)    "test": "node --test"
```

---

## Task 1: Migración SQL

**Files:** Create `sql/002_campos_dinamicos.sql`.

- [ ] **Step 1: Escribir la migración**

```sql
USE operaciones;

CREATE TABLE IF NOT EXISTS campos_tipo_nave (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_nave_id  INT UNSIGNED NOT NULL,
  clave         VARCHAR(50)  NOT NULL,
  etiqueta      VARCHAR(100) NOT NULL,
  tipo_dato     ENUM('texto','numero','fecha','booleano','seleccion') NOT NULL DEFAULT 'texto',
  requerido     TINYINT(1)   NOT NULL DEFAULT 0,
  opciones      JSON         NULL,
  orden         INT          NOT NULL DEFAULT 0,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_campos_tipo_nave
    FOREIGN KEY (tipo_nave_id) REFERENCES tipos_nave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  UNIQUE KEY uq_campo_tipo_clave (tipo_nave_id, clave),
  KEY idx_campos_tipo (tipo_nave_id, activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE naves ADD COLUMN datos_adicionales JSON NULL AFTER estado;
```

- [ ] **Step 2: Aplicar y verificar**

Run: `/c/xampp2026/mysql/bin/mysql.exe -u root < operaciones-api/sql/002_campos_dinamicos.sql`
Luego: `... -e "SHOW COLUMNS FROM naves LIKE 'datos_adicionales'; SHOW TABLES LIKE 'campos_tipo_nave';"` (BD `operaciones`).
Expected: la columna y la tabla existen.

---

## Task 2: Validador (TDD) — núcleo de la fase

**Files:** Create `src/modules/operaciones/campos.validator.js`, `campos.validator.test.js`.

- [ ] **Step 1: Escribir el test que falla**

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validarDatos } from './campos.validator.js';

const campos = [
  { clave: 'teus',      tipo_dato: 'numero',    requerido: 1, opciones: null },
  { clave: 'naviera',   tipo_dato: 'texto',     requerido: 0, opciones: null },
  { clave: 'arribo',    tipo_dato: 'fecha',     requerido: 0, opciones: null },
  { clave: 'peligrosa', tipo_dato: 'booleano',  requerido: 0, opciones: null },
  { clave: 'bandera',   tipo_dato: 'seleccion', requerido: 0, opciones: ['PA', 'LR'] },
];

test('rechaza clave desconocida', () => {
  assert.throws(() => validarDatos(campos, { foo: 1 }), { status: 400 });
});
test('numero válido se normaliza a Number', () => {
  assert.equal(validarDatos(campos, { teus: '3500' }).teus, 3500);
});
test('numero inválido lanza 400', () => {
  assert.throws(() => validarDatos(campos, { teus: 'abc' }), { status: 400 });
});
test('fecha inválida lanza 400', () => {
  assert.throws(() => validarDatos(campos, { arribo: '2026-13-01' }), { status: 400 });
});
test('booleano coacciona "true" a true', () => {
  assert.equal(validarDatos(campos, { peligrosa: 'true' }).peligrosa, true);
});
test('seleccion fuera de opciones lanza 400', () => {
  assert.throws(() => validarDatos(campos, { bandera: 'XX' }), { status: 400 });
});
test('seleccion válida pasa', () => {
  assert.equal(validarDatos(campos, { bandera: 'PA' }).bandera, 'PA');
});
test('requireAll exige los requeridos', () => {
  assert.throws(() => validarDatos(campos, {}, { requireAll: true }), { status: 400 });
});
test('sin requireAll, requerido ausente se omite', () => {
  assert.deepEqual(validarDatos(campos, { naviera: 'MAERSK' }), { naviera: 'MAERSK' });
});
```

- [ ] **Step 2: Correr y ver fallar** → `npm test` → FAIL (módulo no existe).

- [ ] **Step 3: Implementar `campos.validator.js`**

```js
import { ApiError } from '../../utils/ApiError.js';

export const TIPOS = ['texto', 'numero', 'fecha', 'booleano', 'seleccion'];

function esFechaISO(v) {
  if (typeof v !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
  const d = new Date(v + 'T00:00:00');
  return !Number.isNaN(d.getTime()) && v === d.toISOString().slice(0, 10);
}

// Valida y normaliza `datos` contra las definiciones activas `campos`.
// requireAll: exige los campos `requerido`. Devuelve el objeto normalizado a guardar.
export function validarDatos(campos, datos, { requireAll = false } = {}) {
  const obj = datos && typeof datos === 'object' && !Array.isArray(datos) ? datos : {};
  const porClave = new Map(campos.map((c) => [c.clave, c]));

  for (const k of Object.keys(obj)) {
    if (!porClave.has(k)) throw new ApiError(400, `Campo desconocido: ${k}`);
  }

  const out = {};
  for (const c of campos) {
    const tiene = Object.prototype.hasOwnProperty.call(obj, c.clave);
    const v = obj[c.clave];
    const vacio = !tiene || v === null || v === undefined || v === '';

    if (vacio) {
      if (requireAll && c.requerido) throw new ApiError(400, `Campo requerido: ${c.clave}`);
      continue;
    }

    switch (c.tipo_dato) {
      case 'texto':
        out[c.clave] = String(v);
        break;
      case 'numero': {
        const n = Number(v);
        if (!Number.isFinite(n)) throw new ApiError(400, `Campo '${c.clave}' debe ser numérico.`);
        out[c.clave] = n;
        break;
      }
      case 'fecha':
        if (!esFechaISO(v)) throw new ApiError(400, `Campo '${c.clave}' debe ser fecha YYYY-MM-DD.`);
        out[c.clave] = v;
        break;
      case 'booleano':
        if (v === true || v === 1 || v === '1' || v === 'true') out[c.clave] = true;
        else if (v === false || v === 0 || v === '0' || v === 'false') out[c.clave] = false;
        else throw new ApiError(400, `Campo '${c.clave}' debe ser booleano.`);
        break;
      case 'seleccion': {
        const ops = Array.isArray(c.opciones) ? c.opciones : [];
        if (!ops.includes(v)) throw new ApiError(400, `Campo '${c.clave}': valor no permitido.`);
        out[c.clave] = v;
        break;
      }
      default:
        throw new ApiError(400, `Tipo de dato inválido en '${c.clave}'.`);
    }
  }
  return out;
}
```

- [ ] **Step 4: Agregar script de test** en `package.json`: `"test": "node --test"`.
- [ ] **Step 5: Correr** → `npm test` → 9 tests PASS.

---

## Task 3: Modelo de campos — `campos.model.js`

**Files:** Create `src/modules/operaciones/campos.model.js`.

- [ ] **Step 1: Implementar**

```js
import { pool } from '../../config/db.js';

export const CamposModel = {
  async listarPorTipo(tipoNaveId, { soloActivos = true } = {}) {
    const [rows] = await pool.query(
      `SELECT id, tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden, activo
         FROM campos_tipo_nave
        WHERE tipo_nave_id = ? ${soloActivos ? 'AND activo = 1' : ''}
        ORDER BY orden, id`,
      [tipoNaveId],
    );
    return rows;
  },

  async obtenerPorId(id) {
    const [rows] = await pool.query('SELECT * FROM campos_tipo_nave WHERE id = ?', [id]);
    return rows[0] || null;
  },

  async crear({ tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden }) {
    const [r] = await pool.query(
      `INSERT INTO campos_tipo_nave (tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [tipo_nave_id, clave, etiqueta, tipo_dato, requerido ? 1 : 0,
       opciones ? JSON.stringify(opciones) : null, orden ?? 0],
    );
    return this.obtenerPorId(r.insertId);
  },

  async actualizar(id, { etiqueta, tipo_dato, requerido, opciones, orden, activo }) {
    await pool.query(
      `UPDATE campos_tipo_nave
          SET etiqueta = ?, tipo_dato = ?, requerido = ?, opciones = ?, orden = ?, activo = ?
        WHERE id = ?`,
      [etiqueta, tipo_dato, requerido ? 1 : 0,
       opciones ? JSON.stringify(opciones) : null, orden ?? 0, activo ? 1 : 0, id],
    );
    return this.obtenerPorId(id);
  },

  async desactivar(id) {
    await pool.query('UPDATE campos_tipo_nave SET activo = 0 WHERE id = ?', [id]);
  },
};
```

> `opciones` y `datos_adicionales` son columnas JSON: mysql2 las devuelve ya parseadas (array/objeto) y al escribir se pasan con `JSON.stringify`.

- [ ] **Step 2: Checkpoint** — `node -e "import('./operaciones-api/src/modules/operaciones/campos.model.js').then(()=>console.log('ok'))"` (carga sin error de sintaxis; requiere import dinámico desde cwd correcto).

---

## Task 4: Controlador + rutas de campos

**Files:** Create `campos.controller.js`, `campos.routes.js`.

- [ ] **Step 1: `campos.controller.js`**

```js
import { asyncHandler } from '../../utils/asyncHandler.js';
import { ApiError } from '../../utils/ApiError.js';
import { CamposModel } from './campos.model.js';
import { NavesModel } from './naves.model.js';
import { TIPOS } from './campos.validator.js';

const CLAVE_RE = /^[a-z][a-z0-9_]*$/;

async function assertTipoExiste(tipoId) {
  if (!(await NavesModel.tipoExiste(tipoId))) throw new ApiError(404, 'Tipo de nave no encontrado.');
}

function validarDefinicion({ clave, etiqueta, tipo_dato, opciones }, { claveRequerida }) {
  if (claveRequerida && (!clave || !CLAVE_RE.test(clave))) {
    throw new ApiError(400, "La clave es obligatoria y debe ser [a-z][a-z0-9_]* (ej. 'teus').");
  }
  if (!etiqueta?.trim()) throw new ApiError(400, 'La etiqueta es obligatoria.');
  if (!TIPOS.includes(tipo_dato)) throw new ApiError(400, `tipo_dato inválido. Use: ${TIPOS.join(', ')}.`);
  if (tipo_dato === 'seleccion' && (!Array.isArray(opciones) || opciones.length === 0)) {
    throw new ApiError(400, "tipo_dato 'seleccion' requiere 'opciones' (array no vacío).");
  }
}

// GET /tipos-nave/:tipoId/campos
export const listarCampos = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  await assertTipoExiste(tipoId);
  const campos = await CamposModel.listarPorTipo(tipoId);
  res.json({ success: true, count: campos.length, data: campos });
});

// POST /tipos-nave/:tipoId/campos   (Administrador)
export const crearCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  await assertTipoExiste(tipoId);
  const { clave, etiqueta, tipo_dato, requerido, opciones, orden } = req.body;
  validarDefinicion({ clave, etiqueta, tipo_dato, opciones }, { claveRequerida: true });
  try {
    const campo = await CamposModel.crear({ tipo_nave_id: tipoId, clave, etiqueta, tipo_dato, requerido, opciones, orden });
    res.status(201).json({ success: true, data: campo });
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') throw new ApiError(409, `Ya existe un campo con clave '${clave}' en este tipo.`);
    throw e;
  }
});

// PUT /tipos-nave/:tipoId/campos/:campoId   (Administrador)
export const actualizarCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  const campoId = Number(req.params.campoId);
  const campo = await CamposModel.obtenerPorId(campoId);
  if (!campo || campo.tipo_nave_id !== tipoId) throw new ApiError(404, 'Campo no encontrado.');
  const { etiqueta, tipo_dato, requerido, opciones, orden, activo } = req.body;
  validarDefinicion({ etiqueta, tipo_dato, opciones }, { claveRequerida: false });
  const upd = await CamposModel.actualizar(campoId, {
    etiqueta, tipo_dato, requerido, opciones, orden,
    activo: activo === undefined ? campo.activo : activo,
  });
  res.json({ success: true, data: upd });
});

// DELETE /tipos-nave/:tipoId/campos/:campoId   (Administrador) — soft delete
export const desactivarCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  const campoId = Number(req.params.campoId);
  const campo = await CamposModel.obtenerPorId(campoId);
  if (!campo || campo.tipo_nave_id !== tipoId) throw new ApiError(404, 'Campo no encontrado.');
  await CamposModel.desactivar(campoId);
  res.json({ success: true });
});
```

- [ ] **Step 2: `campos.routes.js`**

```js
import { Router } from 'express';
import { requireRole } from '../../middlewares/auth.js';
import { listarCampos, crearCampo, actualizarCampo, desactivarCampo } from './campos.controller.js';

const router = Router();

router.get('/tipos-nave/:tipoId/campos', requireRole('Administrador', 'Supervisor', 'Coordinador'), listarCampos);
router.post('/tipos-nave/:tipoId/campos', requireRole('Administrador'), crearCampo);
router.put('/tipos-nave/:tipoId/campos/:campoId', requireRole('Administrador'), actualizarCampo);
router.delete('/tipos-nave/:tipoId/campos/:campoId', requireRole('Administrador'), desactivarCampo);

export default router;
```

---

## Task 5: Montar rutas de campos — `src/app.js`

**Files:** Modify `src/app.js`.

- [ ] **Step 1:** Agregar el import y el `app.use`. El archivo queda así:

```js
import express from 'express';
import cors from 'cors';
import { simulatedAuth } from './middlewares/auth.js';
import { notFound, errorHandler } from './middlewares/errorHandler.js';
import navesRoutes from './modules/operaciones/naves.routes.js';
import camposRoutes from './modules/operaciones/campos.routes.js';

export function createApp() {
  const app = express();
  app.use(cors());
  app.use(express.json());
  app.use(simulatedAuth);

  app.get('/health', (_req, res) => res.json({ success: true, status: 'ok' }));
  app.use('/api/operaciones', navesRoutes);
  app.use('/api/operaciones', camposRoutes);

  app.use(notFound);
  app.use(errorHandler);
  return app;
}
```

> Nota: se renombra el import `operacionesRoutes` → `navesRoutes` para claridad ahora que hay dos routers.

---

## Task 6: Naves — soportar datos_adicionales

**Files:** Modify `naves.model.js`, `naves.controller.js`, `naves.routes.js`.

- [ ] **Step 1: `naves.model.js`** — incluir `datos_adicionales` en `obtenerPorId` y `listar`, aceptar en `crear`, agregar `setDatos`.

`obtenerPorId` (SELECT con la columna nueva):
```js
  async obtenerPorId(id) {
    const [rows] = await pool.query(
      `SELECT n.id, n.nombre, n.tipo_nave_id, t.nombre AS tipo_nave,
              n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at
         FROM naves n
         JOIN tipos_nave t ON t.id = n.tipo_nave_id
        WHERE n.id = ?`,
      [id],
    );
    return rows[0] || null;
  },
```
`crear` (acepta y persiste datos_adicionales):
```js
  async crear({ nombre, tipo_nave_id, eta, etb, etd, estado, datos_adicionales }) {
    const [r] = await pool.query(
      `INSERT INTO naves (nombre, tipo_nave_id, eta, etb, etd, estado, datos_adicionales)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [nombre, tipo_nave_id, eta ?? null, etb ?? null, etd ?? null, estado || 'Programada',
       datos_adicionales ? JSON.stringify(datos_adicionales) : null],
    );
    return this.obtenerPorId(r.insertId);
  },
```
`listar` (agregar `n.datos_adicionales` a la lista de columnas del SELECT, igual que en obtenerPorId).
Agregar `setDatos`:
```js
  async setDatos(naveId, datos) {
    await pool.query('UPDATE naves SET datos_adicionales = ? WHERE id = ?',
      [datos ? JSON.stringify(datos) : null, naveId]);
    return this.obtenerPorId(naveId);
  },
```

- [ ] **Step 2: `naves.controller.js`** — imports nuevos + crear valida datos + 2 controladores nuevos.

Agregar imports:
```js
import { CamposModel } from './campos.model.js';
import { validarDatos } from './campos.validator.js';
```
`crearNave` (validar datos si vienen, con requireAll=false):
```js
export const crearNave = asyncHandler(async (req, res) => {
  const { nombre, tipo_nave_id, eta, etb, etd, estado, datos_adicionales } = req.body;
  if (!nombre?.trim()) throw new ApiError(400, 'El nombre de la nave es obligatorio.');
  if (!tipo_nave_id) throw new ApiError(400, 'El tipo_nave_id es obligatorio.');
  if (estado && !ESTADOS.includes(estado)) throw new ApiError(400, `Estado inválido. Use: ${ESTADOS.join(', ')}.`);
  if (!(await NavesModel.tipoExiste(tipo_nave_id))) throw new ApiError(400, 'El tipo de nave no existe o está inactivo.');

  let datos = null;
  if (datos_adicionales !== undefined && datos_adicionales !== null) {
    const campos = await CamposModel.listarPorTipo(tipo_nave_id);
    datos = validarDatos(campos, datos_adicionales, { requireAll: false });
  }

  const nave = await NavesModel.crear({ nombre: nombre.trim(), tipo_nave_id, eta, etb, etd, estado, datos_adicionales: datos });
  res.status(201).json({ success: true, data: nave });
});
```
Nuevos controladores:
```js
// GET /api/operaciones/naves/:id  → nave + definiciones de campos de su tipo
export const obtenerNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');
  const campos = await CamposModel.listarPorTipo(nave.tipo_nave_id);
  res.json({ success: true, data: { nave, campos } });
});

// PUT /api/operaciones/naves/:id/datos  (Admin/Supervisor) — reemplaza datos, exige requeridos
export const actualizarDatos = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');
  const campos = await CamposModel.listarPorTipo(nave.tipo_nave_id);
  const datos = validarDatos(campos, req.body?.datos_adicionales ?? {}, { requireAll: true });
  const actualizada = await NavesModel.setDatos(naveId, datos);
  res.json({ success: true, data: actualizada });
});
```

- [ ] **Step 3: `naves.routes.js`** — agregar imports y rutas:
```js
import { crearNave, listarNaves, registrarAvance, historialNave, obtenerNave, actualizarDatos } from './naves.controller.js';
// ...
router.get('/naves/:id', requireRole('Administrador', 'Supervisor', 'Coordinador'), obtenerNave);
router.put('/naves/:id/datos', requireRole('Administrador', 'Supervisor'), actualizarDatos);
```

- [ ] **Step 4: Checkpoint** — `npm test` sigue verde (no rompe el validador).

---

## Task 7: Verificación end-to-end (curl)

- [ ] **Step 1:** Levantar `node src/server.js` (background) y correr, esperando los códigos indicados:
  1. `POST /tipos-nave/1/campos` como **Coordinador** → **403**.
  2. `POST /tipos-nave/1/campos` como **Administrador** `{clave:'teus',etiqueta:'TEUs',tipo_dato:'numero',requerido:true}` → **201**.
  3. `POST /tipos-nave/1/campos` Admin `{clave:'bandera',etiqueta:'Bandera',tipo_dato:'seleccion',opciones:['PA','LR']}` → **201**.
  4. `GET /tipos-nave/1/campos` → **200**, 2 campos.
  5. `POST /naves` Supervisor con `datos_adicionales:{teus:'3500',bandera:'PA'}` → **201** (datos normalizados: teus=3500 número).
  6. `POST /naves` Supervisor con `datos_adicionales:{teus:'abc'}` → **400**.
  7. `PUT /naves/:id/datos` Supervisor `{datos_adicionales:{bandera:'PA'}}` → **400** (falta requerido `teus`).
  8. `PUT /naves/:id/datos` Supervisor `{datos_adicionales:{teus:4000,bandera:'LR'}}` → **200**.
  9. `GET /naves/:id` → **200**, devuelve `nave.datos_adicionales` + `campos`.
- [ ] **Step 2:** Detener el server. Limpiar datos de prueba si se desea (mantener catálogo de tipos).

---

## Self-Review (cobertura del spec)

- Tabla `campos_tipo_nave` + `naves.datos_adicionales` → Task 1. ✓
- Validador con todas las reglas (desconocidas/requeridos/tipos/seleccion) → Task 2 (con tests). ✓
- CRUD de definiciones (Admin) + lectura (operativos) → Tasks 3-4. ✓
- Montaje de rutas → Task 5. ✓
- Naves: crear acepta datos, GET /:id (nave+campos), PUT /:id/datos → Task 6. ✓
- Roles (Admin define; Admin/Supervisor escriben; operativos leen) → Tasks 4 y 6. ✓
- Tests `node --test` sin deps nuevas → Task 2. ✓
- Verificación end-to-end → Task 7. ✓
- Sin git → checkpoints. ✓
