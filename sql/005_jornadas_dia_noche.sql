-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 005 · Jornadas de 12h: Día / Noche
-- Reemplaza el esquema de 3 jornadas (Mañana/Tarde/Noche 8h) por 2 de 12h:
--   Día   06:30–18:30
--   Noche 18:30–06:30  (cruza medianoche)
-- Conserva los turnos existentes repuntando los de 'Tarde' a 'Día'.
-- Ejecutar con: mysql -uroot estiba_turno < sql/005_jornadas_dia_noche.sql
-- Idempotente: re-ejecutarlo no cambia nada una vez migrado.
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

-- Mañana → Día (reusa la fila para conservar su id y los turnos que la referencien)
UPDATE jornadas
   SET codigo='D', nombre='Día', hora_inicio='06:30:00', hora_fin='18:30:00', orden=1
 WHERE codigo='M';

-- Noche: mismos código pero horario de 12h
UPDATE jornadas
   SET nombre='Noche', hora_inicio='18:30:00', hora_fin='06:30:00', orden=2
 WHERE codigo='N';

-- Repuntar los turnos que estaban en 'Tarde' hacia 'Día' (para no perder
-- personal ya cargado), y luego eliminar la jornada 'Tarde'.
SET @dia_id   := (SELECT id FROM jornadas WHERE codigo='D' LIMIT 1);
SET @tarde_id := (SELECT id FROM jornadas WHERE codigo='T' LIMIT 1);

UPDATE turnos SET jornada_id = @dia_id
 WHERE @tarde_id IS NOT NULL AND jornada_id = @tarde_id;

DELETE FROM jornadas WHERE codigo='T';
