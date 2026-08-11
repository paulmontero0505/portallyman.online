-- Detalle operativo de cada sanción: vigencia, días y evidencia.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='fecha_inicio');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD fecha_inicio DATE NULL AFTER fecha_incidencia', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='fecha_fin');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD fecha_fin DATE NULL AFTER fecha_inicio', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='dias_sancion');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD dias_sancion INT UNSIGNED NOT NULL DEFAULT 0 AFTER fecha_fin', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='evidencia_path');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD evidencia_path VARCHAR(255) NULL AFTER dias_sancion', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='declaracion_path');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD declaracion_path VARCHAR(255) NULL AFTER evidencia_path', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
