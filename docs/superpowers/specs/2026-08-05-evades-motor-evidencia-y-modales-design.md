# EVADES · Motor integral de evidencia y experiencia híbrida

**Fecha:** 2026-08-05
**Módulo:** EVADES existente (`pages/evades.php`)
**Estado:** Aprobado para implementación
**Complementa:** `2026-08-03-evades-design.md` y `2026-08-05-evades-evaluacion-por-bloques-design.md`

## Objetivo

Completar el motor EVADES para que las diez competencias se calculen con los
criterios y relaciones cruzadas de `Matriz Guia EVADES.xlsx` y
`Guia_Evaluacion_Desempenio_EVADES.pdf`, y reemplazar los modales actuales por
la experiencia híbrida aprobada: generación con vista previa de cobertura,
nómina lateral para evaluar el bloque y consulta final completamente trazable.

El problema observado no es que el puntaje base sea incorrecto. La guía fija
seis puntos por competencia. El problema es que el motor actual solo consume
incidencias y reconocimientos por coincidencia directa, por lo que deja fuera
las relaciones cruzadas, los criterios de Evaluación en Puesto de Trabajo
(EPT), asistencia, propuestas y apreciaciones documentadas.

## Fuentes normativas verificadas

La implementación toma como autoridad, en este orden:

1. Anexo 2 de la guía PDF para la matriz frecuencia por impacto.
2. Matriz Excel para definición, evidencia positiva, evidencia negativa y
   competencia cruzada de cada criterio.
3. Catálogos internos para traducir registros operativos a esas competencias.
4. Reglas operativas explícitas de esta especificación cuando la guía describe
   evidencia, pero no define un umbral numérico.

La guía establece:

- base de 6 por competencia;
- incremento único de +2 o +4 por competencia;
- descuento por matriz de frecuencia e impacto;
- puntaje final entre 0 y 10;
- uso exclusivo de evidencia del trimestre evaluado;
- trazabilidad y motivo obligatorio de toda decisión manual.

## Alternativas consideradas

### 1. Mantener automatización parcial

Conservar el cruce directo actual y dejar el resto manual. Tiene bajo costo,
pero reproduce el problema reportado: demasiadas filas permanecen en seis y no
se aprovechan datos ya existentes.

### 2. Automatización total sin control humano

Convertir cada registro del sistema en puntos automáticamente. Es rápida para
el coordinador, pero arriesga inferencias injustificadas donde la guía exige
apreciación profesional o donde todavía no existe una base individual de
productividad.

### 3. Motor híbrido explicable — aprobado

Automatizar solo las fuentes objetivas, aplicar relaciones cruzadas de forma
visible y ofrecer apreciación estructurada para los criterios sin datos
suficientes. Esta alternativa fue aprobada junto con la interfaz híbrida B.

## Principios del cálculo

1. Cada competencia inicia en 6; seis se mostrará como **Base**, nunca como
   “mínimo”.
2. El incremento automático será el nivel positivo más alto sustentado por
   cualquier fuente: 0, +2 o +4. Varias fuentes no se suman entre sí.
3. Se elimina el incremento +6 de Autonomía: contradice el máximo de +4 por
   competencia definido en la guía.
4. El descuento se calcula con todos los incidentes aplicables a la competencia
   dentro del trimestre: frecuencia con tope 5 y mayor impacto del conjunto.
5. Un incidente puede alimentar una competencia primaria y su competencia
   cruzada cuando la guía declara esa relación. La interfaz debe señalar que es
   un cruce, para que no parezca un registro duplicado.
6. El resultado es `clamp(6 + incremento - descuento, 0, 10)`.
7. Un bloque cerrado conserva su fotografía de cálculo y jamás se recalcula.
8. Un bloque abierto recalcula fuentes al guardar; si cambiaron desde la
   apertura, se informa al usuario antes de persistir valores distintos.

## Umbrales operativos para evidencia cuantitativa

