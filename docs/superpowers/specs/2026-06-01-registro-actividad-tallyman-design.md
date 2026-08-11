# Registro de Actividad de Turno (Coordinador Tallyman) — Diseño

**Fecha:** 2026-06-01
**Origen:** Google Form "Registro de Actividades Portuarias" (HANDOVER-2026) + Sheet de respuestas.
**Objetivo:** Reemplazar el formulario de Google por un módulo dentro de Estiba_Turno donde el coordinador tallyman registra, por turno, la actividad de cada muelle (Berth) y patio (Yard), y se genera un relevo de turno con gráficos exportable a PDF/imagen.

---

## 1. Contexto y decisiones tomadas

Decisiones confirmadas con el usuario:

- **Planned lo aporta Operaciones.** El total planeado (`cantidad_total` / `teus` / `vehiculos`) ya vive en `naves.datos_adicionales` (JSON). El tallyman **no** lo ingresa: elige la nave y el sistema hereda su planned.
- **El único valor que ingresa el tallyman es `Executed in turn`** (lo realizado en su turno), más metadatos de la actividad (estado, detalles, productividad opcional).
- **`Accumulated` y `Pending` se calculan:** `Accumulated = Σ(Executed de turnos previos de esa nave+actividad+ubicación) + Executed actual`; `Pending = Planned − Accumulated`.
- **UI de registro: lista dinámica.** En vez de posiciones fijas (Berth 01–04, Yard 01–05 como el form), el tallyman pulsa "Añadir actividad de muelle" / "Añadir actividad de patio" y agrega solo las que necesita.
- **Backend: `operaciones-api` (Node/Express)** con BD `operaciones`, vía el proxy seguro `api/operaciones_proxy.php`.
- **Relevo: ambos gráficos** (barras apiladas por actividad + dona de avance global) + comentarios, exportable a PDF/imagen.
- **Entrega por fases.** Este spec cubre las tres fases; cada fase tendrá su propio plan de implementación.

### Modelo del formulario original (referencia)

Por cada bloque (Berth o Yard):

| Campo | Tipo | Obligatorio | Quién |
|---|---|---|---|
| STATE (ACTIVE / INACTIVE / FINISH) | opción | sí | tallyman |
| Activity | dropdown (21 opciones) | sí | tallyman (Yard) / heredado (Berth) |
| Vessel (Nave) | selección | sí | tallyman (elige nave de Operaciones) |
| Details (Avances/QC) | párrafo | no | tallyman |
| Status (Inicio / En Proceso / Culminado) | opción | sí | tallyman |
| Planned | número | sí (form) | **heredado de Operaciones** |
| Executed during the shift | número | sí | **tallyman (único input real)** |
| Productivity (mov/hora) | número | no | tallyman (solo Berth) |

Incidencias al final: ¿hubo incidente? Sí/No → si Sí, detalle (párrafo).

Las 21 actividades del catálogo: Containers Loading/Discharge, Corn Loading/Discharge, Salt Loading/Discharge, Soybean Unloading/Loading, Bulk Carrier Loading/Discharge, Big Bags Loading/Discharge, General Cargo Loading/Discharge, Car Loading/Discharge, Minerals, Fishmeals, Container deconsolidation, Car deconsolidation, Containers Dispatch, Corn Dispatch, Salt Dispatch, Soybean Dispatch, Bulk Carrier Dispatch, Big Bags Dispatch, General Cargo Dispatch, Car Dispatch, Reception of Salt.

---

## 2. Arquitectura

Reusa la arquitectura existente del módulo Operaciones (no inventa stack):

```
Navegador (PHP page + JS)
   │  fetch same-origin
   ▼
api/operaciones_proxy.php   (valida sesión, inyecta x-user-id/role/name)
   │  HTTP a 127.0.0.1:4000
   ▼
operaciones-api (Node/Express)
   └── modules/tallyman/   (NUEVO)
   │
   ▼
BD `operaciones`  (tablas nuevas: catálogo de actividades + registros de turno)
```

### Por qué tablas nuevas y no `avances_nave`

`avances_nave` modela descarga por flujo (directa/indirecta/despacho en TM) y es específico de graneles. El modelo tallyman es distinto: una fila por **actividad × ubicación × turno** con `executed`. Mezclarlos rompería ambos. Se crean tablas dedicadas que conviven con lo existente.

---

## 3. Esquema de base de datos (BD `operaciones`)

Nuevo archivo de migración: `operaciones-api/sql/007_tallyman.sql`.

### 3.1 Catálogo de actividades

```sql
CREATE TABLE tallyman_actividades (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80)  NOT NULL UNIQUE,   -- las 21 actividades
  activo TINYINT(1)   NOT NULL DEFAULT 1,
  orden  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
-- seed con las 21 actividades del form
```

Source-of-truth en DB (coherente con el patrón de catálogos del proyecto: funciones/ubicaciones/jornadas son DB-driven). Permite editarlas a futuro sin tocar código.

### 3.2 Registros de actividad del turno

