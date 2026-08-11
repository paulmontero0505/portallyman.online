# EVADES · Evaluación trimestral por bloques

**Fecha:** 2026-08-05
**Módulo:** EVADES existente (`pages/evades.php`)
**Estado:** Aprobado
**Complementa:** `2026-08-03-evades-design.md`

## Objetivo

Evolucionar el módulo EVADES existente para que el coordinador no cree las
evaluaciones persona por persona. En su lugar, seleccionará un puesto y un
periodo trimestral; el sistema generará y calculará, en una sola operación,
las evaluaciones de todos los colaboradores que le correspondan.

Esta entrega no crea otro módulo ni reemplaza el motor EVADES actual. Extiende
la página, las APIs, el catálogo, el motor y las tablas ya existentes,
preservando las evaluaciones históricas, la consulta individual y la
exportación PDF.

## Alcance funcional aprobado

- Evaluación agrupada por `coordinador + puesto + periodo trimestral`.
- Puestos admitidos:
  - `ASISTENTE DE ESTIBA`.
  - `ANALISTA DE TROUBLE DESK`.
- Ambos puestos usan la misma matriz EVADES de diez competencias y las mismas
  reglas de puntuación y automatización.
- El coordinador se obtiene de la sesión; no se selecciona manualmente.
- La nómina del bloque queda congelada al generarlo.
- Estados del bloque: `generado`, `revisado`, `modificado`, `cerrado`.
- Historial de transiciones y modificaciones.
- Cierre final e irreversible desde el flujo ordinario.
- Conservación del lenguaje visual esmeralda actual.

## Modelo conceptual

### Bloque maestro

Un bloque representa una combinación única de:

```text
coordinador + puesto + periodo trimestral
```

El bloque contiene el estado común, las fechas del flujo, el responsable y el
resumen agregado. Sus evaluaciones individuales continúan almacenando los
puntajes, evidencias y textos de cada colaborador.

La unicidad debe impedir que se genere dos veces el mismo bloque. La
normalización del puesto debe tratar mayúsculas/minúsculas de forma uniforme y
usar exclusivamente los dos nombres admitidos.

### Nómina congelada

Al generar un bloque se consultan los colaboradores activos cuyo
`coordinador_id` corresponde al usuario responsable y cuyo
`funcion_principal` corresponde al puesto seleccionado. Se crea una evaluación
por cada persona encontrada.

La relación queda congelada mediante las evaluaciones hijas y sus datos de
respaldo: identificador, código, nombre, cargo y coordinador. Si después cambia
la asignación o el puesto de un colaborador, el bloque ya creado no se altera.

### Esquema propuesto

La migración siguiente a `sql/031_evades.sql` añadirá:

1. `evades_bloques`
   - `id`.
   - `coordinador_id`, `coordinador_nombre`.
   - `puesto`.
   - `periodo`.
   - `estado`.
   - `total_colaboradores`.
   - `version` para concurrencia optimista.
   - `generado_at`, `revisado_at`, `modificado_at`, `cerrado_at`.
   - `generado_por`, `cerrado_por`.
   - `created_at`, `updated_at`.
   - Restricción única por coordinador, puesto y periodo.
2. `evades_evaluaciones.bloque_id`
   - Relación nullable para conservar registros anteriores.
   - Índice para recuperar las evaluaciones de un bloque.
3. `evades_bloques_estados`
   - Bloque, estado anterior, estado nuevo, usuario, fecha y contexto.
4. `evades_modificaciones`
   - Bloque, evaluación, colaborador, usuario, motivo y fecha.
   - Valores anteriores y posteriores en JSON.

Las evaluaciones existentes permanecen consultables aunque `bloque_id` sea
`NULL`. No se borran, reemplazan ni recalculan durante la migración.

## Flujo y máquina de estados

```text
Generar bloque
      |
      v
  GENERADO --primera apertura--> REVISADO --primer guardado--> MODIFICADO
      |                              |                              |
      +------------------------------+------------------------------+
                                     |
                               cerrar bloque
                                     v
                                  CERRADO
```

### Generado

- Se crea el bloque y toda su nómina en una transacción.
- Se ejecuta el motor para cada colaborador.
- Todavía es editable.

### Revisado

- Se asigna automáticamente en la primera apertura del bloque.
- Abrirlo nuevamente no genera transiciones repetidas.
- Todavía es editable.

### Modificado

- Se asigna al primer guardado que cambie información persistida.
- Cada guardado posterior conserva el estado y añade una auditoría.
- Un guardado sin cambios no crea una modificación vacía.

### Cerrado

- Requiere una acción explícita y confirmación.
- El servidor valida la integridad completa del bloque.
- Después del cierre, ninguna API de edición, eliminación o recálculo puede
  modificar el bloque o sus evaluaciones.
