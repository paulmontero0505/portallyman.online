-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 022 · Reconocimiento Tally: flujo de aprobación + diploma
-- · El "nivel de impacto" deja de usarse (la columna se vuelve opcional,
--   no se borra por si hay datos previos).
-- · Se añade el estado de aprobación (pendiente/aprobado/rechazado) y los
--   datos del supervisor que aprueba (para la firma del diploma).
-- Ejecutar con: mysql -uroot portally_system < sql/022_reconocimientos_estado.sql
-- Idempotente (comprueba si la columna ya existe antes de añadirla).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

-- 1) impacto pasa a ser opcional (ya no se pide en el formulario).
ALTER TABLE reconocimientos
  MODIFY impacto ENUM('bueno','excelente','sobresaliente') NULL DEFAULT NULL;

-- 2) estado de aprobación.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'reconocimientos' AND column_name = 'estado');
SET @sql := IF(@c = 0,
  "ALTER TABLE reconocimientos
     ADD COLUMN estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente' AFTER detalle",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) supervisor que aprueba (nombre + id) y fecha de aprobación.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'reconocimientos' AND column_name = 'supervisor');
SET @sql := IF(@c = 0,
  "ALTER TABLE reconocimientos ADD COLUMN supervisor VARCHAR(100) NULL AFTER estado",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'reconocimientos' AND column_name = 'supervisor_id');
SET @sql := IF(@c = 0,
  "ALTER TABLE reconocimientos ADD COLUMN supervisor_id INT(11) NULL AFTER supervisor",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'reconocimientos' AND column_name = 'aprobado_at');
SET @sql := IF(@c = 0,
  "ALTER TABLE reconocimientos ADD COLUMN aprobado_at TIMESTAMP NULL AFTER supervisor_id",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