La guía define +2/+4, pero no convierte promedios EPT a esos niveles. Para
evitar decisiones ocultas se fijan umbrales configurables en el catálogo:

- mínimo 3 EPT relevantes dentro del trimestre;
- promedio desde 4.0 hasta menor que 4.5: +2;
- promedio igual o mayor que 4.5: +4;
- con menos de 3 observaciones: la EPT se muestra como contexto y no altera
  puntos automáticamente.

Estos umbrales se aplican por criterio, no sobre el `puntaje_total` completo de
la EPT. El motor decodifica `evaluacion_desempeno.criterios` y promedia únicamente
los ítems vinculados a cada competencia.

La ausencia de incidentes solo constituye evidencia positiva para Eficiencia y
Dominio Sólido cuando existen al menos 3 EPT relevantes. No se premiará
automáticamente a una persona sin observaciones.

## Mapa completo de competencias

### Autonomía

- Positivo automático: reconocimiento aprobado de Autonomía o promedio del
  criterio EPT “disposición para apoyar a otros” con los umbrales definidos.
- Negativo: evidencia EVADES estructurada por necesidad de supervisión
  constante; usa la matriz frecuencia-impacto.
- Sin relación cruzada.

### Organización y Gestión del Tiempo

- Positivo: reconocimiento aprobado, apreciación documentada por cumplimiento
  anticipado y asistencia verificada cuando haya al menos 3 eventos asignados.
- Negativo: tardanzas, incumplimientos de charla o refrigerio registrados como
  incidencia.
- Relación cruzada bidireccional con Disciplina Profesional.

La asistencia perfecta aporta +2; el nivel +4 exige además evidencia de
liderazgo o reconocimiento aprobado, tal como describe la guía.

### Adaptabilidad

- Positivo: reconocimiento aprobado, EPT/apreciación sobre respuesta positiva a
  cambios o propuesta implementada que demuestre adaptación.
- Negativo: resistencia a cambios, cuestionamiento reiterado o incumplimiento
  de instrucciones registrado como incidencia.
- Relación cruzada bidireccional con Iniciativa y Compromiso.

### Productividad

- Positivo/negativo automático solo cuando exista una fuente individual de
  volumen nave/patio comparable contra pares del mismo puesto y período.
- El panel de indicadores grupales no se utilizará para puntuar personas.
- Mientras esa fuente individual no exista, se usa apreciación estructurada con
  evidencia obligatoria. La UI muestra “Fuente individual pendiente”, no
  “Manual” sin explicación.

### Eficiencia

- Positivo: cero incidentes técnicos más al menos 3 EPT relevantes; nivel según
  promedio de los ítems de procedimientos y precisión de registro.
- Negativo: incidentes por errores técnicos.
- Relación cruzada bidireccional con Dominio Sólido.

### Dominio Sólido en Tareas Asignadas

- Positivo: cero incidentes de pedeteo, balanzas, USR, CDR o PS más EPT
  suficiente sobre procedimientos y registro preciso.
- Negativo: esos cinco procedimientos alimentan la matriz frecuencia-impacto.
- Relación cruzada bidireccional con Eficiencia.

### Comunicación y Colaboración

- Positivo: reconocimiento aprobado o promedio EPT de trato colaborativo,
  comunicación de novedades y apoyo al equipo.
- Negativo: continuidad operativa deficiente, relevo incompleto, radio o
  desacato documentado como incidencia.
- Sin relación cruzada.

### Iniciativa y Compromiso

- Positivo: reconocimiento aprobado o propuesta del colaborador evaluada.
  Propuesta viable/documentada = +2; propuesta de impacto alto o crítico = +4.
- Negativo: falta de materiales, herramientas, información o sentido de
  urgencia registrada como incidencia.
- Relación cruzada bidireccional con Adaptabilidad.

Solo se consideran propuestas con `colaborador_id`, revisión registrada y
canal de sugerencia; observaciones anónimas no puntúan.

### Disciplina Profesional

