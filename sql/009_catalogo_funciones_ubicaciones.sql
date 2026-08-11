-- ════════════════════════════════════════════════════════════════════════
-- 009 · Catálogo operativo de FUNCIONES y UBICACIONES
-- Reemplaza el seed de 004_turnos.sql por el catálogo real de la operación.
--
-- Estrategia: se DESACTIVAN (activo=0) todas las entradas previas en vez de
-- borrarlas. Las asignaciones históricas en turno_personal guardan el texto
-- de la función/ubicación, y el front antepone el valor actual del colaborador
-- aunque ya no esté en el catálogo (no se pierde su rol real). Idempotente.
--
-- Casing: las funciones se guardan en MAYÚSCULAS para coincidir con los datos
-- ya existentes (p. ej. 'ASISTENTE DE ESTIBA'); las ubicaciones en Title Case.
-- ════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─── Funciones ───────────────────────────────────────────────────────────
UPDATE funciones SET activo = 0;
INSERT INTO funciones (nombre, activo, orden) VALUES
  ('ASISTENTE DE ESTIBA',  1, 1),
  ('ANALISTA DOUBLE DESK', 1, 2)
ON DUPLICATE KEY UPDATE activo = VALUES(activo), orden = VALUES(orden);

-- ─── Ubicaciones ─────────────────────────────────────────────────────────
UPDATE ubicaciones SET activo = 0;
INSERT INTO ubicaciones (nombre, activo, orden) VALUES
  ('Muelle 1',                1, 1),
  ('Muelle 2',                1, 2),
  ('Muelle 3',                1, 3),
  ('Muelle 4',                1, 4),
  ('Muelle 3.5',              1, 5),
  ('Balanza 1',               1, 6),
  ('Balanza 2',               1, 7),
  ('Desconsolidado de autos', 1, 8),
  ('Despacho de cigüeñas',    1, 9),
  ('Despacho de estructuras', 1, 10)
ON DUPLICATE KEY UPDATE activo = VALUES(activo), orden = VALUES(orden);
