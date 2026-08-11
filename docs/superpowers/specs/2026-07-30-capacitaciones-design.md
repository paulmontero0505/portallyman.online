# Módulo de Capacitaciones · Diseño

**Fecha:** 2026-07-30
**Módulo:** Capacitaciones (`pages/capacitaciones.php`) — nuevo
**Estado:** Aprobado

## Problema

No existe forma de saber si el plan de formación del personal se cumple. Hay
registro de *charlas* (`asistencias_preoperativas`, «Charlas» en el sidebar),
pero es un acta retrospectiva: se llena cuando la charla ya ocurrió, nadie la
revisa y nadie la cierra. Su catálogo incluye un tipo `capacitacion`, lo que
tienta a reutilizarlo, pero el módulo no sabe representar las tres cosas que
definen una capacitación:

1. Que se **programa antes** de ocurrir.
2. Que su contenido —temas y material— se carga **después**.
3. Que alguien distinto del que la dictó **valida** si se realizó, y comenta.

Alcance de esta entrega: un módulo nuevo e independiente con ese ciclo completo.

### Por qué no se extiende «Charlas»

Charlas responde «¿quién asistió y firmó la charla pre-operativa de hoy?».
Capacitaciones responde «¿el plan de formación se cumplió y con qué calidad?».
Meter el ciclo de validación dentro de Charlas obligaría a que **cada** charla
pre-operativa diaria arrastre un estado «pendiente de validar» que nadie va a
cerrar, y la bandeja del administrador quedaría inservible por volumen.

Los dos módulos conviven. Charlas no se toca.

## Ciclo de estados

```
             ┌──────────────┐  coordinador   ┌──────────────┐  administrador  ┌───────────────┐
             │  programada  │ ─────────────▶ │  por_validar │ ──────────────▶ │   realizada   │
             └──────────────┘  «Enviar a     └──────────────┘                 │ no_realizada  │
              solo la ve su     validación»   solo lectura                    └───────────────┘
              coordinador                     para el coord.                   inmutable
```

- **`programada`** es la agenda del coordinador. Se crea con título, fecha y
  hora; el contenido y la asistencia se cargan entrando al registro.
- **`por_validar`** es la bandeja del administrador. Se llega solo por acción
  explícita del coordinador, de modo que el administrador nunca ve registros a
  medio llenar.
- **`realizada` / `no_realizada`** son terminales. No hay reapertura en esta
  entrega (ver «Fuera de alcance»).

**Transición `programada → por_validar` con guardas:** exige al menos un tema y
la asistencia **completa** (ningún colaborador activo sin marcar). Sin esas dos
guardas, «Enviar a validación» sería un botón que traslada trabajo sin hacerlo.

## Modelo de datos

`sql/028_capacitaciones.sql`, idempotente (`CREATE TABLE IF NOT EXISTS`) y sin
`USE`, para correr igual en local y en el servidor.

```sql
capacitaciones
  id, titulo, fecha, hora, duracion_min, lugar, expositor, observaciones,
  estado ENUM('programada','por_validar','realizada','no_realizada'),
  total_plantilla INT NULL,          -- sellado al enviar a validación
  coordinador, coordinador_id, enviado_at,
  validado_por, validado_por_id, validado_at, comentario_admin

capacitaciones_temas       (capacitacion_id, orden, titulo, descripcion)
capacitaciones_asistentes  (capacitacion_id, colaborador_id, + copias congeladas,
                            estado ENUM('asistio','tardanza','falta'))
capacitaciones_adjuntos    (capacitacion_id, nombre_archivo, mime, peso_bytes,
                            drive_file_id, drive_url, ruta_local, estado, error_msg)
```

Decisiones:

- **`capacitaciones_asistentes` solo guarda a los MARCADOS.** «Sin marcar» no es
  un valor del `ENUM`, es la ausencia de fila. Así el estado por defecto no puede
  ser «asistió» por accidente, que es justo el fallo que haría inútil al módulo:
  un coordinador que no abre la pestaña generaría un 100 % de asistencia falso y
  el administrador validaría un dato que nadie miró. La `UNIQUE
  (capacitacion_id, colaborador_id)` hace el guardado idempotente.
- **Las copias congeladas** (`colaborador_nombre`, `_dni`, `_cargo`,
  `_cuadrilla`) siguen el patrón de `asistencias_participantes` y de
  `reconocimientos`: el registro histórico no debe cambiar porque alguien edite
  la ficha del colaborador después.
