-- PORTALLY SYSTEM · 031 · Historial de respuestas por WhatsApp
-- Ejecutar en la base de datos portally_system.
CREATE TABLE IF NOT EXISTS sugerencias_whatsapp_historial (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sugerencia_id  INT(11) NOT NULL,
  mensaje        TEXT NOT NULL,
  enviado_por    VARCHAR(100) NOT NULL,
  message_id     VARCHAR(120) NULL,
  enviado_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_swh_sugerencia_fecha (sugerencia_id, enviado_at),
  CONSTRAINT fk_swh_sugerencia
    FOREIGN KEY (sugerencia_id) REFERENCES sugerencias_tallyman(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva como primer registro la última respuesta ya existente antes de
-- activar este historial. Las respuestas anteriores no se podían recuperar.
INSERT INTO sugerencias_whatsapp_historial (sugerencia_id, mensaje, enviado_por, message_id, enviado_at)
SELECT s.id, s.respuesta_whatsapp, COALESCE(s.respuesta_whatsapp_por, 'Administrador'),
       s.respuesta_whatsapp_message_id, s.respuesta_whatsapp_at
  FROM sugerencias_tallyman s
 WHERE s.respuesta_whatsapp IS NOT NULL
   AND s.respuesta_whatsapp <> ''
   AND s.respuesta_whatsapp_at IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sugerencias_whatsapp_historial h
        WHERE h.sugerencia_id = s.id
          AND ((h.message_id IS NOT NULL AND h.message_id = s.respuesta_whatsapp_message_id)
               OR (h.message_id IS NULL AND h.mensaje = s.respuesta_whatsapp))
   );
