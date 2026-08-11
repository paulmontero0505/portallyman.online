-- Evidencias automáticas de las sanciones aplicadas desde Incidencias.
CREATE TABLE IF NOT EXISTS sanciones_disciplinarias (
  id INT NOT NULL AUTO_INCREMENT,
  incidencia_id INT NOT NULL,
  colaborador_id INT NULL,
  colaborador_nombre VARCHAR(150) NOT NULL,
  colaborador_cargo VARCHAR(80) NULL,
  tipo_sancion ENUM('amonestacion_escrita','suspension') NOT NULL,
  impacto VARCHAR(20) NOT NULL,
  punto_mejorar VARCHAR(120) NOT NULL,
  fecha_incidencia DATE NOT NULL,
  zona_trabajo VARCHAR(100) NULL,
  aplicado_por VARCHAR(100) NOT NULL,
  aplicado_por_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sancion_incidencia (incidencia_id),
  KEY ix_sancion_fecha (fecha_incidencia),
  KEY ix_sancion_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recupera las sanciones aplicadas antes de que exista este módulo.
INSERT INTO sanciones_disciplinarias
  (incidencia_id, colaborador_id, colaborador_nombre, colaborador_cargo, tipo_sancion,
   impacto, punto_mejorar, fecha_incidencia, zona_trabajo, aplicado_por, aplicado_por_id)
SELECT id, colaborador_id, colaborador_nombre, colaborador_cargo, sancion_disciplinaria,
       impacto, punto_mejorar, fecha, zona_trabajo, COALESCE(medida_aplicada_por, coordinador), medida_aplicada_por_id
  FROM incidencias
 WHERE sancion_disciplinaria IN ('amonestacion_escrita', 'suspension')
ON DUPLICATE KEY UPDATE tipo_sancion=VALUES(tipo_sancion), impacto=VALUES(impacto),
  punto_mejorar=VALUES(punto_mejorar), fecha_incidencia=VALUES(fecha_incidencia), zona_trabajo=VALUES(zona_trabajo),
  aplicado_por=VALUES(aplicado_por), aplicado_por_id=VALUES(aplicado_por_id), updated_at=CURRENT_TIMESTAMP;
