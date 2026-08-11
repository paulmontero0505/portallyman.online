# Diseño · Jornadas configurables, historial por fecha y reportes Excel

Fecha: 2026-05-29

## Contexto

El tablero de turno ya persiste personal/eventos por (fecha + jornada). Hasta ahora
las jornadas eran fijas (Día/Noche con horas codificadas). El usuario necesita:

1. **Definir él los horarios** — su operación usa varios: 12h 07:00–19:00 y 19:00–07:00,
   y un turno **único** 08:00–21:15. Quiere un catálogo de jornadas editable.
2. **Historial por fecha** — almacenarán turnos de una semana o más; quieren elegir
   fecha + jornada en el tablero para revisar cualquier día.
3. **Excel mejor** — diseño profesional, hoja de cierre de turno, hoja de indicadores,
   y un reporte consolidado por rango de fechas.

## Fases

- **Fase 1** — Jornadas configurables + selector fecha/jornada en el tablero + pantalla
  admin de Jornadas.
- **Fase 2** — Excel rico del turno (diseño profesional + cierre + indicadores) con ExcelJS.
- **Fase 3** — Reporte consolidado por rango de fechas.

Este documento detalla la Fase 1; las Fases 2–3 se especifican al implementarlas.

## Fase 1 · Datos (`sql/006_jornadas_editables.sql`)

`jornadas` pasa a ser un catálogo editable:
- `codigo` → VARCHAR(8) NULL (etiqueta opcional; deja de ser clave única).
- nuevo `activo` TINYINT(1) DEFAULT 1.
- Se actualizan horas reales: Día 07:00–19:00, Noche 19:00–07:00.
- Se agrega "Único" 08:00–21:15.
- La selección de turno pasa a usar `jornada_id` (no el código).

## Fase 1 · Backend

- `includes/turno.php`:
  - `jornada_vigente` solo considera `activo=1`; si ninguna contiene la hora actual,
    cae a la primera activa (fecha hoy).
  - nueva `obtener_turno($conn, $jornadaId, $fecha)`: find-or-create para una jornada
    y fecha explícitas (las del selector).
  - `listar_jornadas` incluye `id` y `activo`.
- `api/get_turno_actual.php`: acepta `?fecha=YYYY-MM-DD&jornadaId=N`. Sin parámetros,
  resuelve el turno vigente (hoy + jornada por hora). Devuelve `turnoFecha`,
  `turnoJornadaId`, y el catálogo `jornadas`.
- CRUD jornadas (admin): `api/get_jornadas.php`, `api/save_jornada.php`,
  `api/delete_jornada.php` (no permite borrar si ya tiene turnos; sugiere desactivar).

## Fase 1 · Frontend

- **Tablero** ([index.php] + [js/estiba.js]): el toggle Día/Noche se reemplaza por
  un **selector de fecha** (input date, default hoy) + **dropdown de jornada** (del
  catálogo). Cambiar cualquiera recarga ese turno. El cálculo de tiempos ya soporta
  jornadas que cruzan medianoche (Noche 19:00–07:00) y normales (Único).
- **Formulario Añadir**: el selector de turno pasa a ser un dropdown de jornada
  (sobre la fecha seleccionada). Agregar registra en ese turno.
- **Pantalla admin** `pages/jornadas.php` (solo Administrador) + link en el sidebar:
  CRUD simple (nombre, hora inicio, hora fin, activo), reusando el lenguaje visual de
  Colaboradores.

## Verificación Fase 1

Migración aplicada; crear/editar una jornada desde la UI; en el tablero elegir fecha y
jornada, agregar personal, recargar y ver que persiste por (fecha+jornada); confirmar
que un turno que cruza medianoche calcula bien tiempos.
