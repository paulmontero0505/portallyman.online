# Operaciones · Histórico de naves · Diseño

**Fecha:** 2026-07-30
**Módulo:** Operaciones — submódulo nuevo (`pages/operaciones_naves.php`)
**Estado:** Implementado (Fase 1 y Fase 2)
**Mockup:** `mockups/operaciones_naves_mes.html`

El nombre visible es **Histórico de naves**. Empareja con el submódulo hermano
«Naves», que muestra lo programado y lo que está pasando; éste muestra lo que ya
ocurrió, mes a mes. Los archivos conservan el nombre `operaciones_naves` para no
romper enlaces ni el resaltado del sidebar.

## Problema

No hay forma de responder, para un mes dado, preguntas básicas de gestión: qué
naves se atendieron, cuánto tiempo estuvo cada una operando en muelle, cuántos
turnos consumió y cómo avanzó. La información existe en
`operaciones.tallyman_registros` pero solo se consulta turno a turno, nunca
agregada por nave ni por mes.

## Alcance

**Solo operación en muelle**, desde que la nave empieza a operar hasta que
termina y zarpa. Todo lo de patio (`ubicacion_tipo = 'YARD'`) queda fuera por
decisión explícita: son operaciones distintas y mezclarlas distorsiona tanto los
días de estadía como el avance.

## Fuente de datos

Única fuente: `operaciones.tallyman_registros`, filtrada por

```sql
WHERE ubicacion_tipo = 'BERTH' AND nave_id IS NOT NULL
```

Enriquecida con `operaciones.naves` y `operaciones.tipos_nave` para nombre, tipo,
muelle y ETB/ETD, y con `operaciones.tallyman_actividades` para el nombre de la
actividad.

### Por qué no `avances_nave`

Existe una tabla que parece diseñada justo para esto y **no se usa**:

- Está **vacía** (0 filas).
- Su `turno` es `ENUM('Mañana','Noche')`, un vocabulario que no existe en el
  catálogo `portally_system.jornadas`, donde el turno de día es
  `D · TURNO DÍA (07:00-19:00)`. Los dos sistemas no se pueden cruzar.

`tallyman_registros` sí tiene datos reales, `nave_id`, y distingue muelle de
patio. Es la fuente correcta.

## Modelo de datos

**Ninguna migración.** La Fase 1 es solo lectura sobre tablas existentes.

## Acceso a la base

El submódulo vive en PHP (`pages/`) y consulta **MySQL directo** contra la base
`operaciones` usando las credenciales `OPER_DB_*` que ya centraliza
`includes/db.php`. No pasa por la API Node.

Se eligió así porque el submódulo es una vista agregada de solo lectura: las
consultas son `GROUP BY` sobre un mes completo, algo que la API Node no expone
hoy y que obligaría a traer todos los registros al cliente para agregarlos en
JavaScript. `api/operaciones_proxy.php` ya establece el precedente de leer esa
base directamente desde PHP como fallback.

Autorización: `api_require_operaciones()`, la misma que protege el proxy.

## Los cinco cálculos

Cada número del submódulo sale de una regla explícita. Las reglas importan más
que la UI: son la diferencia entre un dato y una cifra plausible.

### 1 · Ventana de operación (inicio y cierre real)

```
inicio = fecha_turno del registro BERTH con status_act = 'Inicio'
cierre = fecha_turno del registro BERTH con status_act = 'Culminado'
```

Si falta alguno de los dos marcadores, se cae al primer / último `fecha_turno`
con avance en muelle, y la UI lo indica («sin cierre», «en curso»).

Una nave puede empezar en un mes y cerrar en el siguiente. La ventana se recorta
al mes consultado para los conteos, pero el inicio y cierre reales se muestran
completos —«inicio 31/05 NOCHE»— para que no parezca que la operación empezó el
día 1.

### 2 · Días de estadía

Días de calendario entre inicio y cierre, ambos incluidos.

**No es lo mismo que días trabajados**, y la diferencia es justamente lo que
interesa: los huecos. Por eso se muestran ambos y el submódulo no los confunde.

### 3 · Turnos trabajados

```sql
COUNT(DISTINCT CONCAT(fecha_turno, '|', turno))
```

Solo turnos con al menos un registro de avance en muelle. Un turno con tres
registros (tres actividades) cuenta una vez.

