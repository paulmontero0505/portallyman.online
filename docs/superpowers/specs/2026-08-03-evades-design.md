# Módulo EVADES · Diseño

**Fecha:** 2026-08-03
**Módulo:** nuevo (`pages/evades.php`)
**Estado:** Aprobado

## Problema

La evaluación de desempeño formal para Asistentes de Estiba se hace hoy en
Excel (`Guia_Evaluacion_Desempenio_EVADES.pdf`, `Matriz Guia EVADES.xlsx`,
`formato evades.xlsx`, `Registro_de_Evaluaciones_2026_1.xlsx`), un archivo por
coordinador, llenado a mano cada trimestre. El sistema ya registra, para el
mismo colaborador y en el mismo período, buena parte de la evidencia que la
guía EVADES pide como sustento (incidencias, reconocimientos, asistencia a
capacitaciones, evaluaciones diarias). El objetivo es un módulo EVADES que
calcule automáticamente lo que se pueda a partir de esos datos y deje al
coordinador solo revisar, completar lo que falta y confirmar.

No reemplaza el módulo actual **Evaluación Diaria**
(`pages/evaluacion_desempeno.php`) — usa una rúbrica distinta (checklist 1-5
por turno) y coexiste. Sí lo **consume** como una de las fuentes de evidencia.

## Modelo EVADES (de la guía oficial)

10 competencias, 5 Conductuales (Sección A) + 5 Operativas (Sección B), cada
una con:

- **Base**: 6 puntos.
- **Incremento**: +2 o +4 (desempeño documentado).
- **Descuento**: -2/-4/-6/-8/-10 según la Matriz Frecuencia × Impacto del
  Anexo 2 (frecuencia 1-5 incidentes, impacto Mínimo/Bajo/Medio/Alto/Crítico).
- **Rango final por competencia**: 0-10.

Total /100. Clasificación: Debajo de lo esperado (0-54) · En lo esperado
(55-70) · Sobre lo esperado (71-80) · Sobresaliente (81-100).

Período de evaluación: **trimestral fijo** — Q1 ene-mar, Q2 abr-jun, Q3
jul-sep, Q4 oct-dic.

## Cobertura automática por competencia

Cruzando el catálogo de la guía EVADES contra los catálogos ya existentes de
Incidencias (`includes/incidencias_catalogo.php`) y Reconocimiento Tally
(`includes/reconocimientos_catalogo.php`):

| Competencia | Tipo | Incremento (auto) | Descuento (auto) |
|---|---|---|---|
| Autonomía | Conductual | Reconocimiento | — (manual) |
| Organización y Gestión del Tiempo | Conductual | Reconocimiento | — (manual) |
| Adaptabilidad | Conductual | Reconocimiento | — (manual) |
| Productividad | Conductual | — (manual) | — (manual) |
| Eficiencia | Conductual | — (manual) | — (manual) |
| Dominio Sólido en Tareas Asignadas | Operativa | — (manual) | Incidencia |
| Comunicación y Colaboración | Operativa | Reconocimiento | Incidencia |
| Iniciativa y Compromiso | Operativa | Reconocimiento | Incidencia |
| Disciplina Profesional | Operativa | Reconocimiento | Incidencia |
| Seguridad en el Trabajo | Operativa | Reconocimiento | Incidencia |

Donde dice "manual", el motor no sugiere nada: la celda queda en el valor
base y el coordinador la completa directamente (mismo control que un ajuste,
sin exigir motivo porque no hay sugerencia que esté contradiciendo). Este
vacío de catálogo queda registrado como pendiente — no se amplían los
catálogos de Incidencias/Reconocimiento en esta entrega; se mapeará en una
iteración posterior con opciones concretas para decidir.

### Regla especial: bono Autonomía por Evaluación Diaria

