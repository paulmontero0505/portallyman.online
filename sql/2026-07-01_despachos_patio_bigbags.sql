-- ============================================================================
-- Portally Operaciones — 2026-07-01
-- Nueva actividad de PATIO "Cement Big Bags Dispatch", espejo de la actividad de
-- muelle "Cement Big Bags Loading/Discharge". Se autogenera desde la Descarga
-- Directa del registro de muelle (misma lógica que tenía Cementero, pero disparada
-- por la ACTIVIDAD en vez del tipo de nave).
-- Ejecutar en cPanel / phpMyAdmin sobre la base portally_operaciones.
-- ============================================================================

USE portally_operaciones;

INSERT INTO tallyman_actividades (nombre, orden, activo) VALUES
  ('Cement Big Bags Dispatch (Despacho de Cemento en Big Bags)', 18, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;

-- Verificación
-- SELECT id, nombre, orden, activo FROM tallyman_actividades WHERE nombre LIKE '%Cement Big Bags%' ORDER BY nombre;