Desglose D/N: se agrupa por el valor de `turno` y se resuelve la etiqueta contra
`portally_system.jornadas.codigo`. `tallyman_registros.turno` es `VARCHAR` libre
—el validador solo exige que no venga vacío— así que **un código desconocido se
muestra crudo en lugar de descartarse**. Perder filas en silencio sería peor que
mostrar una etiqueta fea.

### 4 · Turnos sin operación

Turnos que caen dentro de la ventana de estadía y no tienen ningún registro.

Se calculan generando los turnos esperados del rango (fecha × jornadas activas)
y restando los trabajados. Es el indicador de tiempo muerto y el que justifica
mostrar la ventana completa en vez de solo los días con actividad.

### 5 · Avance: TM ejecutadas sobre plan

**Ejecutado** — `SUM(executed)`. Correcto: el campo es incremental por turno.
Lo confirma `tallyman.model.js`, que suma los `executed` de turnos anteriores
para calcular el acumulado previo.

**Planeado** — `planned` es **un solo total por nave**, repetido en cada
registro de muelle. No es un plan por actividad.

Lo fija `tallyman.controller.js`: al guardar el registro con
`status_act='Inicio'` empuja `planned` a la nave con `NavesModel.mergeDatos()`
bajo **una única clave** (`cantidad_total` / `teus` / `vehiculos` según el
tipo), sobrescribiendo. El validador lo dice igual: «el total contra el que se
mide el avance».

De ahí que **dos agregaciones intuitivas den mal**:

| Regla | nave 3 | nave 4 | Comentario |
|---|---|---|---|
| `SUM(planned)` | 4 000 | 7 050 | multiplica por el nº de registros |
| Un plan por `actividad + ubicación` | 2 000 | 4 700 | la nave 4 tiene 2 actividades en el mismo muelle |
| **`planned` del registro con `Inicio`** | **2 000** | **2 350** | correcto |

La regla implementada: el `planned` del registro que marcó `'Inicio'` —el más
reciente si hay varios ciclos—. Si la nave no tiene ese marcador (datos
migrados, ciclos incompletos) se cae al mayor `planned` visto, que es el mismo
número cuando se repite bien. El origen se expone como `plan_origen`
(`'inicio'` | `'maximo'`) y la UI marca con `≈` los avances calculados por
fallback.

Avances resultantes con los datos reales: nave 2 → 30 %, nave 3 → 77,5 %,
nave 4 → 93,6 %.

Un avance puede pasar de 100 %: se muestra tal cual, porque pasarse del plan es
información real. La barra sí se satura visualmente.

## Normalización de `ubicacion`

`tallyman_registros.ubicacion` es texto libre y los datos actuales ya tienen
**«Muelle 2» y «Berth 02» conviviendo para el mismo atracadero**.

Con el plan resuelto por nave, esa inconsistencia ya no afecta al avance. Sí
importa para saber en qué atracaderos operó la nave: sin normalizar, una sola
nave parecería haber estado en dos muelles.

`opn_normalizar_ubicacion()` aplica `TRIM`, mayúsculas, colapso de espacios y
la equivalencia `BERTH nn ≡ MUELLE nn`. El resultado alimenta
`muelles_operados`, que la UI muestra en el detalle **sólo cuando hay más de
uno** — es decir, cuando de verdad hubo cambio de atracadero.

Es un parche sobre un problema de captura. La solución de fondo —que la UI de
tallyman use el catálogo `ubicaciones` en vez de texto libre— queda fuera de
alcance, pero conviene registrarla.

## Archivos

| Archivo | Rol |
|---|---|
| `pages/operaciones_naves.php` | Vista: hero, KPIs, mix, toolbar, tabla, detalle |
| `api/get_operaciones_naves.php` | JSON del mes: una fila por nave con todos los cálculos |
| `includes/operaciones_naves.php` | Cálculos y normalización, compartidos y testeables |
| `includes/sidebar.php` | Operaciones pasa de item simple a grupo con submenú |

`get_operaciones_naves.php` recibe `?mes=YYYY-MM` y devuelve, por nave: datos de
la nave, ventana, días, turnos con desglose, huecos, ejecutado, planeado, avance
y la línea de tiempo de sus turnos. El detalle expandido no dispara una segunda
petición: viene en la misma respuesta, porque son pocos registros por mes y
evita un estado de carga por fila.

