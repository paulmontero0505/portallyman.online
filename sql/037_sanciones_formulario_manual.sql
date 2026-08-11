-- Sanciones disciplinarias manuales y detalle compatible con el formulario de Incidencias.
ALTER TABLE sanciones_disciplinarias MODIFY incidencia_id INT NULL;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='competencia');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD competencia VARCHAR(160) NULL AFTER punto_mejorar', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='turno');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD turno VARCHAR(20) NULL AFTER competencia', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanciones_disciplinarias' AND COLUMN_NAME='detalle');
SET @q := IF(@c=0, 'ALTER TABLE sanciones_disciplinarias ADD detalle TEXT NULL AFTER zona_trabajo', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
