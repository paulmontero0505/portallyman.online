-- Migración 003: agrega campo muelle a la tabla naves
ALTER TABLE naves
  ADD COLUMN muelle VARCHAR(120) DEFAULT NULL AFTER nombre;
