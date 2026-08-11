# Módulo de Tareas · Diseño

**Fecha:** 2026-07-30
**Módulo:** Tareas (`pages/tareas.php`) — nuevo
**Incluye:** nuevo rol de usuario **Tally Soporte**
**Estado:** Aprobado

## Problema

El administrador encarga trabajo a los coordinadores tallyman y hoy no queda
rastro de nada: ni de qué se pidió, ni para cuándo, ni de si se entregó, ni de
con qué calidad. El seguimiento vive en conversaciones sueltas, así que nadie
puede responder «¿qué tiene pendiente el coordinador X y desde hace cuántos
días?».

Aparece además un puesto que el sistema todavía no conoce: el **Tally Soporte**,
apoyo directo de un coordinador. Recibe encargos propios —independientes de los
de su coordinador— y debe responder por ellos.

Alcance de esta entrega: el rol nuevo y un módulo con el ciclo completo
*encargar → entregar con evidencia → revisar y calificar*, con plazo, prórroga y
detección de atraso.

### Por qué un módulo nuevo y no una extensión de Sugerencias

Sugerencias va de abajo hacia arriba: el tallyman propone y el administrador
califica viabilidad e impacto. Tareas va de arriba hacia abajo: el administrador
encarga con un plazo y el asignado responde. Comparten la forma —un registro que
alguien califica— pero no el sujeto, ni el plazo, ni el atraso, ni la devolución.
Meterlas en la misma tabla obligaría a que la mitad de las columnas fueran NULL
en cada fila.

## El rol Tally Soporte

Es un **usuario del portal con credenciales propias**, no un colaborador. Tenía
que serlo: el requisito dice que el soporte alimenta sus propias tareas, y para
alimentarlas necesita entrar.

```sql
ALTER TABLE usuarios
  MODIFY rol ENUM('Administrador','Supervisor','Coordinador','Soporte','Operador')
  NOT NULL DEFAULT 'Coordinador';

ALTER TABLE usuarios
  ADD COLUMN soporte_de_id INT(11) NULL AFTER rol;   -- FK → usuarios(id) ON DELETE SET NULL
```

- Valor en BD `'Soporte'`; etiqueta visible **«Tally Soporte»**, traducida en un
  único sitio (`tk_rol_label()`).
- `soporte_de_id` solo tiene sentido cuando `rol='Soporte'`. El formulario de
  Usuarios lo exige en ese caso y lo oculta en los demás; el servidor lo fuerza a
  `NULL` para cualquier otro rol, de modo que un rol cambiado a mano no deje una
  relación huérfana viva.
- **Sin `UNIQUE`.** Un coordinador puede acabar teniendo dos soportes. Bloquearlo
  hoy sería inventar una regla de negocio que nadie pidió.
- `ON DELETE SET NULL`: borrar al coordinador deja al soporte sin jefe asignado,
  no bloquea el borrado. Mismo criterio que `fk_col_coordinador` en `sql/024`.

### Qué NO cambia

`can_report()`, `can_operaciones()`, `can_operate()` y `can_validate()` **no se
tocan**: ninguno incluye `'Soporte'`, así que Incidencias, Reporte de Inspección,
Evaluación Diaria, Charlas, Capacitaciones, Reconocimientos, Operaciones,
Registro Tallyman y Relevo quedan cerrados al rol nuevo sin editar una línea de
esos módulos. Es lo que hace que esta entrega sea quirúrgica.

Lo que sí se toca fuera del módulo, y es todo:

