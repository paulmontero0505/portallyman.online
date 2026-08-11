# Sugerencias Tallyman · Viabilidad, Impacto y Coordinador a cargo · Diseño

**Fecha:** 2026-07-30
**Módulo:** Sugerencias Tallyman (`pages/sugerencias.php`)
**Estado:** Aprobado

## Problema

El módulo guarda un solo número, `sugerencias_tallyman.puntaje` (1-10), que el
administrador asigna a las propuestas de mejora. La escala vigente —no escrita en
ninguna parte del código, solo en la cabeza de quien califica— era:

```
1-4   no viable, sin impacto
5-8   impacto mínimo, viable
9-10  impacto alto, viable por mes
```

Esa escala colapsa **dos dimensiones independientes en un solo eje**, y por eso no
sabe representar el caso más interesante: *una propuesta de alto impacto que hoy
no es viable*. No hay número que la describa. Un 9 diría «hágase» y un 3 diría
«no vale nada»; ninguno de los dos es verdad.

El segundo hueco es de trazabilidad: la tabla no muestra el Coordinador Tallyman
a cargo de quien envió la sugerencia, así que no se puede agrupar ni
responsabilizar por coordinador. Y con un único chip de canal y un buscador de
texto, la tabla no permite aislar subconjuntos ("las propuestas de alto impacto
del equipo de X").

Alcance de esta entrega: separar las dos dimensiones, derivar una decisión
legible del cruce, mostrar el coordinador a cargo y añadir filtros.

## Modelo de datos

`sql/026_sugerencias_calificacion.sql`, idempotente con el mismo patrón de
`information_schema` que `024_colaboradores_coordinador.sql` y sin `USE`, para
correr igual en local y en el servidor:

```sql
ALTER TABLE sugerencias_tallyman
  CHANGE COLUMN puntaje viabilidad TINYINT NULL;

ALTER TABLE sugerencias_tallyman
  ADD COLUMN impacto ENUM('minimo','medio','alto') NULL AFTER viabilidad;
```

Decisiones:

- **`puntaje` se renombra a `viabilidad`.** Si conservara el nombre, cualquier
  consulta, export o reporte existente seguiría leyendo el número con el
  significado anterior sin que nada avise. El rename hace que el cambio de
  semántica rompa ruidosamente lo que no se actualizó. El costo es bajo:
  `get_sugerencias.php` es el único lector y `save_sugerencia_puntaje.php` el
  único escritor.
- **`puntaje_comentario`, `puntaje_por` y `puntaje_at` no se tocan.** Describen
  el *acto* de calificar (quién, cuándo, con qué nota al margen), no el número.
  Renombrarlos sería churn sin ganancia.
- **Los dos campos son independientes en BD y en validación.** No hay `CHECK`
  ni regla de servidor que ate uno al otro: `viabilidad=2, impacto='alto'` es un
  registro válido y esperado. Ese es justamente el caso que motivó el rediseño.
- **`impacto` es `NULL`-able.** Sin eso, la migración obligaría a inventar un
  valor para las propuestas ya calificadas.

### Migración de los datos existentes

El valor de `puntaje` se conserva tal cual y pasa a leerse como viabilidad;
`impacto` queda en `NULL`. No se infiere el impacto a partir de la escala vieja.

La inferencia (9-10 → Alto, 5-8 → Mínimo) era tentadora porque dejaba todo
completo de una, pero asume que el administrador aplicó la escala al pie de la
letra. Un 7 puesto pensando «viable, impacto medio» quedaría marcado como
Mínimo: data incorrecta, silenciosa e indistinguible de la correcta. Un `NULL`
visible es honesto y accionable.

Esas filas quedan en el estado **«Pendiente de impacto»** (viabilidad sí,
impacto no) y suman al KPI de calificación pendiente, para que se completen
manualmente.

## Catálogo · `includes/sugerencias_catalogo.php`

Única fuente de verdad, serializada a JS igual que `sg_canales()` hoy. Tres
funciones nuevas:

```php
sg_impactos()            // clave => [label, color, peso]
sg_viabilidad_bandas()   // [max, key, label, color]
sg_decisiones()          // clave => [label, color, icono, glosa]
```

### Impacto

| Clave | Label | Color |
|---|---|---|
| `minimo` | Mínimo | `#64748b` gris pizarra |
| `medio` | Medio | `#F79009` ámbar |
| `alto` | Alto | `#00875A` verde corporativo |

**Los colores van invertidos respecto a `inc_impactos()` de Incidencias**, y es
deliberado. Allí un impacto alto es un daño y se pinta rojo; aquí un impacto
alto es el valor que aporta la propuesta y se pinta verde. Reutilizar la paleta
de Incidencias haría leer «Alto» como alarma.

### Bandas de viabilidad

| Rango | Clave | Label | Color |
|---|---|---|---|
| 1-4 | `no_viable` | No viable | `#DC2626` rojo |
| 5-7 | `ajustes` | Viable c/ajustes | `#F79009` ámbar |
| 8-10 | `viable` | Viable | `#12B76A` verde |

El corte en 4 respeta el umbral que ya se venía usando. La banda intermedia es
nueva: recoge las propuestas que no son inviables pero tampoco están listas.

### Matriz de decisión

El cuadrante se **deriva en la UI y nunca se persiste**. Cambiar los umbrales o
las etiquetas más adelante no requiere migrar ni un registro.

| | **1-4** No viable | **5-7** C/ajustes | **8-10** Viable |
|---|---|---|---|
| **Alto** | ✕ Descartar | ◆ Apuesta | ★ Quick win |
| **Medio** | ✕ Descartar | ◷ Evaluar | ✓ Hacer |
| **Mínimo** | ✕ Descartar | ○ Opcional | ○ Opcional |

Estados incompletos:

- `viabilidad IS NULL` → **«Sin calificar»**
- `viabilidad` presente e `impacto IS NULL` → **«Pendiente de impacto»**

La columna de «no viable» colapsa en Descartar para los tres impactos a
propósito: si no se puede hacer, cuánto valdría es información para el
comentario, no para la decisión. La glosa del cuadrante lo dice explícitamente
(«Alto impacto, pero no es viable hoy»), que es la respuesta visible a la
pregunta que originó el rediseño.

## Coordinador a cargo

### API · `api/get_sugerencias.php`

Se añade `LEFT JOIN colaboradores col ON col.id = s.colaborador_id` y
`LEFT JOIN usuarios u ON u.id = col.coordinador_id`, devolviendo
`coord_cargo_id` (int o `null`) y `coord_cargo_nombre` (string o `null`).

**En vivo, no congelado**, con el mismo criterio y el mismo precio que
Incidencias (ver `2026-07-30-colaborador-coordinador-design.md`): el dato
responde a «¿de quién es esta persona hoy?». Reasignar un colaborador mueve
también sus sugerencias pasadas al nuevo coordinador. Si algún día hiciera falta
trazabilidad histórica, habría que añadir una columna congelada.

Los `LEFT JOIN` son obligatorios, no `INNER`: las observaciones tienen
`colaborador_id` en `NULL` por diseño y un `INNER JOIN` las borraría del
listado.

### Tres estados, no dos

| Caso | Celda |
|---|---|
| Colaborador con coordinador | chip con inicial + nombre |
| Colaborador identificado sin coordinador | «Sin asignar», gris tenue |
| Canal anónimo (`colaborador_id IS NULL`) | «🔒 No aplica» |

La distinción importa: una observación anónima **nunca** podrá tener
coordinador, porque su identidad no se persiste. Mezclarla con «Sin asignar»
inflaría un contador que parece trabajo pendiente y no lo es.

Consecuencia en el filtro: «Sin asignar» significa *colaborador identificado sin
coordinador*. Las filas anónimas quedan fuera de cualquier valor del filtro de
coordinador y solo aparecen bajo «Todos».

### Reutilización

`api/get_coordinadores.php` ya existe y ya responde a `api_require_login()`.
No se crea nada nuevo del lado del catálogo de coordinadores.

## API · calificación

`api/save_sugerencia_puntaje.php` se renombra a
**`api/save_sugerencia_calificacion.php`** (un solo llamador, en
`pages/sugerencias.php:537`). El endpoint escribe ahora dos campos de naturaleza
distinta, y «puntaje» dejó de nombrar a ninguno de los dos.

`sg_puntaje_min()` y `sg_puntaje_max()` conservan su nombre: siguen acotando el
rango numérico 1-10 de la calificación, igual que `puntaje_por` y `puntaje_at`
siguen describiendo el acto de calificar. Lo que se renombra es lo que cambió de
significado, no todo lo que contiene la palabra.

Payload nuevo:

```json
{ "id": 12, "viabilidad": 3, "impacto": "alto", "comentario": "…" }
```

Validación:

- `viabilidad`: `null`, o entero entre `sg_puntaje_min()` y `sg_puntaje_max()`.
- `impacto`: `null`, o una clave existente en `sg_impactos()`.
- **Sin validación cruzada.** Cualquier combinación de los dos es legítima.
- Se mantiene la comprobación de que el registro exista y de que
  `canal = sg_canal_puntuable()`.

`puntaje_por` y `puntaje_at` se siguen sellando en cada guardado.

## UI · `pages/sugerencias.php`

### Modal de calificación

El bloque `.sg-puntaje-box` actual (grid de 10 + textarea + guardar) se amplía:

```
┌ CALIFICACIÓN DE LA PROPUESTA ─────────────────────┐
│ VIABILIDAD (1-10)                                  │
│ [1][2][3][4][5][6][7][8][9][10]                    │
│  ● No viable                     ← banda en vivo   │
│                                                     │
│ IMPACTO ESPERADO                                    │
│ [  Mínimo  ][  Medio  ][  Alto  ]                  │
│                                                     │
│ ╔═══════════════════════════════════════════════╗  │
│ ║ ✕ DESCARTAR                                   ║  │
│ ║ Alto impacto, pero no es viable hoy.          ║  │
│ ╚═══════════════════════════════════════════════╝  │
│                                                     │
│ [ Comentario (opcional)…                        ]  │
│ Calificado por Jeff · 30/07 14:20   [ Guardar ]    │
└─────────────────────────────────────────────────────┘
```

- La etiqueta de banda y el recuadro de decisión se recalculan **al hacer clic,
  antes de guardar**. Ver «Descartar» aparecer en el momento en que se combina
  viabilidad 2 con impacto Alto es lo que enseña el modelo sin necesidad de
  documentarlo.
- Ambos selectores son deseleccionables (volver a pulsar el valor activo lo
  limpia), para poder dejar una propuesta a medio calificar.
- Los tres botones de impacto reutilizan el estilo de `.sg-puntaje-grid button`
  con `grid-template-columns:repeat(3,1fr)`.

### Tabla

De 6 a 8 columnas:

```
Canal │ Colaborador │ Coord. a cargo │ Detalle │ Calificación │ Decisión │ Fecha │ Acciones
```

**Viabilidad e impacto comparten la celda «Calificación»**, como dos chips
apilados. Con columnas separadas serían 9 y la tabla exigiría scroll horizontal
casi siempre, obligando además a encoger `.sg-detalle-preview` por debajo de lo
legible. Que compartan celda no afecta a los filtros, que siguen siendo
independientes.

```
Propuesta │ J. Ramírez │ Ⓜ M. Torres │ Cambiar… │ ● 9/10 Viable    │ ★ QUICK WIN │ 30/07
          │ Estibador  │             │          │ ● Alto           │             │
Propuesta │ L. Quispe  │ Ⓜ A. Vega   │ Comprar… │ ● 3/10 No viable │ ✕ DESCARTAR │ 29/07
          │ Tallyman   │             │          │ ● Alto           │             │
Observac. │ 🔒 Anónimo │ 🔒 No aplica│ El port… │ —                │ —           │ 29/07
```

Las filas no puntuables (observación, consulta, solicitud) muestran «—» en
Calificación y Decisión, igual que hoy con Puntaje.

### Toolbar y filtros

Se conservan el buscador y los chips de canal. Se suman tres `<select>`:

| Filtro | Opciones |
|---|---|
| Coordinador | Todos · cada coordinador activo · Sin asignar |
| Impacto | Todos · Mínimo · Medio · Alto · Sin definir |
| Viabilidad | Todas · No viable (1-4) · C/ajustes (5-7) · Viable (8-10) · Sin calificar |

- Se combinan con AND entre sí, con los chips de canal y con la búsqueda.
- `coord_cargo_nombre` se añade a los campos que recorre el buscador de texto.
- **Filtrar por Impacto o Viabilidad excluye las filas no puntuables.** Pedir
  «impacto alto» implica que solo interesan propuestas; dejar pasar
  observaciones y consultas sin calificación posible sería ruido. El `<select>`
  lo anuncia en su `title`.
- Cada `<select>` con valor distinto del neutro recibe la clase `.on` (borde
  verde), como en Incidencias, para que un filtro activo no pase inadvertido.
- `listaVisible()` sigue siendo la **única** fuente para tabla, Excel y PDF, así
  que las exportaciones heredan los filtros nuevos sin código adicional.

### KPIs

De 4 a 5 indicadores:

| KPI | Definición |
|---|---|
| Total | sin cambios |
| Observaciones | sin cambios |
| Calificación pendiente | propuestas con `viabilidad IS NULL` **o** `impacto IS NULL` |
| Quick wins | propuestas con viabilidad 8-10 e impacto Alto |
| Este mes | sin cambios |

«Calificación pendiente» reemplaza a «Propuestas sin calificar» y amplía su
definición: así las propuestas migradas (con viabilidad pero sin impacto)
aparecen en el contador en lugar de quedar invisibles.

Hay que añadir el color del quinto KPI en `.sg-kpi:nth-child(5) .val`; el CSS
actual solo define hasta el cuarto.

**Alcance de los KPIs.** El filtro de coordinador acota la población y se
muestra el aviso «Indicadores del equipo a cargo de X» con enlace *Ver todos*.
Canal, impacto, viabilidad y búsqueda son lentes sobre la tabla y no mueven los
números — si lo hicieran, filtrar por «Impacto alto» dejaría el KPI de Quick
wins clavado en su propio subconjunto. Es el mismo criterio ya adoptado en
Incidencias.

## Exportaciones

Ambas reemplazan la columna `Puntaje`:

- **Excel (CSV):** `Fecha · Canal · Colaborador · Cargo · Coord. a cargo ·
  Detalle · Adjuntos · Viabilidad · Impacto · Decisión · Calificado por`
- **PDF:** `Fecha · Canal · Colaborador · Coord. a cargo · Detalle · Adjuntos ·
  Viabilidad · Impacto · Decisión`

La cabecera del PDF ya imprime `descripcionFiltro()`; se extiende para incluir
coordinador, impacto y viabilidad cuando estén activos, de modo que un reporte
impreso diga sobre qué subconjunto se generó.

## Fuera de alcance

- No se sincroniza a Google Sheets: `includes/sheets.php` no toca sugerencias
  hoy y no se añade.
- El formulario público (`sugerencias.php`) no cambia: el colaborador nunca
  declara viabilidad ni impacto, eso lo hace el administrador.
- No se añade filtro por cuadrante de decisión. El badge se muestra, pero
  aislarlo se consigue combinando los filtros de viabilidad e impacto.
- No hay histórico de calificaciones: se sigue guardando solo la última, con su
  autor y fecha.

## Verificación

1. Migración aplicada: `SHOW COLUMNS FROM sugerencias_tallyman` muestra
   `viabilidad` e `impacto`, y ya no `puntaje`.
2. Re-ejecutar `026` no falla ni duplica columnas (idempotencia).
3. Los puntajes previos siguen intactos bajo `viabilidad`, con `impacto` en
   `NULL`.
4. `get_sugerencias.php` devuelve todos los registros —incluidas las
   observaciones anónimas— con `coord_cargo_nombre` en `null` para éstas.
5. Guardar `viabilidad=2, impacto='alto'` persiste sin error y la tabla muestra
   «✕ Descartar».
6. Guardar solo viabilidad, sin impacto: persiste y muestra «Pendiente de
   impacto».
7. Guardar `impacto` con una clave inexistente ⇒ error controlado, sin
   escritura.
8. Intentar calificar un registro con `canal != 'propuesta'` ⇒ sigue
   rechazándose.
9. Filtrar por un coordinador y por «Sin asignar»; comprobar que las filas
   anónimas no aparecen bajo ninguno de los dos y que los KPIs se recalculan con
   el aviso de alcance.
10. Combinar coordinador + impacto + viabilidad + búsqueda; comprobar que Excel
    y PDF exportan exactamente las filas visibles.
