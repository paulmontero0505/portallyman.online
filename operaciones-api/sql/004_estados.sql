-- Migración 004: nuevos estados de operación.
-- Reemplaza Zarpada/Cancelada por En Operaciones/Finalizada.
USE operaciones;

-- Migra filas existentes con estados retirados a un estado válido.
UPDATE naves SET estado = 'Finalizada' WHERE estado IN ('Zarpada', 'Cancelada');

ALTER TABLE naves
  MODIFY COLUMN estado
  ENUM('Programada','En Puerto','En Operaciones','Finalizada')
  NOT NULL DEFAULT 'Programada';