| Archivo | Cambio |
|---|---|
| `includes/auth.php` | Añade `is_soporte()`, `can_tareas()`, `require_tareas()`, `api_require_tareas()` |
| `includes/sidebar.php` | Ítem «Tareas» y rama de sidebar reducido para el Soporte |
| `pages/usuarios.php` | Opción «Tally Soporte» + campo «Coordinador a cargo» condicional + chip `is-sop` |
| `api/save_usuario.php` | Amplía la lista blanca de roles ([línea 30](../../../api/save_usuario.php#L30)) y persiste `soporte_de_id` |
| `api/get_usuarios.php` | Devuelve `soporte_de_id` y el nombre del coordinador |

`index.php` («Turno Actual») solo llama a `require_login()`, así que el Soporte
entra sin cambios.

## Ciclo de estados

```
                        ┌─────────────┐
                        │  pendiente  │◀── el admin la crea
                        └──────┬──────┘
        asignado sube evidencia│y pulsa «Enviar entrega»
                        ┌──────▼──────┐
                        │  entregada  │  (en revisión, nadie la edita)
                        └──────┬──────┘
                     administrador revisa
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
 ┌─────────────┐        ┌─────────────┐        ┌─────────────┐
 │  aprobada   │        │  observada  │        │  rechazada  │
 │  terminal   │        │  vuelve al  │        │  terminal   │
 └─────────────┘        │   asignado  │        └─────────────┘
                        └──────┬──────┘
                               └──▶ reenvía (entregas_count++)
```

| Transición | Quién | Guardas |
|---|---|---|
| — → `pendiente` | Administrador | Título, al menos un destinatario y fecha 1 |
| `pendiente`\|`observada` → `entregada` | El asignado (o el Admin en su nombre) | Al menos un adjunto **o** un comentario de entrega |
| `entregada` → `aprobada` | Administrador | Nota 1-5 obligatoria |
| `entregada` → `rechazada` | Administrador | Nota 1-5 y comentario obligatorios |
| `entregada` → `observada` | Administrador | Comentario obligatorio; nota y 2ª fecha opcionales |

`aprobada` y `rechazada` son terminales: no hay reapertura (ver «Fuera de
alcance»). `pendiente` y `observada` son los dos estados **abiertos**, y son los
únicos en los que se puede acumular atraso.

**La guarda de entrega** —un adjunto o un comentario— existe porque un envío
completamente vacío no comunica nada y sin embargo detiene el reloj del atraso.
No se exige adjunto siempre: hay encargos administrativos cuya entrega es una
respuesta escrita.

**Editar el enunciado** solo es posible en `pendiente` y `observada`. En
`entregada` la tarea está bajo revisión y cambiar lo que se pidió invalidaría lo
que se está juzgando; en los estados terminales, el registro es histórico.

## Plazos, prórroga y «tarea atrasada»

### El plazo vigente

```
plazo vigente = COALESCE(fecha_limite_2, fecha_limite)
```

Una sola función, `tk_plazo_vigente()`, y nadie más decide eso. Ambas fechas son
`DATETIME`: el formulario propone las 23:59 por defecto, porque sin hora «vence
hoy» es ambiguo durante todo el día y no se puede pintar un semáforo honesto.

### El atraso se calcula, no se guarda

**`atrasada` no es un valor del `ENUM` de estado.** Dos razones:

1. Un estado guardado necesitaría un proceso programado que lo voltee a
   medianoche. Este sistema no tiene ninguno corriendo, así que el estado sería
   correcto solo hasta la primera vez que nadie ejecutara el proceso — y esa es
   justo la clase de fallo que no avisa.
2. Calculado, la prórroga funciona sola: en cuanto el administrador fija la 2ª
   fecha, la tarea deja de figurar atrasada **en el acto**, sin actualizar
   ninguna fila y sin riesgo de que quede una marca vieja pegada.

Se derivan dos hechos, que no son el mismo y no deben mezclarse:

| Hecho | Definición | Dónde se ve |
|---|---|---|
| **Atraso abierto** | `estado ∈ (pendiente, observada)` **y** `NOW() > plazo vigente` | Chip rojo `ATRASADA · N días`; la fila sube al tope de la lista |
| **Entregada tarde** | `enviado_at > plazo_al_enviar` | Marca en la fila aunque el estado final sea `aprobada` |

Y un **semáforo del plazo**, también derivado, sobre las tareas abiertas:

| Clave | Condición | Color |
|---|---|---|
| `vencida` | ya pasó el plazo vigente | rojo |
| `hoy` | vence hoy | ámbar |
| `proxima` | vence dentro de 48 h | ámbar claro |
| `a_tiempo` | el resto | gris |

Es lo que convierte la tabla en un tablero de control en lugar de un listado.

### Por qué `plazo_al_enviar` se sella

Al entregar se copia en la fila el plazo que estaba vigente **en ese instante**.
Sin ese sello, la comparación tendría que hacerse contra `COALESCE(fecha_2,
fecha_1)` en tiempo de lectura, y entonces una prórroga concedida *después* de
una entrega tardía convertiría retroactivamente esa entrega en puntual: el dato
que mide el incumplimiento desaparecería justo en el caso que hay que medir. Es
el mismo razonamiento del `total_plantilla` de Capacitaciones.

### La 2ª fecha

- La concede **solo el Administrador**.
- Solo sobre tareas abiertas (`pendiente` u `observada`). Prorrogar algo ya
  entregado no significa nada, y prorrogar algo terminal, menos.
- Debe ser **posterior a la fecha 1**; el servidor lo valida, no solo el
  navegador.
- **Exige motivo** (`prorroga_motivo`). Una prórroga sin motivo es indistinguible
  de un error de digitación.
- Se puede conceder de dos maneras, y las dos quedan en el historial: suelta
  (`prorrogar_tarea.php`) o dentro de la revisión, cuando el veredicto es
  `observada` (misma transacción).
- Retirarla es posible: pone `fecha_limite_2 = NULL` y la tarea vuelve a
  medirse contra la fecha 1.

## Modelo de datos · `sql/029_tareas.sql`

Idempotente (`CREATE TABLE IF NOT EXISTS` y comprobación en
`information_schema` para cada `ALTER`) y **sin `USE`**, para correr igual en
local y en el servidor, como `sql/024`, `026` y `028`.

**Una fila de `tareas` = un responsable = un expediente completo.** Cuando el
administrador encarga lo mismo a cinco personas se generan cinco filas con el
mismo `lote_id`. Cada una lleva su propio plazo, su evidencia, su 2ª fecha, su
nota y su estado, que es exactamente el comportamiento pedido. El modelo
alternativo —una definición con una tabla de asignaciones— obligaría a un JOIN en
cada consulta, KPI y exportación para ganar solo la edición del enunciado en un
sitio; esa ganancia se recupera abajo con `aplicar_a_lote`.

```sql
tareas
  id                 INT PK
  lote_id            INT NULL          -- agrupa las creadas en una misma tanda
  titulo             VARCHAR(180) NOT NULL
  descripcion        TEXT NULL
  prioridad          ENUM('baja','media','alta') NOT NULL DEFAULT 'media'

  -- destinatario (copias congeladas + FK que sobrevive al borrado del usuario)
  asignado_id        INT NULL          -- FK → usuarios(id) ON DELETE SET NULL
  asignado_nombre    VARCHAR(100) NOT NULL
  asignado_rol       ENUM('Coordinador','Soporte') NOT NULL
  coordinador_ref_id     INT NULL      -- si el asignado es Soporte: su coordinador AL CREAR
  coordinador_ref_nombre VARCHAR(100) NULL   -- congelado, igual que asignado_nombre

  -- plazos
  fecha_limite       DATETIME NOT NULL          -- «fecha 1»
  fecha_limite_2     DATETIME NULL              -- «fecha 2»
  prorroga_motivo    VARCHAR(255) NULL
  prorroga_por       VARCHAR(100) NULL
  prorroga_por_id    INT NULL
  prorroga_at        TIMESTAMP NULL

  -- entrega
  estado             ENUM('pendiente','entregada','observada','aprobada','rechazada')
                     NOT NULL DEFAULT 'pendiente'
  entrega_comentario TEXT NULL
  enviado_at         TIMESTAMP NULL    -- «fecha de envío» de la entrega vigente
  plazo_al_enviar    DATETIME NULL     -- sellado: plazo que regía al entregar
  entregas_count     INT NOT NULL DEFAULT 0

  -- revisión
  nota               TINYINT NULL      -- 1..5, escala ed_escala()
  comentario_admin   TEXT NULL
  revisado_por       VARCHAR(100) NULL
  revisado_por_id    INT NULL
  revisado_at        TIMESTAMP NULL

  creado_por         VARCHAR(100) NOT NULL
  creado_por_id      INT NULL
  created_at, updated_at

  KEY ix_tar_asignado (asignado_id), ix_tar_estado (estado),
      ix_tar_fecha (fecha_limite), ix_tar_lote (lote_id)
```

```sql
tareas_adjuntos      -- misma forma que capacitaciones_adjuntos
  id, tarea_id, nombre_archivo, mime, peso_bytes,
  drive_file_id, drive_url, ruta_local,
  estado ENUM('subido','pendiente','error'), error_msg,
  origen ENUM('admin','asignado') NOT NULL,   -- material de referencia vs evidencia
  entrega_nro INT NOT NULL DEFAULT 1,         -- a qué ronda de envío pertenece
  subido_por, subido_por_id, created_at
  FK tarea_id → tareas(id) ON DELETE CASCADE

tareas_historial     -- bitácora, mismo espíritu que turno_acciones (sql/007)
  id, tarea_id,
  accion ENUM('creada','editada','enviada','observada','aprobada','rechazada',
              'prorroga','prorroga_retirada','adjunto','adjunto_borrado'),
  usuario_id, usuario_nombre, usuario_rol,
  detalle TEXT NULL, created_at
  FK tarea_id → tareas(id) ON DELETE CASCADE
```

### Decisiones del modelo

- **`asignado_id` es NULL-able con `ON DELETE SET NULL`, y el nombre y el rol van
  congelados.** Borrar un usuario no debe borrar ni bloquear el historial de lo
  que se le encargó. Mismo patrón que `capacitaciones_asistentes`.
- **`coordinador_ref_id` es el jefe *al crear*, no el jefe actual.** No se usa
  para decidir visibilidad —eso se resuelve contra `usuarios.soporte_de_id`, que
  es la relación viva— sino para que un reporte de hace seis meses siga diciendo
  bajo qué coordinador se encargó aquello. Confundir las dos haría que reasignar
  un soporte reescribiera el pasado.
- **`lote_id` se resuelve sin tabla ni secuencia extra:** dentro de la
  transacción de alta se inserta la primera fila, y su `id` se usa como `lote_id`
  de todas las filas del lote, incluida ella misma. Una tarea creada para una
  sola persona lleva su propio id como lote, así que la columna nunca es un caso
  especial.
- **`entregas_count` es un contador denormalizado a propósito.** Responde «¿la
  hizo bien a la primera?», que es un KPI del listado; derivarlo del historial
  obligaría a una subconsulta por fila en la consulta más caliente del módulo. Se
  mantiene en la misma transacción que inserta el evento en el historial.
- **`comentario_admin` se sobrescribe en cada revisión; el historial conserva el
  texto anterior** en `detalle`. Si el administrador observa dos veces, no se
  pierde qué dijo la primera vez.
- **No hay tabla de comentarios.** El comentario de entrega, el del administrador
  y el historial cubren el ida y vuelta sin montar un hilo de conversación.
- **`entrega_nro` en los adjuntos** distingue la evidencia del primer envío de la
  del reenvío tras una observación. Sin él, el administrador vería un montón de
  archivos sin saber cuáles son la respuesta a lo que observó. Se calcula como
  `entregas_count + 1` en el momento de subir.

## Catálogo · `includes/tareas_catalogo.php`

Fuente única de verdad, serializada a JS desde `pages/tareas.php`, igual que
hacen `sg_canales()` y `cap_estados()`.

```php
tk_estados()          // pendiente|entregada|observada|aprobada|rechazada => [label, color, bg]
tk_prioridades()      // baja|media|alta => [label, color]
tk_rol_label($rol)    // 'Soporte' => 'Tally Soporte'
tk_acciones()         // etiquetas legibles del historial

tk_plazo_vigente($row)     // COALESCE(fecha_limite_2, fecha_limite)
tk_es_abierta($estado)     // pendiente | observada
tk_es_terminal($estado)    // aprobada | rechazada
tk_esta_atrasada($row)     // abierta && NOW() > plazo vigente
tk_dias_atraso($row)
tk_entregada_tarde($row)   // enviado_at > plazo_al_enviar
tk_semaforo($row)          // vencida | hoy | proxima | a_tiempo

tk_puede_ver($row)         // ─┐
tk_puede_editar($row)      //  │ las reglas de permiso viven aquí una sola vez
tk_puede_entregar($row)    //  │ y las llaman TODOS los endpoints
tk_puede_revisar($row)     //  │
tk_puede_prorrogar($row)   // ─┘
tk_filtro_visibilidad()    // fragmento WHERE, construido solo con enteros de sesión

tk_carpeta_drive()         // 'Tareas'
tk_max_bytes()             // SG_MAX_BYTES
tk_max_adjuntos()          // 10 por tarea, contando material de referencia y evidencia
```

**La escala de nota no se redefine aquí.** El catálogo hace
`require_once 'evaluacion_desempeno_catalogo.php'` y usa `ed_escala()`
(1 Deficiente → 5 Sobresaliente). Así el promedio de tareas de un coordinador y
su evaluación diaria hablan el mismo idioma y se pueden leer juntos; duplicar la
escala sería garantizar que las dos copias diverjan, igual que pasaría con
`sg_tipos_permitidos()`, que también se reutiliza tal cual desde
`drive_config.php`.

## Permisos

| Acción | Quién |
|---|---|
| Ver el módulo | Administrador, Supervisor, Coordinador, Soporte (`can_tareas()`) |
| Crear, editar enunciado y fecha 1, eliminar | **Solo Administrador** |
| Conceder o retirar la 2ª fecha | **Solo Administrador** |
| Revisar: aprobar / observar / rechazar + nota | **Solo Administrador** |
| Subir evidencia y enviar la entrega | El asignado; el Administrador puede hacerlo en su nombre y el historial lo registra como tal |
| Borrar un adjunto | Quien lo subió, mientras la tarea esté abierta; el Administrador siempre |

El **Supervisor mira pero no decide**: no crea, no prorroga y no califica. Es el
mismo criterio ya adoptado en Capacitaciones, donde validar es exclusivo del
Administrador.

### Visibilidad

| Rol | Ve |
|---|---|
| Administrador, Supervisor | Todas |
| Coordinador | Las suyas + las de su(s) Tally Soporte, estas en **solo lectura** |
| Soporte | Solo las suyas |

Aquí se rompe a propósito con el criterio de Incidencias y Sugerencias, donde
todos ven todo: esas no llevan una nota personal. Exponer la calificación y los
atrasos de un coordinador ante sus pares es una decisión de gestión de personas
que nadie pidió tomar.

**El filtro se aplica en el SQL, no en PHP después de traer todo.** Un
coordinador no debe descargar en su navegador las notas de otro coordinador ni
siquiera para que la interfaz las oculte.

```sql
-- Administrador / Supervisor
1=1
-- Coordinador (uid de sesión)
(t.asignado_id = uid
 OR t.asignado_id IN (SELECT id FROM usuarios WHERE soporte_de_id = uid))
-- Soporte
t.asignado_id = uid
-- Cualquier otro rol
0=1
```

La última línea no es decorativa: si mañana se añade un rol y alguien olvida
actualizar `can_tareas()`, el fallo será «no veo nada», no «lo veo todo».

## API

Todos bajo `api/`, JSON, con el patrón de `save_capacitacion.php`: transacción,
`mysqli_prepare` y respuesta `{success, error}`.

| Endpoint | Método | Qué hace |
|---|---|---|
| `get_tareas.php` | GET | Listado ya filtrado por visibilidad, con adjuntos anidados y `plazo_vigente`, `atrasada`, `dias_atraso` y `semaforo` calculados en el servidor |
| `get_tarea.php` | GET | Detalle de una tarea + historial completo |
| `get_asignables.php` | GET | Coordinadores y Soportes activos, agrupados, para el selector |
| `save_tarea.php` | POST | Alta multi-destinatario (N filas + `lote_id`) y edición. Solo Administrador |
| `enviar_tarea.php` | POST | `pendiente\|observada → entregada`. Sella `enviado_at` y `plazo_al_enviar`, suma `entregas_count` |
| `revisar_tarea.php` | POST | `entregada → aprobada\|observada\|rechazada` + nota + comentario (+ 2ª fecha si observa). Solo Administrador |
| `prorrogar_tarea.php` | POST | Fija o retira `fecha_limite_2` + motivo. Solo Administrador |
| `upload_tarea_file.php` | POST | Un archivo a Drive vía `sg_drive_subir()`, con respaldo local si Drive falla |
| `delete_tarea_adjunto.php` | POST | Baja del adjunto |
| `delete_tarea.php` | POST | Borra la tarea en cascada. Solo Administrador |

Notas:

- **`get_asignables.php` es nuevo en vez de ampliar `get_coordinadores.php`.**
  Ese endpoint alimenta hoy el selector de Colaboradores; devolver ahí también a
  los Soportes metería en esa lista un puesto que no puede tener colaboradores a
  cargo.
- **El historial va en `get_tarea.php`, no en el listado.** Pesa y la tabla no lo
  usa. Los adjuntos sí van anidados en el listado, con una sola consulta
  agregada (`WHERE tarea_id IN (…)`), no una por tarea.
- **`save_tarea.php` acepta `aplicar_a_lote`** al editar: propaga título,
  descripción, prioridad y fecha 1 al resto del lote, **solo a las filas que
  sigan en `pendiente`**. Corregir la redacción de un encargo enviado a cinco
  personas es una acción, no cinco; pero tocar una tarea ya entregada o ya
  calificada cambiaría el enunciado bajo el que se juzgó.
- **Los cálculos de plazo se hacen en el servidor** y viajan resueltos en el
  JSON. Recalcularlos en JavaScript los ataría al reloj del navegador, que puede
  estar en otra zona horaria; todo el sistema opera en `America/Lima`.

## Interfaz · `pages/tareas.php`

Una sola página que se bifurca por rol. Dos páginas duplicarían el modal de
detalle, el catálogo de estados y las exportaciones. Autocontenida, con su CSS y
su JS embebidos, igual que el resto del módulo de Control de Campo.

### Administrador y Supervisor · tablero

```
KPIs:    Pendientes · Atrasadas · Por revisar · Aprobadas (mes) · Nota media
Toolbar: buscador · chips de estado + chip ATRASADAS · select persona
         (agrupado: Coordinadores / Tally Soporte) · select mes · [Nueva tarea]
Tabla:   Tarea │ Asignado │ Plazo │ Entrega │ Adj. │ Estado │ Nota
```

- **Atrasadas es un chip de filtro**, no un estado más en la fila de chips de
  estado: cruza con cualquiera de los dos estados abiertos.
- **Nota media** es el promedio de `nota` sobre las tareas ya calificadas que
  quedan dentro de los filtros vigentes, no sobre todo el histórico: si no,
  cambiar de mes no cambiaría el número y el KPI no diría nada.
- La columna **Plazo** muestra la fecha vigente con su semáforo y, si hay 2ª
  fecha, la 1ª tachada al lado. Que la prórroga sea visible es media función del
  módulo: una tarea entregada a tiempo *gracias a* una prórroga no es lo mismo
  que una entregada a tiempo.
- La columna **Entrega** muestra `enviado_at` y, si aplica, la marca de entrega
  tardía; en tareas reenviadas, `2.º envío`.
- El **chip de rol** junto al nombre distingue Coordinador de Tally Soporte de un
  vistazo.

**Modal de nueva tarea:** título, descripción, prioridad, **destinatarios en
multi-selección** agrupados por puesto, fecha 1 con hora y adjuntos de referencia
opcionales. Al guardar confirma «Se crearon 5 tareas».

**Modal de detalle:** enunciado y material de referencia → evidencia del asignado
agrupada por ronda de envío → panel de revisión (veredicto en tres tarjetas,
nota 1-5 con las etiquetas de `ed_escala()`, comentario) → bloque de prórroga (2ª
fecha + motivo) → historial en línea de tiempo. El panel de revisión está
deshabilitado mientras la tarea no esté `entregada`.

### Coordinador y Tally Soporte · «Mis tareas»

```
KPIs:  Por hacer · Atrasadas · En revisión · Aprobadas
Lista: ordenada por plazo vigente ascendente; las atrasadas arriba y en rojo
```

El detalle es el enunciado en solo lectura, el material de referencia, la zona de
arrastre de evidencia, el comentario de entrega y **[Enviar entrega]**. Si la
tarea está `observada`, el comentario del administrador encabeza el modal en un
bloque ámbar y el botón pasa a **[Reenviar]**: lo que hay que corregir tiene que
verse antes que el formulario, no debajo.

El coordinador con soporte a cargo tiene además un segmento **«Mi soporte»**, en
solo lectura, con las mismas columnas de plazo y estado.

### Sidebar

**Tareas** entra como ítem de primer nivel, justo debajo de «Turno Actual»: es lo
primero que un asignado abre cada día, no un submódulo de Control de Campo. Para
el Soporte el sidebar se reduce a Turno Actual + Tareas + Cerrar sesión, lo que
obliga a sacar el ítem del bloque `in_array($rol, ['Administrador','Supervisor',
'Coordinador'])` que hoy envuelve a los módulos de operación.

### Exportaciones

Excel (CSV) y PDF sobre las filas visibles, con el patrón ya usado en Sugerencias
y Capacitaciones: una única `listaVisible()` alimenta tabla, Excel y PDF, así que
las exportaciones heredan los filtros sin código adicional.

## Fuera de alcance

- **Notificaciones.** El sistema no envía hoy correos ni mensajes por ningún
  canal; añadir un emisor es un proyecto propio, no una línea de este módulo.
- **Proceso programado de atrasos.** Innecesario: el atraso es derivado.
- **Tareas recurrentes** («cada lunes») y **plantillas de tarea**.
- **Subtareas o checklist** dentro de una tarea.
- **Reapertura** de una tarea ya aprobada o rechazada. Implicaría decidir qué
  pasa con la nota ya puesta; se deja para una segunda entrega.
- **Hilo de comentarios.** El historial cubre la trazabilidad.
- **Asignar tareas a Operadores** o a colaboradores sin usuario del portal.
- **Sincronización con Google Sheets.** `includes/sheets.php` no toca este
  módulo.

## Verificación

1. `sql/029_tareas.sql` corre dos veces seguidas sin error ni duplicados.
2. Un usuario con rol Soporte entra al portal, ve solo Turno Actual y Tareas, y
   recibe 403 en Incidencias, Capacitaciones, Reporte de Inspección y
   Operaciones.
3. Guardar un usuario con rol Soporte sin coordinador a cargo se rechaza;
   cambiarlo a Coordinador limpia `soporte_de_id`.
4. Un Coordinador que llama a `revisar_tarea.php`, `prorrogar_tarea.php` o
   `save_tarea.php` recibe 403.
5. El JSON de `get_tareas.php` para un Coordinador no contiene ni una tarea de
   otro coordinador, y sí contiene las de su soporte.
6. Crear una tarea con 5 destinatarios genera 5 filas con el mismo `lote_id` y
   con `estado='pendiente'`, `entregas_count=0`, `plazo_al_enviar=NULL`.
7. Una tarea vencida y sin entregar figura `ATRASADA · N días` sin que corra
   ningún proceso programado.
8. Conceder la 2ª fecha la saca del atraso de inmediato; retirarla la devuelve.
9. Una 2ª fecha anterior a la fecha 1, o sin motivo, se rechaza **en el
   servidor**, no solo en el formulario.
10. Entregar fuera de plazo y *después* conceder una prórroga **mantiene** la
    marca de entrega tardía.
11. Enviar sin adjunto y sin comentario se rechaza; con solo comentario se
    acepta.
12. Observar y reenviar deja `entregas_count = 2`, conserva el comentario
    anterior del administrador en el historial y etiqueta la evidencia nueva
    como `entrega_nro = 2`.
13. Aprobar sin nota se rechaza; observar sin comentario se rechaza.
14. Editar el enunciado de una tarea `entregada` o terminal se rechaza; con
    `aplicar_a_lote` solo cambian las filas del lote que siguen `pendiente`.
15. Con Drive caído, el adjunto queda con `estado='pendiente'` y `ruta_local`
    poblada — no se pierde.
16. Borrar el usuario asignado deja la tarea viva, con `asignado_nombre` y
    `asignado_rol` legibles.
17. Borrar una tarea elimina en cascada sus adjuntos y su historial.
18. Filtrar por estado + persona + mes y comprobar que Excel y PDF exportan
    exactamente las filas visibles.