```sql
CREATE TABLE tallyman_registros (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE          NOT NULL,             -- fecha del turno
  turno          VARCHAR(20)   NOT NULL,             -- código/nombre de jornada vigente
  ubicacion_tipo ENUM('BERTH','YARD') NOT NULL,
  ubicacion      VARCHAR(40)   NOT NULL,             -- 'Berth 01'..'Berth 04','Berth 3.5','Yard', etc.
  nave_id        INT UNSIGNED  NULL,                 -- FK naves.id (de Operaciones)
  actividad_id   INT UNSIGNED  NOT NULL,             -- FK tallyman_actividades.id
  estado_pos     ENUM('ACTIVE','INACTIVE','FINISH') NOT NULL DEFAULT 'ACTIVE',
  status_act     ENUM('Inicio','En Proceso','Culminado') NOT NULL DEFAULT 'Inicio',
  planned        DECIMAL(14,2) NULL,                 -- snapshot heredado de la nave al registrar
  executed       DECIMAL(14,2) NOT NULL DEFAULT 0,   -- ÚNICO input del tallyman
  productivity   DECIMAL(12,2) NULL,                 -- mov/hora (opcional, solo berth)
  details        TEXT          NULL,
  coord_entrante VARCHAR(120)  NULL,
  coord_saliente VARCHAR(120)  NULL,
  registrado_por VARCHAR(120)  NOT NULL,             -- $_SESSION user_name
  fecha_registro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_tr_nave FOREIGN KEY (nave_id) REFERENCES naves(id) ON DELETE SET NULL,
  CONSTRAINT fk_tr_act  FOREIGN KEY (actividad_id) REFERENCES tallyman_actividades(id),
  KEY idx_tr_turno (fecha_turno, turno),
  KEY idx_tr_nave (nave_id)
) ENGINE=InnoDB;
```

### 3.3 Incidencias del turno

```sql
CREATE TABLE tallyman_incidencias (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_turno    DATE         NOT NULL,
  turno          VARCHAR(20)  NOT NULL,
  hubo           TINYINT(1)   NOT NULL DEFAULT 0,
  detalle        TEXT         NULL,
  registrado_por VARCHAR(120) NOT NULL,
  fecha_registro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ti_turno (fecha_turno, turno)
) ENGINE=InnoDB;
```

`planned` se guarda como **snapshot** en el registro (no solo se lee de la nave) para que el relevo histórico sea reproducible aunque la nave cambie después.

---

## 4. Cálculo de Accumulated / Pending

Calculado en el backend (no se almacena, para evitar desincronización):

```
Para un registro R (nave_id, actividad_id, ubicacion):
  accumulated = SUM(executed) de todos los tallyman_registros con misma
                (nave_id, actividad_id, ubicacion) cuya (fecha_turno, turno)
                sea <= la de R, ordenado cronológicamente.
  pending     = MAX(planned - accumulated, 0)
  porcentaje  = planned > 0 ? min(accumulated / planned * 100, 100) : null
```

Mismo patrón que `construirResumen()` en `naves.controller.js`.

---

## 5. API (Node) — módulo nuevo `modules/tallyman/`

Rutas bajo `/api/operaciones/tallyman/*` (extiende el prefijo ya proxeado; **requiere ampliar el allow-list del proxy** que hoy es `(naves|tipos-nave)...` → añadir `tallyman`).

| Método | Ruta | Rol | Acción |
|---|---|---|---|
| GET | `/tallyman/actividades` | Admin/Sup/Coord | catálogo de 21 actividades |
| GET | `/tallyman/turno-actual` | Admin/Sup/Coord | fecha+turno vigentes + registros ya cargados |
| GET | `/tallyman/registros?fecha=&turno=` | Admin/Sup/Coord | registros de un turno (para relevo) |
| POST | `/tallyman/registros` | **Coordinador** | crear registro de actividad |
| PUT | `/tallyman/registros/:id` | Coordinador (mismo turno) / Admin/Sup | editar |
| DELETE | `/tallyman/registros/:id` | Coordinador (mismo turno) / Admin/Sup | borrar |
| POST | `/tallyman/incidencias` | Coordinador | guardar incidencia del turno |
| GET | `/tallyman/relevo?fecha=&turno=` | Admin/Sup/Coord | payload completo del relevo: registros (con accumulated/pending/%), incidencia del turno, y `totales` { planned, executed, pending, porcentaje, n_actividades } para los KPIs |

Estructura de archivos (espeja `modules/operaciones/`):
```
modules/tallyman/
  tallyman.routes.js
  tallyman.controller.js   (validación, cálculo accumulated/pending, armado de relevo)
  tallyman.model.js        (acceso BD)
  tallyman.validator.js
```

---

## 6. Frontend (PHP + JS)

### 6.1 Fase 1 — Página de registro: `pages/tallyman.php`