Si el colaborador tiene **≥ 5 evaluaciones diarias** (`evaluacion_desempeno`)
registradas dentro del trimestre y el **promedio de `puntaje_total`** de esas
evaluaciones es **> 38** (sobre el máximo actual de 45), se suma **+2** extra
a Autonomía, como línea aparte ("Bono evaluación diaria: promedio X/45 en N
evaluaciones") para que quede trazable y no se confunda con el incremento por
reconocimiento.

## Modelo de datos

`sql/031_evades.sql`:

```sql
CREATE TABLE IF NOT EXISTS evades_evaluaciones (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id      INT(11)      NULL,
  colaborador_nombre  VARCHAR(150) NOT NULL,
  colaborador_codigo  VARCHAR(20)  NULL,
  colaborador_cargo   VARCHAR(60)  NULL,
  colaborador_dni     VARCHAR(8)   NULL,
  fecha_ingreso       DATE         NULL,
  coordinador_id      INT(11)      NULL,             -- usuarios.id, evaluador
  coordinador_nombre  VARCHAR(100) NOT NULL,
  periodo             VARCHAR(7)   NOT NULL,          -- '2026-T1'..'2026-T4'
  fecha_evaluacion    DATE         NOT NULL,
  puntaje_total       INT(11)      NOT NULL DEFAULT 0, -- suma de puntaje_final, 0-100
  clasificacion       VARCHAR(30)  NOT NULL,
  puntaje_anterior    INT(11)      NULL,               -- copiado de evades_historico o evaluación previa
  variacion_pct       DECIMAL(6,2) NULL,
  fortalezas          TEXT         NULL,
  aspectos_mejora     TEXT         NULL,
  plan_accion         TEXT         NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evades_colab_periodo (colaborador_id, periodo),
  KEY ix_evades_periodo (periodo),
  KEY ix_evades_coordinador (coordinador_id),
  CONSTRAINT fk_evades_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL,
  CONSTRAINT fk_evades_coordinador FOREIGN KEY (coordinador_id)
     REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evades_competencias (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  evaluacion_id       INT(11)      NOT NULL,
  competencia_key     VARCHAR(40)  NOT NULL,           -- clave fija de evades_catalogo.php
  tipo                ENUM('conductual','operativa') NOT NULL,
  base                INT(11)      NOT NULL DEFAULT 6,
  auto_incremento     INT(11)      NULL,               -- lo que sugirió el motor (null = sin evidencia)
  auto_descuento      INT(11)      NULL,
  incremento_final    INT(11)      NOT NULL DEFAULT 0,
  descuento_final     INT(11)      NOT NULL DEFAULT 0,
  puntaje_final       INT(11)      NOT NULL DEFAULT 6,  -- 0-10, clamp
  motivo_ajuste       TEXT         NULL,                -- obligatorio si final != auto
  evidencia_json       TEXT        NULL,                -- detalle de incidencias/reconocimientos usados
  PRIMARY KEY (id),
  KEY ix_evc_evaluacion (evaluacion_id),
  CONSTRAINT fk_evc_evaluacion FOREIGN KEY (evaluacion_id)
     REFERENCES evades_evaluaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evades_historico (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id INT(11)      NULL,
  colaborador_codigo VARCHAR(20) NULL,                  -- respaldo si colaborador_id no calza
  periodo        VARCHAR(7)   NOT NULL,
  puntaje_total  INT(11)      NOT NULL,
  clasificacion  VARCHAR(30)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evh_colab_periodo (colaborador_id, periodo),
  CONSTRAINT fk_evh_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Decisiones:

- `evades_competencias` guarda las 10 filas siempre (incluidas las
  manuales), para que la ficha y el PDF exportado no dependan de recalcular
  el catálogo en el momento de ver una evaluación pasada.
- `evidencia_json` guarda qué incidencias/reconocimientos (id + fecha +
  impacto) sustentaron la sugerencia, para mostrarlo en la UI y en auditorías
  futuras. Si el catálogo cambia después, la evidencia de evaluaciones ya
  hechas no se ve afectada.
- `evades_historico` se carga una sola vez por mí, por SQL directo, cuando
  el usuario proporcione el Excel con los puntajes de trimestres previos. No
  hay pantalla de importación en esta entrega.
- `UNIQUE (colaborador_id, periodo)` en ambas tablas: una sola evaluación
  EVADES por colaborador y trimestre.

## Catálogo fijo — `includes/evades_catalogo.php`

Mismo patrón que `evaluacion_desempeno_catalogo.php` / `incidencias_catalogo.php`:

- `evades_competencias()` → array con las 10 competencias: `key`, `label`,
  `tipo`, `definicion`, `auto_incremento` (bool: ¿tiene catálogo de
  reconocimiento?), `auto_descuento` (bool: ¿tiene catálogo de incidencia?),
  `incidencia_competencia` (el valor de `competencia` usado en `incidencias`/
  `reconocimientos` para cruzar, cuando aplica).
- `evades_matriz_fi()` → grid 5×5 (frecuencia 1-5 × impacto
  minimo/bajo/moderado/alto/critico) → puntos de descuento, tomado del Anexo 2.
- `evades_periodos()` → helper que dado un año devuelve los 4 trimestres con
  sus fechas de inicio/fin.
- `evades_clasificacion($puntaje)` → banda según el puntaje total.

## Motor de cálculo — `includes/evades_engine.php`

`evades_calcular_sugerencias($conn, $colaborador_id, $fecha_inicio, $fecha_fin)`
devuelve un array de 10 filas (una por competencia) con:

```
[competencia_key, base, auto_incremento, auto_descuento, evidencia[]]
```

Lógica por fila, según lo que declare `evades_catalogo.php`:

- **Incremento** (si `auto_incremento`): `SELECT * FROM reconocimientos WHERE
  colaborador_id=? AND competencia=? AND estado='aprobado' AND fecha BETWEEN ?
  AND ?`. Si hay resultados, incremento = 4 si algún `impacto` es
  `excelente`/`sobresaliente`, si no 2. `evidencia[]` lista los ids usados.
- **Descuento** (si `auto_descuento`): `SELECT * FROM incidencias WHERE
  colaborador_id=? AND competencia IN (...) AND fecha BETWEEN ? AND ?`
  (el `IN` cubre los varios "puntos a mejorar" que mapean a una competencia,
  ej. Dominio Sólido). Frecuencia = `COUNT(*)` (tope 5, igual que la matriz),
  Impacto = el más severo presente → `evades_matriz_fi()[frecuencia][impacto]`.
- **Bono Autonomía**: consulta aparte a `evaluacion_desempeno` del mismo
  colaborador y rango de fechas; si `COUNT(*) >= 5` y
  `AVG(puntaje_total) > 38`, agrega +2 a la fila de Autonomía con su propia
  entrada en `evidencia[]`.
- Competencias sin `auto_incremento` ni `auto_descuento` (Productividad,
  Eficiencia) devuelven `auto_incremento=null, auto_descuento=null,
  evidencia=[]` — la UI las muestra como "Sin evidencia automática".

El motor solo **sugiere**; no escribe en `evades_competencias`. Eso lo hace
`api/save_evades.php` con los valores finales que llegan del formulario
(iguales o ajustados por el coordinador).

## API

- `api/get_evades.php` — lista de evaluaciones (filtrable por período,
  coordinador, colaborador). `LEFT JOIN` a `evades_competencias` para el
  detalle cuando se pide una sola (`?id=`).
- `api/calcular_evades.php` — `POST {colaborador_id, periodo}` → corre el
  motor y devuelve las 10 filas sugeridas + el bono Autonomía, sin persistir
  nada. Lo llama el modal al elegir colaborador + trimestre, antes de mostrar
  el formulario editable.
- `api/save_evades.php` — crea o actualiza una evaluación completa: cabecera
  + 10 filas de `evades_competencias`. Valida server-side que toda fila cuyo
  `puntaje_final` difiera del `auto_incremento - auto_descuento + base`
  esperado traiga `motivo_ajuste` no vacío. Calcula `puntaje_total`,
  `clasificacion` y `variacion_pct` (contra `evades_historico` o la
  evaluación del trimestre anterior del mismo colaborador si existe).
- `api/delete_evades.php` — borra evaluación (cascade a `evades_competencias`).

Autorización: `api_require_report()` (mismo nivel que Incidencias /
Evaluación Diaria — Administrador, Supervisor, Coordinador). Un Coordinador
solo puede leer/guardar evaluaciones de colaboradores con
`colaboradores.coordinador_id = $_SESSION['user_id']`; Administrador y
Supervisor ven todos.

## UI · `pages/evades.php`

Mismo lenguaje visual que `evaluacion_desempeno.php` (prefijo `.ev-*`,
mismo tema esmeralda). Estructura:

- **Hero + KPIs**: Total evaluados, Promedio, % Sobresaliente, Variación
  promedio del trimestre.
- **Toolbar**: búsqueda + filtro por trimestre + filtro por clasificación.
- **Tabla**: Evaluado · Cargo · Coordinador · Período · Puntaje · Clasificación
  · Variación · Acciones (Ver / Editar / PDF / Eliminar).
- **Modal "Nueva evaluación"**:
  1. Selector de colaborador (solo `funcion_principal = 'Asistente de
     Estiba'`; si el usuario es Coordinador, restringido a
     `coordinador_id` propio) + selector de trimestre.
  2. Botón "Calcular sugerencias" → llama `calcular_evades.php` → pinta las
     10 filas (Sección A / Sección B) con base/sugerido/final editable y el
     detalle de evidencia visible al pasar el cursor o en un desplegable por
     fila.
  3. Si el coordinador cambia `puntaje_final` respecto a lo sugerido,
     aparece un campo de texto obligatorio "Motivo del ajuste" para esa fila
     (mismo patrón interactivo que ya usa el checklist de Evaluación Diaria).
  4. Filas sin evidencia automática (Productividad, Eficiencia) se muestran
     con un badge "Manual" y el campo de puntaje habilitado directamente,
     sin exigir motivo.
  5. Totales en vivo (Subtotal A, Subtotal B, Total/100, Clasificación).
  6. Sección de retroalimentación: Fortalezas, Aspectos a mejorar, Plan de
     acción (texto libre).
