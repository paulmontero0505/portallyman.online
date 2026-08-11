-- ============================================================
-- ESTIBA_TURNO · relevo_generado
-- Registro de control de cada relevo generado: quién lo generó y
-- cuándo, las observaciones, y un snapshot JSON (base para Sheets).
-- ============================================================
USE estiba_turno;

CREATE TABLE IF NOT EXISTS relevo_generado (
  id              INT(11)      NOT NULL AUTO_INCREMENT,
  fecha           DATE         NOT NULL,
  turno           VARCHAR(8)   NOT NULL,        -- código de jornada (D/N/U…)
  turno_id        INT(11)      NULL,
  generado_por    VARCHAR(120) NOT NULL,
  generado_por_id INT(11)      NULL,
  observaciones   TEXT         NULL,
  datos_json      LONGTEXT     NULL,            -- snapshot (personal/radios/totales) para Sheets
  generado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rg_turno (fecha, turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
