-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 033 · Evidencia manual estructurada para EVADES
-- Ejecutar después de sql/032_evades_bloques.sql.
-- Aditiva e idempotente: no recalcula ni altera evaluaciones cerradas.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS evades_apreciaciones (
  id                    INT(11)      NOT NULL AUTO_INCREMENT,
  bloque_id             INT(11)      NOT NULL,
  evaluacion_id         INT(11)      NOT NULL,
  colaborador_id        INT(11)      NULL,
  competencia_key       VARCHAR(40)  NOT NULL,
  direccion             ENUM('positiva','negativa') NOT NULL,
  nivel                 TINYINT      NULL,
  impacto               ENUM('minimo','bajo','moderado','alto','critico') NULL,
  descripcion           TEXT         NOT NULL,
  vigente               TINYINT(1)   NOT NULL DEFAULT 1,
  creado_por            INT(11)      NULL,
  anulado_por           INT(11)      NULL,
  anulado_at            DATETIME     NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_evap_eval_comp (evaluacion_id, competencia_key, vigente),
  KEY ix_evap_bloque_fecha (bloque_id, created_at),
  KEY ix_evap_colaborador (colaborador_id),
  KEY ix_evap_creado_por (creado_por),
  KEY ix_evap_anulado_por (anulado_por),
  CONSTRAINT fk_evap_bloque FOREIGN KEY (bloque_id)
    REFERENCES evades_bloques(id) ON DELETE CASCADE,
  CONSTRAINT fk_evap_evaluacion FOREIGN KEY (evaluacion_id)
    REFERENCES evades_evaluaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_evap_colaborador FOREIGN KEY (colaborador_id)
    REFERENCES colaboradores(id) ON DELETE SET NULL,
  CONSTRAINT fk_evap_creado_por FOREIGN KEY (creado_por)
    REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_evap_anulado_por FOREIGN KEY (anulado_por)
    REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
