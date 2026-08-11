-- Migración 006: detalle de TM por bodega en el reporte de turno.
-- Cuando el granelero tiene bodegas con cargas distintas, el Coordinador reporta
-- las TM movidas por bodega (no un total global). Se guarda como JSON:
--   [ { "bodega": 1, "tipo_carga": "Maíz", "tm": 1200 }, ... ]
-- Las columnas globales (directa/indirecta/despacho) siguen usándose para el
-- caso uniforme y para el despacho de almacén.
USE operaciones;

ALTER TABLE avances_nave
  ADD COLUMN bodegas JSON NULL AFTER despacho_tm;
