-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 006 · Jornadas editables
-- Convierte `jornadas` en un catálogo administrable:
--   · codigo pasa a etiqueta opcional (VARCHAR, sin unicidad)
--   · nueva columna `activo`
--   · horarios reales: Día 07:00–19:00, Noche 19:00–07:00
--   · nueva jornada "Único" 08:00–21:15
-- La selección de turno usa jornada_id (no el código).
-- Ejecutar con: mysql -uroot estiba_turno < sql/006_jornadas_editables.sql
-- NOTA: contiene ALTERs no idempotentes (ejecutar una sola vez).
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

-- codigo: etiqueta opcional, ya no clave única.
ALTER TABLE jornadas DROP INDEX uq_jornada_codigo;
ALTER TABLE jornadas MODIFY codigo VARCHAR(8) NULL;

-- columna activo (para desactivar sin borrar).
ALTER TABLE jornadas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER hora_fin;

-- Horarios reales del usuario.
UPDATE jornadas SET nombre='Día',   hora_inicio='07:00:00', hora_fin='19:00:00', orden=1, activo=1 WHERE codigo='D';
UPDATE jornadas SET nombre='Noche', hora_inicio='19:00:00', hora_fin='07:00:00', orden=2, activo=1 WHERE codigo='N';

-- Turno único 08:00–21:15 (idempotente por nombre).
INSERT INTO jornadas (codigo, nombre, hora_inicio, hora_fin, orden, activo)
SELECT 'U', 'Único', '08:00:00', '21:15:00', 3, 1
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM jornadas WHERE nombre = 'Único');
