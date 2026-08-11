# Indicadores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the "Indicadores" module: a digitized version of `Panel_Indicadores_Tally_2026-2.xlsx` with 6 tabs mirroring its sheets, where 10 of the 21 indicators compute live from existing tables (Incidencias, Capacitaciones, EVADES, Evaluación en Puesto de Trabajo, Reporte de Inspección, Sugerencias) and the rest are captured manually per team/month exactly like the Excel does today.

**Architecture:** Three new SQL tables (`indicadores_catalogo`, `indicadores_captura`, `indicadores_cronograma`) seeded from the Excel's Catálogo sheet. A pure-logic calculation module (`includes/indicadores_catalogo.php`) replicates the Excel's Ratio/Suma/Promedio/Binario formulas and CUMPLE/EN RIESGO/NO CUMPLE/SIN DATO thresholds. An evidence-style engine (`includes/indicadores_engine.php`, same pattern as `includes/evades_evidence.php`) computes the 10 automatic + 1 partial indicators on the fly, grouped by team via `colaboradores.cuadrilla`. A single page (`pages/indicadores.php`) renders the 6 tabs against 4 new API endpoints.

**Tech Stack:** PHP 8 + mysqli (existing pattern), vanilla JS + fetch (no framework, matches every other page in this codebase), MariaDB 10.4, plain-PHP test scripts (`ok()`/`eq()` pattern, no PHPUnit).

**Reference spec:** `docs/superpowers/specs/2026-08-07-indicadores-design.md`

---

## Before you start

- DB connection: `includes/db.php` exposes `$conn` (mysqli) against `portally_system`.
- Session/permissions: `includes/auth.php` — you will add `can_indicadores()`, `require_indicadores()`, `api_require_indicadores()` following the exact pattern already used for `can_tareas()` / `require_tareas()` / `api_require_tareas()` (lines 115-136 of that file).
- Tests run directly with the PHP CLI, no test runner config: `php tests/<file>.php`. Exit code 0 = pass. DB-touching tests wrap their fixtures in `mysqli_begin_transaction($conn)` / `mysqli_rollback($conn)` so they never leave data behind — copy this from `tests/evades_evidence_db_test.php`.
- `colaboradores.cuadrilla` is a free-text field with inconsistent values in production data (`"TEAM A"`, `"G1 TEAM A"`, `"DIURNO"`, `"SIN ASIGNAR"`). Every provider must go through `ind_team_normalizado()` (Task 3) — never read `cuadrilla` directly.
- `colaboradores.coordinador_id` (FK → `usuarios.id`) is how a logged-in Coordinador's team is derived: the team of the colaboradores they're in charge of.

---

### Task 1: SQL schema — `indicadores_catalogo`, `indicadores_captura`, `indicadores_cronograma`

**Files:**
- Create: `sql/034_indicadores.sql`

- [ ] **Step 1: Write the migration file**

```sql
-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 034 · Módulo Indicadores
-- Digitaliza Panel_Indicadores_Tally_2026-2.xlsx: catálogo de los 21
-- indicadores, captura manual por indicador×team×mes, y cronograma de
-- teams responsables por gestión y mes.
-- Ejecutar con: mysql -uroot portally_system < sql/034_indicadores.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS indicadores_catalogo (
  codigo             VARCHAR(6)   NOT NULL,
  gestion_codigo     VARCHAR(4)   NOT NULL,
  gestion_nombre     VARCHAR(80)  NOT NULL,
  objetivo           TEXT         NOT NULL,
  kpi                VARCHAR(180) NOT NULL,
  formula            VARCHAR(255) NOT NULL,
  numerador_label    VARCHAR(150) NOT NULL,
  denominador_label  VARCHAR(150) NOT NULL,
  tipo_calculo       ENUM('Ratio','Suma','Promedio','Binario') NOT NULL,
  meta               DECIMAL(10,4) NOT NULL,
  operador           ENUM('>=','<=') NOT NULL,
  unidad             VARCHAR(10)  NOT NULL,
  tipo               ENUM('General','Individual') NOT NULL,
  frecuencia         ENUM('Semanal','Mensual','Trimestral') NOT NULL,
  entregable         VARCHAR(255) NOT NULL,
  fuente_automatica  VARCHAR(10)  NULL,   -- clave del provider en indicadores_engine.php; NULL = manual
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (codigo),
  KEY ix_ic_gestion (gestion_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicadores_captura (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  indicador_codigo   VARCHAR(6)   NOT NULL,
  periodo            VARCHAR(7)   NOT NULL,   -- YYYY-MM
  team               ENUM('TEAM A','TEAM B','TEAM C','TEAM D') NOT NULL,
  numerador          DECIMAL(12,2) NULL,
  denominador        DECIMAL(12,2) NULL,
  capturado_por      VARCHAR(100) NULL,
  capturado_por_id   INT(11)      NULL,
  capturado_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ind_captura (indicador_codigo, periodo, team),
  KEY ix_icap_periodo (periodo),
  CONSTRAINT fk_icap_indicador FOREIGN KEY (indicador_codigo)
     REFERENCES indicadores_catalogo(codigo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicadores_cronograma (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  gestion_codigo     VARCHAR(4)   NOT NULL,
  periodo            VARCHAR(7)   NOT NULL,   -- YYYY-MM
  team               ENUM('TEAM A','TEAM B','TEAM C','TEAM D') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ind_cronograma (gestion_codigo, periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed: los 21 indicadores del Catálogo del Excel ──────────────────
INSERT IGNORE INTO indicadores_catalogo
  (codigo, gestion_codigo, gestion_nombre, objetivo, kpi, formula, numerador_label, denominador_label, tipo_calculo, meta, operador, unidad, tipo, frecuencia, entregable, fuente_automatica) VALUES
('G1.1','G1','Gestión Operativa y de Procesos','Garantizar el inicio puntual del turno mediante charlas preoperativas estructuradas.','% de charlas pre operativas realizadas','Charlas ejecutadas / Charlas programadas','Charlas ejecutadas','Charlas programadas','Ratio',1,'>=','%','General','Mensual','Registro de Charlas / Material didáctico / Lista de asistencia','g11'),
('G1.2','G1','Gestión Operativa y de Procesos','Asegurar la disponibilidad oportuna de recursos operativos (plumones, lapiceros, formatos).','% Disponibilidad óptima de Recursos','Veces de quiebre / Número de naves','Veces de quiebre de recursos','N° de naves atendidas','Ratio',0.05,'<=','%','General','Mensual','Matriz de disponibilidad / Solicitudes extraordinarias / Naves atendidas',NULL),
('G1.3','G1','Gestión Operativa y de Procesos','Medir la tasa activa de reporte.','Tasa de aporte al registro','Número de reportes al mes','N° de reportes al mes','(no aplica)','Suma',64,'>=','num','Individual','Mensual','Registro de seguimiento de refrigerio y cobertura',NULL),
('G1.4','G1','Gestión Operativa y de Procesos','Corrección de errores.','Índice de reincidencia grupal','Errores mismo tipo por grupo / Total errores del periodo','Errores del mismo tipo','Total de errores del periodo','Ratio',0.2,'<=','%','Individual','Mensual','Matriz de incidencias actualizada','g14'),
('G1.5','G1','Gestión Operativa y de Procesos','Cumplir con los tiempos de refrigerio del personal según programación.','N° de incumplimientos de plazos de refrigerio','Incumplimientos semanal / personal asignado','Incumplimientos de refrigerio','Personal asignado en la semana','Ratio',0.01,'<=','%','Individual','Semanal','Registro de seguimiento de refrigerio y cobertura',NULL),
('G1.6','G1','Gestión Operativa y de Procesos','Asegurar continuidad operativa entre turnos mediante handover de calidad dentro de los primeros 20 min.','% Cumplimiento óptimo de relevo dentro del plazo','Relevos dentro de plazo / Turnos al mes','Relevos dentro de plazo','Turnos al mes','Ratio',0.95,'>=','%','Individual','Mensual','Formulario de registro / Formato de relevo actualizado',NULL),
('G2.1','G2','Gestión de Personas y Desarrollo','Evaluar trimestralmente el desempeño del personal tallyman.','EVADES dentro de plazo','EVADES realizadas / Personal asistente de estiba','EVADES realizadas','Personal asistente de estiba','Ratio',1,'>=','%','General','Trimestral','Evaluaciones digitalizadas + actas firmadas + reporte trimestral','g21'),
('G2.2','G2','Gestión de Personas y Desarrollo','Cumplimiento del plan mensual de capacitaciones.','% cumplimiento de capacitaciones programadas','Capacitaciones ejecutadas / 4','Capacitaciones ejecutadas','Capacitaciones programadas (4)','Ratio',0.75,'>=','%','General','Mensual','Cronograma mensual de capacitaciones','g22'),
('G2.3','G2','Gestión de Personas y Desarrollo','Respuesta rápida a incidencias.','Tiempo de respuesta de incidencias','Días promedio entre registro y acción correctiva','Días promedio de respuesta','(no aplica)','Promedio',3,'<=','num','Individual','Mensual','Reporte de variación de incidencias','g23'),
('G2.4','G2','Gestión de Personas y Desarrollo','Difundir procedimientos / instructivos operativos.','% Instructivos actualizados','Instructivos actualizados / Instructivos totales','Instructivos actualizados','Instructivos totales','Ratio',1,'>=','%','General','Mensual','Repositorio digital de instructivos',NULL),
('G2.5','G2','Gestión de Personas y Desarrollo','Seguimiento al cumplimiento de normas y procedimientos.','EPT (evaluación en puestos de trabajo)','Número de EPT al mes','N° de EPT realizadas','(no aplica)','Suma',32,'>=','num','Individual','Mensual','Slide / Estadístico de asistencia','g25'),
('G2.6','G2','Gestión de Personas y Desarrollo','Promover un clima laboral basado en respeto y trabajo en equipo.','% satisfacción laboral','Encuestas favorables / Asistentes de estiba','Encuestas favorables','Asistentes de estiba','Ratio',0.8,'>=','%','General','Trimestral','Encuesta de satisfacción laboral',NULL),
('G3.1','G3','Gestión de Seguridad y Salud Ocupacional','Identificar, reportar y registrar condiciones y actos inseguros.','N° de reportes de inspección','Número total de reportes de inspección','N° de reportes de inspección','(no aplica)','Suma',7,'>=','num','Individual','Mensual','Reporte de inspección en puesto de trabajo','g31'),
('G3.2','G3','Gestión de Seguridad y Salud Ocupacional','Gestionar y dar seguimiento a incidentes ocupacionales y operativos.','% Acciones correctivas implementadas','Acciones correctivas / Incidentes detectados','Acciones correctivas implementadas','Incidentes detectados','Ratio',1,'>=','%','General','Mensual','Matriz causa raíz','g32'),
('G3.3','G3','Gestión de Seguridad y Salud Ocupacional','Asegurar la disponibilidad y uso correcto de EPPs.','% incumplimiento uso de EPP en inspecciones','EPPs incompletos / Reportes de inspección','EPPs incompletos detectados','Reportes de inspección realizados','Ratio',0.05,'<=','%','General','Mensual','Reporte de inspección en puesto de trabajo','g33'),
('G3.4','G3','Gestión de Seguridad y Salud Ocupacional','Consolidar los riesgos y peligros del puesto de Asistente de Estiba.','Memorial SSO actualizado','Actualización del Memorial SSO','Memorial actualizado (1=Sí / 0=No)','(no aplica)','Binario',1,'>=','num','General','Mensual','Memorial SSO',NULL),
('G4.1','G4','Gestión de Mejora Continua e Innovación','Mantener canal formal para propuestas de mejora del personal.','% de participación en propuestas','Propuestas / Asistentes de estiba','Propuestas recibidas','Asistentes de estiba','Ratio',0.5,'>=','%','General','Mensual','Base de datos de propuestas','g41'),
('G4.2','G4','Gestión de Mejora Continua e Innovación','Analizar y priorizar la factibilidad de las propuestas recibidas.','% propuestas analizadas','Propuestas analizadas / Propuestas recibidas','Propuestas analizadas','Propuestas recibidas','Ratio',1,'>=','%','General','Mensual','Matriz de viabilidad (técnica, operativa, económica)','g42'),
('G4.3','G4','Gestión de Mejora Continua e Innovación','Implementar las propuestas de mejora aprobadas.','% implementación de propuestas','Propuestas implementadas / Propuestas aceptadas','Propuestas implementadas','Propuestas aceptadas','Ratio',0.75,'>=','%','General','Mensual','Informe de implementación de mejora',NULL),
('G4.4','G4','Gestión de Mejora Continua e Innovación','Medir el impacto de las mejoras implementadas.','Reporte de impacto de implementación','Reporte de impacto de implementación','Reporte de impacto entregado (1=Sí / 0=No)','(no aplica)','Binario',1,'>=','num','General','Mensual','Informe de impacto + tablero KPIs antes/después',NULL),
('G4.5','G4','Gestión de Mejora Continua e Innovación','Mantener actualizada y digitalizada la documentación del área.','% Carpetas digitalizadas actualizadas','Carpetas digitales actualizadas / Carpetas totales','Carpetas digitales actualizadas','Carpetas digitales totales','Ratio',1,'>=','%','General','Semanal','Repositorio digital (matriz documental con vigencia y responsable)',NULL);

-- ── Seed: cronograma Junio-Diciembre 2026, igual al Excel ────────────
INSERT IGNORE INTO indicadores_cronograma (gestion_codigo, periodo, team) VALUES
('G1','2026-06','TEAM A'),('G1','2026-07','TEAM B'),('G1','2026-08','TEAM C'),('G1','2026-09','TEAM D'),('G1','2026-10','TEAM A'),('G1','2026-11','TEAM B'),('G1','2026-12','TEAM C'),
('G2','2026-06','TEAM B'),('G2','2026-07','TEAM C'),('G2','2026-08','TEAM D'),('G2','2026-09','TEAM A'),('G2','2026-10','TEAM B'),('G2','2026-11','TEAM C'),('G2','2026-12','TEAM D'),
('G3','2026-06','TEAM C'),('G3','2026-07','TEAM D'),('G3','2026-08','TEAM A'),('G3','2026-09','TEAM B'),('G3','2026-10','TEAM C'),('G3','2026-11','TEAM D'),('G3','2026-12','TEAM A'),
('G4','2026-06','TEAM D'),('G4','2026-07','TEAM A'),('G4','2026-08','TEAM B'),('G4','2026-09','TEAM C'),('G4','2026-10','TEAM D'),('G4','2026-11','TEAM A'),('G4','2026-12','TEAM B');
```