## UI

Detallada en `mockups/operaciones_naves_mes.html`. Resumen:

**KPIs (5):** Naves atendidas · Días de estadía · Turnos trabajados ·
TM movilizadas · Turnos sin operación.

**Tabla:** `Nave · Tipo · Muelle · Operación en muelle · Días · Turnos · Avance ·
Personas · Estado`. La columna «Operación en muelle» muestra la ventana
`02/06 → 09/06` con el inicio y cierre reales debajo.

**Detalle expandido**, en dos columnas:

- *Ventana de operación* — rejilla D/N de la estadía. Verde = turno con avance,
  ámbar = sin operación, gris = sin registro, vacío = fuera de la ventana.
  Hace **auditable** el conteo: no dice «13 turnos», muestra cuáles.
- *Cómo fue el proceso* — línea de tiempo por turno con actividad, TM del turno,
  acumulado sobre el plan, productividad y coordinador entrante. Los huecos
  aparecen como fila ámbar en vez de omitirse.

**Filtros:** búsqueda por nave/muelle, y selects de tipo, muelle y estado.
Selector de mes en el hero.

### Columna «Personas»

Se muestra con un badge **FASE 2** y sin número.

No existe relación entre colaborador y nave: `turno_personal` guarda `ubicacion`
como texto del catálogo y **no tiene `nave_id`**. Se descartaron las tres
inferencias posibles porque las tres dan **cero** contra los datos reales:

- Cruzar `turno_personal.ubicacion` con `naves.muelle`: 0 coincidencias (hay
  gente en «Muelle 1» y las naves están en Muelle 4, Muelle 2 y una con `muelle`
  en NULL).
- Puentear vía `tallyman_registros`: los vocabularios de ubicación solo coinciden
  en «Muelle 1» y las fechas de ambos sistemas no se solapan en un solo día.

Mostrar un número inferido sería peor que no mostrarlo: el día que empiece a dar
cifras no habrá contra qué contrastarlas. El diseño reserva el sitio —un bloque
punteado en el detalle— para que la Fase 2 encaje sin rediseñar.

## Fase 2 · Personas por nave (implementada)

`sql/027_turno_personal_nave.sql` añade `nave_id INT(11) NULL` a
`turno_personal`, más el índice `ix_tp_nave`.

**Sin clave foránea:** `portally_system` y `operaciones` son bases distintas y la
arquitectura —API Node con su propia BD— apunta a que se separen de servidor.
Se valida en aplicación con `opn_validar_nave()`, igual que hace
`tallyman_registros`, que tampoco referencia nada de `portally_system`.

### Captura

Un `<select>` de nave junto a Ubicación, en los dos sitios donde se asigna:
el modal de alta masiva y el drawer de cambio de puesto (`index.php` +
`js/estiba.js`).

- **Sólo aparece si la ubicación es un muelle** (`/^(muelle|berth)\b/i`). Gate,
  Balanza o Administrativo no atienden nave, y ofrecer el campo ahí invitaría a
  rellenarlo con cualquier cosa.
- Si hay **exactamente una** nave atracada en esa ubicación, se preselecciona.
  Se sugiere, no se impone.
- Al mover a alguien fuera de un muelle, **la nave se limpia**: mantenerla
  dejaría a una persona en Balanza «atendiendo» una nave.
- Una nave guardada que ya no esté en la lista (finalizó, cambió de muelle) se
  inyecta igualmente en el `<select>` para no perderla en silencio al guardar.
- El cambio de nave entra en la auditoría con el **nombre**, no el id: la lee
  una persona, y el nombre sigue siendo legible aunque la nave se borre.

Endpoints tocados: `get_naves_muelle.php` (nuevo), `add_personal_turno.php`,
`add_personal_turno_masivo.php`, `update_asignacion.php`, `get_turno_actual.php`.

`get_turno_actual.php` resuelve el nombre de la nave con una **consulta aparte**
contra Operaciones, no con un JOIN entre bases: así el turno sigue cargando si
esa base no responde, y el SQL no asume que ambas vivan en el mismo servidor.

### Consumo

