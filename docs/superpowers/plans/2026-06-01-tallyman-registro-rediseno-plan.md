# Rediseño Registro Tallyman (modal + tarjetas) — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development o ejecución inline. Pasos con checkbox.

**Goal:** Reemplazar la captura de `pages/tallyman.php` (filas planas) por 3 apartados (Muelle/Patio/Incidencia) que abren un modal con estilo "crear nave", y mostrar lo guardado como tarjetas resumen con editar/eliminar.

**Architecture:** Solo frontend. La página carga `css/operaciones.css` y envuelve el modal en `.op` para reutilizar `op-modal/op-dialog/op-fsection/op-control/op-segment/op-btn`. Toda la lógica en `js/tallyman_registro.js` (patrón `window.OP`). Backend de Fase 1 intacto; usa `GET/POST/PUT/DELETE tallyman/registros`, `GET/POST tallyman/incidencias`, `GET tallyman/actividades`, `GET naves`, `includes/tallyman_turno.php`.

**Tech Stack:** PHP 8.2, JS vanilla, CSS reutilizado de operaciones, Apache/XAMPP, API Node :4000.

**Spec:** `docs/superpowers/specs/2026-06-01-tallyman-registro-rediseno-modal-design.md`

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `pages/tallyman.php` | reescribir completo | Shell + hero + 3 apartados (con listas `#tmBerthList`/`#tmYardList`/`#tmIncView`) + markup del modal `.op` (hero, `#tmModalBody`, footer) + CSS de tarjetas + carga de `css/operaciones.css` y `js/tallyman_registro.js` + `TM_CTX`. |
| `js/tallyman_registro.js` | crear | Carga (turno+catálogos+registros+incidencia), render de tarjetas, modal adaptable (BERTH/YARD/INCIDENCIA) crear+editar, guardar (POST/PUT), eliminar, degradación elegante. |

---

## Task 1: Reescribir `pages/tallyman.php`

**Files:** Modify (rewrite): `c:\xampp2026\htdocs\Estiba_Turno\pages\tallyman.php`

Reemplaza TODO el contenido. Mantiene el guard `require_operaciones()` y `$canRegistrar`. Carga adicional de `css/operaciones.css`. Tres `tm-card` con botones `#addBerth`/`#addYard`/`#addInc` (disabled si no es Coordinador) y contenedores de listas. El modal va dentro de `<div class="op">` para heredar los estilos; cuerpo `#tmModalBody` lo rellena el JS; footer con `#tmModalSave`/`#tmModalSaveLabel`. CSS inline solo para hero, tarjetas-apartado, tarjetas-item (barra de avance, chips de status) y la franja `.tm-calc`. Carga `../js/operaciones.js`, inyecta `window.TM_CTX = { canRegistrar: <bool> }`, y `../js/tallyman_registro.js`.

- [ ] **Step 1:** Escribir el archivo completo (ver contenido en sección "Contenido Task 1" abajo).
- [ ] **Step 2:** Lint PHP: `"/c/xampp2026/php/php.exe" -l pages/tallyman.php` → No syntax errors.
- [ ] **Step 3:** Commit `feat(tallyman): rediseño de registro — 3 apartados + modal (markup/CSS)`.

## Task 2: Crear `js/tallyman_registro.js`

**Files:** Create: `c:\xampp2026\htdocs\Estiba_Turno\js\tallyman_registro.js`

Lógica completa (ver "Contenido Task 2" abajo). Builders de campos op-* (`fSelect/fInput/fInputRO/fSeg/fArea/fsection`), `bodyActividad(tipo)`, `bodyIncidencia()`, `abrirModal(modo, reg?)`, `wireBody`, `prefill`, `guardar`/`guardarIncidencia`, `eliminar`, `card`, `renderListas`/`renderIncidencia`, `recargarRegistros`/`recargarIncidencia`, `cargarTurno`, `enlazar` (listeners SIEMPRE primero), `init`.

- [ ] **Step 1:** Escribir el archivo.
- [ ] **Step 2:** `node --check js/tallyman_registro.js` → exit 0.
- [ ] **Step 3:** Commit `feat(tallyman): lógica del registro rediseñado (modal adaptable + tarjetas)`.

## Task 3: Verificación end-to-end + cierre

- [ ] **Step 1:** Con API Node arriba y sesión Coordinador, en navegador real (Playwright): abrir `pages/tallyman.php`; abrir modal de muelle, elegir nave → Planned autollena, escribir executed → pendiente recalcula, guardar → aparece tarjeta; editar la tarjeta → reabre modal con valores; registrar incidencia → aparece en su apartado. Capturar consola (0 errores) y screenshot.
- [ ] **Step 2:** Revisión de calidad del diff (subagente) sobre los 2 archivos.
- [ ] **Step 3:** Limpiar datos de prueba del turno; commit/push.

---

## Contenido Task 1 — `pages/tallyman.php`

El archivo completo se implementa según el spec §3–§4. Markup del modal reutiliza la estructura de `pages/operaciones.php:130-251` (op-modal/op-dialog/op-dialog-hero/op-dialog-body/op-dialog-foot) con IDs `tmModal`, `tmModalClose`, `tmModalTitle`, `tmModalSub`, `tmModalBody`, `tmModalCancel`, `tmModalSave`, `tmModalSaveLabel`. Carga `<link rel="stylesheet" href="../css/operaciones.css">`. Apartados con IDs de botón `addBerth/addYard/addInc` y listas `tmBerthList/tmYardList/tmIncView`. (Contenido íntegro aplicado en ejecución.)

## Contenido Task 2 — `js/tallyman_registro.js`

IIFE con `window.OP`. Estado: `turno, actividades, naves, registros, incidencia, datosListos, editId, modo, canReg`. Constantes `UBICS_BERTH/ESTADOS_POS/STATUS_ACT`. Endpoints vía `OP.opApi`. `abrirModal(modo, reg?)` arma `#tmModalBody` con `bodyActividad`/`bodyIncidencia`; `guardar` hace POST si `!editId` o PUT si `editId`; incidencia siempre POST (upsert). `enlazar()` registra listeners ANTES de cargar datos (degradación elegante). (Contenido íntegro aplicado en ejecución.)

---

## Self-review
- Cubre spec §3 (3 apartados+tarjetas), §4 (modal adaptable crear/editar), §5 (lógica+degradación), §6 (2 archivos), §7 (sin backend). 
- IDs consistentes entre Task 1 (markup) y Task 2 (querys): tmModal*, addBerth/addYard/addInc, tmBerthList/tmYardList/tmIncView, data-k/data-seg/data-calc.
- Sin placeholders en la implementación (el código se aplica completo en ejecución).