- [ ] **Step 2: Run the migration against the local database**

Run: `mysql -uroot portally_system < sql/034_indicadores.sql`
Expected: no errors. Verify with `mysql -uroot portally_system -e "SELECT COUNT(*) FROM indicadores_catalogo; SELECT COUNT(*) FROM indicadores_cronograma;"` → 21 and 28.

- [ ] **Step 3: Commit**

```bash
git add sql/034_indicadores.sql
git commit -m "feat(indicadores): agrega esquema y seed del catálogo de 21 indicadores"
```

---

### Task 2: Pure calculation module — `includes/indicadores_catalogo.php`

Replicates the Excel's per-team `Valor`, aggregate `Resultado General`, `% vs Meta` and `Estado` formulas. No DB access in these functions — fully unit-testable.

**Files:**
- Create: `includes/indicadores_catalogo.php`
- Test: `tests/indicadores_catalogo_test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

require_once(__DIR__ . '/../includes/indicadores_catalogo.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . '  (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($actual, true) . ')');
}

echo "\n── ind_valor_team ──────────────────────────────────────────\n";
eq(ind_valor_team('Ratio', 4, 5), 0.8, 'Ratio = numerador/denominador');
eq(ind_valor_team('Ratio', 4, 0), null, 'Ratio con denominador 0 es null (evita division por cero)');
eq(ind_valor_team('Ratio', null, 5), null, 'Ratio sin numerador es null');
eq(ind_valor_team('Suma', 12, null), 12, 'Suma ignora el denominador');
eq(ind_valor_team('Suma', null, null), null, 'Suma sin numerador es null');
eq(ind_valor_team('Promedio', 3.5, null), 3.5, 'Promedio por team es el numerador tal cual');
eq(ind_valor_team('Binario', 1, null), 1, 'Binario con numerador > 0 es 1');
eq(ind_valor_team('Binario', 0, null), 0, 'Binario con numerador 0 es 0');
eq(ind_valor_team('Binario', null, null), null, 'Binario sin dato es null');

echo "\n── ind_resultado_general ───────────────────────────────────\n";
eq(ind_resultado_general('Ratio', [], 16, 17), 16 / 17, 'Ratio general = TotalN/TotalD');
eq(ind_resultado_general('Ratio', [], 0, 0), null, 'Ratio general sin datos es null');
eq(ind_resultado_general('Suma', [], 44, null), 44, 'Suma general = TotalN');
eq(ind_resultado_general('Promedio', [0.8, 1.0, 1.0], null, null), (0.8 + 1.0 + 1.0) / 3, 'Promedio general = AVERAGE de los valores por team');
eq(ind_resultado_general('Promedio', [], null, null), null, 'Promedio sin ningun team con dato es null');
eq(ind_resultado_general('Binario', [], 3, null), 1, 'Binario general = 1 si TotalN > 0');
eq(ind_resultado_general('Binario', [], 0, null), 0, 'Binario general = 0 si TotalN = 0');
eq(ind_resultado_general('Binario', [], null, null), null, 'Binario general sin dato es null');

echo "\n── ind_pct_vs_meta ─────────────────────────────────────────\n";
eq(ind_pct_vs_meta(0.9411764705882353, 1), 0.9411764705882353, '%vsMeta = resultado/meta');
eq(ind_pct_vs_meta(null, 1), null, 'sin resultado es null');
eq(ind_pct_vs_meta(5, 0), null, 'meta 0 es null (evita division por cero)');

echo "\n── ind_estado ──────────────────────────────────────────────\n";
eq(ind_estado(null), 'SIN DATO', 'sin %vsMeta es SIN DATO');
eq(ind_estado(1), 'CUMPLE', '%vsMeta = 1 exacto es CUMPLE');
eq(ind_estado(1.5), 'CUMPLE', '%vsMeta > 1 es CUMPLE');
eq(ind_estado(0.8), 'EN RIESGO', '%vsMeta = 0.8 exacto es EN RIESGO');
eq(ind_estado(0.99), 'EN RIESGO', '%vsMeta entre 0.8 y 1 es EN RIESGO');
eq(ind_estado(0.79), 'NO CUMPLE', '%vsMeta < 0.8 es NO CUMPLE');
eq(ind_estado(0), 'NO CUMPLE', '%vsMeta = 0 es NO CUMPLE');

echo "\n── ind_periodo_fechas / ind_trimestre_de_periodo ───────────\n";
eq(ind_periodo_fechas('2026-06'), ['inicio' => '2026-06-01', 'fin' => '2026-06-30'], 'junio: 30 dias');
eq(ind_periodo_fechas('2026-02'), ['inicio' => '2026-02-01', 'fin' => '2026-02-28'], 'febrero no bisiesto: 28 dias');
eq(ind_periodo_fechas('2028-02'), ['inicio' => '2028-02-01', 'fin' => '2028-02-29'], 'febrero bisiesto: 29 dias');
eq(ind_periodo_fechas('2026-13'), null, 'mes invalido devuelve null');
eq(ind_trimestre_de_periodo('2026-01'), '2026-T1', 'enero cae en T1');
eq(ind_trimestre_de_periodo('2026-06'), '2026-T2', 'junio cae en T2');
eq(ind_trimestre_de_periodo('2026-09'), '2026-T3', 'setiembre cae en T3');
eq(ind_trimestre_de_periodo('2026-12'), '2026-T4', 'diciembre cae en T4');

echo "\n── ind_team_normalizado ─────────────────────────────────────\n";
eq(ind_team_normalizado('TEAM A'), 'TEAM A', 'formato limpio se reconoce tal cual');
eq(ind_team_normalizado('G1 TEAM A'), 'TEAM A', 'extrae el team de un prefijo de gestion');
eq(ind_team_normalizado('team b'), 'TEAM B', 'no distingue mayusculas/minusculas');
eq(ind_team_normalizado('DIURNO'), null, 'valor sin patron TEAM [A-D] es null');
eq(ind_team_normalizado('SIN ASIGNAR'), null, 'sin asignar es null');
eq(ind_team_normalizado(null), null, 'null es null');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/indicadores_catalogo_test.php`
Expected: fatal error — `includes/indicadores_catalogo.php` doesn't exist yet.

- [ ] **Step 3: Write the implementation**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Módulo Indicadores — cálculo puro (sin BD)
   ───────────────────────────────────────────────────────────────────────
   Replica exactamente las fórmulas de la hoja "Datos Mensuales" del Excel
   Panel_Indicadores_Tally_2026-2.xlsx:
     · Valor por team      → según Tipo Cálculo del indicador
     · Resultado General   → agregado de los 4 teams
     · % vs Meta            → Resultado General / Meta
     · Estado               → semáforo SIN DATO / CUMPLE / EN RIESGO / NO CUMPLE
   ═══════════════════════════════════════════════════════════════════════ */

/** Valor de un indicador para UN team, según su tipo de cálculo. */
function ind_valor_team($tipoCalculo, $numerador, $denominador) {
    if ($tipoCalculo === 'Ratio') {
        if ($numerador === null || $denominador === null || (float)$denominador == 0.0) return null;
        return (float)$numerador / (float)$denominador;
    }
    // Suma, Promedio y Binario solo usan el numerador (igual que la nota de la hoja).
    if ($numerador === null) return null;
    if ($tipoCalculo === 'Binario') return ((float)$numerador > 0) ? 1 : 0;
    return (float)$numerador;
}

/**
 * Resultado General agregando los 4 teams.
 * $valoresPorTeam = array de Valor-por-team ya calculados (puede tener menos de 4 si faltan datos).
 * $totalN / $totalD = suma de numeradores/denominadores de los teams con dato.
 */
function ind_resultado_general($tipoCalculo, $valoresPorTeam, $totalN, $totalD) {
    if ($tipoCalculo === 'Ratio') {
        if ($totalN === null || $totalD === null || (float)$totalD == 0.0) return null;
        return (float)$totalN / (float)$totalD;
    }
    if ($tipoCalculo === 'Suma') {
        return $totalN === null ? null : (float)$totalN;
    }
    if ($tipoCalculo === 'Promedio') {
        $valores = array_values(array_filter($valoresPorTeam, fn($v) => $v !== null));
        if (empty($valores)) return null;
        return array_sum($valores) / count($valores);
    }
    if ($tipoCalculo === 'Binario') {
        if ($totalN === null) return null;
        return ((float)$totalN > 0) ? 1 : 0;
    }
    return null;
}

