-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 034 · Módulo Indicadores
-- Digitaliza Panel_Indicadores_Tally_2026-2.xlsx: catálogo de los 21
-- indicadores, captura manual por indicador×team×mes, y cronograma de
-- teams responsables por gestión y mes.
-- Ejecutar con: mysql -uroot portally_system < sql/034_indicadores.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

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