`opn_dotacion_por_nave()` agrupa `turno_personal` por nave y por
`fecha + jornada`, y devuelve **cantidades netas y ubicaciones, sin nombres**.
La columna «Personas» muestra el total de personas distintas del mes con
`prom X · pico Y / turno`, y el detalle expandido lista turno a turno cuánta
gente hubo y en qué ubicaciones.

**`dotacion === null` ≠ `0 personas`.** `null` significa que ningún turno de esa
nave tiene `nave_id`, es decir que el periodo es anterior a la captura; la UI lo
muestra como «SIN TRAZA». Cero significaría que sí hay trazabilidad y nadie
estuvo asignado. Confundirlos convertiría una laguna de datos en una afirmación.

Los meses anteriores a la migración siguen mostrando «SIN TRAZA», que es la
verdad: no son reconstruibles.

## Bug corregido · el estado «Traslado» se perdía

`turno_personal.estado` era `ENUM('activo','refrigerio','incidencia')`, pero el
drawer de `index.php:815-817` **ofrece «Traslado» y «Permiso»** y
`api/update_asignacion.php:24` los acepta. Con `sql_mode` sin
`STRICT_TRANS_TABLES`, esos dos valores se guardaban como **cadena vacía** en
vez de fallar.

No era teórico: quedó registrado en la propia auditoría del sistema, con el
valor anterior en blanco —`estado:  → Traslado`, `estado:  → Refrigerio`—.

La migración 027 amplía el enum a los cinco estados y normaliza a `'activo'` las
filas que se hubieran guardado vacías. Verificado: escribir `'traslado'` ahora
persiste como `traslado`.

## Pendiente · los cambios de ubicación sólo quedan como texto

Están en `turno_acciones.detalle` con el formato
`ubicación: Muelle 2 → Muelle 1`. Origen y destino se guardan, pero hay que
parsear la cadena: si alguien cambia el formato del mensaje, el parseo deja de
funcionar en silencio. Y `turno_personal.ubicacion` se sobrescribe, así que la
fila sólo conserva la ubicación final del turno.

`turno_eventos` tiene un `tipo = 'traslado'` estructurado y **cero filas**: la
tabla que existe para esto no se está usando. Migrar los traslados ahí queda
fuera de alcance.

## Verificación

1. Un mes sin naves devuelve lista vacía y KPIs en cero, sin error.
2. Una nave que empieza en el mes anterior muestra su inicio real (mayo) y
   cuenta solo los turnos del mes consultado.
3. Una nave sin `status_act='Culminado'` aparece como «en curso» y usa el último
   turno con avance como cierre provisional.
4. Los registros con `ubicacion_tipo='YARD'` **no** afectan a ningún número.
5. Los registros con `nave_id IS NULL` se excluyen sin romper los totales.
6. El plan de una nave con varios turnos **no** se multiplica: contrastar contra
   la tabla de la sección de avance (nave 3 → 2 000, nave 4 → 2 350).
7. Una nave sin registro `'Inicio'` cae al plan máximo y su avance se marca
   con `≈`.
8. «Muelle 2» y «Berth 02» de la misma nave se agrupan como un solo atracadero
   y no aparecen como dos en `muelles_operados`.
9. Un `turno` con código fuera de las jornadas **activas** marca su fecha como
   «opaca»: la fila cuenta como turno trabajado y **no** genera huecos falsos
   para ese día. Verificado con el código `U` (jornada inactiva) de los datos
   actuales.
10. Una nave con `executed > planned` muestra el porcentaje real por encima de
    100 y la barra saturada.
11. Los turnos sin operación coinciden con los huecos ámbar de la rejilla.

### Fase 2

12. Re-ejecutar `027` no falla ni duplica columna, índice ni valores del enum.
13. Guardar `estado='traslado'` persiste como `traslado` y **no** como cadena
    vacía. Ídem `'permiso'`.
14. El selector de nave desaparece al elegir una ubicación que no es muelle, y
    la nave se limpia al guardar.
15. Una nave con turnos asignados muestra personas, promedio, pico y el
    desglose por ubicación; una sin ellos muestra «SIN TRAZA», **no** cero.
16. Guardar un `naveId` inexistente ⇒ error controlado, sin escritura.
17. Si la base de Operaciones no responde, la pantalla de turno sigue cargando
    y el selector de nave queda vacío en vez de bloquear la asignación.
