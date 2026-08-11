# Rediseño de Registro Tallyman — Modal con estilo + tarjetas resumen

**Fecha:** 2026-06-01
**Tipo:** Rediseño de interfaz (frontend). **El backend de Fase 1 no cambia.**
**Sustituye:** la captura actual de `pages/tallyman.php` (filas planas con inputs en una grid), que el usuario considera demasiado simple.

## 1. Objetivo

Capturar la actividad del turno mediante un **formulario emergente con estilo** (modal), replicando el patrón visual del modal "crear nave" de Operaciones, en lugar de las filas editables actuales. La actividad guardada se muestra como **tarjetas resumen** con editar/eliminar.

## 2. Decisiones (aprobadas)

- **Un único modal adaptable** (no tres separados): cambia título y campos según se abra desde Muelle, Patio o Incidencia. Mismo modal para crear y editar.
- **Tarjetas resumen + editar/eliminar** para lo ya guardado (reemplaza las filas editables).
- **Incidencia también en modal** (toggle Sí/No + detalle).
- **Reutilizar `css/operaciones.css`**: cargar esa hoja en la página y envolver el modal con la clase `.op` para heredar los componentes `op-modal`, `op-dialog`, `op-dialog-hero`, `op-fsection`, `op-control`, `op-field`, `op-segment`/`op-seg`, `op-btn`, `op-dialog-foot`. Cero duplicación de CSS de componentes.
- **Precargar** los registros e incidencia ya existentes del turno al abrir la página (hoy abre vacía).
- **Sin cambios de backend**: usa los endpoints de Fase 1: `GET/POST tallyman/registros`, `PUT/DELETE tallyman/registros/:id`, `GET/POST tallyman/incidencias`, y `GET tallyman/actividades`, `GET naves`, `includes/tallyman_turno.php`.

## 3. Estructura de la página (`pages/tallyman.php`)

- Hero navy (se mantiene) con chip del turno vigente.
- **Tres apartados** (cada uno una tarjeta-sección con cabecera + botón de acción):
  1. **Actividad en Muelle (Berth)** — botón `+ Añadir actividad de muelle` (abre modal en modo BERTH). Debajo: `#tmBerthList` con tarjetas de las actividades de muelle del turno.
  2. **Actividad en Patio (Yard)** — botón `+ Añadir actividad de patio` (modo YARD). Debajo: `#tmYardList`.
  3. **Incidencia del turno** — botón `Registrar incidencia` (modo INCIDENCIA). Debajo: `#tmIncView` con la incidencia guardada (si hay).
- La página carga `css/operaciones.css` además de las hojas actuales; el contenedor del modal lleva `class="op"`.
- El JS vive en un archivo nuevo externo: **`js/tallyman_registro.js`** (como `js/tallyman_relevo.js`).

### Tarjeta resumen de actividad (`#tmBerthList`/`#tmYardList`)
Muestra: `ubicacion · nave · [chip status]`, la actividad, `Executed N / Planned M`, **barra de avance** con `%`, `Pendiente`, y acciones **✎ Editar** (reabre el modal en modo edición) / 🗑 **Eliminar** (con confirmación). Si la actividad no tiene planned, se omite la barra/% (igual que el relevo).

## 4. El modal adaptable (`.op .op-modal`)

Estructura idéntica a `operaciones.php` (hero gradiente, `op-fsection`, footer):

- **Hero:** etiqueta `◗ Registro de turno`, título y subtítulo según el modo, botón ✕.
  - BERTH → "Actividad en Muelle" / "Registra lo realizado en el muelle durante tu turno."
  - YARD → "Actividad en Patio" / "Registra lo realizado en patio durante tu turno."
  - INCIDENCIA → "Incidencia del turno" / "Reporta cualquier incidente, demora o condición anómala."
