-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 014 · Asistencia con foto + geolocalización
-- Guarda la foto de asistencia y las coordenadas del ingreso auto-registrado.
-- Ejecutar en cPanel / phpMyAdmin sobre la base portally_system.
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

ALTER TABLE turno_personal
  ADD COLUMN foto_ingreso VARCHAR(255) NULL AFTER radio,
  ADD COLUMN ingreso_lat  DECIMAL(10,7) NULL AFTER foto_ingreso,
  ADD COLUMN ingreso_lng  DECIMAL(10,7) NULL AFTER ingreso_lat;

-- Verificación
-- SHOW COLUMNS FROM turno_personal LIKE 'foto_ingreso';
