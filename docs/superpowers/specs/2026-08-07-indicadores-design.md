# Indicadores · Panel digitalizado con auto-fill desde módulos existentes

**Fecha:** 2026-08-07
**Módulo:** nuevo (`pages/indicadores.php`)
**Estado:** Aprobado para implementación
**Origen:** `Panel_Indicadores_Tally_2026-2.xlsx` (Coordinación Tally 2026)

## Objetivo

Digitalizar el panel de indicadores que hoy vive en Excel (`Panel_Indicadores_Tally_2026-2.xlsx`)
como un módulo nuevo del sistema, con la misma estructura de 4 Gestiones · 21
Indicadores · 4 Teams, pero mostrando en tiempo real los indicadores cuyo dato
fuente ya se captura en otros módulos (Incidencias, Capacitaciones, Reporte de
Inspección, EVADES, Evaluación en Puesto de Trabajo, Sugerencias) en vez de
volver a digitarlos a mano.

El Excel deja de ser el lugar donde se captura el dato; pasa a ser el formato
que este módulo reproduce en pantalla y que, en una fase futura, podrá volver
a exportarse igual a como se ve hoy.

## Alcance

- Un contenedor nuevo "Indicadores" con 6 sub-contenedores (pestañas), espejo
  de las hojas del Excel: **Inicio · Dashboard · Resumen Gestión · Datos
  Mensuales · Catálogo · Cronograma**.
- Cálculo automático en tiempo real de los 10 indicadores cuyo dato fuente ya
  existe en el sistema, más 1 indicador parcialmente automático (numerador
  automático, denominador capturado a mano).
- Captura manual (numerador/denominador por indicador × team × mes, igual que
  hoy en Excel) para los 10 indicadores sin módulo fuente.
- Catálogo de los 21 indicadores digitalizado en base de datos (editable por
  Administrador), reemplazando la hoja "Catálogo" fija.
- Cronograma de teams responsables por gestión y mes, digitalizado y editable.

## Fuera de alcance

- Exportar de vuelta a `.xlsx` — el modelo de datos queda preparado para
  esto (tablas espejo de las hojas), pero la exportación en sí es una fase
  futura, tal como se acordó con el usuario.
- Automatizar G1.2, G1.3, G1.5, G2.4, G2.6, G3.4, G4.3, G4.4, G4.5 — no
  existe hoy ningún módulo que capture ese dato de origen; quedan con
  captura manual N/D idéntica a la hoja "Datos Mensuales" actual.
- Cambiar las fórmulas, metas u operadores definidos en el Catálogo del
  Excel: se digitalizan tal cual están.
- Tocar el módulo EVADES, Capacitaciones, Incidencias, etc. — este módulo
  solo LEE de sus tablas; no modifica su lógica ni su UI.

## Mapeo de los 21 indicadores

### ✅ Automático (10) — se calculan en tiempo real, sin captura manual

| Cód. | Indicador | Fuente | Regla de cálculo |
|---|---|---|---|
| G1.4 | Índice de reincidencia grupal | `incidencias` | Filas cuyo `punto_mejorar` se repite dentro del team en el mes / total errores del team en el mes |
| G2.1 | EVADES dentro de plazo | `evades_bloques` / `evades_evaluaciones` | Evaluaciones generadas en el trimestre / personal activo con `funcion_principal` = Asistente de Estiba |
| G2.2 | % cumplimiento de capacitaciones programadas | `capacitaciones` | Capacitaciones con `estado='realizada'` en el mes / 4 |
| G2.3 | Tiempo de respuesta de incidencias | `incidencias` | Promedio de días entre `created_at` y `declaracion_uploaded_at` en el mes |
| G2.5 | EPT (evaluación en puesto de trabajo) | `evaluacion_desempeno` | Conteo de evaluaciones del mes (tipo Suma) |
| G3.1 | N° de reportes de inspección | `reporte_inspeccion` | Conteo de reportes del mes |
| G3.2 | % acciones correctivas implementadas | `reporte_inspeccion` | Reportes con `accion_fecha` no nulo / reportes cuyo JSON `criterios` tiene algún ítem `no_conforme` |
| G3.3 | % incumplimiento uso de EPP en inspecciones | `reporte_inspeccion` | Reportes donde el criterio "Uso de Epps en la zona" = `no_conforme` (parseado del JSON) / total de reportes del mes |
| G4.1 | % de participación en propuestas | `sugerencias_tallyman` | Propuestas (`canal='propuesta'`) del mes / personal activo Asistente de Estiba |
| G4.2 | % propuestas analizadas | `sugerencias_tallyman` | Propuestas con `puntaje_at IS NOT NULL` / propuestas recibidas en el mes |