/** % vs Meta = Resultado General / Meta (igual formula para operador >= y <=, tal como el Excel). */
function ind_pct_vs_meta($resultadoGeneral, $meta) {
    if ($resultadoGeneral === null || $meta === null || (float)$meta == 0.0) return null;
    return (float)$resultadoGeneral / (float)$meta;
}

/** Semáforo de Estado. Umbrales identicos a la hoja: SIN DATO / CUMPLE >=1 / EN RIESGO >=0.8 / NO CUMPLE. */
function ind_estado($pctVsMeta) {
    if ($pctVsMeta === null) return 'SIN DATO';
    if ($pctVsMeta >= 1) return 'CUMPLE';
    if ($pctVsMeta >= 0.8) return 'EN RIESGO';
    return 'NO CUMPLE';
}

/** Rango de fechas [inicio,fin] del mes de un periodo 'YYYY-MM'. Null si el mes es invalido. */
function ind_periodo_fechas($periodo) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$periodo, $m)) return null;
    $anio = (int)$m[1]; $mes = (int)$m[2];
    if ($mes < 1 || $mes > 12) return null;
    $inicio = sprintf('%04d-%02d-01', $anio, $mes);
    $fin = date('Y-m-t', strtotime($inicio));
    return ['inicio' => $inicio, 'fin' => $fin];
}

/** Trimestre 'YYYY-T#' (formato evades_periodo_fechas) al que pertenece un periodo 'YYYY-MM'. */
function ind_trimestre_de_periodo($periodo) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$periodo, $m)) return null;
    $anio = (int)$m[1]; $mes = (int)$m[2];
    if ($mes < 1 || $mes > 12) return null;
    $trimestre = (int)ceil($mes / 3);
    return "$anio-T$trimestre";
}

/**
 * Extrae 'TEAM A'..'TEAM D' de un valor libre de colaboradores.cuadrilla
 * (hoy inconsistente en produccion: "TEAM A", "G1 TEAM A", "DIURNO", "SIN ASIGNAR").
 * Devuelve null si no matchea — nunca se inventa un team para que cuadre.
 */
function ind_team_normalizado($cuadrilla) {
    if ($cuadrilla === null) return null;
    if (preg_match('/TEAM\s*([A-D])/i', (string)$cuadrilla, $m)) {
        return 'TEAM ' . strtoupper($m[1]);
    }
    return null;
}

