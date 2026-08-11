-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 023 · Fecha de subida de la Hoja de Declaración
-- Añade la columna declaracion_uploaded_at para poder calcular:
--   · ESTADO       → COMPLETO (tiene declaración) / PENDIENTE (no tiene)
--   · DÍAS DE DEMORA → días entre created_at y declaracion_uploaded_at
-- Idempotente: sólo añade la columna si aún no existe.
-- Se ejecuta sobre la base YA seleccionada (en phpMyAdmin: elige la base
-- antes de importar). No lleva "USE" para funcionar en local (portally_system)
-- y en el servidor sin importar el nombre de la base.
-- ════════════════════════════════════════════════════════════════════

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'incidencias'
     AND COLUMN_NAME  = 'declaracion_uploaded_at'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE incidencias ADD COLUMN declaracion_uploaded_at DATETIME NULL AFTER declaracion_drive_url',
  'SELECT "declaracion_uploaded_at ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: para incidencias que YA tienen declaración pero sin fecha registrada,
-- se usa updated_at como mejor estimación del momento de subida.
UPDATE incidencias
   SET declaracion_uploaded_at = updated_at
 WHERE declaracion_path IS NOT NULL
   AND declaracion_uploaded_at IS NULL;