### 🟡 Parcial (1) — numerador automático, resto capturado a mano

| Cód. | Indicador | Automático | Manual |
|---|---|---|---|
| G1.1 | % charlas pre-operativas realizadas | Numerador: conteo de `asistencias_preoperativas` del mes | Denominador: "charlas programadas" (no existe en ningún módulo) |

### ⚪ Manual (10) — sin módulo fuente hoy, o sin forma de atribuir team

G1.2 (Disponibilidad de recursos), G1.3 (Tasa de aporte al registro), G1.5
(Incumplimientos de refrigerio), **G1.6 (% relevo dentro de plazo — corregido:
`relevo_generado` no tiene columna de team/cuadrilla, es un registro único
por turno completo, no por team; forzar el split por team mostraría el mismo
valor repetido en los 4 teams, así que se deja manual)**, G2.4 (Instructivos
actualizados), G2.6 (Satisfacción laboral — la encuesta existente en
Sugerencias es de refuerzo de capacitación, no de clima laboral), G3.4
(Memorial SSO actualizado), G4.3 (% implementación de propuestas — Tareas es
un módulo de asignación top-down sin relación con propuestas, no sirve como
fuente), G4.4 (Reporte de impacto de implementación), G4.5 (Carpetas
digitalizadas actualizadas).

Estos 10 indicadores se capturan con el mismo formulario N/D por team y mes
que usa hoy la hoja "Datos Mensuales" del Excel.

## Arquitectura

Un módulo nuevo, `pages/indicadores.php`, agregado al sidebar bajo un ítem
propio (no dentro de "Control de Campo"), visible para Administrador y
Supervisor con todos los teams; Coordinador ve/captura solo el team que le
corresponde según el Cronograma del mes. Sigue el patrón visual y de pestañas
ya usado en otros módulos del sistema (cards, tablas, selector de mes/team
como filtros).

Sub-contenedores (pestañas), espejo de las hojas del Excel:

1. **Inicio** — texto introductorio + resumen de estructura (4 Gestiones,
   21 Indicadores, 4 Teams), estático.
2. **Dashboard** — selector de Mes + Vista (General/Team), resumen por
   Gestión con % promedio de cumplimiento y semáforo.
3. **Resumen Gestión** — tabla consolidada por Gestión y por mes, con
   detalle por indicador (% vs Meta por mes + promedio), igual a la hoja.
4. **Datos Mensuales** — la vista central: los 21 indicadores por team y
   mes. Los automáticos se muestran de solo lectura con badge "Automático"
   y un detalle expandible con la evidencia (mismo lenguaje visual que
   EVADES: fuente, cantidad de registros, fecha de cálculo). Los
   manuales/parciales muestran el campo N/D editable.
5. **Catálogo** — listado de los 21 indicadores (código, gestión, objetivo,
   fórmula, meta, tipo de cálculo, frecuencia, entregable), editable solo
   por Administrador.
6. **Cronograma** — team responsable por gestión y mes, editable por
   Administrador/Supervisor.

## Modelo de datos

