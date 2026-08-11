-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 032 · EVADES por bloques de coordinador
-- Ejecutar sobre portally_system después de sql/031_evades.sql.
-- Compatible con MariaDB 10.4; no elimina ni recalcula históricos.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS evades_bloques (
  id                    INT(11)      NOT NULL AUTO_INCREMENT,
  coordinador_id        INT(11)      NOT NULL,
  coordinador_nombre    VARCHAR(100) NOT NULL,
  puesto                VARCHAR(60)  NOT NULL,
  periodo               VARCHAR(7)   NOT NULL,
  estado                ENUM('generado','revisado','modificado','cerrado') NOT NULL DEFAULT 'generado',
  total_colaboradores   INT(11)      NOT NULL DEFAULT 0,
  version               INT(11)      NOT NULL DEFAULT 1,
  generado_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisado_at           DATETIME     NULL,
  modificado_at         DATETIME     NULL,
  cerrado_at            DATETIME     NULL,
  generado_por          INT(11)      NULL,
  cerrado_por           INT(11)      NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evades_bloque (coordinador_id, puesto, periodo),
  KEY ix_evb_estado_periodo (estado, periodo),
  KEY ix_evb_generado_por (generado_por),
  KEY ix_evb_cerrado_por (cerrado_por),
  CONSTRAINT fk_evb_coordinador FOREIGN KEY (coordinador_id)
    REFERENCES usuarios(id) ON DELETE RESTRICT,
  CONSTRAINT fk_evb_generado_por FOREIGN KEY (generado_por)
    REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_evb_cerrado_por FOREIGN KEY (cerrado_por)
    REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND COLUMN_NAME='bloque_id');
SET @ddl := IF(@col=0,
  'ALTER TABLE evades_evaluaciones ADD COLUMN bloque_id INT(11) NULL AFTER id',
  'SELECT "bloque_id ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND COLUMN_NAME='version');
SET @ddl := IF(@col=0,
  'ALTER TABLE evades_evaluaciones ADD COLUMN version INT(11) NOT NULL DEFAULT 1 AFTER bloque_id',
  'SELECT "version ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND INDEX_NAME='ix_evades_bloque');
SET @ddl := IF(@ix=0,
  'ALTER TABLE evades_evaluaciones ADD KEY ix_evades_bloque (bloque_id)',
  'SELECT "ix_evades_bloque ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND CONSTRAINT_NAME='fk_evades_bloque');
SET @ddl := IF(@fk=0,
  'ALTER TABLE evades_evaluaciones ADD CONSTRAINT fk_evades_bloque FOREIGN KEY (bloque_id) REFERENCES evades_bloques(id) ON DELETE RESTRICT',
  'SELECT "fk_evades_bloque ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS evades_bloques_estados (
  id                    INT(11)      NOT NULL AUTO_INCREMENT,
  bloque_id             INT(11)      NOT NULL,
  estado_anterior       ENUM('generado','revisado','modificado','cerrado') NULL,
  estado_nuevo          ENUM('generado','revisado','modificado','cerrado') NOT NULL,
  usuario_id            INT(11)      NULL,
  contexto              VARCHAR(255) NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_evbe_bloque_fecha (bloque_id, created_at),
  KEY ix_evbe_usuario (usuario_id),
  CONSTRAINT fk_evbe_bloque FOREIGN KEY (bloque_id)
    REFERENCES evades_bloques(id) ON DELETE CASCADE,
  CONSTRAINT fk_evbe_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evades_modificaciones (
  id                    INT(11)      NOT NULL AUTO_INCREMENT,
  bloque_id             INT(11)      NOT NULL,
  evaluacion_id         INT(11)      NOT NULL,
  colaborador_id        INT(11)      NULL,
  usuario_id            INT(11)      NULL,
  motivo                TEXT         NULL,
  antes_json            LONGTEXT     NOT NULL,
  despues_json          LONGTEXT     NOT NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_evm_bloque_fecha (bloque_id, created_at),
  KEY ix_evm_evaluacion (evaluacion_id),
  KEY ix_evm_colaborador (colaborador_id),
  KEY ix_evm_usuario (usuario_id),
  CONSTRAINT fk_evm_bloque FOREIGN KEY (bloque_id)
    REFERENCES evades_bloques(id) ON DELETE CASCADE,
  CONSTRAINT fk_evm_evaluacion FOREIGN KEY (evaluacion_id)
    REFERENCES evades_evaluaciones(id) ON DELETE RESTRICT,
  CONSTRAINT fk_evm_colaborador FOREIGN KEY (colaborador_id)
    REFERENCES colaboradores(id) ON DELETE SET NULL,
  CONSTRAINT fk_evm_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
