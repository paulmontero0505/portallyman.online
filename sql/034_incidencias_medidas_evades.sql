-- Decisión administrativa de una incidencia según la matriz EVADES.
-- Ejecutar sobre la base seleccionada (local o producción). Es idempotente.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'descuento_puntos');
SET @ddl := IF(@col = 0, 'ALTER TABLE incidencias ADD COLUMN descuento_puntos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER declaracion_uploaded_at', 'SELECT "descuento_puntos ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'frecuencia_descuento');
SET @ddl := IF(@col = 0, 'ALTER TABLE incidencias ADD COLUMN frecuencia_descuento TINYINT UNSIGNED NULL AFTER descuento_puntos', 'SELECT "frecuencia_descuento ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'sancion_disciplinaria');
SET @ddl := IF(@col = 0, "ALTER TABLE incidencias ADD COLUMN sancion_disciplinaria VARCHAR(32) NOT NULL DEFAULT 'sin_sancion' AFTER frecuencia_descuento", 'SELECT "sancion_disciplinaria ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'medida_aplicada_por');
SET @ddl := IF(@col = 0, 'ALTER TABLE incidencias ADD COLUMN medida_aplicada_por VARCHAR(100) NULL AFTER sancion_disciplinaria', 'SELECT "medida_aplicada_por ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'medida_aplicada_por_id');
SET @ddl := IF(@col = 0, 'ALTER TABLE incidencias ADD COLUMN medida_aplicada_por_id INT NULL AFTER medida_aplicada_por', 'SELECT "medida_aplicada_por_id ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'incidencias' AND COLUMN_NAME = 'medida_aplicada_at');
SET @ddl := IF(@col = 0, 'ALTER TABLE incidencias ADD COLUMN medida_aplicada_at DATETIME NULL AFTER medida_aplicada_por_id', 'SELECT "medida_aplicada_at ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
