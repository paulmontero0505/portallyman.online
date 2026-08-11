-- ============================================================================
-- Portally Operaciones — 2026-07-01
-- 1) Quitar "Cementero" del selector TIPO DE NAVE
-- 2) Renombrar la actividad Big Bags a Cemento y agregar Nitrato, Urea y Bolas de Acero
-- Ejecutar en cPanel / phpMyAdmin sobre la base portally_operaciones.
-- ============================================================================

USE portally_operaciones;

-- ─────────────────────────────────────────────────────────────
-- 1) Quitar "Cementero" del selector TIPO DE NAVE (no se borra,
--    solo se desactiva para conservar naves y lógica existentes)
-- ─────────────────────────────────────────────────────────────
UPDATE tipos_nave SET activo = 0 WHERE nombre = 'Cementero';

-- ─────────────────────────────────────────────────────────────
-- 2) ACTIVIDADES
-- ─────────────────────────────────────────────────────────────
-- Asegurar ancho suficiente (los nombres bilingües llegan a ~85 chars)
ALTER TABLE tallyman_actividades MODIFY nombre VARCHAR(150) NOT NULL;

-- 2a) Renombrar "Big Bags Loading/Discharge" → Cemento en Big Bags
UPDATE tallyman_actividades
SET nombre = 'Cement Big Bags Loading/Discharge (Carga/Descarga de Cemento en Big Bags)'
WHERE nombre = 'Big Bags Loading/Discharge (Carga/Descarga de Big Bags)';

-- 2b) Agregar las 3 nuevas (orden = 6 para agruparlas junto al cemento)
INSERT INTO tallyman_actividades (nombre, orden, activo) VALUES
  ('Nitrate Big Bags Loading/Discharge (Carga/Descarga de Nitrato en Big Bags)',            6, 1),
  ('Urea Big Bags Loading/Discharge (Carga/Descarga de Urea en Big Bags)',                  6, 1),
  ('Steel Balls Big Bags Loading/Discharge (Carga/Descarga de Bolas de Acero en Big Bags)', 6, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;

-- ─────────────────────────────────────────────────────────────
-- Verificación (opcional)
-- ─────────────────────────────────────────────────────────────
-- SELECT id, nombre, orden, activo FROM tallyman_actividades ORDER BY orden, nombre;
-- SELECT id, nombre, activo FROM tipos_nave ORDER BY id;