/** Los 4 teams validos, en orden fijo (igual que las columnas del Excel). */
function ind_teams() {
    return ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/indicadores_catalogo_test.php`
Expected: `TODO OK: 33 aserciones, 0 fallidas`

- [ ] **Step 5: Commit**

```bash
git add includes/indicadores_catalogo.php tests/indicadores_catalogo_test.php
git commit -m "feat(indicadores): agrega calculo puro de formulas y semaforo"
```

---

### Task 3: Auto-fill engine — `includes/indicadores_engine.php`

One provider function per automatic/partial indicator. Every provider takes `($conn, $periodo, $team)` and returns `['numerador' => float|null, 'denominador' => float|null, 'fuente' => string, 'n_registros' => int]`, or `null` if `$team` isn't resolvable. `$team` is `null` to mean "toda la planta" (used for denominators like "Personal asistente de estiba" that aren't split by team) or one of `ind_teams()`.

**Files:**
- Create: `includes/indicadores_engine.php`
- Test: `tests/indicadores_engine_db_test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/indicadores_catalogo.php');
require_once(__DIR__ . '/../includes/indicadores_engine.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . '  (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($actual, true) . ')');
}

echo "\n── dispatcher ind_calcular_automatico ──────────────────────\n";
ok(function_exists('ind_calcular_automatico'), 'existe el dispatcher');
eq(ind_calcular_automatico($conn, 'G1.2', '2026-06', 'TEAM A'), null, 'indicador sin fuente_automatica devuelve null');
eq(ind_calcular_automatico($conn, 'NO-EXISTE', '2026-06', 'TEAM A'), null, 'codigo inexistente devuelve null');

mysqli_begin_transaction($conn);
try {
    $sufijo = bin2hex(random_bytes(4));
    $periodo = '2037-05';

    // ── Fixture: dos colaboradores, uno por team, ambos Asistente de Estiba ──
    $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?, 'ASISTENTE DE ESTIBA','TALLY CALIFICADO','G1 TEAM A',1)");
    $codigoA = "IND$sufijo" . 'A'; $nombreA = "Fixture Ind A $sufijo";
    mysqli_stmt_bind_param($stmt, 'ss', $codigoA, $nombreA);
    mysqli_stmt_execute($stmt);
    $colabA = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?, 'ASISTENTE DE ESTIBA','TALLY CALIFICADO','TEAM B',1)");
    $codigoB = "IND$sufijo" . 'B'; $nombreB = "Fixture Ind B $sufijo";
    mysqli_stmt_bind_param($stmt, 'ss', $codigoB, $nombreB);
    mysqli_stmt_execute($stmt);
    $colabB = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    echo "\n── ind_auto_g14 (reincidencia grupal) ──────────────────────\n";
    // TEAM A: 2 incidencias "Errores de pedeteo" (reincidencia) + 1 "Otro tipo" -> num=2, den=3
    foreach (['Errores de pedeteo', 'Errores de pedeteo', 'Otro tipo'] as $punto) {
        $stmt = mysqli_prepare($conn, "INSERT INTO incidencias (colaborador_id,colaborador_nombre,punto_mejorar,competencia,impacto,coordinador,turno,fecha,zona_trabajo,detalle) VALUES (?,?,?,'x','bajo','Test','dia',?,'Muelle 1','fixture')");
        $fecha = '2037-05-10';
        mysqli_stmt_bind_param($stmt, 'isss', $colabA, $nombreA, $punto, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g14 = ind_calcular_automatico($conn, 'G1.4', $periodo, 'TEAM A');
    eq($g14['numerador'], 2.0, 'G1.4 numerador cuenta las filas de tipo repetido');
    eq($g14['denominador'], 3.0, 'G1.4 denominador cuenta el total de incidencias del team');
    $g14b = ind_calcular_automatico($conn, 'G1.4', $periodo, 'TEAM B');
    eq($g14b['numerador'], 0.0, 'G1.4 sin incidencias en TEAM B es 0 (no null: se sabe que no hubo)');
    eq($g14b['denominador'], 0.0, 'G1.4 denominador tambien 0');

    echo "\n── ind_auto_g22 (cumplimiento de capacitaciones) ───────────\n";
    foreach (['realizada', 'realizada', 'programada'] as $estado) {
        $stmt = mysqli_prepare($conn, "INSERT INTO capacitaciones (titulo,fecha,hora,coordinador,estado) VALUES ('Fixture','2037-05-15','08:00:00','Test',?)");
        mysqli_stmt_bind_param($stmt, 's', $estado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g22 = ind_calcular_automatico($conn, 'G2.2', $periodo, null);
    eq($g22['numerador'], 2.0, 'G2.2 cuenta solo las capacitaciones realizadas del mes');
    eq($g22['denominador'], 4.0, 'G2.2 denominador fijo = 4 (programacion mensual)');

    echo "\n── ind_auto_g31 / g32 / g33 (reportes de inspeccion) ───────\n";
    $criteriosConEpp = json_encode([
        ['item' => 'Uso de Epps en la zona', 'estado' => 'no_conforme', 'observaciones' => ''],
        ['item' => 'Señalización', 'estado' => 'conforme', 'observaciones' => ''],
    ]);
    $stmt = mysqli_prepare($conn, "INSERT INTO reporte_inspeccion (tally_id,tally_nombre,zona_trabajo,fecha,inspector,criterios,accion_fecha) VALUES (?,?, 'Muelle 1','2037-05-12','Test',?, '2037-05-13 10:00:00')");
    mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $criteriosConEpp);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $criteriosOk = json_encode([
        ['item' => 'Uso de Epps en la zona', 'estado' => 'conforme', 'observaciones' => ''],
    ]);
    $stmt = mysqli_prepare($conn, "INSERT INTO reporte_inspeccion (tally_id,tally_nombre,zona_trabajo,fecha,inspector,criterios) VALUES (?,?, 'Muelle 1','2037-05-14','Test',?)");
    mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $criteriosOk);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $g31 = ind_calcular_automatico($conn, 'G3.1', $periodo, 'TEAM A');
    eq($g31['numerador'], 2.0, 'G3.1 cuenta los 2 reportes del team en el mes');

    $g32 = ind_calcular_automatico($conn, 'G3.2', $periodo, 'TEAM A');
    eq($g32['numerador'], 1.0, 'G3.2 numerador: reportes con accion_fecha');
    eq($g32['denominador'], 1.0, 'G3.2 denominador: reportes con algun criterio no_conforme');

    $g33 = ind_calcular_automatico($conn, 'G3.3', $periodo, 'TEAM A');
    eq($g33['numerador'], 1.0, 'G3.3 numerador: reportes con EPP no_conforme');
    eq($g33['denominador'], 2.0, 'G3.3 denominador: total de reportes del mes');

    echo "\n── ind_auto_g11 (charlas pre-operativas, solo numerador) ───\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_preoperativas (tema,tipo_reunion,capacitador,turno,fecha,coordinador) VALUES ('Fixture','charla_seguridad','Test','dia','2037-05-11','Test')");
    mysqli_stmt_execute($stmt);
    $asistId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_participantes (asistencia_id,colaborador_id,colaborador_nombre,estado) VALUES (?,?,?, 'asistio')");
    mysqli_stmt_bind_param($stmt, 'iis', $asistId, $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $g11 = ind_calcular_automatico($conn, 'G1.1', $periodo, 'TEAM A');
    eq($g11['numerador'], 1.0, 'G1.1 numerador cuenta las charlas del team en el mes');
    eq($g11['denominador'], null, 'G1.1 denominador queda null: se captura a mano');

    echo "\n── ind_auto_g41 / g42 (participacion y analisis de propuestas) ─\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO sugerencias_tallyman (canal,colaborador_id,colaborador_nombre,detalle,viabilidad,puntaje_at) VALUES ('propuesta',?,?, 'fixture', 7, '2037-05-20 00:00:00')");
    mysqli_stmt_bind_param($stmt, 'is', $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO sugerencias_tallyman (canal,colaborador_id,colaborador_nombre,detalle) VALUES ('propuesta',?,?, 'fixture sin calificar')");
    mysqli_stmt_bind_param($stmt, 'is', $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    // Forzar la fecha de created_at al periodo de fixture (no acepta parametro directo, created_at es DEFAULT CURRENT_TIMESTAMP)
    mysqli_query($conn, "UPDATE sugerencias_tallyman SET created_at='2037-05-20 00:00:00' WHERE colaborador_id=$colabA");

    $g41 = ind_calcular_automatico($conn, 'G4.1', $periodo, 'TEAM A');
    eq($g41['numerador'], 2.0, 'G4.1 cuenta las 2 propuestas del team en el mes');

    $g42 = ind_calcular_automatico($conn, 'G4.2', $periodo, 'TEAM A');
    eq($g42['numerador'], 1.0, 'G4.2 cuenta solo la propuesta ya calificada (puntaje_at no nulo)');
    eq($g42['denominador'], 2.0, 'G4.2 denominador: total de propuestas recibidas');

    echo "\n── ind_team_de_coordinador ──────────────────────────────────\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (nombre, usuario, password, rol) VALUES (?, ?, 'x', 'Coordinador')");
    $nombreCoord = "Fixture Coord $sufijo"; $userCoord = "fixture_$sufijo";
    mysqli_stmt_bind_param($stmt, 'ss', $nombreCoord, $userCoord);
    mysqli_stmt_execute($stmt);
    $coordId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, "UPDATE colaboradores SET coordinador_id=$coordId WHERE id=$colabA");

    eq(ind_team_de_coordinador($conn, $coordId), 'TEAM A', 'el team del coordinador se deriva de sus colaboradores a cargo');
    eq(ind_team_de_coordinador($conn, 999999), null, 'coordinador sin colaboradores a cargo devuelve null');

    mysqli_rollback($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo "  FALLA excepcion inesperada: " . $e->getMessage() . "\n";
    $FALLOS++;
}

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/indicadores_engine_db_test.php`
Expected: fatal error — `includes/indicadores_engine.php` doesn't exist yet.

- [ ] **Step 3: Write the implementation**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Módulo Indicadores — motor de auto-fill
   ───────────────────────────────────────────────────────────────────────
   Un provider por indicador con fuente_automatica. Mismo patrón que
   includes/evades_evidence.php: cada función consulta las tablas fuente,
   agrupa por colaboradores.cuadrilla normalizada, y devuelve
   {numerador, denominador, fuente, n_registros}. $team = null significa
   "toda la planta" (para denominadores tipo "Personal asistente de estiba").
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/indicadores_catalogo.php');

function ind_resultado_normalizado($numerador, $denominador, $fuente, $nRegistros) {
    return [
        'numerador' => $numerador,
        'denominador' => $denominador,
        'fuente' => $fuente,
        'n_registros' => $nRegistros,
    ];
}

/** Personal activo con funcion_principal = Asistente de Estiba, opcionalmente filtrado por team. */
function ind_conteo_asistentes_estiba($conn, $team = null) {
    $r = mysqli_query($conn, "SELECT cuadrilla FROM colaboradores WHERE activo=1 AND funcion_principal='ASISTENTE DE ESTIBA'");
    $n = 0;
    while ($row = mysqli_fetch_assoc($r)) {
        if ($team === null || ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    return $n;
}

/** Team (TEAM A..D) a cargo de un usuario Coordinador, derivado de sus colaboradores. Null si no hay ninguno o son mixtos. */
function ind_team_de_coordinador($conn, $coordinadorId) {
    $stmt = mysqli_prepare($conn, "SELECT cuadrilla FROM colaboradores WHERE coordinador_id=? AND activo=1");
    mysqli_stmt_bind_param($stmt, 'i', $coordinadorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $teams = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $t = ind_team_normalizado($row['cuadrilla']);
        if ($t !== null) $teams[$t] = true;
    }
    mysqli_stmt_close($stmt);
    if (count($teams) !== 1) return null;
    return array_key_first($teams);
}

// ── G1.1 · % charlas pre-operativas (solo numerador) ────────────────
function ind_auto_g11($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT c.cuadrilla FROM asistencias_participantes ap
           INNER JOIN asistencias_preoperativas a ON a.id = ap.asistencia_id
           LEFT JOIN colaboradores c ON c.id = ap.colaborador_id
          WHERE a.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    mysqli_stmt_close($stmt);
    return ind_resultado_normalizado((float)$n, null, 'asistencias_preoperativas', $n);
}

// ── G1.4 · Índice de reincidencia grupal ─────────────────────────────
function ind_auto_g14($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT i.punto_mejorar, c.cuadrilla FROM incidencias i
           LEFT JOIN colaboradores c ON c.id = i.colaborador_id
          WHERE i.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $porTipo = [];
    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $total++;
        $porTipo[$row['punto_mejorar']] = ($porTipo[$row['punto_mejorar']] ?? 0) + 1;
    }
    mysqli_stmt_close($stmt);
    $reincidentes = 0;
    foreach ($porTipo as $cnt) if ($cnt >= 2) $reincidentes += $cnt;
    return ind_resultado_normalizado((float)$reincidentes, (float)$total, 'incidencias', $total);
}

// ── G2.1 · EVADES dentro de plazo (trimestral) ───────────────────────
function ind_auto_g21($conn, $periodo, $team) {
    if ($team === null) return null;
    $trimestre = ind_trimestre_de_periodo($periodo);
    if ($trimestre === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT c.cuadrilla FROM evades_evaluaciones e
           LEFT JOIN colaboradores c ON c.id = e.colaborador_id
          WHERE e.periodo = ?"
    );
    mysqli_stmt_bind_param($stmt, 's', $trimestre);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    mysqli_stmt_close($stmt);
    $denominador = ind_conteo_asistentes_estiba($conn, $team);
    return ind_resultado_normalizado((float)$n, (float)$denominador, 'evades_evaluaciones', $n);
}

// ── G2.2 · % cumplimiento de capacitaciones (denominador fijo = 4) ───
function ind_auto_g22($conn, $periodo, $team) {
    // Capacitaciones no tiene columna de team: es un indicador General (planta completa),
    // se calcula una sola vez y se muestra igual en los 4 teams (como TEAM A en Datos Mensuales
    // del Excel original, donde los indicadores "General" solo llenan una columna).
    if ($team !== null && $team !== 'TEAM A') return ind_resultado_normalizado(null, null, 'capacitaciones', 0);
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM capacitaciones WHERE estado='realizada' AND fecha BETWEEN ? AND ?");
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $n = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
    mysqli_stmt_close($stmt);
    return ind_resultado_normalizado((float)$n, 4.0, 'capacitaciones', $n);
}

// ── G2.3 · Tiempo de respuesta de incidencias (Promedio, sin team) ───
function ind_auto_g23($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT i.created_at, i.declaracion_uploaded_at, c.cuadrilla FROM incidencias i
           LEFT JOIN colaboradores c ON c.id = i.colaborador_id
          WHERE i.fecha BETWEEN ? AND ? AND i.declaracion_uploaded_at IS NOT NULL"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $dias = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $d1 = new DateTime(date('Y-m-d', strtotime($row['created_at'])));
        $d2 = new DateTime(date('Y-m-d', strtotime($row['declaracion_uploaded_at'])));
        $dias[] = (int)$d1->diff($d2)->days;
    }
    mysqli_stmt_close($stmt);
    if (empty($dias)) return ind_resultado_normalizado(null, null, 'incidencias', 0);
    $promedio = array_sum($dias) / count($dias);
    return ind_resultado_normalizado($promedio, null, 'incidencias', count($dias));
}

// ── G2.5 · EPT realizadas al mes ─────────────────────────────────────
function ind_auto_g25($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT c.cuadrilla FROM evaluacion_desempeno e
           LEFT JOIN colaboradores c ON c.id = e.colaborador_id
          WHERE e.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    mysqli_stmt_close($stmt);
    return ind_resultado_normalizado((float)$n, null, 'evaluacion_desempeno', $n);
}

/** Trae los reportes de inspección del team+mes ya parseados (uso interno de g31/g32/g33). */
function ind_reportes_inspeccion_mes($conn, $periodo, $team) {
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT r.criterios, r.accion_fecha, c.cuadrilla FROM reporte_inspeccion r
           LEFT JOIN colaboradores c ON c.id = r.tally_id
          WHERE r.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// ── G3.1 · N° de reportes de inspección ──────────────────────────────
function ind_auto_g31($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    return ind_resultado_normalizado((float)count($reportes), null, 'reporte_inspeccion', count($reportes));
}

// ── G3.2 · % acciones correctivas implementadas ──────────────────────
function ind_auto_g32($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    $conAccion = 0; $conHallazgo = 0;
    foreach ($reportes as $r) {
        if (!empty($r['accion_fecha'])) $conAccion++;
        $criterios = json_decode($r['criterios'], true);
        if (is_array($criterios)) {
            foreach ($criterios as $c) {
                if (($c['estado'] ?? '') === 'no_conforme') { $conHallazgo++; break; }
            }
        }
    }
    return ind_resultado_normalizado((float)$conAccion, (float)$conHallazgo, 'reporte_inspeccion', count($reportes));
}

// ── G3.3 · % incumplimiento uso de EPP en inspecciones ───────────────
function ind_auto_g33($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    $eppIncompleto = 0;
    foreach ($reportes as $r) {
        $criterios = json_decode($r['criterios'], true);
        if (!is_array($criterios)) continue;
        foreach ($criterios as $c) {
            if (($c['item'] ?? '') === 'Uso de Epps en la zona' && ($c['estado'] ?? '') === 'no_conforme') {
                $eppIncompleto++;
                break;
            }
        }
    }
    return ind_resultado_normalizado((float)$eppIncompleto, (float)count($reportes), 'reporte_inspeccion', count($reportes));
}

/** Propuestas del team+mes (uso interno de g41/g42). */
function ind_propuestas_mes($conn, $periodo, $team) {
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $inicio = $rango['inicio'] . ' 00:00:00';
    $fin = $rango['fin'] . ' 23:59:59';
    $stmt = mysqli_prepare($conn,
        "SELECT s.puntaje_at, c.cuadrilla FROM sugerencias_tallyman s
           LEFT JOIN colaboradores c ON c.id = s.colaborador_id
          WHERE s.canal='propuesta' AND s.created_at BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fin);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// ── G4.1 · % de participación en propuestas ──────────────────────────
function ind_auto_g41($conn, $periodo, $team) {
    if ($team === null) return null;
    $propuestas = ind_propuestas_mes($conn, $periodo, $team);
    if ($propuestas === null) return null;
    $denominador = ind_conteo_asistentes_estiba($conn, $team);
    return ind_resultado_normalizado((float)count($propuestas), (float)$denominador, 'sugerencias_tallyman', count($propuestas));
}

// ── G4.2 · % propuestas analizadas ───────────────────────────────────
function ind_auto_g42($conn, $periodo, $team) {
    if ($team === null) return null;
    $propuestas = ind_propuestas_mes($conn, $periodo, $team);
    if ($propuestas === null) return null;
    $analizadas = 0;
    foreach ($propuestas as $p) if (!empty($p['puntaje_at'])) $analizadas++;
    return ind_resultado_normalizado((float)$analizadas, (float)count($propuestas), 'sugerencias_tallyman', count($propuestas));
}

/** Mapa código de indicador -> nombre de la función provider. */
function ind_providers() {
    return [
        'g11' => 'ind_auto_g11',
        'g14' => 'ind_auto_g14',
        'g21' => 'ind_auto_g21',
        'g22' => 'ind_auto_g22',
        'g23' => 'ind_auto_g23',
        'g25' => 'ind_auto_g25',
        'g31' => 'ind_auto_g31',
        'g32' => 'ind_auto_g32',
        'g33' => 'ind_auto_g33',
        'g41' => 'ind_auto_g41',
        'g42' => 'ind_auto_g42',
    ];
}

/**
 * Dispatcher: calcula el numerador/denominador automático de un indicador.
 * Devuelve null si el código no existe, no tiene fuente_automatica, o el team no es resoluble.
 */
function ind_calcular_automatico($conn, $codigo, $periodo, $team) {
    $stmt = mysqli_prepare($conn, "SELECT fuente_automatica FROM indicadores_catalogo WHERE codigo=?");
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row || empty($row['fuente_automatica'])) return null;

    $providers = ind_providers();
    $fn = $providers[$row['fuente_automatica']] ?? null;
    if ($fn === null || !function_exists($fn)) return null;

    return $fn($conn, $periodo, $team);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/indicadores_engine_db_test.php`
Expected: `TODO OK` with 0 failures. If `sugerencias_tallyman.created_at` isn't updatable via the fixture's UPDATE (some MySQL configs reject setting `created_at` after insert only if it has `ON UPDATE CURRENT_TIMESTAMP`; this table's `created_at` doesn't, so the UPDATE works) — if it fails, inspect actual column defaults with `SHOW CREATE TABLE sugerencias_tallyman` and adjust the fixture's date instead of the schema.

- [ ] **Step 5: Commit**

```bash
git add includes/indicadores_engine.php tests/indicadores_engine_db_test.php
git commit -m "feat(indicadores): agrega motor de auto-fill con 9 providers"
```

---

### Task 4: Permissions — `includes/auth.php`

**Files:**
- Modify: `includes/auth.php` (append after `api_require_tareas()`, around line 136)
- Test: `tests/indicadores_auth_test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

require_once(__DIR__ . '/../includes/auth.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}

echo "\n── can_indicadores ──────────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador'];
ok(can_indicadores(), 'Administrador puede');
$_SESSION = ['user_rol' => 'Supervisor'];
ok(can_indicadores(), 'Supervisor puede');
$_SESSION = ['user_rol' => 'Coordinador'];
ok(can_indicadores(), 'Coordinador puede');
$_SESSION = ['user_rol' => 'Soporte'];
ok(!can_indicadores(), 'Soporte no puede');
$_SESSION = ['user_rol' => 'Operador'];
ok(!can_indicadores(), 'Operador no puede');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/indicadores_auth_test.php`
Expected: fatal error — `can_indicadores()` undefined.

- [ ] **Step 3: Add the functions to `includes/auth.php`**

Insert after `api_require_tareas()` (after line 136, before `can_delete_turno`):

```php

function can_indicadores() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor', 'Coordinador'], true);
}

function require_indicadores() {
    require_login();
    if (!can_indicadores()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a Indicadores.');
    }
}

function api_require_indicadores() {
    api_require_login();
    if (!can_indicadores()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para Indicadores.']);
        exit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/indicadores_auth_test.php`
Expected: `TODO OK: 5 aserciones, 0 fallidas`

- [ ] **Step 5: Commit**

```bash
git add includes/auth.php tests/indicadores_auth_test.php
git commit -m "feat(indicadores): agrega permisos can/require/api_require_indicadores"
```

---

### Task 5: API — `api/get_indicadores.php`

Returns the full catalog for a `periodo` (+ optional `team`), each row carrying its computed value (automatic via the engine, or the persisted manual capture), the aggregated `Resultado General`, `% vs Meta` and `Estado`.

**Files:**
- Create: `api/get_indicadores.php`

- [ ] **Step 1: Write the implementation**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Indicadores — catálogo + valores computados (JSON)
   Query params: periodo=YYYY-MM (requerido), team=TEAM A..D (opcional)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
require_once('../includes/indicadores_engine.php');
api_require_indicadores();

header('Content-Type: application/json');

$periodo = $_GET['periodo'] ?? '';
if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido, use formato YYYY-MM.']);
    exit;
}