- **Cuerpo (BERTH/YARD):**
  - *Sección "Ubicación y actividad":* Ubicación (BERTH: `op-select` con Berth 01–04/3.5/4.5; YARD: input texto, valor por defecto "Yard"), Nave (`op-select` de naves activas; al elegir, autocompleta Planned), Actividad (`op-select` con las 21), Estado posición (`op-segment`: ACTIVE/INACTIVE/FINISH), Status (`op-segment`: Inicio/En Proceso/Culminado).
  - *Sección "Indicadores":* Planned (heredado de la nave, solo-lectura), Executed (`op-control` number, obligatorio), Productividad (`op-control` number, **solo BERTH**), Detalles (textarea). Franja de cálculo en vivo: `Pendiente (estimado)` y `Avance %` (mismo criterio actual; el acumulado real se recalcula al guardar).
- **Cuerpo (INCIDENCIA):**
  - *Sección "Incidencia del turno":* `op-segment` ¿Hubo incidente, demora o problema? Sí/No; Detalle (textarea, visible solo si "Sí", obligatorio si "Sí").
- **Footer:** hint `* Campos obligatorios` + `Cancelar` / `Guardar` (texto del botón según modo: "Guardar actividad" / "Guardar incidencia").

El modal es **uno solo**; una función `abrirModal(modo, registro?)` arma el cuerpo correcto y, si recibe `registro`, precarga los valores para editar (guardar = PUT) en lugar de crear (POST).

## 5. Lógica (`js/tallyman_registro.js`)

Patrón `window.OP` (reusa `OP.opApi/esc/toast/$`). Pasos:
1. **init():** enlaza SIEMPRE los botones de los 3 apartados (degradación elegante — lección del bug previo), luego carga datos: turno (`tallyman_turno.php`), `tallyman/actividades`, `naves`, y los existentes del turno (`tallyman/registros?fecha=&turno=`, `tallyman/incidencias?fecha=&turno=`). Si la carga falla: aviso claro + toast, botones igual responden con mensaje.
2. **render de tarjetas:** separa registros por `ubicacion_tipo` (BERTH/YARD) en `#tmBerthList`/`#tmYardList`; pinta la incidencia en `#tmIncView`.
3. **abrirModal(modo, registro?):** construye el cuerpo del modal según modo; precarga si es edición; al elegir nave autocompleta Planned; recálculo en vivo de pendiente.
4. **guardar:** valida (Executed obligatorio en actividad; Detalle obligatorio si hubo incidente); POST (crear) o PUT (editar) `tallyman/registros`; incidencia → POST `tallyman/incidencias`. Cierra modal y re-renderiza la sección.
5. **editar:** `abrirModal(modo, registro)`. **eliminar:** confirm + DELETE + re-render.
6. **Permisos:** crear/editar/eliminar visibles solo si Coordinador (`TM_CTX.canRegistrar`, inyectado por PHP); el resto solo ve. El backend ya refuerza por rol.

## 6. Archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `pages/tallyman.php` | reescribir | Shell + 3 apartados + markup del modal `.op`; carga `css/operaciones.css` y `js/tallyman_registro.js`; inyecta `TM_CTX`. |
| `js/tallyman_registro.js` | crear | Toda la lógica (carga, render de tarjetas, modal adaptable crear/editar, guardar/eliminar, degradación). |
| *(backend)* | sin cambios | Endpoints de Fase 1 intactos. |

## 7. Fuera de alcance (YAGNI)

- No se tocan los endpoints ni el esquema.
- No se añade histórico ni filtros de fecha (la página opera sobre el turno vigente).
- No se capturan coord_entrante/saliente en esta entrega (siguen disponibles en BD para el futuro).

## 8. Criterio de éxito

- Al abrir la página se ven los 3 apartados y lo ya registrado del turno como tarjetas.
- "Añadir actividad de muelle/patio" abre un modal con el estilo de "crear nave"; guardar crea la tarjeta.
- Editar reabre el modal con los valores; eliminar quita la tarjeta.
- "Registrar incidencia" abre el modal de incidencia; queda visible en su apartado.
- Verificado en navegador real, sin errores de consola.