- Positivo: reconocimiento aprobado, asistencia a capacitaciones/charlas y
  apreciación documentada de liderazgo.
- Negativo: responsabilidad en funciones, inasistencia, abandono de puesto,
  refrigerio o desacato registrados como incidencia.
- Relación cruzada bidireccional con Organización y Gestión del Tiempo.

### Seguridad en el Trabajo

- Positivo: reconocimiento aprobado o promedio EPT de uso de EPP, permanencia
  en zona segura y reporte oportuno de riesgos.
- Negativo: incidentes de seguridad y salud en el trabajo.
- Los reportes de inspección del propio colaborador se muestran como contexto;
  solo incrementan cuando el registro permite demostrar autoría de un reporte
  proactivo, no por aparecer como persona inspeccionada.
- Sin relación cruzada.

## Catálogo de mapeo

`includes/evades_catalogo.php` pasa de almacenar un único texto de incidencia y
reconocimiento a declarar fuentes por competencia:

```text
competencia
  fuentes_positivas[]
  puntos_incidencia_primarios[]
  competencias_cruzadas[]
  criterios_ept[]
  regla_especial opcional
```

Los incidentes se buscarán por `punto_mejorar`, usando
`inc_puntos_competencia()` como fuente canónica. No dependerán únicamente de la
cadena redundante `incidencias.competencia`.

El catálogo de incidencias se amplía con los eventos descritos en la guía que
hoy no tienen opción explícita: supervisión constante, tardanza/charla,
refrigerio, resistencia al cambio, relevo/radio, falta de recursos, abandono o
desacato. Cada opción guarda su competencia primaria y queda disponible para
las relaciones cruzadas EVADES.

## Apreciación documentada

Se añadirá `evades_apreciaciones` como evidencia estructurada:

- bloque y evaluación;
- colaborador;
- competencia;
- dirección positiva o negativa;
- nivel positivo +2/+4 o impacto negativo;
- descripción obligatoria;
- usuario y fecha;
- estado vigente/anulado para auditoría.

Una apreciación negativa entra a la matriz como evento EVADES; no permite
escribir directamente un descuento arbitrario. Una apreciación positiva puede
proponer +2/+4, pero nunca superar el tope. Editarla o anularla crea auditoría.

## Evidencia persistida y explicación

Cada entrada de `evidencia_json` tendrá un contrato común:

```text
tipo, fuente, id, fecha, competencia_origen, competencia_destino,
es_cruce, valor, impacto, descripcion
```

Además del valor sugerido, el motor devolverá:

- cantidad de observaciones;
- frecuencia e impacto usados;
- regla aplicada;
- nivel de cobertura: suficiente, parcial o sin fuente;
- fecha de cálculo.

Así la pantalla puede explicar “frecuencia 2 × impacto moderado = −4” y no
limitarse a mostrar un selector en −4.

## Experiencia visual aprobada

Se conserva el azul actual y se adopta la opción B, con el asistente de la
opción C únicamente para generar el bloque.

### Modal de generación

- Cabecera con acción principal y contexto.
- Campos coordinador, puesto y trimestre.
- Cobertura previa: número de personas, incidencias, EPT, reconocimientos,
  asistencias y propuestas aplicables.
- Lista resumida por colaborador con estado `Lista` o `Revisar`.
- Advertencia explícita de que solo se usará el trimestre seleccionado.
- Confirmación del número exacto de evaluaciones que se crearán.

### Espacio híbrido de evaluación

- Nómina lateral con búsqueda, puntaje, fuentes y estado por persona.
- Perfil y puntaje total siempre visibles.
- Pestañas Conductuales, Operativas y Feedback/Plan.
- Tarjetas compactas por competencia con base, incremento, descuento y final.
- Evidencias visibles como etiquetas y explicación expandida “Por qué”.
- Cruces identificados con competencia de origen y destino.
- Ajuste manual mediante apreciación estructurada, no solo selectores.
- Navegación de teclado, foco visible y advertencia por cambios sin guardar.
- En móvil, la nómina se convierte en un selector horizontal y las tarjetas
  apilan cálculo debajo del título.

