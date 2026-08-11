-- PORTALLY SYSTEM · 025 · Normalización de celulares de Perú
-- Ejecutar después de 024_colaboradores_celular.sql.

USE portally_system;

-- Convierte los valores existentes 9XXXXXXXX o +519XXXXXXXX al formato único.
UPDATE colaboradores
SET celular = CONCAT(
  '+51 ',
  CASE
    WHEN REPLACE(REPLACE(REPLACE(celular, '+', ''), ' ', ''), '-', '') REGEXP '^519[0-9]{8}$'
      THEN RIGHT(REPLACE(REPLACE(REPLACE(celular, '+', ''), ' ', ''), '-', ''), 9)
    ELSE REPLACE(REPLACE(REPLACE(celular, '+', ''), ' ', ''), '-', '')
  END
)
WHERE REPLACE(REPLACE(REPLACE(celular, '+', ''), ' ', ''), '-', '') REGEXP '^(51)?9[0-9]{8}$';

-- Verificación: solo deben quedar valores +51 9XXXXXXXX o NULL.
-- SELECT celular FROM colaboradores WHERE celular IS NOT NULL AND celular NOT REGEXP '^\\+51 9[0-9]{8}$';
