-- ============================================================
-- Operaciones · Tallyman · Migración 011
-- Nuevas actividades Big Bags (cemento, nitrato, fertilizantes, bolas de acero).
-- Idempotente: ALTER seguro + INSERT ... ON DUPLICATE KEY.
-- Ejecutar sobre la BD de operaciones (local y producción).
-- ============================================================
USE portally_operaciones;

-- Ampliar la columna: los nombres bilingües nuevos superan los 80 caracteres.
-- 150 chars en utf8mb4 (600 bytes) es seguro para el índice UNIQUE de `nombre`.
ALTER TABLE tallyman_actividades MODIFY nombre VARCHAR(150) NOT NULL;

INSERT INTO tallyman_actividades (nombre, orden, activo) VALUES
  ('Loading and unloading of cement (Big Bags) (Carga y descarga de cemento (Big Bags))',            24, 1),
  ('Loading and unloading of nitrate (Big Bags) (Carga y descarga de nitrato (Big Bags))',           25, 1),
  ('Loading and unloading of fertilizers (Big Bags) (Carga y descarga de fertilizantes (Big Bags))', 26, 1),
  ('Loading and unloading of steel balls (Big Bags) (Carga y descarga de bolas de acero (Big Bags))', 27, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;
