-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 020 · Asistencias Pre-Operativas: estado por participante
-- Se reemplaza la firma digital por 3 estados por participante:
--   asistio (A) · falta (F) · tardanza (T)
-- La columna firma_data de 019 se deja intacta (no se borra, por si ya
-- tiene datos en producción) pero deja de usarse.
-- Ejecutar con: mysql -uroot portally_system < sql/020_asistencias_estado_participante.sql
-- Idempotente (comprueba si la columna ya existe antes de añadirla).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'asistencias_participantes' AND column_name = 'estado'
);

SET @sql := IF(@col_existe = 0,
  'ALTER TABLE asistencias_participantes
     ADD COLUMN estado ENUM(''asistio'',''falta'',''tardanza'') NOT NULL DEFAULT ''asistio''
     AFTER colaborador_cargo',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
