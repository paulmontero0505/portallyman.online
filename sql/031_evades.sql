-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 031 · Módulo EVADES (Evaluación de Desempeño Asistente de Estiba)
-- ────────────────────────────────────────────────────────────────────
-- 10 competencias (5 conductuales + 5 operativas), base 6 pts c/u,
-- incremento/descuento auto-sugeridos desde incidencias/reconocimientos
-- y ajustables por el coordinador con motivo obligatorio.
-- Catálogo fijo en código: includes/evades_catalogo.php
-- Motor de cálculo: includes/evades_engine.php
-- Se ejecuta sobre la base YA seleccionada (sin "USE").
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS evades_evaluaciones (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id      INT(11)      NULL,
  colaborador_nombre  VARCHAR(150) NOT NULL,
  colaborador_codigo  VARCHAR(20)  NULL,
  colaborador_cargo   VARCHAR(60)  NULL,
  colaborador_dni     VARCHAR(8)   NULL,
  fecha_ingreso       DATE         NULL,
  coordinador_id      INT(11)      NULL,
  coordinador_nombre  VARCHAR(100) NOT NULL,
  periodo             VARCHAR(7)   NOT NULL,
  fecha_evaluacion    DATE         NOT NULL,
  puntaje_total       INT(11)      NOT NULL DEFAULT 0,
  clasificacion       VARCHAR(30)  NOT NULL,
  puntaje_anterior    INT(11)      NULL,
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
  competencia_key     VARCHAR(40)  NOT NULL,
  tipo                ENUM('conductual','operativa') NOT NULL,
  base                INT(11)      NOT NULL DEFAULT 6,
  auto_incremento     INT(11)      NULL,
  auto_descuento      INT(11)      NULL,
  incremento_final    INT(11)      NOT NULL DEFAULT 0,
  descuento_final     INT(11)      NOT NULL DEFAULT 0,
  puntaje_final       INT(11)      NOT NULL DEFAULT 6,
  motivo_ajuste       TEXT         NULL,
  evidencia_json      TEXT         NULL,
  PRIMARY KEY (id),
  KEY ix_evc_evaluacion (evaluacion_id),
  KEY ix_evc_competencia (competencia_key),
  CONSTRAINT fk_evc_evaluacion FOREIGN KEY (evaluacion_id)
     REFERENCES evades_evaluaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evades_historico (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id      INT(11)      NULL,
  colaborador_codigo  VARCHAR(20)  NULL,
  periodo             VARCHAR(7)   NOT NULL,
  puntaje_total       INT(11)      NOT NULL,
  clasificacion       VARCHAR(30)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evh_colab_periodo (colaborador_id, periodo),
  KEY ix_evh_periodo (periodo),
  CONSTRAINT fk_evh_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación
-- SHOW TABLES LIKE 'evades%';
-- SELECT COUNT(*) FROM evades_evaluaciones;