```sql
indicadores_catalogo      -- seed de los 21 indicadores del Excel
  codigo, gestion_codigo, gestion_nombre, objetivo, kpi, formula,
  numerador_label, denominador_label, tipo_calculo, meta, operador,
  unidad, tipo (General/Individual), frecuencia, entregable,
  fuente_automatica NULL   -- clave del provider en indicadores_engine.php, o NULL si es manual

indicadores_captura        -- solo para los 11 indicadores manuales/parciales
  id, indicador_codigo, periodo (YYYY-MM), team,
  numerador, denominador,
  capturado_por, capturado_por_id, capturado_at

indicadores_cronograma     -- team responsable por gestión y mes
  gestion_codigo, periodo (YYYY-MM), team
```

Los indicadores automáticos **no se persisten**: se calculan al vuelo con
`includes/indicadores_engine.php`, un motor de "providers" (una función por
código de indicador) que consulta las tablas fuente agrupando por
`colaboradores.cuadrilla` (team) y mes — el mismo patrón que
`includes/evades_evidence.php` ya usa en el motor EVADES, así que mantiene
la consistencia arquitectónica del proyecto.

**Nota sobre `cuadrilla`:** el valor de este campo hoy es inconsistente en
producción (`"TEAM A"`, `"G1 TEAM A"`, `"A"` en distintos seeds). El
provider debe normalizar extrayendo el patrón `TEAM [A-D]` o el team
consolidado desde `capacitaciones_asistentes.colaborador_cuadrilla` /
`colaboradores.cuadrilla` según el módulo, y reportar como "Sin team" los
casos que no calcen — nunca inventar un team para que cuadre.

## Reglas de cálculo (idénticas al Excel)

Mismas fórmulas de la hoja "Datos Mensuales":

- **Ratio:** `Valor = Numerador / Denominador`
- **Suma:** `Valor = Numerador` (solo se usa el numerador)
- **Promedio:** `Valor = AVERAGE` de los valores por team
- **Binario:** `Valor = 1 si Numerador > 0, si no 0`
- **% vs Meta** = `Resultado General / Meta`
- **Estado:** `SIN DATO` si no hay valor · `CUMPLE` si ≥ 100% · `EN RIESGO`
  si ≥ 80% · `NO CUMPLE` si < 80%

## Permisos

- **Administrador / Supervisor:** ven y capturan todos los teams y meses;
  editan Catálogo y Cronograma.
- **Coordinador:** ve el Dashboard/Resumen general (solo lectura) y captura
  Datos Mensuales únicamente para el team que le corresponde ese mes según
  el Cronograma.
- **Soporte / Operador:** sin acceso al módulo.

## Testing

- Test de las fórmulas de cálculo (Ratio/Suma/Promedio/Binario y el
  semáforo de Estado) en aislamiento, con casos de SIN DATO.
- Un test por provider automático en `indicadores_engine.php`, verificando
  que agrupa correctamente por team y mes y que no revienta con JSON
  inválido o `cuadrilla` sin normalizar (sigue el patrón de
  `tests/evades_evidence_db_test.php`).
- Test de permisos: Coordinador no puede capturar/ver teams ajenos.

## Verificación requerida

1. Los 10 indicadores automáticos muestran el mismo valor que se obtendría
   calculando a mano desde las tablas fuente, para al menos un mes con
   datos reales.
2. El indicador parcial (G1.1) muestra el numerador automático correcto y
   acepta la captura manual del denominador.
3. Los 10 indicadores manuales se capturan y persisten igual que hoy en el
   Excel (por indicador × team × mes).
4. Dashboard y Resumen Gestión recalculan correctamente al cambiar de mes o
   de vista (General/Team).
5. Un Coordinador no puede capturar datos de un team que no le corresponde
   ese mes según el Cronograma.
6. Catálogo y Cronograma reflejan exactamente el contenido de las hojas
   equivalentes del Excel original tras el seed inicial.