- No existe reapertura en el flujo normal. Una corrección excepcional futura
  deberá ser administrativa, explícita y auditada.

## Generación masiva y cálculo

La generación recibe únicamente `puesto` y `periodo`. El coordinador procede
de la sesión. Para administradores que actúen sobre un coordinador, la
selección explícita deberá validarse en el servidor y quedar auditada.

El servidor:

1. Valida permisos, puesto y periodo.
2. Comprueba que el bloque no exista.
3. Obtiene y bloquea lógicamente la nómina elegible.
4. Crea el bloque.
5. Para cada colaborador, ejecuta el motor actual y guarda la cabecera y las
   diez competencias con sus evidencias.
6. Registra la transición inicial a `generado`.
7. Confirma la transacción.

Si un colaborador no puede calcularse o persistirse, se revierte la operación
completa y se informa cuál registro produjo el error.

## Automatización por colaborador

El rango de fechas se deriva de `YYYY-T1` a `YYYY-T4`. Para ambos puestos se
mantienen:

- Diez competencias.
- Base de 6 por competencia.
- Incrementos finales permitidos por las reglas EVADES actuales.
- Descuentos según frecuencia e impacto de la matriz FI.
- Rango de 0 a 10 por competencia.
- Total de 0 a 100 y clasificación vigente.
- Puntaje anterior y variación frente al periodo previo.

El motor consulta dentro del trimestre:

- Reconocimientos aprobados.
- Incidencias del colaborador.
- Frecuencia por competencia.
- Mayor impacto registrado.
- Descuento resultante de la matriz FI.
- Evaluaciones diarias usadas por la regla de Autonomía.

La interfaz debe explicar el origen de cada valor automático; por ejemplo,
`3 incidencias · impacto alto · descuento -8`. La evidencia almacenada debe
permitir reconstruir el resultado sin depender de datos futuros del catálogo.

Un ajuste manual que contradiga un valor automático exige motivo. Al guardar,
el servidor recalcula las fuentes y valida los valores finales. Las
competencias que el catálogo vigente marca como manuales conservan ese
comportamiento.

## Interfaz dentro de EVADES

### Pantalla principal

La unidad principal de la lista pasa a ser el bloque. Cada fila o tarjeta
muestra:

- Puesto y periodo.
- Coordinador.
- Estado con texto, icono y color.
- Cantidad de colaboradores.
- Progreso de evaluaciones completas.
- Promedio general.
- Distribución por clasificación.
- Última modificación.

Las evaluaciones históricas sin bloque deben seguir accesibles desde una vista
compatible o una sección de históricos.

### Modal de nueva evaluación

El modal contiene:

- Selector de puesto con las dos opciones aprobadas.
- Selector de trimestre.
- Vista previa del número y lista de colaboradores encontrados.
- Acción `Generar bloque`.

No se selecciona una persona. Si no hay colaboradores elegibles, la acción se
deshabilita y se explica el motivo.

### Espacio de trabajo del bloque

- Cabecera con puesto, periodo, estado, avance y promedio.
- Panel lateral con buscador y nómina congelada.
- Cada persona muestra puntaje, clasificación y estado de completitud.
- Área principal reutiliza el formulario individual existente.
- Resumen visible de valores y evidencias automáticas.
- Navegación `Anterior` y `Siguiente`.
- Advertencia antes de cambiar de persona con cambios sin guardar.
- Acciones `Guardar cambios` y `Cerrar evaluación`.

En móvil, la nómina lateral se reemplaza por un selector y el formulario ocupa
todo el ancho. Los controles principales permanecen accesibles sin cubrir los
campos.

### Estados visuales

Se conserva el tema esmeralda del módulo:

- `Generado`: neutro azulado.
- `Revisado`: verde suave.
- `Modificado`: ámbar.
- `Cerrado`: verde oscuro.

El estado nunca se comunica únicamente mediante color.

## Guardado, auditoría y concurrencia

El guardado ocurre por evaluación individual dentro del bloque. La API recibe
el identificador del bloque, la evaluación, su versión y los datos editados.

Antes de actualizar:

1. Verifica que el bloque pertenece al usuario o que este tiene privilegios.
2. Rechaza el estado `cerrado`.
3. Comprueba la versión recibida.
4. Recalcula sugerencias automáticas.
5. Valida motivos, rangos y estructura de las diez competencias.
6. Calcula total, clasificación y variación.
7. Actualiza evaluación, competencias, auditoría y estado en una transacción.

Si la versión cambió desde que se abrió la pantalla, la API devuelve conflicto
y obliga a recargar. No se sobrescriben silenciosamente cambios ajenos.

La auditoría registra únicamente campos modificados, valores anteriores y
nuevos, usuario, fecha y motivo. Los cambios de estado se registran además en
su historial específico.

