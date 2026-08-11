ALTER TABLE sugerencias_tallyman
  MODIFY COLUMN canal ENUM('observacion','consulta','solicitud','propuesta','encuesta') NOT NULL;