- **Modal "Ver detalle"**: idéntico al de Evaluación Diaria, con botón
  "Exportar PDF".

### Exportación PDF

`jsPDF` + `jspdf-autotable` (misma dependencia ya cargada en
`evaluacion_desempeno.php`), replicando el layout de `formato evades.xlsx`:
encabezado con datos del evaluado, Sección A y B en tabla (Competencia · Base
· Incremento · Descuento · Final · Nivel), subtotales, total, clasificación,
y bloque de retroalimentación.

## Sidebar

Nueva entrada en el submenú "Control de Campo" (`includes/sidebar.php`),
junto a Evaluación Diaria:

```php
<a href="<?= $sb_base ?? '..' ?>/pages/evades.php" class="sub-item sub-item--icon<?= ($cur === 'evades.php') ? ' active' : '' ?>">
  ...
  <span class="sub-label">EVADES</span>
</a>
```

Y se agrega `'evades.php'` al array `$ccActive` que mantiene abierto el
submenú.

## Fuera de alcance de esta entrega

- No se amplían los catálogos de Incidencias/Reconocimiento Tally para cerrar
  los huecos de automatización (Autonomía/Organización/Adaptabilidad sin
  descuento; Dominio Sólido sin incremento; Eficiencia y Productividad sin
  ninguno). Queda pendiente como siguiente iteración, con opciones concretas
  a proponer una vez el módulo esté en uso.
- Sin pantalla de importación de histórico: la carga de `evades_historico`
  la hago yo por SQL cuando se entregue el Excel de puntajes previos.
- Productividad es 100% manual (sin motor, sin catálogo).
- Sin notificación al colaborador ni firma digital — el PDF exportado cubre
  el registro físico si se necesita firma en papel.

## Verificación

1. Migración `031_evades.sql` aplicada; `SHOW TABLES LIKE 'evades%'`.
2. `evades_catalogo.php`: test unitario (`tests/evades_catalogo_test.php`,
   mismo patrón que `tests/tareas_catalogo_test.php`) que valida que las 10
   competencias sumen 100 en el máximo teórico (10×10) y que la matriz F×I
   tenga las 25 celdas del Anexo 2.
3. `calcular_evades.php` con un colaborador con incidencias y reconocimientos
   de prueba en el trimestre → sugerencias coinciden con cálculo manual.
4. Guardar una evaluación con un ajuste sin motivo ⇒ rechazado por el
   servidor.
5. Exportar PDF y comparar visualmente contra `formato evades.xlsx`.
6. Filtro de colaborador por coordinador logueado.
7. Segunda evaluación del mismo colaborador en otro trimestre calcula
   `variacion_pct` contra la primera.
