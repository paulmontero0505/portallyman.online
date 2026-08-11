-- Migración 005: reporte de avance por turno (estructurado).
-- Amplía avances_nave para que el Coordinador reporte, al cerrar su jornada,
-- las toneladas movidas por flujo (granelera): descarga directa (barco→camión),
-- indirecta (barco→almacén) y despacho de almacén (almacén→camión).
-- No rompe lo existente: descripcion_avance se conserva (ahora opcional) y pasa
-- a usarse como "observaciones".
USE operaciones;

ALTER TABLE avances_nave
  ADD COLUMN fecha_turno  DATE                       NULL AFTER nave_id,
  ADD COLUMN turno        ENUM('Mañana','Noche')     NULL AFTER fecha_turno,
  ADD COLUMN directa_tm   DECIMAL(12,2)              NULL AFTER turno,
  ADD COLUMN indirecta_tm DECIMAL(12,2)              NULL AFTER directa_tm,
  ADD COLUMN despacho_tm  DECIMAL(12,2)              NULL AFTER indirecta_tm;

-- descripcion_avance deja de ser obligatorio (algunos reportes serán solo cifras).
ALTER TABLE avances_nave
  MODIFY COLUMN descripcion_avance TEXT NULL;