$teams = ind_teams();

$r = mysqli_query($conn, "SELECT * FROM indicadores_catalogo WHERE activo=1 ORDER BY codigo");
$catalogo = [];
while ($row = mysqli_fetch_assoc($r)) $catalogo[] = $row;

// Capturas manuales del periodo, indexadas por indicador+team.
$capturas = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM indicadores_captura WHERE periodo=?");
mysqli_stmt_bind_param($stmt, 's', $periodo);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $capturas[$row['indicador_codigo'] . '|' . $row['team']] = $row;
mysqli_stmt_close($stmt);

$out = [];
foreach ($catalogo as $ind) {
    $codigo = $ind['codigo'];
    $porTeam = [];
    $valoresPorTeam = [];
    $totalN = null; $totalD = null; $huboDato = false;

    foreach ($teams as $team) {
        $auto = $ind['fuente_automatica'] ? ind_calcular_automatico($conn, $codigo, $periodo, $team) : null;
        $captura = $capturas[$codigo . '|' . $team] ?? null;

        $numerador = $auto['numerador'] ?? ($captura['numerador'] !== null ? (float)$captura['numerador'] : null);
        $denominador = $auto['denominador'] ?? ($captura['denominador'] !== null ? (float)$captura['denominador'] : null);

        $valor = ind_valor_team($ind['tipo_calculo'], $numerador, $denominador);
        if ($valor !== null) $huboDato = true;
        $valoresPorTeam[] = $valor;

        if ($numerador !== null) $totalN = ($totalN ?? 0) + $numerador;
        if ($denominador !== null) $totalD = ($totalD ?? 0) + $denominador;

        $porTeam[$team] = [
            'numerador' => $numerador,
            'denominador' => $denominador,
            'valor' => $valor,
            'automatico' => $auto !== null,
            'fuente' => $auto['fuente'] ?? null,
        ];
    }

    $resultadoGeneral = $huboDato ? ind_resultado_general($ind['tipo_calculo'], $valoresPorTeam, $totalN, $totalD) : null;
    $pctVsMeta = ind_pct_vs_meta($resultadoGeneral, (float)$ind['meta']);

    $out[] = [
        'codigo' => $codigo,
        'gestion_codigo' => $ind['gestion_codigo'],
        'gestion_nombre' => $ind['gestion_nombre'],
        'kpi' => $ind['kpi'],
        'tipo_calculo' => $ind['tipo_calculo'],
        'meta' => (float)$ind['meta'],
        'operador' => $ind['operador'],
        'unidad' => $ind['unidad'],
        'frecuencia' => $ind['frecuencia'],
        'automatico' => $ind['fuente_automatica'] !== null,
        'teams' => $porTeam,
        'resultado_general' => $resultadoGeneral,
        'pct_vs_meta' => $pctVsMeta,
        'estado' => ind_estado($pctVsMeta),
    ];
}

echo json_encode(['success' => true, 'periodo' => $periodo, 'data' => $out]);
```

- [ ] **Step 2: Manual verification**

With the dev server running and a session cookie for an Administrador user:
Run: `curl -s -b cookies.txt "http://localhost/api/get_indicadores.php?periodo=2026-06" | php -r 'var_dump(json_decode(file_get_contents("php://stdin"), true)["success"]);'`
Expected: `bool(true)`, and `data` has 21 entries.

- [ ] **Step 3: Commit**

```bash
git add api/get_indicadores.php
git commit -m "feat(indicadores): agrega endpoint get_indicadores con calculo combinado"
```

---

### Task 6: API — `api/save_indicador_captura.php`

Upserts one manual N/D cell. Coordinador is restricted to the team derived from `ind_team_de_coordinador()`; Administrador/Supervisor can save any team. Rejects saving onto an indicator that has a `fuente_automatica` for the field being written (numerador is never writable if automatic; denominador is writable only for the partial case).

**Files:**
- Create: `api/save_indicador_captura.php`

- [ ] **Step 1: Write the implementation**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Indicadores — guarda una captura manual N/D
   Body JSON: { indicador_codigo, periodo, team, numerador, denominador }
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
require_once('../includes/indicadores_engine.php');
api_require_indicadores();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim($body['indicador_codigo'] ?? '');
$periodo = trim($body['periodo'] ?? '');
$team = trim($body['team'] ?? '');
$numerador = array_key_exists('numerador', $body) && $body['numerador'] !== '' ? (float)$body['numerador'] : null;
$denominador = array_key_exists('denominador', $body) && $body['denominador'] !== '' ? (float)$body['denominador'] : null;

if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido.']); exit;
}
if (!in_array($team, ind_teams(), true)) {
    echo json_encode(['success' => false, 'error' => 'Team inválido.']); exit;
}

$stmt = mysqli_prepare($conn, "SELECT fuente_automatica FROM indicadores_catalogo WHERE codigo=? AND activo=1");
mysqli_stmt_bind_param($stmt, 's', $codigo);
mysqli_stmt_execute($stmt);
$ind = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$ind) { echo json_encode(['success' => false, 'error' => 'Indicador no existe.']); exit; }

// El único caso parcial hoy es G1.1: numerador automático, solo se puede escribir el denominador.
if ($ind['fuente_automatica'] !== null && $codigo !== 'G1.1') {
    echo json_encode(['success' => false, 'error' => 'Este indicador se calcula automáticamente, no admite captura manual.']);
    exit;
}
if ($codigo === 'G1.1') $numerador = null; // nunca se sobreescribe el numerador automático

// Coordinador: solo puede capturar el team que le corresponde.
$rol = $_SESSION['user_rol'] ?? '';
if ($rol === 'Coordinador') {
    $miTeam = ind_team_de_coordinador($conn, (int)($_SESSION['user_id'] ?? 0));
    if ($miTeam === null || $miTeam !== $team) {
        echo json_encode(['success' => false, 'error' => 'Solo puedes capturar el team que tienes a cargo.']);
        exit;
    }
}

$nombre = $_SESSION['user_name'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "INSERT INTO indicadores_captura (indicador_codigo, periodo, team, numerador, denominador, capturado_por, capturado_por_id)
     VALUES (?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE numerador=VALUES(numerador), denominador=VALUES(denominador),
       capturado_por=VALUES(capturado_por), capturado_por_id=VALUES(capturado_por_id)"
);
mysqli_stmt_bind_param($stmt, 'sssddsi', $codigo, $periodo, $team, $numerador, $denominador, $nombre, $uid);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)]);
```

- [ ] **Step 2: Manual verification**

Run: `curl -s -b cookies.txt -X POST -d '{"indicador_codigo":"G1.2","periodo":"2026-06","team":"TEAM A","numerador":"2","denominador":"3"}' "http://localhost/api/save_indicador_captura.php"`
Expected: `{"success":true}`. Then re-fetch `get_indicadores.php?periodo=2026-06` and confirm G1.2/TEAM A shows valor 0.666...

- [ ] **Step 3: Commit**

```bash
git add api/save_indicador_captura.php
git commit -m "feat(indicadores): agrega endpoint de captura manual con permisos por team"
```

---

### Task 7: API — Cronograma (`api/get_indicadores_cronograma.php`, `api/save_indicador_cronograma.php`)

**Files:**
- Create: `api/get_indicadores_cronograma.php`
- Create: `api/save_indicador_cronograma.php`

- [ ] **Step 1: Write `api/get_indicadores_cronograma.php`**

```php
<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_indicadores();

header('Content-Type: application/json');

$r = mysqli_query($conn, "SELECT gestion_codigo, periodo, team FROM indicadores_cronograma ORDER BY periodo, gestion_codigo");
$out = [];
while ($row = mysqli_fetch_assoc($r)) $out[] = $row;

echo json_encode(['success' => true, 'data' => $out]);
```

- [ ] **Step 2: Write `api/save_indicador_cronograma.php`**