## Cierre del bloque

Antes de cerrar, el servidor valida:

- Que el bloque tenga al menos una evaluación.
- Que toda evaluación tenga las diez competencias.
- Que los puntajes y totales sean consistentes.
- Que los ajustes automáticos contradichos tengan motivo.
- Que los campos narrativos definidos como obligatorios estén completos.
- Que no existan conflictos o guardados pendientes.

El cierre actualiza el bloque y su historial en una transacción. Desde ese
momento, las APIs de guardar, recalcular, eliminar y regenerar deben responder
con un error de dominio estable. La interfaz queda en modo lectura y conserva
detalle, historial y exportación.

## Permisos

- **Coordinador:** solo genera, consulta, modifica y cierra bloques de su
  propia nómina.
- **Administrador:** consulta todos y puede operar sobre bloques no cerrados;
  toda actuación sobre otro coordinador queda auditada.
- **Supervisor:** mantiene la visibilidad que ya autoriza EVADES; las acciones
  de escritura deben ajustarse a la política vigente y validarse explícitamente
  durante la implementación.
- **Todos:** ningún rol edita un bloque cerrado desde el flujo ordinario.

Los permisos se comprueban en cada API y no dependen de que un botón esté
oculto en la interfaz.

## APIs

Se adaptan las APIs existentes y se añaden endpoints de bloque cuando la
responsabilidad no encaje limpiamente:

- Listar y consultar bloques con su resumen y nómina.
- Previsualizar personal por puesto y periodo.
- Generar un bloque transaccionalmente.
- Abrir/marcar como revisado de forma idempotente.
- Guardar una evaluación hija.
- Cerrar un bloque.
- Consultar historial de estados y modificaciones.

`get_evades.php` debe conservar compatibilidad de lectura con evaluaciones
anteriores. `save_evades.php` y `calcular_evades.php` pueden reutilizarse
internamente, pero las operaciones nuevas deben tener contratos claros de
bloque y no aceptar un colaborador arbitrario para la generación normal.

## Compatibilidad y migración

- No se elimina ninguna columna ni tabla actual.
- `bloque_id` comienza nullable.
- La restricción individual `colaborador_id + periodo` continúa evitando
  duplicados de una misma persona en el mismo trimestre.
- La lista histórica reconoce registros sin bloque.
- La consulta y PDF individual continúan funcionando.
- La migración debe poder ejecutarse más de una vez de manera segura según el
  patrón usado por el repositorio.

## Manejo de errores

Se devuelven mensajes específicos para:

- Bloque ya existente.
- Nómina vacía.
- Colaborador fuera de la nómina del coordinador.
- Puesto o periodo inválido.
- Falla de una fuente automática.
- Ajuste sin motivo.
- Evaluación incompleta.
- Conflicto de versión.
- Bloque cerrado.

No se deja un bloque parcialmente generado ni un cambio auditado sin su
actualización correspondiente.

## Verificación

### Pruebas de dominio y servidor

1. Matriz FI y clasificación existentes continúan pasando.
2. `Asistente de Estiba` y `Analista de Trouble Desk` son aceptados y usan las
   mismas diez competencias.
3. Un coordinador solo obtiene su nómina.
4. La generación crea un bloque y una evaluación completa por colaborador.
5. Una falla intermedia revierte todo el bloque.
6. No se permite duplicar coordinador, puesto y trimestre.
7. Primera apertura: `generado -> revisado` una sola vez.
8. Primer cambio: `revisado/generado -> modificado`.
9. Cada cambio real crea auditoría; guardar sin cambios no la duplica.
10. Un ajuste automático sin motivo es rechazado.
11. Una versión antigua produce conflicto.
12. El cierre incompleto es rechazado.
13. El cierre válido deja el bloque inmutable para todos los endpoints.
14. Las evaluaciones históricas sin bloque siguen consultándose.

### Pruebas de interfaz

1. Nueva evaluación no permite seleccionar una persona.
2. La vista previa coincide con la nómina que finalmente se genera.
3. El panel permite recorrer, buscar y guardar colaboradores.
4. Cambios sin guardar generan advertencia.
5. Estados, avance y métricas se actualizan correctamente.
6. El modo cerrado no muestra controles editables.
7. Escritorio y móvil son utilizables.
8. PDF y detalle individual existentes no presentan regresiones.

## Fuera de alcance

- Crear un módulo EVADES alternativo.
- Cambiar la periodicidad trimestral.
- Diseñar otra matriz para Analista de Trouble Desk.
- Reabrir bloques cerrados desde el flujo normal.
- Incorporar nuevas categorías de incidencias o reconocimientos no existentes.
- Sustituir la Evaluación Diaria.
