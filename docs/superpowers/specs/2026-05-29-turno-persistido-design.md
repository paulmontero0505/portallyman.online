# Diseño · Colocar personal en turno con persistencia

Fecha: 2026-05-29

## Problema

La pantalla **Turno Actual** ([index.php](../../../index.php) + [js/estiba.js](../../../js/estiba.js))
se alimenta de datos **hardcodeados** en [js/data-source.js](../../../js/data-source.js):
8 colaboradores ficticios con bitácoras inventadas. Todas las mutaciones
(función/ubicación/estado, eventos) viven **solo en memoria** y se pierden al recargar.

Existe un catálogo real de personal (`colaboradores`) expuesto por
[api/get_colaboradores.php](../../../api/get_colaboradores.php).

## Objetivo

1. Quitar la data falsa.
2. Permitir colocar colaboradores reales en el turno.
3. **Persistir** en base de datos: quién está en turno, su asignación y la bitácora.

## Decisiones (acordadas con el usuario)

- Persistir en base de datos.
- Al agregar a alguien se define **función + ubicación** (la función se pre-llena
  con `funcion_principal` del catálogo). Entra como `activo`.
- Un turno = **(fecha + jornada)**. Jornadas: Mañana 06–14, Tarde 14–22, Noche 22–06.
  El turno de hoy se abre automáticamente según la hora.
- Catálogos (funciones, ubicaciones, límites de tiempo) se mueven a **base de datos**.
- Se incluye **quitar** personal del turno.
- Cerrar turno manual queda **diferido** (la columna `estado` existe pero la UI no
  expone el botón por ahora; el turno se abre solo por hora).

## Modelo de datos (`sql/004_turnos.sql`)

```
jornadas        id, codigo (M/T/N), nombre, hora_inicio TIME, hora_fin TIME, orden
limites_pausa   tipo PK (refrigerio|permiso|traslado), limite_min INT NULL
ubicaciones     id, nombre UNIQUE, activo, orden
funciones       id, nombre UNIQUE, activo, orden

turnos          id, fecha DATE, jornada_id, estado ENUM(abierto|cerrado),
                abierto_por (usuarios.id), created_at
                UNIQUE(fecha, jornada_id)

turno_personal  id, turno_id FK, colaborador_id FK, funcion VARCHAR (snapshot),
                ubicacion VARCHAR (snapshot), estado ENUM(activo|refrigerio|incidencia),
                created_at, updated_at
                UNIQUE(turno_id, colaborador_id)

turno_eventos   id, turno_personal_id FK, tipo ENUM(traslado|refrigerio|permiso),
                hora_inicio TIME, hora_fin TIME NULL, observaciones TEXT, created_at
```

`funcion`/`ubicacion` se copian (snapshot) en `turno_personal`, así editar el catálogo
después no altera turnos pasados. Seeds de catálogos = valores actuales de data-source.js.
No se siembra ningún `turno_personal`: el turno arranca vacío.

## Resolución del turno actual

El backend mira la hora del servidor y resuelve la jornada vigente. La Noche
(22:00–06:00) cruza medianoche: entre 00:00 y 06:00 pertenece al turno que inició
el **día anterior**. Si no existe el `turnos` (fecha+jornada), se crea vacío `abierto`.

## APIs (`api/`, patrón existente: prepared statements, `{success,...}`)

- `get_turno_actual.php` — `api_require_login`. Resuelve/crea el turno y devuelve el
  contrato que ya espera el módulo, extendido con IDs:
  `{ success, turnoId, turnoLabel, turnoEstado, limitesMin, funcionesDisponibles,
     ubicacionesDisponibles, personal:[{ tpId, id(codigo), colaboradorId, nombre,
     funcion, ubicacion, estado, bitacora:[{ id, tipo, horaInicio, horaFin, observaciones }] }] }`
- `add_personal_turno.php` — `{ turnoId, colaboradorId, funcion, ubicacion }` → inserta en
  `turno_personal`, devuelve el registro creado (con `tpId`).
- `remove_personal_turno.php` — `{ tpId }` → borra (cascade de eventos).
- `update_asignacion.php` — `{ tpId, funcion, ubicacion, estado }`.
- `add_evento.php` — `{ tpId, tipo, horaInicio, horaFin, observaciones }` → devuelve `id`.
- `delete_evento.php` — `{ id }`.

## Frontend

- **[js/data-source.js](../../../js/data-source.js)**: se elimina el seed; pasa a hacer
  `fetch('api/get_turno_actual.php')` y luego `EstibaModule.boot()`.
- **[js/estiba.js](../../../js/estiba.js)**:
  - `boot()` acepta los datos del fetch; cada persona lleva `tpId`/`colaboradorId`,
    cada evento su `id`; guarda `state.turnoId`.
  - `applyAsignacion`, `addEvento`, borrar evento → llaman a las APIs (optimista, con
    toast de error si falla).
  - Nuevo **picker** "Añadir personal": busca `colaboradores` activos que no estén ya
    en el turno (reusa `get_colaboradores.php`, filtra en cliente), elige función
    (pre-llenada) + ubicación, llama `add_personal_turno.php`.
  - Acción **quitar del turno** por tarjeta → `remove_personal_turno.php`.
  - Estado vacío del grid: "Aún no hay personal en este turno · Añadir personal".
- **[index.php](../../../index.php)**: botón "Añadir personal al turno" en la toolbar y
  markup del modal de selección.

## Verificación

Migración aplicada en MySQL; abrir la pantalla con turno vacío, agregar un colaborador
real, cambiar su estado/ubicación, registrar un evento, recargar y confirmar que todo
persiste; quitar a alguien y confirmar que desaparece tras recargar.