```php
<?php
/* Body JSON: { gestion_codigo, periodo, team } — solo Administrador/Supervisor. */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
api_require_indicadores();

header('Content-Type: application/json');

$rol = $_SESSION['user_rol'] ?? '';
if (!in_array($rol, ['Administrador', 'Supervisor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Solo Administrador o Supervisor pueden editar el Cronograma.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$gestion = trim($body['gestion_codigo'] ?? '');
$periodo = trim($body['periodo'] ?? '');
$team = trim($body['team'] ?? '');

if (!in_array($gestion, ['G1', 'G2', 'G3', 'G4'], true)) {
    echo json_encode(['success' => false, 'error' => 'Gestión inválida.']); exit;
}
if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido.']); exit;
}
if (!in_array($team, ind_teams(), true)) {
    echo json_encode(['success' => false, 'error' => 'Team inválido.']); exit;
}

$stmt = mysqli_prepare($conn,
    "INSERT INTO indicadores_cronograma (gestion_codigo, periodo, team) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE team=VALUES(team)"
);
mysqli_stmt_bind_param($stmt, 'sss', $gestion, $periodo, $team);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)]);
```

- [ ] **Step 3: Manual verification**

Run: `curl -s -b cookies.txt "http://localhost/api/get_indicadores_cronograma.php"`
Expected: `{"success":true,"data":[...]}` with 28 rows (4 gestiones × 7 meses del seed).

- [ ] **Step 4: Commit**

```bash
git add api/get_indicadores_cronograma.php api/save_indicador_cronograma.php
git commit -m "feat(indicadores): agrega endpoints de cronograma"
```

---

### Task 8: API — `api/save_indicador_catalogo.php`

Only `meta` and `activo` are editable — the formula, numerador/denominador labels and `tipo_calculo` are wired into the engine and the Excel's original definition; changing them silently would desync the providers from what the UI claims to compute. Editing those requires a code change, not just a UI edit.

**Files:**
- Create: `api/save_indicador_catalogo.php`

- [ ] **Step 1: Write the implementation**

```php
<?php
/* Body JSON: { codigo, meta, activo } — solo Administrador. */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim($body['codigo'] ?? '');
$meta = isset($body['meta']) ? (float)$body['meta'] : null;
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : null;

if ($codigo === '' || $meta === null || $activo === null) {
    echo json_encode(['success' => false, 'error' => 'Faltan campos.']); exit;
}

$stmt = mysqli_prepare($conn, "UPDATE indicadores_catalogo SET meta=?, activo=? WHERE codigo=?");
mysqli_stmt_bind_param($stmt, 'dis', $meta, $activo, $codigo);
$ok = mysqli_stmt_execute($stmt);
$afectadas = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => mysqli_error($conn)]); exit; }
echo json_encode(['success' => true, 'actualizado' => $afectadas > 0]);
```

- [ ] **Step 2: Commit**

```bash
git add api/save_indicador_catalogo.php
git commit -m "feat(indicadores): agrega endpoint para editar meta y activo del catalogo"
```

---

### Task 9: Sidebar entry

**Files:**
- Modify: `includes/sidebar.php:170-171` (right after the "Relevo de turno" link, still inside the `can_operate`-gated Administrador/Supervisor/Coordinador block)

- [ ] **Step 1: Add the nav item**

In `includes/sidebar.php`, insert right before the `<?php endif; ?>` on line 171 (the one closing the `in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'])` block that also contains "Registro tallyman" and "Relevo de turno"):

```php
    <a href="<?= $sb_base ?? '..' ?>/pages/indicadores.php" class="nav-item<?= ($cur === 'indicadores.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>
        </svg>
      </span>
      <span class="nav-label">Indicadores</span>
      <span class="tip">Indicadores</span>
    </a>
```

- [ ] **Step 2: Verify by loading any page and checking the sidebar link exists (visual, no automated test — this file has no existing test)**

Open any page as Administrador/Supervisor/Coordinador and confirm "Indicadores" appears in the sidebar with the chart icon.

- [ ] **Step 3: Commit**

```bash
git add includes/sidebar.php
git commit -m "feat(indicadores): agrega Indicadores al sidebar"
```

---

### Task 10: Page shell + Inicio tab — `pages/indicadores.php`

Sets up the page skeleton (header, CSS, tab navigation) and the static "Inicio" tab. Later tasks append markup and JS to this same file for the other 5 tabs.

**Files:**
- Create: `pages/indicadores.php`
- Test: `tests/indicadores_ui_test.php` (structural smoke test, same pattern as `tests/evades_modal_ui_test.php`)

- [ ] **Step 1: Write the page shell**

```php
<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_indicadores();

$ES_ADMIN = is_admin();
$ES_COORD = ($_SESSION['user_rol'] ?? '') === 'Coordinador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Indicadores · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════════ INDICADORES (prefijo .ind-*) ════════════════ */
    .ind-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18);
      --co-ink:#111827; --co-mute:#4b5563;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .ind-wrap *, .ind-wrap *::before, .ind-wrap *::after { box-sizing:border-box; }
    .ind-hero {
      background:linear-gradient(135deg,#005c3d 0%,#00875A 100%);
      color:#fff; border-radius:20px; padding:22px 28px;
    }
    .ind-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; }
    .ind-hero p { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:640px; }
    .ind-tabs { display:flex; gap:6px; flex-wrap:wrap; border-bottom:1px solid var(--co-line); padding-bottom:0; }
    .ind-tab { padding:10px 16px; border:none; background:none; font-family:inherit; font-size:13px;
      font-weight:600; color:var(--co-mute); cursor:pointer; border-bottom:2px solid transparent; }
    .ind-tab.active { color:var(--co-navy-700); border-bottom-color:var(--co-navy-700); }
    .ind-panel { display:none; }
    .ind-panel.active { display:block; }
    .ind-estado-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .ind-estado-CUMPLE { background:var(--ok-bg); color:var(--ok); }
    .ind-estado-EN_RIESGO { background:var(--wn-bg); color:var(--wn); }
    .ind-estado-NO_CUMPLE { background:var(--er-bg); color:var(--er); }
    .ind-estado-SIN_DATO { background:var(--sl-bg); color:var(--sl); }
    .ind-select { padding:8px 12px; border-radius:8px; border:1px solid var(--co-line); font-family:inherit; font-size:13px; }
  </style>
</head>
<body>
<?php include('../includes/header.php'); ?>
<div class="app-shell">
<?php $sb_base = '..'; include('../includes/sidebar.php'); ?>
<main class="main-content">
  <div class="ind-wrap">

    <div class="ind-hero">
      <span class="tag">PANEL DE SEGUIMIENTO</span>
      <h1>Indicadores</h1>
      <p>Coordinación Tally 2026 — 4 Gestiones · 21 Indicadores · 4 Teams. Los indicadores marcados
      "Automático" se calculan en tiempo real desde los módulos operativos del sistema.</p>
    </div>

    <div class="ind-tabs" id="indTabs">
      <button class="ind-tab active" data-tab="inicio">Inicio</button>
      <button class="ind-tab" data-tab="dashboard">Dashboard</button>
      <button class="ind-tab" data-tab="resumen">Resumen Gestión</button>
      <button class="ind-tab" data-tab="datos">Datos Mensuales</button>
      <button class="ind-tab" data-tab="catalogo">Catálogo</button>
      <button class="ind-tab" data-tab="cronograma">Cronograma</button>
    </div>

    <section class="ind-panel active" id="indPanelInicio">
      <div class="card">
        <div class="card-body">
          <h3>Estructura del panel</h3>
          <p><strong>Dashboard</strong> — vista ejecutiva con selector de mes y team, semáforo de avance por gestión.</p>
          <p><strong>Resumen Gestión</strong> — consolidado por las 4 gestiones y por mes, con detalle por indicador.</p>
          <p><strong>Datos Mensuales</strong> — captura del Numerador y Denominador por team. El Valor, Resultado, % vs Meta y Estado se calculan (automáticamente para los indicadores con fuente de datos ya conectada).</p>
          <p><strong>Catálogo</strong> — listado completo de los 21 indicadores con código, fórmula, meta y tipo de cálculo.</p>
          <p><strong>Cronograma</strong> — team responsable por gestión y mes.</p>
        </div>
      </div>
    </section>

    <section class="ind-panel" id="indPanelDashboard"></section>
    <section class="ind-panel" id="indPanelResumen"></section>
    <section class="ind-panel" id="indPanelDatos"></section>
    <section class="ind-panel" id="indPanelCatalogo"></section>
    <section class="ind-panel" id="indPanelCronograma"></section>

  </div>
</main>
</div>

<script>
const IND_ES_ADMIN = <?= $ES_ADMIN ? 'true' : 'false' ?>;
const IND_ES_COORD = <?= $ES_COORD ? 'true' : 'false' ?>;
const IND_BASE = '..';

document.querySelectorAll('.ind-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.ind-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.ind-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const map = { inicio: 'indPanelInicio', dashboard: 'indPanelDashboard', resumen: 'indPanelResumen',
      datos: 'indPanelDatos', catalogo: 'indPanelCatalogo', cronograma: 'indPanelCronograma' };
    document.getElementById(map[btn.dataset.tab]).classList.add('active');
    if (typeof window.indOnTabShown === 'function') window.indOnTabShown(btn.dataset.tab);
  });
});
</script>
</body>
</html>
```

- [ ] **Step 2: Write the structural smoke test**

```php
<?php

$source = file_get_contents(__DIR__ . '/../pages/indicadores.php');
$total = 0;
$failures = 0;

function ui_has($source, $needle, $message) {
    global $total, $failures;
    $total++;
    if (strpos($source, $needle) !== false) echo "  ok    $message\n";
    else { $failures++; echo "  FALLA $message\n"; }
}

echo "\n── estructura del panel Indicadores ────────────────────────\n";
ui_has($source, 'require_indicadores()', 'la pagina exige permiso de indicadores');
ui_has($source, 'id="indTabs"', 'existe la barra de pestanas');
ui_has($source, 'data-tab="inicio"', 'pestana Inicio');
ui_has($source, 'data-tab="dashboard"', 'pestana Dashboard');
ui_has($source, 'data-tab="resumen"', 'pestana Resumen Gestion');
ui_has($source, 'data-tab="datos"', 'pestana Datos Mensuales');
ui_has($source, 'data-tab="catalogo"', 'pestana Catalogo');
ui_has($source, 'data-tab="cronograma"', 'pestana Cronograma');
ui_has($source, 'id="indPanelInicio"', 'panel Inicio');
ui_has($source, 'id="indPanelDashboard"', 'panel Dashboard');
ui_has($source, 'id="indPanelResumen"', 'panel Resumen');
ui_has($source, 'id="indPanelDatos"', 'panel Datos Mensuales');
ui_has($source, 'id="indPanelCatalogo"', 'panel Catalogo');
ui_has($source, 'id="indPanelCronograma"', 'panel Cronograma');
ui_has($source, 'indOnTabShown', 'las pestanas notifican al cambiar para lazy-load');

echo "\n" . ($failures === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $total aserciones, $failures fallidas\n\n";
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 3: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 15 aserciones, 0 fallidas`

