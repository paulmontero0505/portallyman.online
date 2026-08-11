-- Asigna cada colaborador a un usuario activo con rol Coordinador.
ALTER TABLE colaboradores
  ADD COLUMN coordinador_id INT NULL AFTER cuadrilla,
  ADD INDEX idx_colaboradores_coordinador (coordinador_id),
  ADD CONSTRAINT fk_colaboradores_coordinador
    FOREIGN KEY (coordinador_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
