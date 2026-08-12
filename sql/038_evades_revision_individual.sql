-- EVADES · confirmación de revisión por tallyman.
-- Ejecutar después de 032_evades_bloques.sql. Seguro para reejecutar.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND COLUMN_NAME='revisado_at');
SET @ddl := IF(@col=0,
  'ALTER TABLE evades_evaluaciones ADD COLUMN revisado_at DATETIME NULL AFTER plan_accion',
  'SELECT "revisado_at ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND COLUMN_NAME='revisado_por');
SET @ddl := IF(@col=0,
  'ALTER TABLE evades_evaluaciones ADD COLUMN revisado_por INT(11) NULL AFTER revisado_at',
  'SELECT "revisado_por ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evades_evaluaciones' AND INDEX_NAME='ix_evades_revision');
SET @ddl := IF(@ix=0,
  'ALTER TABLE evades_evaluaciones ADD KEY ix_evades_revision (revisado_at)',
  'SELECT "ix_evades_revision ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