- [ ] **Step 4: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): agrega el shell de la pagina con 6 pestanas"
```

---

### Task 11: Datos Mensuales tab (core capture UI)

Appends JS to `pages/indicadores.php` that renders the central grid: month + team selectors, one row per indicator (21 rows), automatic indicators read-only with an "Automático" badge, manual/partial ones with editable N/D inputs that POST to `save_indicador_captura.php`.

**Files:**
- Modify: `pages/indicadores.php` (append before `</script>`)
- Modify: `tests/indicadores_ui_test.php` (append assertions)

- [ ] **Step 1: Add assertions to the smoke test**

Append to `tests/indicadores_ui_test.php`, before the final summary block:

```php
echo "\n── pestana Datos Mensuales ──────────────────────────────────\n";
ui_has($source, 'async function loadDatosMensuales', 'carga los datos mensuales via fetch');
ui_has($source, 'get_indicadores.php', 'consume el endpoint de valores computados');
ui_has($source, 'save_indicador_captura.php', 'guarda la captura manual');
ui_has($source, 'ind-badge-auto', 'distingue visualmente los indicadores automaticos');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/indicadores_ui_test.php`
Expected: 4 new FALLA lines (functions/strings don't exist yet).

- [ ] **Step 3: Append the tab implementation**

Insert into `pages/indicadores.php`, right before the closing `</script>` tag:

```javascript
// ── Datos Mensuales ──────────────────────────────────────────────────
let indDatosPeriodo = new Date().toISOString().slice(0, 7);
let indDatosCache = null;

function indMesInputHtml() {
  return `<input type="month" id="indDatosPeriodo" class="ind-select" value="${indDatosPeriodo}">`;
}

async function loadDatosMensuales() {
  const panel = document.getElementById('indPanelDatos');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos Mensuales</div>
        <div>${indMesInputHtml()}</div>
      </div>
      <div class="card-body" id="indDatosBody">Cargando…</div>
    </div>`;
  document.getElementById('indDatosPeriodo').addEventListener('change', (e) => {
    indDatosPeriodo = e.target.value;
    loadDatosMensuales();
  });
  await indFetchYRenderDatos();
}

async function indFetchYRenderDatos() {
  const body = document.getElementById('indDatosBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { body.innerHTML = `<p>${json.error || 'Error al cargar.'}</p>`; return; }
    indDatosCache = json.data;
    body.innerHTML = indRenderTablaDatos(json.data);
    indBindCapturaInputs();
  } catch (e) {
    body.innerHTML = '<p>Error de red al cargar los indicadores.</p>';
  }
}

function indRenderTablaDatos(indicadores) {
  const teams = ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
  const filas = indicadores.map(ind => {
    const celdas = teams.map(team => {
      const t = ind.teams[team];
      if (t.automatico) {
        const val = t.valor === null ? '—' : (ind.tipo_calculo === 'Ratio' ? (t.valor * 100).toFixed(1) + '%' : t.valor);
        return `<td><span class="ind-badge-auto" title="Fuente: ${t.fuente || ''}">${val === null ? '—' : val}</span></td>`;
      }
      const n = t.numerador === null ? '' : t.numerador;
      const d = t.denominador === null ? '' : t.denominador;
      const numDisabled = ind.codigo === 'G1.1'; // G1.1: numerador es automatico, solo el denominador es editable
      return `<td>
        <input type="number" step="0.01" class="ind-cap-n" data-codigo="${ind.codigo}" data-team="${team}" value="${n}" ${numDisabled ? 'disabled' : ''} style="width:64px">
        <input type="number" step="0.01" class="ind-cap-d" data-codigo="${ind.codigo}" data-team="${team}" value="${d}" style="width:64px">
      </td>`;
    }).join('');
    const estadoClase = 'ind-estado-' + ind.estado.replace(/ /g, '_');
    return `<tr>
      <td>${ind.codigo}</td>
      <td>${ind.kpi}</td>
      ${celdas}
      <td>${ind.resultado_general === null ? '—' : ind.resultado_general}</td>
      <td><span class="ind-estado-badge ${estadoClase}">${ind.estado}</span></td>
    </tr>`;
  }).join('');

  return `<div class="table-wrap"><table>
    <thead><tr><th>Cód.</th><th>Indicador</th><th>TEAM A</th><th>TEAM B</th><th>TEAM C</th><th>TEAM D</th><th>Resultado</th><th>Estado</th></tr></thead>
    <tbody>${filas}</tbody>
  </table></div>`;
}

function indBindCapturaInputs() {
  document.querySelectorAll('.ind-cap-n, .ind-cap-d').forEach(input => {
    input.addEventListener('change', async () => {
      const codigo = input.dataset.codigo;
      const team = input.dataset.team;
      const nEl = document.querySelector(`.ind-cap-n[data-codigo="${codigo}"][data-team="${team}"]`);
      const dEl = document.querySelector(`.ind-cap-d[data-codigo="${codigo}"][data-team="${team}"]`);
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_captura.php`, {
          method: 'POST',
          body: JSON.stringify({
            indicador_codigo: codigo, periodo: indDatosPeriodo, team,
            numerador: nEl.value, denominador: dEl.value,
          }),
        });
        const json = await res.json();
        if (!json.success) { alert(json.error || 'Error al guardar.'); return; }
        await indFetchYRenderDatos();
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}
```

- [ ] **Step 4: Wire the tab into `indOnTabShown` and run on first load**

Replace the previous placeholder function reference (currently `if (typeof window.indOnTabShown === 'function') ...` calls a function that doesn't exist yet) by adding this definition right after the tab-click listener block, still before `</script>`:

```javascript
let indDatosLoaded = false;
window.indOnTabShown = function (tab) {
  if (tab === 'datos' && !indDatosLoaded) { indDatosLoaded = true; loadDatosMensuales(); }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 19 aserciones, 0 fallidas`

- [ ] **Step 6: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): implementa pestana Datos Mensuales"
```

---

### Task 12: Catálogo tab

**Files:**
- Modify: `pages/indicadores.php` (append before `</script>`, and wire into `indOnTabShown`)
- Modify: `tests/indicadores_ui_test.php`

- [ ] **Step 1: Add assertions**

```php
echo "\n── pestana Catalogo ─────────────────────────────────────────\n";
ui_has($source, 'async function loadCatalogo', 'carga el catalogo');
ui_has($source, 'save_indicador_catalogo.php', 'permite editar meta desde el catalogo');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/indicadores_ui_test.php` → 2 new FALLA lines.

- [ ] **Step 3: Implement**

Append before `</script>`:

```javascript
// ── Catálogo ────────────────────────────────────────────────────────
let indCatalogoLoaded = false;

async function loadCatalogo() {
  const panel = document.getElementById('indPanelCatalogo');
  panel.innerHTML = `<div class="card"><div class="card-header"><div class="card-title">Catálogo de Indicadores</div></div>
    <div class="card-body" id="indCatalogoBody">Cargando…</div></div>`;
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { document.getElementById('indCatalogoBody').innerHTML = json.error; return; }
    document.getElementById('indCatalogoBody').innerHTML = indRenderCatalogo(json.data);
    if (IND_ES_ADMIN) indBindCatalogoEdicion();
  } catch (e) {
    document.getElementById('indCatalogoBody').innerHTML = 'Error de red.';
  }
}

function indRenderCatalogo(indicadores) {
  const filas = indicadores.map(ind => `
    <tr>
      <td>${ind.codigo}</td>
      <td>${ind.gestion_nombre}</td>
      <td>${ind.kpi}</td>
      <td>${ind.tipo_calculo}</td>
      <td>${ind.frecuencia}</td>
      <td>${ind.automatico ? '<span class="ind-badge-auto">Automático</span>' : 'Manual'}</td>
      <td>${IND_ES_ADMIN
        ? `<input type="number" step="0.0001" class="ind-cat-meta" data-codigo="${ind.codigo}" value="${ind.meta}" style="width:80px">`
        : ind.meta}</td>
    </tr>`).join('');
  return `<div class="table-wrap"><table>
    <thead><tr><th>Cód.</th><th>Gestión</th><th>Indicador</th><th>Cálculo</th><th>Frecuencia</th><th>Origen</th><th>Meta</th></tr></thead>
    <tbody>${filas}</tbody>
  </table></div>`;
}

function indBindCatalogoEdicion() {
  document.querySelectorAll('.ind-cat-meta').forEach(input => {
    input.addEventListener('change', async () => {
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_catalogo.php`, {
          method: 'POST',
          body: JSON.stringify({ codigo: input.dataset.codigo, meta: input.value, activo: true }),
        });
        const json = await res.json();
        if (!json.success) alert(json.error || 'Error al guardar.');
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}
```

- [ ] **Step 4: Wire into `indOnTabShown`**

Replace the body of `window.indOnTabShown` with:

```javascript
window.indOnTabShown = function (tab) {
  if (tab === 'datos' && !indDatosLoaded) { indDatosLoaded = true; loadDatosMensuales(); }
  if (tab === 'catalogo' && !indCatalogoLoaded) { indCatalogoLoaded = true; loadCatalogo(); }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 21 aserciones, 0 fallidas`

- [ ] **Step 6: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): implementa pestana Catalogo"
```

---

### Task 13: Cronograma tab

**Files:**
- Modify: `pages/indicadores.php`
- Modify: `tests/indicadores_ui_test.php`

- [ ] **Step 1: Add assertions**

```php
echo "\n── pestana Cronograma ───────────────────────────────────────\n";
ui_has($source, 'async function loadCronograma', 'carga el cronograma');
ui_has($source, 'save_indicador_cronograma.php', 'permite editar el team responsable');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/indicadores_ui_test.php` → 2 new FALLA lines.

- [ ] **Step 3: Implement**

Append before `</script>`:

```javascript
// ── Cronograma ──────────────────────────────────────────────────────
let indCronogramaLoaded = false;
const IND_GESTIONES = [
  { codigo: 'G1', nombre: 'Gestión Operativa y de Procesos' },
  { codigo: 'G2', nombre: 'Gestión de Personas y Desarrollo' },
  { codigo: 'G3', nombre: 'Gestión de Seguridad y Salud Ocupacional' },
  { codigo: 'G4', nombre: 'Gestión de Mejora Continua e Innovación' },
];
const IND_MESES = ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'];
const IND_MES_LABEL = { '2026-06': 'Junio', '2026-07': 'Julio', '2026-08': 'Agosto', '2026-09': 'Setiembre', '2026-10': 'Octubre', '2026-11': 'Noviembre', '2026-12': 'Diciembre' };

async function loadCronograma() {
  const panel = document.getElementById('indPanelCronograma');
  panel.innerHTML = `<div class="card"><div class="card-header"><div class="card-title">Cronograma de Responsables</div></div>
    <div class="card-body" id="indCronogramaBody">Cargando…</div></div>`;
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores_cronograma.php`);
    const json = await res.json();
    if (!json.success) { document.getElementById('indCronogramaBody').innerHTML = json.error; return; }
    document.getElementById('indCronogramaBody').innerHTML = indRenderCronograma(json.data);
    if (IND_ES_ADMIN || IND_ES_COORD === false) indBindCronogramaEdicion();
  } catch (e) {
    document.getElementById('indCronogramaBody').innerHTML = 'Error de red.';
  }
}

function indRenderCronograma(filas) {
  const mapa = {};
  filas.forEach(f => { mapa[f.gestion_codigo + '|' + f.periodo] = f.team; });
  const teams = ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
  const filasHtml = IND_GESTIONES.map(g => {
    const celdas = IND_MESES.map(mes => {
      const actual = mapa[g.codigo + '|' + mes] || '';
      const opciones = teams.map(t => `<option value="${t}" ${t === actual ? 'selected' : ''}>${t}</option>`).join('');
      return `<td><select class="ind-cron-select" data-gestion="${g.codigo}" data-periodo="${mes}">${opciones}</select></td>`;
    }).join('');
    return `<tr><td>${g.codigo}</td><td>${g.nombre}</td>${celdas}</tr>`;
  }).join('');
  const cabecerasMes = IND_MESES.map(m => `<th>${IND_MES_LABEL[m]}</th>`).join('');
  return `<div class="table-wrap"><table>
    <thead><tr><th>Código</th><th>Gestión</th>${cabecerasMes}</tr></thead>
    <tbody>${filasHtml}</tbody>
  </table></div>`;
}

