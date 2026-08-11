-- PORTALLY SYSTEM · 024 · Campo celular de colaboradores
-- Ejecutar una sola vez en phpMyAdmin/cPanel sobre la base portally_system.

USE portally_system;

ALTER TABLE colaboradores
  ADD COLUMN celular VARCHAR(20) NULL AFTER dni;

-- Verificación:
-- SHOW COLUMNS FROM colaboradores LIKE 'celular';