- Hero de marca (navy) coherente con `operaciones.php`.
- Cabecera del turno: fecha + jornada vigente (de `jornada_vigente()`), coordinador entrante/saliente (dropdown de usuarios con rol Coordinador, o lista fija si se prefiere replicar el form).
- **Sección "Actividad en Muelle (Berth)"**: botón "Añadir actividad de muelle" → fila/tarjeta con: ubicación (Berth 01–04 / 3.5 / 4.5), nave (select de naves de Operaciones → al elegir, muestra Planned heredado, solo lectura), actividad, estado posición (ACTIVE/INACTIVE/FINISH), status (Inicio/Proceso/Culminado), details, **executed** (input), productivity (opcional).
- **Sección "Actividad en Patio (Yard)"**: igual, pero la actividad la elige siempre el tallyman (no se hereda).
- **Sección Incidencias**: toggle Sí/No + textarea condicional.
- Cada tarjeta muestra en vivo: `Pending = Planned − (Accumulated previo + Executed)` y % (reusa el patrón de preview de duración de `jornadas.php`).
- Guarda vía proxy. Permisos: registrar = Coordinador; ver = Admin/Sup/Coord.

### 6.2 Fase 2 — Vista de relevo: `pages/tallyman_relevo.php`

- Default: turno vigente (de `tallyman_turno.php`). (Selección de fecha+turno histórico queda fuera de alcance v1.)
- Consume `GET tallyman/relevo?fecha=&turno=` (endpoint nuevo en el backend, ver §5) que devuelve registros (con accumulated/pending/%), incidencia y totales del turno en una sola llamada.
- **Cabecera ejecutiva**: título, fecha, turno, coordinador entrante/saliente.
- **KPIs**: total planned, total executed del turno, % de avance global, nº de actividades.
- Tabla de actividades del turno: ubicación, nave, actividad, planned, executed (turno), accumulated, pending, %, status.
- **Gráfico de barras apiladas** (una barra por actividad: executed del turno vs pending, sobre planned).
- **Gráfico de dona** de avance global del turno (executed total vs pending total).
- Bloque de incidencias del turno (solo si hubo).
- **Librería de gráficos:** Chart.js vía CDN (cliente, sin build).

### 6.3 Fase 3 — Exportación PDF ejecutivo (auto-oculta vacíos)

- Botón "Exportar PDF" en la vista de relevo.
- **Reporte ejecutivo** inspirado en el formato del Sheets HANDOVER: cabecera ejecutiva + KPIs + gráficos + tabla de actividades + incidencias.
- **Ajustable = auto-oculta vacíos** (sin checkboxes de usuario):
  - Berths/Yards sin actividad registrada en el turno → no aparecen.
  - Columna que está vacía en TODAS las filas del turno (ej. Productividad) → se omite.
  - Sección de incidencias → solo si `hubo = true`.
  - KPIs/gráficos que no tienen datos (ej. sin planned en ninguna fila → sin dona de %) → se omiten con gracia.
- **Enfoque cliente (sin servidor):** Chart.js para los gráficos + `html2canvas` para capturar el contenedor de relevo (gráficos incluidos) + `jsPDF` para empaquetar la captura en un PDF A4. La omisión de vacíos ocurre en el DOM (lo que no se renderiza no se exporta), por lo que el "ajuste" es consecuencia natural de construir el relevo sin filas/columnas vacías.
- El relevo se renderiza ya "limpio" (sin vacíos) tanto en pantalla como en el PDF — misma fuente de verdad.

---

## 7. Roles

Reusa `auth.php` y el patrón del módulo Operaciones:

- **Coordinador**: registra actividad de su turno, ve relevo.
- **Administrador / Supervisor**: ven todo, editan/borran registros, ven relevo y exportan.
- El proxy ya inyecta `x-user-name` → se guarda en `registrado_por`.

---

## 8. Fases de entrega

- **Fase 1 — Registro:** migración SQL (007), módulo Node `tallyman`, ampliación del allow-list del proxy, página `tallyman.php` con registro Berth+Yard+incidencias y cálculo en vivo de pending. Guarda en BD.
- **Fase 2 — Relevo:** `tallyman_relevo.php` con tabla + ambos gráficos + comentarios.
- **Fase 3 — Exportación:** PDF e imagen del relevo.

Cada fase: su propio plan de implementación (writing-plans) y su ciclo de revisión.

---

## 9. Riesgos / cuestiones abiertas

- **Forma de `planned` por tipo de nave:** `cantidad_total` (granelera), `teus` (containera), `vehiculos` (ro-ro). El backend debe normalizar a un solo número "planned" al heredar. A resolver en el plan de Fase 1 (función `plannedDeNave(nave)`).
- **Coordinador entrante/saliente:** el form usa lista fija de 8 personas; el sistema tiene `usuarios` con rol Coordinador. Decisión: usar la tabla `usuarios` (más mantenible) salvo que el usuario prefiera la lista fija.
- **Identidad del turno:** `jornada_vigente()` vive en PHP (`includes/turno.php`); el backend Node necesitará recibir fecha+turno desde el cliente (ya autenticado por el proxy) en lugar de recalcularlos.
- **Berth Additional con sub-selección** (Berth 3.5 / 4.5): se modela con `ubicacion` libre dentro de `ubicacion_tipo='BERTH'`.