### Modal de consulta

- Modo de solo lectura claramente indicado.
- Puntaje, clasificación, subtotales, incrementos y descuentos.
- Resumen de las diez competencias con causa breve de variación.
- Evidencia expandible, plan de acción y trazabilidad de estados/cambios.
- Exportación PDF conserva los mismos datos y explicaciones.

### Lenguaje visual

- Azul principal existente para cabeceras y acciones.
- Verde exclusivamente para evidencia positiva o estado satisfactorio.
- Rojo para descuentos/evidencia negativa.
- Ámbar para cobertura insuficiente o revisión pendiente.
- Estados siempre acompañados por texto; el color nunca es el único indicador.
- Sin degradados decorativos ni exceso de tarjetas anidadas.

## Compatibilidad y migración

- No se recalculan evaluaciones históricas cerradas.
- Evaluaciones abiertas pueden actualizar su sugerencia con el motor nuevo,
  conservando la diferencia en auditoría.
- La migración de `evades_apreciaciones` es aditiva.
- El esquema actual de bloques, estados, competencias y modificaciones se
  conserva.
- APIs individuales antiguas siguen leyendo registros sin bloque.
- Analista de Trouble Desk usa el mismo modelo de diez competencias, como fue
  aprobado, pero solo se aplican fuentes que existan para esa persona y puesto.

## Manejo de ausencia o inconsistencia de datos

- Sin evidencia: base 6 y explicación “Sin evidencia suficiente”.
- Fuente parcial: base 6, evidencia visible y estado “Requiere revisión”.
- JSON EPT inválido: se omite esa observación, se registra advertencia técnica y
  no se inventa un valor.
- Incidencia con competencia redundante desactualizada: prevalece el
  `punto_mejorar` canónico.
- Reconocimiento pendiente o rechazado: visible como contexto opcional, pero no
  aumenta puntaje.
- Propuesta anónima o no evaluada: no puntúa.
- Dato fuera del trimestre: no participa en cálculo.

## Verificación requerida

### Motor

1. Los diez criterios conservan base 6 sin evidencia.
2. Incremento máximo por competencia es +4; nunca +6.
3. Reconocimientos pendientes no puntúan y aprobados sí.
4. EPT usa los ítems mapeados, no el total general.
5. Menos de 3 EPT no altera puntaje.
6. Promedios 4.0 y 4.5 producen +2 y +4 respectivamente.
7. Errores técnicos descuentan a Dominio y Eficiencia con cruce visible.
8. Disciplina cruza con Organización; Iniciativa cruza con Adaptabilidad.
9. La matriz FI coincide con las 25 celdas del Anexo 2.
10. `punto_mejorar` prevalece sobre una competencia redundante incorrecta.
11. Propuestas y asistencias respetan sus requisitos mínimos.
12. Todos los datos quedan limitados al trimestre.
13. Un bloque cerrado no cambia aunque aparezca evidencia posterior.

### Interfaz

1. Generación muestra cobertura antes de crear el bloque.
2. La nómina final coincide con la vista previa.
3. Cada cambio de puntaje muestra una explicación.
4. Cruces se distinguen de registros primarios.
5. La apreciación exige nivel, descripción y fuente.
6. La consulta cerrada no contiene controles editables.
7. Estados y cobertura no dependen solo del color.
8. Los tres modales funcionan en escritorio y móvil.
9. Navegación de teclado y foco son visibles.
10. PDF refleja el cálculo y la trazabilidad mostrados en pantalla.

## Fuera de alcance

- Crear una base de productividad individual que todavía no existe.
- Puntuar a una persona con indicadores agregados de team.
- Reabrir bloques cerrados.
- Aplicar evidencia de un trimestre a otro.
- Cambiar la matriz o las bandas de clasificación de la guía.