- **`total_plantilla` se sella al enviar a validación.** Sin él, dar de alta un
  colaborador nuevo movería hacia abajo el porcentaje de asistencia de todas las
  capacitaciones pasadas. Mientras está `programada` vale `NULL` y el conteo se
  calcula en vivo, que es lo correcto: la plantilla de hoy es la que debe asistir.
- **`temas` es una tabla, no un `TEXT`.** Permite contar temas en el listado,
  numerarlos en la vista del administrador y exportarlos. Un textarea corrido no
  sabe hacer ninguna de las tres.
- **`orden` explícito** en los temas: el orden de exposición es información, y
  `ORDER BY id` se rompería al reordenar.

## Catálogo · `includes/capacitaciones_catalogo.php`

Fuente única de verdad, serializada a JS igual que hace `sg_canales()`:

```php
cap_estados()        // clave => [label, color, dot]
cap_asistencia()     // asistio|tardanza|falta => [label, abrev, color]
cap_carpeta_drive()  // 'Capacitaciones'
cap_max_bytes()      // 4 MB, alineado con SG_MAX_BYTES
cap_es_editable($estado)
cap_es_terminal($estado)
```

Los adjuntos reutilizan `sg_tipos_permitidos()` de `drive_config.php`: la lista
de extensiones y MIME permitidos ya está resuelta y validada ahí, y duplicarla
sería garantizar que las dos copias diverjan.

## Permisos

| Acción | Roles |
|---|---|
| Ver el listado | Administrador, Supervisor, Coordinador (`can_report()`) |
| Crear / editar / enviar | El coordinador **dueño** del registro; Administrador y Supervisor sobre cualquiera |
| Validar | **Solo Administrador** (`api_require_admin()`) |
| Eliminar | El dueño mientras esté `programada`; Administrador siempre |

Un coordinador no valida lo suyo: eso vacía de sentido el paso de validación.
La comprobación de propiedad vive en `cap_puede_editar()`, en el catálogo, y la
usan todos los endpoints de escritura — no se repite la regla en cada uno.

**Visibilidad:** cada coordinador ve todas las capacitaciones, no solo las
suyas, con un filtro por coordinador para acotar. Es el mismo criterio ya
adoptado en Incidencias y Sugerencias.

## API

Todos bajo `api/`, JSON, siguiendo el patrón de `save_asistencia.php`
(transacción, `mysqli_prepare`, `{success, error}`).

| Endpoint | Método | Qué hace |
|---|---|---|
| `get_capacitaciones.php` | GET | Listado con temas, adjuntos, conteo de asistencia y datos de validación. Un solo viaje. |
| `get_capacitacion_plantilla.php` | GET | Plantilla activa + las marcas ya guardadas de una capacitación. |
| `save_capacitacion.php` | POST | Alta y edición de cabecera + temas + observaciones, en transacción. |
| `save_capacitacion_asistencia.php` | POST | Reemplaza las marcas. Borra las filas sin marca en lugar de guardarlas. |
| `upload_capacitacion_file.php` | POST | Un archivo a Drive vía `sg_drive_subir()`, con respaldo local. |
| `delete_capacitacion_adjunto.php` | POST | Baja lógica del adjunto (solo mientras sea editable). |
| `enviar_capacitacion.php` | POST | `programada → por_validar`. Valida las guardas y sella `total_plantilla`. |
| `validar_capacitacion.php` | POST | `por_validar → realizada|no_realizada` + comentario. Solo Administrador. |
| `delete_capacitacion.php` | POST | Borra la capacitación (cascada a temas, asistentes y adjuntos). |

`get_capacitaciones.php` devuelve los temas y adjuntos ya anidados en cada fila
mediante dos consultas agregadas (`WHERE capacitacion_id IN (…)`), no una
consulta por capacitación. La plantilla, en cambio, va aparte: son ~38 filas por
capacitación y meterla en el listado multiplicaría el payload sin que la tabla
lo use.

## UI · `pages/capacitaciones.php`

Autocontenida, con su CSS y su JS embebidos, igual que el resto de páginas del
módulo de Control de Campo.

### Listado

```
KPIs:  Programadas · Por validar · Realizadas (mes) · No realizadas · Asistencia media
Toolbar: buscador · chips de estado · select coordinador · select mes · [Nueva capacitación]
Tabla:  Capacitación │ Fecha y hora │ Temas │ Adj. │ Asistencia │ Estado │ Coordinador │
```