function indBindCronogramaEdicion() {
  document.querySelectorAll('.ind-cron-select').forEach(sel => {
    sel.addEventListener('change', async () => {
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_cronograma.php`, {
          method: 'POST',
          body: JSON.stringify({ gestion_codigo: sel.dataset.gestion, periodo: sel.dataset.periodo, team: sel.value }),
        });
        const json = await res.json();
        if (!json.success) alert(json.error || 'Error al guardar.');
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}
```

- [ ] **Step 4: Wire into `indOnTabShown`**

```javascript
window.indOnTabShown = function (tab) {
  if (tab === 'datos' && !indDatosLoaded) { indDatosLoaded = true; loadDatosMensuales(); }
  if (tab === 'catalogo' && !indCatalogoLoaded) { indCatalogoLoaded = true; loadCatalogo(); }
  if (tab === 'cronograma' && !indCronogramaLoaded) { indCronogramaLoaded = true; loadCronograma(); }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 23 aserciones, 0 fallidas`

- [ ] **Step 6: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): implementa pestana Cronograma"
```

---

### Task 14: Resumen Gestión tab

**Files:**
- Modify: `pages/indicadores.php`
- Modify: `tests/indicadores_ui_test.php`

- [ ] **Step 1: Add assertions**

```php
echo "\n── pestana Resumen Gestion ──────────────────────────────────\n";
ui_has($source, 'async function loadResumenGestion', 'carga el resumen por gestion');
ui_has($source, 'indAgruparPorGestion', 'agrupa los indicadores por gestion');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/indicadores_ui_test.php` → 2 new FALLA lines.

- [ ] **Step 3: Implement**

Append before `</script>`:

```javascript
// ── Resumen Gestión ─────────────────────────────────────────────────
let indResumenLoaded = false;

function indAgruparPorGestion(indicadores) {
  const grupos = {};
  indicadores.forEach(ind => {
    if (!grupos[ind.gestion_codigo]) grupos[ind.gestion_codigo] = { nombre: ind.gestion_nombre, indicadores: [] };
    grupos[ind.gestion_codigo].indicadores.push(ind);
  });
  return grupos;
}

async function loadResumenGestion() {
  const panel = document.getElementById('indPanelResumen');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header"><div class="card-title">Resumen Gestión</div><div>${indMesInputHtml()}</div></div>
      <div class="card-body" id="indResumenBody">Cargando…</div>
    </div>`;
  document.getElementById('indDatosPeriodo') && null; // el input propio de esta pestaña tiene el mismo id por simplicidad de reuso visual
  panel.querySelector('#indDatosPeriodo')?.addEventListener('change', (e) => { indDatosPeriodo = e.target.value; loadResumenGestion(); });
  await indFetchYRenderResumen();
}

async function indFetchYRenderResumen() {
  const body = document.getElementById('indResumenBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { body.innerHTML = json.error; return; }
    const grupos = indAgruparPorGestion(json.data);
    body.innerHTML = Object.keys(grupos).sort().map(codigo => {
      const g = grupos[codigo];
      const conDato = g.indicadores.filter(i => i.pct_vs_meta !== null);
      const promedio = conDato.length ? (conDato.reduce((s, i) => s + i.pct_vs_meta, 0) / conDato.length) : null;
      const filas = g.indicadores.map(i => `
        <tr>
          <td>${i.codigo}</td><td>${i.kpi}</td>
          <td>${i.pct_vs_meta === null ? '—' : (i.pct_vs_meta * 100).toFixed(1) + '%'}</td>
          <td><span class="ind-estado-badge ind-estado-${i.estado.replace(/ /g, '_')}">${i.estado}</span></td>
        </tr>`).join('');
      return `<div class="card" style="margin-bottom:14px">
        <div class="card-header"><div class="card-title">${codigo} — ${g.nombre}</div>
          <div>${promedio === null ? 'Sin dato' : (promedio * 100).toFixed(1) + '% promedio de cumplimiento'}</div></div>
        <div class="table-wrap"><table>
          <thead><tr><th>Cód.</th><th>Indicador</th><th>% vs Meta</th><th>Estado</th></tr></thead>
          <tbody>${filas}</tbody>
        </table></div>
      </div>`;
    }).join('');
  } catch (e) {
    body.innerHTML = 'Error de red.';
  }
}
```

- [ ] **Step 4: Wire into `indOnTabShown`**

```javascript
window.indOnTabShown = function (tab) {
  if (tab === 'datos' && !indDatosLoaded) { indDatosLoaded = true; loadDatosMensuales(); }
  if (tab === 'catalogo' && !indCatalogoLoaded) { indCatalogoLoaded = true; loadCatalogo(); }
  if (tab === 'cronograma' && !indCronogramaLoaded) { indCronogramaLoaded = true; loadCronograma(); }
  if (tab === 'resumen' && !indResumenLoaded) { indResumenLoaded = true; loadResumenGestion(); }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 25 aserciones, 0 fallidas`

- [ ] **Step 6: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): implementa pestana Resumen Gestion"
```

---

### Task 15: Dashboard tab

**Files:**
- Modify: `pages/indicadores.php`
- Modify: `tests/indicadores_ui_test.php`

- [ ] **Step 1: Add assertions**

```php
echo "\n── pestana Dashboard ────────────────────────────────────────\n";
ui_has($source, 'async function loadDashboard', 'carga el dashboard');
ui_has($source, 'indVistaTeam', 'permite filtrar la vista por team');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/indicadores_ui_test.php` → 2 new FALLA lines.

- [ ] **Step 3: Implement**

Append before `</script>`:

```javascript
// ── Dashboard ───────────────────────────────────────────────────────
let indDashboardLoaded = false;
let indVistaTeam = ''; // '' = General

async function loadDashboard() {
  const panel = document.getElementById('indPanelDashboard');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Dashboard de Seguimiento</div>
        <div style="display:flex;gap:10px;align-items:center">
          ${indMesInputHtml()}
          <select id="indVistaSelect" class="ind-select">
            <option value="">Vista: General</option>
            <option value="TEAM A">Vista: TEAM A</option>
            <option value="TEAM B">Vista: TEAM B</option>
            <option value="TEAM C">Vista: TEAM C</option>
            <option value="TEAM D">Vista: TEAM D</option>
          </select>
        </div>
      </div>
      <div class="card-body" id="indDashboardBody">Cargando…</div>
    </div>`;
  panel.querySelector('#indDatosPeriodo')?.addEventListener('change', (e) => { indDatosPeriodo = e.target.value; indFetchYRenderDashboard(); });
  panel.querySelector('#indVistaSelect').addEventListener('change', (e) => { indVistaTeam = e.target.value; indFetchYRenderDashboard(); });
  await indFetchYRenderDashboard();
}

async function indFetchYRenderDashboard() {
  const body = document.getElementById('indDashboardBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { body.innerHTML = json.error; return; }
    const grupos = indAgruparPorGestion(json.data);
    body.innerHTML = `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">` +
      Object.keys(grupos).sort().map(codigo => {
        const g = grupos[codigo];
        let valores;
        if (indVistaTeam === '') {
          valores = g.indicadores.filter(i => i.pct_vs_meta !== null).map(i => i.pct_vs_meta);
        } else {
          valores = g.indicadores
            .map(i => {
              const t = i.teams[indVistaTeam];
              if (!t || t.valor === null) return null;
              const pct = i.meta ? t.valor / i.meta : null;
              return pct;
            })
            .filter(v => v !== null);
        }
        const promedio = valores.length ? (valores.reduce((s, v) => s + v, 0) / valores.length) : null;
        return `<div class="card"><div class="card-body">
          <div class="card-sub">${codigo} — ${g.nombre}</div>
          <h2>${promedio === null ? 'SIN DATO' : (promedio * 100).toFixed(1) + '%'}</h2>
          <p>% promedio de cumplimiento de indicadores</p>
        </div></div>`;
      }).join('') + `</div>`;
  } catch (e) {
    body.innerHTML = 'Error de red.';
  }
}
```

- [ ] **Step 4: Wire into `indOnTabShown`**

```javascript
window.indOnTabShown = function (tab) {
  if (tab === 'datos' && !indDatosLoaded) { indDatosLoaded = true; loadDatosMensuales(); }
  if (tab === 'catalogo' && !indCatalogoLoaded) { indCatalogoLoaded = true; loadCatalogo(); }
  if (tab === 'cronograma' && !indCronogramaLoaded) { indCronogramaLoaded = true; loadCronograma(); }
  if (tab === 'resumen' && !indResumenLoaded) { indResumenLoaded = true; loadResumenGestion(); }
  if (tab === 'dashboard' && !indDashboardLoaded) { indDashboardLoaded = true; loadDashboard(); }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/indicadores_ui_test.php`
Expected: `TODO OK: 27 aserciones, 0 fallidas`

- [ ] **Step 6: Commit**

```bash
git add pages/indicadores.php tests/indicadores_ui_test.php
git commit -m "feat(indicadores): implementa pestana Dashboard"
```

---

### Task 16: Full test suite + manual verification pass

**Files:** none created — this task runs everything built so far and walks the spec's "Verificación requerida" checklist by hand.

- [ ] **Step 1: Run every automated test**

```bash
php tests/indicadores_catalogo_test.php
php tests/indicadores_engine_db_test.php
php tests/indicadores_auth_test.php
php tests/indicadores_ui_test.php
```

Expected: all four print `TODO OK` and exit 0.

- [ ] **Step 2: Run the full existing suite to confirm nothing else broke**

```bash
for f in tests/*_test.php; do echo "== $f =="; php "$f" || echo "FAILED: $f"; done
```

Expected: no `FAILED:` lines.

- [ ] **Step 3: Manual walkthrough against the spec's verification list**

Log in as Administrador, open `pages/indicadores.php`, and for the current month:
1. Confirm the 10 automatic indicators (G1.4, G2.1, G2.2, G2.3, G2.5, G3.1, G3.2, G3.3, G4.1, G4.2) show a value with the "Automático" badge in Datos Mensuales, matching what you'd get running the equivalent SQL by hand for one team with known data.
2. Confirm G1.1 shows an auto-filled numerador (disabled input) and an editable denominador.
3. Confirm the other 10 indicators accept manual N/D entry and persist after a page reload.
4. Switch Dashboard between Vista General and a specific TEAM and confirm the percentages change.
5. Log in as a Coordinador linked (via `colaboradores.coordinador_id`) to colaboradores of a single team; confirm they can only save captures for that team (attempts on other teams return the permission error).
6. Edit a Meta value in Catálogo as Administrador and confirm `% vs Meta` recalculates in Resumen Gestión.
7. Edit the Cronograma and confirm the new team assignment persists on reload.

- [ ] **Step 4: Commit if any fixes were needed during the walkthrough**

```bash
git add -A
git commit -m "fix(indicadores): ajustes tras verificacion manual"
```

(Skip this step if the walkthrough found nothing to fix.)
