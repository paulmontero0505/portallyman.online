# Coordinador Tallyman a cargo · Diseño

**Fecha:** 2026-07-30
**Módulo:** Colaboradores (`pages/colaboradores.php`)
**Estado:** Aprobado

## Problema

El catálogo maestro de colaboradores (72 registros) no guarda quién es el
Coordinador Tallyman responsable de cada persona. Esa relación se necesita
como dato base: acompañará al colaborador en otros módulos (asistencias,
evaluaciones, reconocimientos, reportes) para poder agrupar y responsabilizar
por coordinador.

Alcance de esta entrega: registrar la relación, verla en el listado y poder
filtrar por ella.

## Origen de los coordinadores

Los coordinadores **son los usuarios del sistema** con `usuarios.rol =
'Coordinador'` y `usuarios.estado = 'Activo'` (hoy 11 personas). No se crea un
catálogo paralelo: al dar de alta un coordinador en el módulo Usuarios aparece
automáticamente en el selector, sin mantenimiento duplicado ni nombres
divergentes.

## Modelo de datos

`sql/024_colaboradores_coordinador.sql`:

```sql
ALTER TABLE colaboradores
  ADD COLUMN coordinador_id INT(11) NULL AFTER cuadrilla,
  ADD KEY ix_coordinador (coordinador_id),
  ADD CONSTRAINT fk_col_coordinador
      FOREIGN KEY (coordinador_id) REFERENCES usuarios(id)
      ON DELETE SET NULL ON UPDATE CASCADE;
```

Decisiones:

- **`NULL` permitido.** Los 72 colaboradores existentes quedan «Sin asignar» y
  la importación desde Excel —que no trae la columna— sigue funcionando.
- **`ON DELETE SET NULL`.** Borrar un usuario coordinador deja a sus
  colaboradores sin asignar en vez de bloquear el borrado o arrastrar registros.
- Ambas tablas son InnoDB, así que la clave foránea se puede crear.

## API

### `api/get_coordinadores.php` (nuevo)

`GET` → `{success:true, data:[{id, nombre}]}` con los usuarios
`rol='Coordinador' AND estado='Activo'`, ordenados por nombre.

Autorización `api_require_login()` —no `api_require_admin()`— para que los
módulos futuros que necesiten la lista puedan consumirla sin ser admin.

### `api/get_colaboradores.php`

Se agrega `LEFT JOIN usuarios u ON u.id = c.coordinador_id`; cada fila incluye
`coordinador_id` (int o `null`) y `coordinador_nombre` (string o `null`). El
`LEFT JOIN` garantiza que los colaboradores sin coordinador se sigan
devolviendo.

### `api/save_colaborador.php`

Acepta `coordinador_id` en el payload. `0`, `""` o ausente ⇒ `NULL`. Si viene
un id, se valida contra la base que exista y tenga `rol='Coordinador'`; si no,
responde `success:false` con mensaje explícito en vez de guardar una referencia
inválida.

La validación acepta coordinadores inactivos ya asignados para no bloquear la
edición de otros campos de un colaborador cuyo coordinador fue desactivado.

### `includes/sheets.php`

`sheets_sync_colaboradores()` añade la columna **Coordinador** a la hoja
TALLYMANS, entre Team y Estado.

## UI · `pages/colaboradores.php`

### Modal de alta/edición

El campo Team pasa a compartir fila (`col-row2`) con el nuevo selector
**Coordinador Tallyman**:

```
┌─ Team ────────────┬─ Coordinador Tallyman ─┐
│ G1 TEAM A         │ — Sin asignar —     ▾  │
└───────────────────┴────────────────────────┘
```

- Las opciones se cargan una vez al iniciar la página desde
  `get_coordinadores.php`.
- Al editar se preselecciona el coordinador actual. Si el colaborador apunta a
  un coordinador que ya no está activo, esa opción se inyecta en el `<select>`
  para no perder la asignación silenciosamente al guardar.
- Campo opcional: guardar sin coordinador es válido.

### Tabla

Nueva columna **Coordinador** inmediatamente después de Puesto:

```
Colaborador       │ Puesto              │ Coordinador          │ Función │ Team │ Estado │ Acciones
```

Se muestra como chip con la inicial y el nombre del coordinador; los no
asignados muestran «Sin asignar» en gris tenue.

### Filtro

`<select>` en la toolbar junto a la búsqueda:

- `Todos los coordinadores` (por defecto)
- una opción por coordinador activo
- `Sin asignar`

Se combina con AND sobre la búsqueda de texto y sobre el filtro
Activos/Inactivos existente. El nombre del coordinador también se vuelve
buscable desde el input de texto.

### KPI

Se agrega un séptimo indicador **Sin coordinador** con el conteo de
colaboradores sin asignar, para detectar de un vistazo lo que falta completar.

## Fuera de alcance

- La importación desde Excel no incorpora columna de coordinador; las filas
  importadas quedan sin asignar y se completan desde el modal.
- El consumo en el resto de módulos (asistencias, evaluaciones,
  reconocimientos) se hará en entregas posteriores. El primer consumidor,
  Incidencias, se describe abajo.

## Primer consumidor · Incidencias

`pages/incidencias.php` muestra el coordinador a cargo del colaborador
incidentado y permite filtrar por él.

### Cuidado con los dos coordinadores

La tabla `incidencias` ya tenía `coordinador` / `coordinador_id`: ese es **quien
registró** la incidencia (usuario de la sesión, congelado al guardar). El
coordinador **a cargo** es otro dato, el de `colaboradores.coordinador_id`.
Conviven, así que la UI los desambigua: la columna existente pasa a llamarse
**Registró** y la nueva es **Coord. a cargo**.

### En vivo, no congelado

`api/get_incidencias.php` resuelve el coordinador a cargo con
`LEFT JOIN colaboradores → usuarios`, devolviendo `coord_cargo_id` y
`coord_cargo_nombre`. No se guarda copia en `incidencias`.

Se eligió así porque el dato responde a «¿de quién es esta persona hoy?»: las
incidencias ya registradas reflejan la asignación actual del colaborador. Con
una copia congelada, toda la historia previa a la creación del campo quedaría
vacía para siempre. El precio es que reasignar un colaborador mueve también sus
incidencias pasadas al nuevo coordinador; si más adelante se necesita
trazabilidad histórica, habrá que añadir una columna congelada en `incidencias`.

### Alcance de los KPIs

El filtro de coordinador acota la **población**: los cuatro KPIs (Total,
Crítico, Alto, Este mes) se recalculan sobre ese subconjunto y aparece un aviso
—«Indicadores del equipo a cargo de X», con enlace *Ver todos*— para que no se
lean como totales globales.

Impacto, declaración y búsqueda son **lentes sobre la tabla** y no mueven los
KPIs: si lo hicieran, filtrar por «Alto» dejaría el KPI de Crítico siempre en
cero.

### Fuera de alcance en Incidencias

Sin exportación ni vista de impresión: sólo visualización y filtro en pantalla.

## Verificación

1. Migración aplicada: `SHOW COLUMNS FROM colaboradores LIKE 'coordinador_id'`.
2. `get_coordinadores.php` devuelve los coordinadores activos.
3. `get_colaboradores.php` devuelve los 72 colaboradores, con
   `coordinador_nombre` en `null` antes de asignar.
4. Guardar un colaborador con coordinador y confirmar que persiste tras
   recargar.
5. Guardar con `coordinador_id` inexistente ⇒ error controlado, sin escritura.
6. Filtrar por un coordinador y por «Sin asignar»; comprobar que combina con
   búsqueda y con Activos/Inactivos.