La columna de asistencia es una **barra de progreso con `marcados/total`**, no
un número suelto: «22/38» en ámbar se lee de un vistazo, y es la señal que
convierte la tabla en un tablero de cumplimiento.

### Modal de creación

Título, fecha y hora (obligatorios) + duración, lugar y expositor (opcionales).
Nada más. Pedir los temas aquí obligaría a declarar en el momento de programar
algo que solo se sabe después de dictar.

### Modal de detalle · tres pestañas

Con la plantilla completa dentro, un modal de secciones apiladas enterraría los
adjuntos y la validación bajo 38 filas de asistencia. Las pestañas mantienen
cada tarea corta y dejan la barra de acciones siempre visible.

| Pestaña | Contenido |
|---|---|
| **Contenido** | Datos de cabecera, temas (filas numeradas, título + descripción opcional), zona de arrastre de archivos con estado de subida a Drive, observaciones del coordinador. |
| **Asistencia** | Cuatro contadores (asistieron / tardanza / faltaron / **sin marcar**), buscador, filtros por cuadrilla y por coordinador a cargo, y la plantilla agrupada por cuadrilla con tres botones A/T/F por fila. Las filas sin marcar salen en ámbar. |
| **Validación** | Deshabilitada mientras el estado sea `programada`. Para el administrador: resumen en solo lectura de lo declarado, veredicto como dos tarjetas grandes, comentario e historial del ciclo. |

El badge de cada pestaña lleva el conteo (`Contenido 4`, `Asistencia 34/38`),
de modo que se sabe qué falta sin entrar.

**Guardado:** los cambios de Contenido y de Asistencia se persisten por separado
(`save_capacitacion.php` y `save_capacitacion_asistencia.php`), y cambiar de
pestaña no pierde lo escrito porque el estado vive en memoria hasta pulsar
Guardar. La subida de archivos sí es inmediata, porque va contra Drive.

### Sidebar

Entra en el grupo **Control de Campo**, debajo de «Charlas»
(`includes/sidebar.php`).

## Exportaciones

Excel (CSV) y PDF sobre las filas visibles, con el mismo patrón que Sugerencias:
una única `listaVisible()` alimenta tabla, Excel y PDF, así que las
exportaciones heredan los filtros sin código adicional.

## Fuera de alcance

- **Reapertura.** El administrador no puede devolver una capacitación ya
  validada. Añadirla implicaría decidir qué pasa con la asistencia ya marcada y
  un hilo de comentarios; se deja para una segunda entrega.
- **Hilo de comentarios.** Un solo comentario del administrador, no una
  conversación.
- **Firma digital.** `asistencias_participantes.firma_data` existe pero está en
  desuso desde `020`. No se reintroduce.
- **Google Sheets.** `includes/sheets.php` no toca este módulo.
- **Recurrencia.** Nada de «repetir cada mes»; cada capacitación se crea una vez.
- **Enganche con Charlas.** Una capacitación no enlaza a un registro de
  `asistencias_preoperativas`. Los dos módulos son independientes.

## Verificación

1. `sql/028_capacitaciones.sql` corre dos veces seguidas sin error ni duplicados.
2. Crear una capacitación deja `estado='programada'` y `total_plantilla=NULL`.
3. «Enviar a validación» se rechaza sin temas, y se rechaza con temas pero con
   colaboradores sin marcar; el mensaje dice cuál de las dos guardas falló.
4. Al enviar, `total_plantilla` queda sellado con el conteo de activos y
   `enviado_at` con la hora de Lima.
5. Un Coordinador que llama a `validar_capacitacion.php` recibe 403.
6. Un Coordinador que edita una capacitación de otro recibe 403; un
   Administrador, no.
7. Editar una capacitación en `por_validar` o terminal se rechaza.
8. Marcar A, luego T, luego quitar la marca deja al colaborador sin fila en
   `capacitaciones_asistentes` y suma a «Sin marcar».
9. Subir un `.exe` se rechaza; subir un PDF de 5 MB se rechaza por tamaño.
10. Con Drive caído, el adjunto queda con `estado='pendiente'` y `ruta_local`
    poblada — no se pierde.
11. Validar como «No realizada» conserva el registro, sus temas y sus marcas.
12. Borrar una capacitación elimina en cascada temas, asistentes y adjuntos.
13. Filtrar por estado + coordinador + mes y comprobar que Excel y PDF exportan
    exactamente las filas visibles.
